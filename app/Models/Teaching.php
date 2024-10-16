<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Teaching extends Model
{
    use HasFactory;

    protected $table = 'teaching';

    protected $fillable = [
        'start_time',
        'end_time',
        'duration',
        'class_type_id',
        'status',
        'classes_id',
        'teachers_id',
    ];

    public function class_type()
    {
        return $this->belongsTo(ClassType::class);
    }

    // สร้างความสัมพันธ์ไปยัง Classes
    public function class()
    {
        return $this->belongsTo(Classes::class, 'class_id');
    }

    // สร้างความสัมพันธ์ไปยัง Teachers
    public function teacher()
    {
        return $this->belongsTo(Teachers::class, 'teacher_id');
    }

    public function attendance()
    {
        return $this->hasOne(Attendances::class, 'teaching_id');
    }

    public function extra_teaching()
    {
        return $this->hasMany(ExtraTeaching::class);
    }
}
