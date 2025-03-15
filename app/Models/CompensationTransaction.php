<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CompensationTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'course_id',
        'student_id',
        'month_year',
        'hours_worked',
        'calculated_amount',
        'actual_amount',
        'is_adjusted',
        'adjustment_reason',
    ];


    public function student()
    {
        return $this->belongsTo(Students::class, 'student_id', 'id');
    }


    public function course()
    {
        return $this->belongsTo(Courses::class, 'course_id', 'course_id');
    }


    public function courseBudget()
    {
        return $this->belongsTo(CourseBudget::class, 'course_id', 'course_id');
    }
}