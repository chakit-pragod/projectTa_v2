<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Classes extends Model
{
    use HasFactory;

    
    protected $fillable = [
        'section_num',
        'title',
        'class_type_id',
        'open_num',
        'enrolled_num',
        'available_num',
        'teachers_id',
        'courses_id',
        'semesters_id',
        'major_id',
    ];

    
    public function class_type()
    {
        return $this->belongsTo(ClassType::class);
    }

    
    public function teachers()
    {
        return $this->belongsToMany(Teachers::class);
    }

   // ความสัมพันธ์กับ Course
    public function course()
    {
        return $this->belongsTo(Courses::class, 'course_id');
    }

    
    public function semesters()
    {
        return $this->hasOne(Semesters::class);
    }

    
    public function major()
    {
        return $this->hasOne(Major::class);
    }

    public function teaching()
    {
        return $this->belongsTo(Teaching::class);
    }

    public function extra_teaching()
    {
        return $this->hasMany(ExtraTeaching::class);
    }

    public function courseTaClasses()
    {
        return $this->hasMany(CourseTaClasses::class, 'class_id', 'id');
    }
}
