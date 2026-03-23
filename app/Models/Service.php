<?php
// app/Models/Service.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Service extends Model
{
    protected $fillable = [
        'salon_id',
        'name',
        'name_ar',
        'description',
        'description_ar',
        'price',
        'duration_minutes',
        'is_active',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'duration_minutes' => 'integer',
        'is_active' => 'boolean',
    ];

  

    /**
     * العلاقة مع الصالون
     * خدمة -> تتبع صالون واحد
     */
    public function salon()
    {
        return $this->belongsTo(Salon::class);
    }


    public function barberServices()
    {
        return $this->hasMany(BarberService::class, 'service_id');
    }


    public function appointments()
    {
        return $this->hasMany(Appointment::class);
    }


    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
