<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class User extends Authenticatable implements HasMedia
{
    use HasApiTokens, Notifiable, HasRoles, InteractsWithMedia;

    protected string $guard_name = 'api';

    protected $fillable = [
        'name',
        'phone',
        'email',
        'password',
        'role',
        'is_active',
        'center_name',
        'address',
        'owner_name',
    ];


    protected $hidden = ['password', 'remember_token', 'verification_code'];

   protected $casts = [
        'email_verified_at' => 'datetime',
        'phone_verified_at' => 'datetime',
        'is_active' => 'boolean',
    ];
  public function registerMediaCollections(): void
    {
        $this->addMediaCollection('avatar')
            ->singleFile() // صورة واحدة فقط لكل مستخدم
            ->acceptsMimeTypes(['image/jpeg', 'image/jpg', 'image/png', 'image/webp'])
            ->registerMediaConversions(function (Media $media = null) {
                $this->addMediaConversion('thumb')
                    ->width(100)
                    ->height(100)
                    ->sharpen(10);

                $this->addMediaConversion('medium')
                    ->width(300)
                    ->height(300);
            });
    }

   
    public function getAvatarUrlAttribute(): ?string
    {
        return $this->getFirstMediaUrl('avatar', 'medium') ?: null;
    }


    public function getAvatarThumbUrlAttribute(): ?string
    {
        return $this->getFirstMediaUrl('avatar', 'thumb') ?: null;
    }


    public function hasAvatar(): bool
    {
        return $this->getFirstMedia('avatar') !== null;
    }
    // العلاقات
    public function ownedSalon()
    {
        return $this->hasOne(Salon::class, 'owner_id');
    }

    public function salons()
    {
        return $this->belongsToMany(Salon::class, 'barber_salon', 'barber_id', 'salon_id')
                    ->withPivot('is_active')
                    ->withTimestamps();
    }

    public function customerAppointments()
    {
        return $this->hasMany(Appointment::class, 'customer_id');
    }

    public function barberAppointments()
    {
        return $this->hasMany(Appointment::class, 'barber_id');
    }

    // دوال مساعدة
    public function isSalonOwner()
    {
        return $this->role === 'salon_owner' || $this->hasRole('salon_owner');
    }

    public function isBarber()
    {
        return $this->role === 'barber' || $this->hasRole('barber');
    }

    public function isCustomer()
    {
        return $this->role === 'customer' || $this->hasRole('customer');
    }

    public function isAdmin()
    {
        return $this->role === 'admin' || $this->hasRole('admin');
    }

    public function generateVerificationCode()
    {
        $this->verification_code = rand(100000, 999999);
        $this->verification_expires_at = now()->addMinutes(10);
        $this->save();
        return $this->verification_code;
    }

    public function verifyCode($code)
    {
        if ($this->verification_code === $code && $this->verification_expires_at > now()) {
            $this->phone_verified_at = now();
            $this->verification_code = null;
            $this->verification_expires_at = null;
            $this->save();
            return true;
        }
        return false;
    }
}
