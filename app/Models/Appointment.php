<?php
// app/Models/Appointment.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;


class Appointment extends Model
{
    use HasFactory;

    protected $table = 'appointments';

    protected $fillable = [
        'customer_id',
        'barber_id',
        'salon_id',
        'service_id',
        'services',
        'services_details',
        'appointment_date',
        'appointment_time',
        'end_time',
        'status',
        'total_price',
        'duration_minutes',
        'notes',
        'customer_notes',
        'barber_notes',
        'rating',
        'review',
        'review_date',
        'cancellation_reason',
        'cancelled_by',
        'cancelled_at',
        'reminder_sent_at',
        'is_walk_in',
    ];

    protected $casts = [
        'services' => 'array',
        'services_details' => 'array',
        'appointment_date' => 'date',
        'appointment_time' => 'datetime:H:i',
        'end_time' => 'datetime:H:i',
        'cancelled_at' => 'datetime',
        'review_date' => 'datetime',
    ];

    /**
     * العلاقات
     */
    public function customer()
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function barber()
    {
        return $this->belongsTo(User::class, 'barber_id');
    }

    public function salon()
    {
        return $this->belongsTo(Salon::class);
    }

    /**
     *  الخدمة (من barber_services)
     */
    public function service()
    {
        return $this->belongsTo(BarberService::class, 'service_id');
    }

    public function cancelledBy()
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }
    public function services()
    {
        return $this->belongsToMany(BarberService::class, 'appointment_services', 'appointment_id', 'service_id')
            ->withPivot('price', 'duration_minutes')
            ->withTimestamps();
    }

    /**
     *  الحصول على جميع الخدمات (للدعم متعدد الخدمات)
     */
    public function getAllServices()
    {
        if ($this->services_details) {
            return json_decode($this->services_details, true);
        }

        if ($this->services) {
            $serviceIds = json_decode($this->services, true);
            if (is_array($serviceIds) && !empty($serviceIds)) {
                return BarberService::whereIn('id', $serviceIds)->get();
            }
        }

        if ($this->service) {
            return collect([$this->service]);
        }

        return collect();
    }

    /**
     *  الحصول على نص الحالة بالعربية
     */
    public function getStatusTextAttribute()
    {
        return match ($this->status) {
            'pending' => 'قيد الانتظار',
            'confirmed' => 'مؤكد',
            'completed' => 'مكتمل',
            'cancelled' => 'ملغي',
            default => $this->status,
        };
    }

    /**
     *  التحقق من إمكانية الإلغاء
     */
    public function canBeCancelled(): bool
    {
        return in_array($this->status, ['pending', 'confirmed']) &&
            !$this->isPast();
    }

    /**
     *  التحقق من أن الموعد في الماضي
     */
    public function isPast(): bool
    {
        $dateTime = $this->appointment_date . ' ' . $this->appointment_time;
        return now()->parse($dateTime)->isPast();
    }



    protected function appointmentTime(): Attribute
    {
        return Attribute::make(
            get: function ($value) {
                if (is_string($value) && strpos($value, ' ') !== false) {
                    $parts = explode(' ', $value);
                    return end($parts);
                }
                return $value;
            },
            set: function ($value) {
                if (is_string($value) && strpos($value, ' ') !== false) {
                    $parts = explode(' ', $value);
                    return end($parts);
                }
                return $value;
            }
        );
    }

    protected function endTime(): Attribute
    {
        return Attribute::make(
            get: function ($value) {
                if (is_string($value) && strpos($value, ' ') !== false) {
                    $parts = explode(' ', $value);
                    return end($parts);
                }
                return $value;
            },
            set: function ($value) {
                if (is_string($value) && strpos($value, ' ') !== false) {
                    $parts = explode(' ', $value);
                    return end($parts);
                }
                return $value;
            }
        );
    }
       public function scopeActive($query)
    {
        return $query->where('status', '!=', 'cancelled');
    }
      public function scopeStatus($query, $status)
    {
        return $query->where('status', $status);
    }
      public static function isTimeSlotAvailable(int $barberId, string $date, string $time, ?int $excludeId = null): bool
    {
        $query = self::where('barber_id', $barberId)
            ->where('appointment_date', $date)
            ->where('appointment_time', $time)
            ->where('status', '!=', 'cancelled');

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        return !$query->exists();
    }
       public static function getBookedTimes(int $barberId, string $date): array
    {
        return self::where('barber_id', $barberId)
            ->where('appointment_date', $date)
            ->where('status', '!=', 'cancelled')
            ->pluck('appointment_time')
            ->toArray();
    }
       public static function getAvailableTimes(int $barberId, string $date, array $allTimes = []): array
    {
        $bookedTimes = self::getBookedTimes($barberId, $date);
        return array_values(array_diff($allTimes, $bookedTimes));
    }
      public function cancel(string $cancelledBy, ?string $reason = null): bool
    {
        if (!in_array($this->status, ['pending', 'confirmed'])) {
            return false;
        }

        $this->status = 'cancelled';
        $this->cancelled_by = $cancelledBy;
        // $this->cancelled_at = now();

        if ($reason) {
            $this->cancellation_reason = $reason;
        }

        return $this->save();
    }

}
