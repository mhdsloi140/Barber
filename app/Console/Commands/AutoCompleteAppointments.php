<?php

namespace App\Console\Commands;

use App\Models\Appointment;
use App\Services\ultraMessage\UltraMsgService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class AutoCompleteAppointments extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'appointments:auto-complete
                            {--dry-run : تشغيل في وضع التجربة دون إجراء تغييرات فعلية}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'تغيير حالة الحجوزات من confirmed إلى completed بعد انتهاء وقتها، وإرسال تذكير للحلاق قبل 5 دقائق من الانتهاء';

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
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->warn('  وضع التجربة (Dry Run) - لن يتم إجراء أي تغييرات فعلية');
        }

        $now = Carbon::now();

        // ===================== الجزء 1: إرسال تذكير للحلاق قبل 5 دقائق من انتهاء الموعد =====================
        $this->sendCompletionReminders($now, $dryRun);

        // ===================== الجزء 2: تغيير الحالة إلى completed بعد انتهاء الموعد بنصف ساعة =====================
        $this->completeExpiredAppointments($now, $dryRun);

        $this->info(' تم الانتهاء من معالجة الحجوزات');

        return Command::SUCCESS;
    }

    /**
     * إرسال تذكير للحلاق قبل 5 دقائق من انتهاء الموعد
     */
    private function sendCompletionReminders(Carbon $now, bool $dryRun): void
    {
        // الوقت المستهدف: قبل 5 دقائق من وقت الانتهاء
        $targetEndTime = $now->copy()->addMinutes(5);

        // جلب الحجوزات التي ستنتهي بعد 5 دقائق من الآن
        $appointments = Appointment::query()
            ->where('status', 'confirmed')
            ->whereDate('appointment_date', $targetEndTime->toDateString())
            ->whereTime('end_time', '>=', $targetEndTime->subMinute()->format('H:i:s'))
            ->whereTime('end_time', '<=', $targetEndTime->addMinutes(2)->format('H:i:s'))
            ->with(['barber', 'customer', 'salon'])
            ->get();

        // فلترة الحجوزات التي لم يتم إرسال تذكير انتهاء لها بعد
        $appointments = $appointments->filter(function ($appointment) {
            return !$appointment->completion_reminder_sent_at;
        });

        $this->info(" عدد الحجوزات التي تحتاج تذكير انتهاء للحلاق: {$appointments->count()}");

        foreach ($appointments as $appointment) {
            $this->sendCompletionReminderToBarber($appointment, $dryRun);
        }
    }

    /**
     * إرسال رسالة للحلاق بأن الموعد على وشك الانتهاء
     */
    private function sendCompletionReminderToBarber(Appointment $appointment, bool $dryRun): void
    {
        $barber = $appointment->barber;
        $customer = $appointment->customer;
        $salon = $appointment->salon;

        if (!$barber || !$barber->phone) {
            Log::error('Barber phone not found', ['appointment_id' => $appointment->id]);
            return;
        }

        // تنسيق الوقت
        $endTime = $appointment->end_time instanceof Carbon
            ? $appointment->end_time->format('g:i A')
            : Carbon::parse($appointment->end_time)->format('g:i A');

        $startTime = $appointment->appointment_time instanceof Carbon
            ? $appointment->appointment_time->format('g:i A')
            : Carbon::parse($appointment->appointment_time)->format('g:i A');

        // جلب أسماء الخدمات
        $services = $this->getServicesNames($appointment);

        // بناء رسالة التذكير للحلاق
        $message = " *تذكير: الموعد على وشك الانتهاء* \n\n" .
                   "مرحباً {$barber->name}،\n\n" .
                   "ينتهي موعدك بعد 5 دقائق:\n\n" .
                   " *الزبون:* {$customer->name}\n" .
                   " *رقم الزبون:* {$customer->phone}\n" .
                   "⏱ *وقت البدء:* {$startTime}\n" .
                   "⏱ *وقت الانتهاء:* {$endTime}\n" .
                   " *الخدمات:* {$services}\n" .
                   " *الصالون:* {$salon->name}\n\n" .
                   " بعد الانتهاء من الخدمة، سيتم إكمال الحجز تلقائياً.\n\n" .
                   "شكراً لجهودك! ";

        if ($dryRun) {
            $this->line("[DRY RUN] كان سيتم إرسال تذكير للحلاق {$barber->name} (رقم: {$barber->phone})");
            return;
        }

        // إرسال عبر واتساب
        $result = $this->whatsappService->sendMessage($barber->phone, $message, 1);

        if ($result['success']) {
            $appointment->update(['completion_reminder_sent_at' => now()]);
            $this->info("✓ تم إرسال تذكير الانتهاء للحلاق {$barber->name} (حجز #{$appointment->id})");

            Log::info('Completion reminder sent to barber', [
                'appointment_id' => $appointment->id,
                'barber_id' => $barber->id,
                'barber_phone' => $barber->phone
            ]);
        } else {
            $this->error("✗ فشل إرسال تذكير الانتهاء للحلاق {$barber->name}");
            Log::error('Failed to send completion reminder to barber', [
                'appointment_id' => $appointment->id,
                'error' => $result['error'] ?? 'unknown'
            ]);
        }
    }

    /**
     * تغيير حالة الحجوزات المنتهية إلى completed
     */
    private function completeExpiredAppointments(Carbon $now, bool $dryRun): void
    {
        // الوقت المستهدف: الحجوزات التي انتهت منذ نصف ساعة أو أكثر
        $expiredTime = $now->copy()->subMinutes(30);

        // جلب الحجوزات التي انتهت منذ نصف ساعة
        $appointments = Appointment::query()
            ->where('status', 'confirmed')
            ->where(function ($query) use ($expiredTime) {
                // حجوزات انتهى وقتها منذ نصف ساعة أو أكثر
                $query->where(function ($q) use ($expiredTime) {
                    $q->whereDate('appointment_date', '<', $expiredTime->toDateString())
                        ->orWhere(function ($q2) use ($expiredTime) {
                            $q2->whereDate('appointment_date', $expiredTime->toDateString())
                                ->whereTime('end_time', '<=', $expiredTime->format('H:i:s'));
                        });
                });
            })
            ->with(['barber', 'customer', 'salon'])
            ->get();

        $this->info(" عدد الحجوزات التي سيتم إكمالها: {$appointments->count()}");

        foreach ($appointments as $appointment) {
            $this->completeAppointment($appointment, $dryRun);
        }
    }

    /**
     * إكمال الحجز وإرسال رسالة تأكيد للزبون
     */
    private function completeAppointment(Appointment $appointment, bool $dryRun): void
    {
        $customer = $appointment->customer;
        $barber = $appointment->barber;
        $salon = $appointment->salon;

        if ($dryRun) {
            $this->line("[DRY RUN] كان سيتم تغيير حالة الحجز #{$appointment->id} إلى completed");
            return;
        }

        // تغيير حالة الحجز
        $appointment->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        $this->info("✓ تم إكمال الحجز #{$appointment->id} (زبون: {$customer->name})");

        // إرسال رسالة تأكيد للزبون
        if ($customer && $customer->phone) {
            $this->sendCompletionMessageToCustomer($appointment);
        }

        Log::info('Appointment auto-completed', [
            'appointment_id' => $appointment->id,
            'customer_id' => $customer->id,
            'barber_id' => $barber->id,
            'completed_at' => now()
        ]);
    }

    /**
     * إرسال رسالة تأكيد للزبون بعد إكمال الحجز
     */
    private function sendCompletionMessageToCustomer(Appointment $appointment): void
    {
        $customer = $appointment->customer;
        $barber = $appointment->barber;
        $salon = $appointment->salon;

        $services = $this->getServicesNames($appointment);
        $totalPrice = $appointment->total_price;

        $message = " *تم إكمال خدمتك بنجاح* \n\n" .
                   "مرحباً {$customer->name}،\n\n" .
                   "نشكرك على ثقتك بنا، نأمل أن تكون راضياً عن الخدمة:\n\n" .
                   " *الصالون:* {$salon->name}\n" .
                   " *الحلاق:* {$barber->name}\n" .
                   " *الخدمات:* {$services}\n" .
                   " *الإجمالي:* {$totalPrice} ريال\n\n" .
                   " نتمنى منك تقييم الخدمة لمساعدتنا على التحسين.\n\n" .
                   "ننتظر زيارتك القادمة! ";

        $result = $this->whatsappService->sendMessage($customer->phone, $message, 1);

        if ($result['success']) {
            $this->info(" تم إرسال رسالة الإكمال للزبون {$customer->name}");
        } else {
            $this->error(" فشل إرسال رسالة الإكمال للزبون {$customer->name}");
        }
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
}
