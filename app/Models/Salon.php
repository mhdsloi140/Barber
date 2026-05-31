<?php
// app/Models/Salon.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class Salon extends Model implements HasMedia
{
    use SoftDeletes, InteractsWithMedia;

    protected $fillable = [
        'name',
        'owner_id',
        'address',
        'latitude',
        'longitude',
        'phone',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
    ];

    // ========== العلاقات ==========

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
    public function appointments()
    {
        return $this->hasMany(Appointment::class, 'salon_id');
    }

    public function workingHours()
    {
        return $this->morphMany(WorkingHour::class, 'workable');
    }
    public function favoritedByCustomers()
    {
        return $this->belongsToMany(User::class, 'favorite_salons', 'salon_id', 'customer_id')
            ->withTimestamps();
    }

    /**
     * التحقق مما إذا كان الصالون مفضلاً من قبل عميل معين
     */
    public function isFavoritedBy($customerId): bool
    {
        return $this->favoritedByCustomers()->where('customer_id', $customerId)->exists();
    }
    //


    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('salon_images')
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/jpg', 'image/webp'])
            ->registerMediaConversions(function (Media $media = null) {
                $this->addMediaConversion('thumb')
                    ->width(150)
                    ->height(150)
                    ->sharpen(10);

                $this->addMediaConversion('medium')
                    ->width(400)
                    ->height(300)
                    ->sharpen(10);

                $this->addMediaConversion('large')
                    ->width(800)
                    ->height(600);
            });
    }

    public function getImagesUrlsAttribute(): array
    {
        $urls = [];
        foreach ($this->getMedia('salon_images') as $media) {
            $urls[] = [
                'id' => $media->id,
                'original' => url($media->getUrl()),
                'medium' => $media->getUrl('medium'),
                'thumb' => $media->getUrl('thumb'),
                'large' => $media->getUrl('large'),
            ];
        }
        return $urls;
    }

    public function getMainImageUrlAttribute(): ?string
    {
        $media = $this->getFirstMedia('salon_images');
        return $media ? $media->getUrl('medium') : null;
    }

    // ========== دوال مساعدة ==========

    public function getBarbersCountAttribute(): int
    {
        return $this->barbers()->count();
    }
    public function ratings()
    {
        return $this->hasMany(Rating::class);
    }
    public function getLocationAttribute(): ?array
    {
        if ($this->latitude && $this->longitude) {
            return [
                'latitude' => $this->latitude,
                'longitude' => $this->longitude,
            ];
        }
        return null;
    }
}
