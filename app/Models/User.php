<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class User extends Authenticatable implements HasMedia
{
    use HasApiTokens, Notifiable, HasRoles, SoftDeletes, InteractsWithMedia;
    protected $guard_name = 'api';
    protected $fillable = [
        'name',
        'phone',
        'email',
        'password',
        'role',
        'is_active',
        'verification_code',
        'verification_expires_at',
        'phone_verified_at',
        'email_verified_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'verification_code',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'phone_verified_at' => 'datetime',
        'verification_expires_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    // ========== العلاقات ==========


    public function ownedSalon()
    {
        return $this->hasOne(Salon::class, 'owner_id');
    }

    /**
     * العلاقة مع الصالونات التي يعمل بها (للحلاق)
     * حلاق -> يعمل في عدة صالونات
     */
    public function salons()
    {
        return $this->belongsToMany(Salon::class, 'barber_salon', 'barber_id', 'salon_id')
            ->withPivot('is_active')
            ->withTimestamps();
    }

    /**
     * العلاقة مع المواعيد كزبون
     * زبون -> لديه عدة مواعيد
     */
    public function customerAppointments()
    {
        return $this->hasMany(Appointment::class, 'customer_id');
    }

    /**
     * العلاقة مع المواعيد كحلاق
     * حلاق -> لديه عدة مواعيد
     */
    public function barberAppointments()
    {
        return $this->hasMany(Appointment::class, 'barber_id');
    }

    /**
     * العلاقة مع خدمات الحلاق الخاصة
     * حلاق -> لديه عدة خدمات
     */
    public function barberServices()
    {
        return $this->hasMany(BarberService::class, 'barber_id');
    }

    /**
     * العلاقة مع أوقات العمل
     * (للحلاق أو صاحب الصالون)
     */
    public function workingHours()
    {
        return $this->morphMany(WorkingHour::class, 'workable');
    }
    public function ratingsGiven()
    {
        return $this->hasMany(Rating::class, 'customer_id');
    }

    public function ratingsReceived()
    {
        return $this->hasMany(Rating::class, 'barber_id');
    }


    /**
     * العلاقة مع التقييمات (كحلاق)
     */
    // public function ratings()
    // {
    //     return $this->hasMany(Rating::class, 'barber_id');
    // }

    // ========== دوال مساعدة ==========

    public function isSalonOwner(): bool
    {
        return $this->role === 'salon_owner' || $this->hasRole('salon_owner');
    }

    public function isBarber(): bool
    {
        return $this->role === 'barber' || $this->hasRole('barber');
    }

    public function isCustomer(): bool
    {
        return $this->role === 'customer' || $this->hasRole('customer');
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin' || $this->hasRole('admin');
    }

    // ========== الصور الشخصية ==========

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('avatar')
            ->singleFile()
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/jpg', 'image/webp']);
    }

    public function getAvatarUrlAttribute(): ?string
    {
        return $this->getFirstMediaUrl('avatar');
    }
      public function getProfileImageUrlAttribute()
    {
        $media = $this->getFirstMedia('profile_image');
        if ($media) {
            return $media->getUrl('thumb');
        }
        return null;
    }
}
