<?php
// app/Models/Salon.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Salon extends Model implements HasMedia
{
    use InteractsWithMedia;

    protected $fillable = [
        'name',
        'owner_id',
        'address',
        'phone',
        'description',
        'latitude',
        'longitude',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    // ========== العلاقات ==========

    /**
     * العلاقة مع صاحب الصالون
     * صالون -> يتبع صاحب واحد
     */
    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * العلاقة مع الحلاقين
     * صالون -> لديه عدة حلاقين
     */
    public function barbers()
    {
        return $this->belongsToMany(User::class, 'barber_salon', 'salon_id', 'barber_id')
                    ->withPivot('is_active')
                    ->withTimestamps();
    }

    /**
     * العلاقة مع خدمات الصالون العامة
     * صالون -> لديه عدة خدمات
     */
    public function services()
    {
        return $this->hasMany(Service::class);
    }

    /**
     * العلاقة مع المواعيد
     * صالون -> لديه عدة مواعيد
     */
    public function appointments()
    {
        return $this->hasMany(Appointment::class);
    }

    /**
     * العلاقة مع أوقات العمل
     */
    public function workingHours()
    {
        return $this->morphMany(WorkingHour::class, 'workable');
    }

    /**
     * الحصول على الحلاقين النشطين فقط
     */
    public function activeBarbers()
    {
        return $this->barbers()->wherePivot('is_active', true);
    }

    // ========== الصور ==========

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('salon_images')
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/jpg', 'image/webp']);
    }
}
