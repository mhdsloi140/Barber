<?php
// app/Models/BarberService.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BarberService extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'barber_id',
        'service_id',
        'name',
        // 'name_ar',
        'description',
        // 'description_ar',
        'price',
        'duration_minutes',
        'is_active',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'duration_minutes' => 'integer',
        'is_active' => 'boolean',
    ];



    public function barber()
    {
        return $this->belongsTo(User::class, 'barber_id');
    }


    public function service()
    {
        return $this->belongsTo(Service::class, 'service_id');
    }

    /**
     * العلاقة مع المواعيد
     */
    public function appointments()
    {
        return $this->hasMany(Appointment::class, 'barber_service_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
