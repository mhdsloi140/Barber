<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Salon extends Model implements HasMedia
{
    use InteractsWithMedia;

    protected $fillable = [
        'name', 'owner_id', 'address', 'phone',
        'description', 'latitude', 'longitude', 'is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    // العلاقات
    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function barbers()
    {
        return $this->belongsToMany(User::class, 'barber_salon', 'salon_id', 'barber_id')
                    ->withPivot('is_active')
                    ->withTimestamps();
    }

    public function services()
    {
        return $this->hasMany(Service::class);
    }

    public function appointments()
    {
        return $this->hasMany(Appointment::class);
    }

    public function workingHours()
    {
        return $this->morphMany(WorkingHour::class, 'workable');
    }

    // دوال مساعدة
    public function getAvailableBarbers()
    {
        return $this->barbers()->where('is_active', true)->get();
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('salon_images')
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/jpg'])
            ->registerMediaConversions(function ($media) {
                $this->addMediaConversion('thumb')
                    ->width(150)
                    ->height(150);
                $this->addMediaConversion('medium')
                    ->width(400)
                    ->height(300);
            });
    }
}
