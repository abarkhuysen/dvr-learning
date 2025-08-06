<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CourseStats extends Model
{
    protected $fillable = [
        'course_id',
        'total_enrollments',
        'active_enrollments',
        'average_completion_rate',
        'last_updated',
    ];
    
    protected $casts = [
        'average_completion_rate' => 'decimal:2',
        'last_updated' => 'datetime',
    ];
    
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }
}
