<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CompensationRate extends Model
{
    use HasFactory;

    protected $fillable = [
        'teaching_type',
        'class_type',
        'degree_level',
        'rate_per_hour',
        'is_fixed_payment',
        'fixed_amount',
        'status',
    ];

    /**
     * ดึงอัตราค่าตอบแทนที่ใช้งานอยู่
     */
    public static function getActiveRate($teachingType, $classType, $degreeLevel = 'undergraduate')
    {
        return self::where('teaching_type', $teachingType)
            ->where('class_type', $classType)
            ->where('degree_level', $degreeLevel)
            ->where('status', 'active')
            ->where('is_fixed_payment', false)
            ->first();
    }

    /**
     * ดึงอัตราค่าตอบแทนแบบเหมาจ่ายที่ใช้งานอยู่
     */
    public static function getActiveFixedRate($teachingType, $degreeLevel = 'graduate')
    {
        return self::where('teaching_type', $teachingType)
            ->where('degree_level', $degreeLevel)
            ->where('status', 'active')
            ->where('is_fixed_payment', true)
            ->first();
    }
}