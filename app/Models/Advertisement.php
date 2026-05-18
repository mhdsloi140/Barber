<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class Advertisement extends Model implements HasMedia
{
    use InteractsWithMedia;

    protected $table = 'advertisements';

    protected $fillable = [
        'title',
        'description',
        'link_url',
        'is_active',
        'sort_order',
        'starts_at',
        'ends_at'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime'
    ];

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('ad_images')
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp', 'image/jpg', 'image/gif'])
            ->registerMediaConversions(function (Media $media) {
                $this->addMediaConversion('thumb')
                    ->width(150)
                    ->height(150)
                    ->sharpen(10);

                $this->addMediaConversion('medium')
                    ->width(400)
                    ->height(300)
                    ->sharpen(10);
            });
    }

    public function getImagesAttribute()
    {
        return $this->getMedia('ad_images');
    }

    public function getFirstImageAttribute()
    {
        return $this->getFirstMediaUrl('ad_images');
    }
}
