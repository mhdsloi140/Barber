<?php
// app/Models/Appointment.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Appointment extends Model
{
    use HasFactory;

    protected $table = 'appointments';

    protected $fillable = [
        'customer_id',
        'barber_id',
        'salon_id',
        'service_id',
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
        'is_walk_in'
    ];

    
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

    public function service()
    {
        return $this->belongsTo(Service::class);
    }

    public function cancelledBy()
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }
}
