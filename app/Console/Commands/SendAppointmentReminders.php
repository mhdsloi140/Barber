<?php

namespace App\Console\Commands;

use App\Models\Appointment;
use App\Services\ultraMessage\UltraMsgService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SendAppointmentReminders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'appointments:send-reminders
                            {--minutes=30 : عدد الدقائق قبل الموعد لإرسال التذكير}
                            {--force : إرسال التذكيرات حتى لو تم إرسالها سابقاً}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'إرسال تذكيرات واتساب للزبائن قبل الموعد بنصف ساعة';

    protected UltraMsgService $whatsappService;

    public function __construct(UltraMsgService $whatsappService)
    {
        parent::__construct();
        $this->whatsappService = $whatsappService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $minutesBefore = (int) $this->option('minutes');
        $force = $this->option('force');

        $this->info("بدء إرسال التذكيرات قبل {$minutesBefore} دقيقة من الموعد...");

        // الوقت المستهدف: الآن + عدد الدقائق المحددة
        $targetTime = Carbon::now()->addMinutes($minutesBefore);

        // جلب الحجوزات التي يحين موعدها بعد $minutesBefore دقيقة بالضبط
        // والتي لم يتم إرسال تذكير لها بعد
        $appointments = Appointment::query()
            ->where('status', 'confirmed')
            ->whereDate('appointment_date', $targetTime->toDateString())
            ->whereTime('appointment_time', '>=', $targetTime->subMinute()->format('H:i:s'))
            ->whereTime('appointment_time', '<=', $targetTime->addMinutes(2)->format('H:i:s'))
            ->with(['customer', 'barber', 'salon'])
            ->get();

        if (!$force) {
            $appointments = $appointments->filter(function ($appointment) {
                return !$appointment->reminder_sent_at;
            });
        }

        $this->info("تم العثور على {$appointments->count()} حجز يحتاج إلى تذكير");

        $sentCount = 0;
        $failedCount = 0;

        foreach ($appointments as $appointment) {
            $this->sendReminder($appointment);

            if ($this->sendReminderToCustomer($appointment)) {
                $sentCount++;
                $appointment->update(['reminder_sent_at' => now()]);
                $this->info("✓ تم إرسال تذكير للحجز #{$appointment->id}");
            } else {
                $failedCount++;
                $this->error("✗ فشل إرسال تذكير للحجز #{$appointment->id}");
            }
        }

        $this->info("تم الإرسال: {$sentCount} نجاح, {$failedCount} فشل");

        return Command::SUCCESS;
    }

    /**
     * إرسال تذكير للزبون عبر واتساب
     */
    private function sendReminderToCustomer(Appointment $appointment): bool
    {
        $customer = $appointment->customer;
        $barber = $appointment->barber;
        $salon = $appointment->salon;

        if (!$customer || !$customer->phone) {
            Log::error('Customer phone not found', ['appointment_id' => $appointment->id]);
            return false;
        }

        // تنسيق الوقت
        $appointmentTime = $appointment->appointment_time instanceof Carbon
            ? $appointment->appointment_time->format('g:i A')
            : Carbon::parse($appointment->appointment_time)->format('g:i A');

        $appointmentDate = $appointment->appointment_date instanceof Carbon
            ? $appointment->appointment_date->format('Y-m-d')
            : $appointment->appointment_date;

        // تنسيق التاريخ بالعربية
        $date = Carbon::parse($appointmentDate);
        $arabicDay = $this->getArabicDayName($date->format('l'));
        $formattedDate = $date->format('d/m/Y');

        // جلب أسماء الخدمات
        $services = $this->getServicesNames($appointment);

        // بناء رسالة التذكير
        $message = " *تذكير بموعدك معنا*\n\n" .
                   "مرحباً {$customer->name}،\n\n" .
                   "نذكرك بموعدك بعد {$this->getMinutesBeforeText()} دقيقة:\n\n" .
                   " *التاريخ:* {$arabicDay} {$formattedDate}\n" .
                   "⏱ *الوقت:* {$appointmentTime}\n" .
                   " *الصالون:* {$salon->name}\n" .
                   " *الحلاق:* {$barber->name}\n" .
                   " *الخدمات:* {$services}\n\n" .
                   " *العنوان:* {$salon->address}\n\n" .
                   "نتمنى لك تجربة ممتعة! \n\n" .
                   "للإلغاء أو التعديل، يرجى التواصل معنا.";

        // إرسال عبر واتساب
        $result = $this->whatsappService->sendMessage($customer->phone, $message, 1);

        if ($result['success']) {
            Log::info('Reminder sent', [
                'appointment_id' => $appointment->id,
                'customer_phone' => $customer->phone,
                'message_id' => $result['message_id'] ?? null
            ]);
            return true;
        }

        Log::error('Failed to send reminder', [
            'appointment_id' => $appointment->id,
            'error' => $result['error'] ?? 'unknown'
        ]);

        return false;
    }

    /**
     * الحصول على أسماء الخدمات كنص
     */
    private function getServicesNames(Appointment $appointment): string
    {
        if ($appointment->services_details) {
            $services = json_decode($appointment->services_details, true);
            if (is_array($services) && !empty($services)) {
                $names = array_column($services, 'name');
                return implode(' + ', $names);
            }
        }

        if ($appointment->services) {
            $serviceIds = json_decode($appointment->services, true);
            if (is_array($serviceIds) && !empty($serviceIds)) {
                $services = \App\Models\BarberService::whereIn('id', $serviceIds)->get();
                return $services->pluck('name')->implode(' + ');
            }
        }

        if ($appointment->service) {
            return $appointment->service->name;
        }

        return 'خدمات الحلاقة';
    }

    /**
     * اسم اليوم بالعربية
     */
    private function getArabicDayName(string $day): string
    {
        $days = [
            'Sunday' => 'الأحد',
            'Monday' => 'الإثنين',
            'Tuesday' => 'الثلاثاء',
            'Wednesday' => 'الأربعاء',
            'Thursday' => 'الخميس',
            'Friday' => 'الجمعة',
            'Saturday' => 'السبت',
        ];
        return $days[$day] ?? $day;
    }

    /**
     * نص عدد الدقائق المتبقية
     */
    private function getMinutesBeforeText(): string
    {
        $minutes = (int) $this->option('minutes');

        if ($minutes == 30) {
            return 'نصف ساعة';
        } elseif ($minutes == 60) {
            return 'ساعة';
        } elseif ($minutes == 15) {
            return 'ربع ساعة';
        }

        return "{$minutes} دقيقة";
    }

    /**
     * إرسال تذكير (مع إمكانية التوسع لاحقاً)
     */
    private function sendReminder(Appointment $appointment): void
    {

    }
}
