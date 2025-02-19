<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Courses extends Model
{
    use HasFactory;

    protected $primaryKey = 'course_id';
    public $incrementing = false; // ถ้า course_id เป็น string
    protected $keyType = 'string'; // ถ้า course_id เป็น string

    protected $fillable = [
        'course_id',
        'subject_id',
        'semester_id',
        'owner_teacher_id',
        'major_id',
        'cur_id',
        'status'
    ];

    public function subjects()
    {
        return $this->belongsTo(Subjects::class, 'subject_id', 'subject_id');
    }

    public function teachers()
    {
        return $this->belongsTo(Teachers::class, 'owner_teacher_id');
    }

    public function semesters()
    {
        return $this->belongsTo(Semesters::class, 'semester_id');
    }

    public function major()
    {
        return $this->belongsTo(Major::class, 'major_id');
    }
    
    public function curriculums()
    {
        return $this->belongsTo(Curriculums::class, 'cur_id');
    }

    // ความสัมพันธ์กับ Classes
    public function classes()
    {
        return $this->hasMany(Classes::class, 'course_id');
    }

    public function course_tas() 
    {
        return $this->hasMany(CourseTas::class , 'course_id', 'course_id');
    }

    public function course_teacher() 
    {
        return $this->hasMany(CourseTeacher::class);
    }

    public function extra_attendences()
    {
        // return $this->hasOne(ExtraAttendences::class);
    }

    public function teacherRequests()
{
    return $this->hasManyThrough(
        TeacherRequest::class,
        TeacherRequestsDetail::class,
        'course_id', // Foreign key on teacher_requests_detail table
        'id', // Local key on teacher_requests table
        'course_id', // Local key on courses table
        'teacher_request_id' // Foreign key on teacher_requests_detail table
    );
}
}
