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
        'notifications_enabled',
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
        'notifications_enabled' => 'boolean',
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
    public function favoriteBarbers()
    {
        return $this->belongsToMany(User::class, 'favorite_barbers', 'customer_id', 'barber_id')
            ->withTimestamps();
    }
    public function favoritedByCustomers()
    {
        return $this->belongsToMany(User::class, 'favorite_barbers', 'barber_id', 'customer_id')
            ->withTimestamps();
    }
    public function isFavoriteBarber($barberId): bool
    {
        return $this->favoriteBarbers()->where('barber_id', $barberId)->exists();
    }
    public function favoriteSalons()
    {
        return $this->belongsToMany(Salon::class, 'favorite_salons', 'customer_id', 'salon_id')
            ->withTimestamps();
    }

    /**
     * التحقق مما إذا كان الصالون مفضلاً لدى العميل
     */
    public function isFavoriteSalon($salonId): bool
    {
        return $this->favoriteSalons()->where('salon_id', $salonId)->exists();
    }

    /**
     * الحصول على معرفات الصالونات المفضلة
     */
    public function getFavoriteSalonIdsAttribute(): array
    {
        return $this->favoriteSalons()->pluck('salon_id')->toArray();
    }

    /**
     * عدد الصالونات المفضلة
     */
    public function getFavoriteSalonsCountAttribute(): int
    {
        return $this->favoriteSalons()->count();
    }


  public function canReceiveNotifications(): bool
    {
        return $this->notifications_enabled && !empty($this->fcm_token);
    }

    /**
     * تفعيل الإشعارات
     */
    public function enableNotifications(): void
    {
        $this->update(['notifications_enabled' => true]);
    }

    /**
     * تعطيل الإشعارات
     */
    public function disableNotifications(): void
    {
        $this->update(['notifications_enabled' => false]);
    }

    /**
     * تبديل حالة الإشعارات
     */
    public function toggleNotifications(): bool
    {
        $this->notifications_enabled = !$this->notifications_enabled;
        $this->save();

        return $this->notifications_enabled;
    }

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


    public function activeDeviceTokens()
    {
        return $this->hasMany(DeviceToken::class)->where('is_active', true);
    }
    public function specializations()
    {
        return $this->belongsToMany(Specialization::class, 'barber_specializations', 'barber_id', 'specialization_id')
            ->withTimestamps();
    }
    public function deviceTokens()
    {
        return $this->hasMany(UserDeviceToken::class);
    }
    public function getLatestDeviceTokenAttribute()
    {
        return $this->deviceTokens()
            ->where('is_active', true)
            ->latest('last_used_at')
            ->value('device_token');
    }

    /**
     * تحديث أو إنشاء توكن جهاز
     */
    public function updateDeviceToken(string $token, string $deviceType = 'android', ?string $deviceName = null): UserDeviceToken
    {
        return $this->deviceTokens()->updateOrCreate(
            ['device_token' => $token],
            [
                'device_type' => $deviceType,
                'device_name' => $deviceName,
                'is_active' => true,
                'last_used_at' => now(),
            ]
        );
    }

    /**
     * تعطيل توكن جهاز
     */
    public function deactivateDeviceToken(string $token): bool
    {
        return $this->deviceTokens()
            ->where('device_token', $token)
            ->update(['is_active' => false]);
    }

    /**
     * حذف التوكنات غير النشطة القديمة
     */
    public function cleanupInactiveTokens(): int
    {
        return $this->deviceTokens()
            ->where('is_active', false)
            ->where('updated_at', '<', now()->subDays(30))
            ->delete();
    }
}
