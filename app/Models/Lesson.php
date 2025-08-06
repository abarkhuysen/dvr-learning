<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Lesson extends Model
{
    protected $fillable = [
        'course_id',
        'title',
        'description',
        'vimeo_video_id',
        'order',
        'is_free',
        'metadata',
    ];

    protected $casts = [
        'is_free' => 'boolean',
        'metadata' => 'array',
    ];

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function userProgress(): HasMany
    {
        return $this->hasMany(UserLessonProgress::class);
    }
}
