<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'phone',
        'bio',
        'avatar',
        'is_active',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        return Str::of($this->name)
            ->explode(' ')
            ->take(2)
            ->map(fn ($word) => Str::substr($word, 0, 1))
            ->implode('');
    }

    /**
     * Role helper methods
     */
    public function isStudent(): bool
    {
        return $this->role === 'student';
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /**
     * Role switching methods for admins
     */
    public function isActingAsStudent(): bool
    {
        return $this->role === 'admin' && session('acting_as') === 'student';
    }

    public function canSwitchRoles(): bool
    {
        return $this->role === 'admin';
    }

    public function getCurrentRole(): string
    {
        if ($this->isActingAsStudent()) {
            return 'student';
        }

        return $this->role;
    }

    public function getCurrentRoleDisplay(): string
    {
        if ($this->isActingAsStudent()) {
            return 'Student View (Admin)';
        }

        return ucfirst($this->role);
    }

    /**
     * Relationships
     */
    public function enrollments()
    {
        return $this->hasMany(Enrollment::class);
    }

    public function managedCourses()
    {
        return $this->hasMany(Course::class, 'created_by');
    }

    public function lessonProgress()
    {
        return $this->hasMany(UserLessonProgress::class);
    }
}
