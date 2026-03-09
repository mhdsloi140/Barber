<?php
// app/Models/Service.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Service extends Model
{
    use HasFactory;

    protected $table = 'services';

    protected $fillable = [
        'salon_id',
        'name',
        'name_ar',
        'description',
        'description_ar',
        'price',
        'duration_minutes',
        'category',
        'for_all_barbers',
        'is_active',
        'sort_order'
    ];

    /**
     * العلاقات
     */
    public function salon()
    {
        return $this->belongsTo(Salon::class);
    }

    public function barbers()
    {
        return $this->belongsToMany(User::class, 'barber_service', 'service_id', 'barber_id')
                    ->withPivot('price', 'duration_minutes')
                    ->withTimestamps();
    }

    public function appointments()
    {
        return $this->hasMany(Appointment::class);
    }
}
