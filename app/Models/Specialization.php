<?php
// app/Models/Specialization.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Specialization extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
   
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * العلاقة مع الحلاقين
     */
    public function barbers()
    {
        return $this->belongsToMany(User::class, 'barber_specializations', 'specialization_id', 'barber_id');
    }

    /**
     * الحصول على الاسم بالعربية
     */

}
