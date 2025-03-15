<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CourseBudget extends Model
{
    use HasFactory;

    protected $fillable = [
        'course_id',
        'total_students',
        'total_budget',
        'used_budget',
        'remaining_budget',
    ];

    public function course()
    {
        return $this->belongsTo(Courses::class, 'course_id', 'course_id');
    }

    public function transactions()
    {
        return $this->hasMany(CompensationTransaction::class, 'course_id', 'course_id');
    }
}