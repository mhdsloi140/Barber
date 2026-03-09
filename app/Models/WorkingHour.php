<?php


namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WorkingHour extends Model
{
    use HasFactory;

    protected $table = 'working_hours';

    protected $fillable = [
        'workable_type',
        'workable_id',
        'day_of_week',
        'is_open',
        'shift1_start',
        'shift1_end',
        'shift2_start',
        'shift2_end',
        'break_start',
        'break_end'
    ];

    /**
     * العلاقات
     */
    public function workable()
    {
        return $this->morphTo();
    }
}
