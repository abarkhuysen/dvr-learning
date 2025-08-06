<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Course extends Model
{
    protected $fillable = [
        'code',
        'title', 
        'description',
        'status',
        'created_by',
        'metadata',
    ];
    
    protected $casts = [
        'metadata' => 'array',
    ];
    
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
    
    public function lessons(): HasMany
    {
        return $this->hasMany(Lesson::class);
    }
    
    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }
    
    public function stats()
    {
        return $this->hasOne(CourseStats::class);
    }
}
