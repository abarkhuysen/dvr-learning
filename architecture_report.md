# Enterprise-Scale Learning Management System Architecture with Laravel 12 and FilamentPHP

## Executive Summary

Building an enterprise-scale Learning Management System (LMS) with Laravel 12 and FilamentPHP requires a comprehensive architectural approach that balances performance, security, and scalability. Laravel 12's refined features combined with FilamentPHP's powerful admin capabilities provide an excellent foundation for educational platforms serving thousands of concurrent users. This research synthesizes best practices, implementation patterns, and practical code examples to guide the development of a robust LMS solution.

## Laravel 12 Features for LMS Development

### Enhanced Real-Time Capabilities with Laravel Reverb

Laravel 12 introduces **Laravel Reverb**, a first-party WebSocket server that transforms real-time feature implementation. For LMS applications, this enables live progress tracking, collaborative learning sessions, and instant notifications without third-party dependencies.

```php
// Real-time progress broadcasting implementation
class LessonCompleted implements ShouldBroadcast
{
    public function __construct(
        public User $user,
        public Lesson $lesson,
        public int $progressPercentage
    ) {}
    
    public function broadcastOn()
    {
        return [
            new PrivateChannel("user.{$this->user->id}.progress"),
            new PrivateChannel("course.{$this->lesson->course_id}.stats")
        ];
    }
}
```

The framework's maintenance release philosophy means minimal breaking changes while introducing performance enhancements crucial for high-traffic educational platforms. **Key improvements include asynchronous caching mechanisms, enhanced query optimization, and modern PHP 8+ integration with JIT compilation support**.

### Multi-Guard Authentication Architecture

Laravel 12 maintains robust multi-guard authentication essential for LMS role separation. Since we're extending the default users table with roles, we can use a single table with role-based guards:

```php
// config/auth.php
'guards' => [
    'web' => [
        'driver' => 'session',
        'provider' => 'users',
    ],
    'admin' => [
        'driver' => 'session',
        'provider' => 'users',
    ],
],

'providers' => [
    'users' => [
        'driver' => 'eloquent',
        'model' => App\Models\User::class,
    ],
],

// Custom middleware for role-based access
class EnsureUserHasRole
{
    public function handle($request, Closure $next, $role)
    {
        if (!$request->user() || $request->user()->role !== $role) {
            abort(403, 'Unauthorized');
        }
        
        return $next($request);
    }
}
```

**User Model Extensions for Role Management:**

```php
// App/Models/User.php
class User extends Authenticatable
{
    protected $fillable = [
        'name', 'email', 'password', 'role', 'phone', 'bio', 'avatar', 'is_active'
    ];
    
    protected $casts = [
        'email_verified_at' => 'datetime',
        'is_active' => 'boolean',
        'password' => 'hashed',
    ];
    
    // Role helper methods
    public function isStudent(): bool
    {
        return $this->role === 'student';
    }
    
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }
    
    // Relationships
    public function enrollments()
    {
        return $this->hasMany(Enrollment::class);
    }
    
    // Admin users can manage courses
    public function managedCourses()
    {
        return $this->hasMany(Course::class, 'created_by');
    }
}
```

The optional **WorkOS AuthKit integration** provides enterprise-grade SSO capabilities, supporting up to 1 million monthly active users free—ideal for educational institutions requiring seamless authentication across multiple systems.

## Application Architecture Overview

### Two-Endpoint Design

The LMS follows a clean, simple architecture with just two main endpoints:

**Student Frontend (`/`):**
- Built with Livewire + Flux UI components
- Student authentication and dashboard
- Course viewing and video streaming
- Progress tracking and completion
- Mobile-responsive design

**Admin Backend (`/admin`):**
- Powered by FilamentPHP v3
- Complete course management (CRUD)
- User management and enrollment
- Vimeo video upload integration
- Analytics and progress monitoring
- Bulk operations and reporting

### Route Structure

```php
// routes/web.php
Route::get('/', function () {
    if (auth()->check()) {
        return auth()->user()->isAdmin() 
            ? redirect('/admin') 
            : redirect('/dashboard');
    }
    return view('welcome');
});

// Student routes (Livewire components)
Route::middleware(['auth', 'role:student'])->group(function () {
    Route::get('/dashboard', Dashboard::class)->name('dashboard');
    Route::get('/course/{course}', CourseViewer::class)->name('course.view');
    Route::get('/profile', Profile::class)->name('profile');
});

// Admin routes (FilamentPHP handles these automatically)
Route::middleware(['auth', 'role:admin'])->group(function () {
    // FilamentPHP admin panel routes are auto-registered
});

// Auth routes
require __DIR__.'/auth.php';
```

### FilamentPHP Admin Panel Only

FilamentPHP v3 is used exclusively for the admin backend, providing a powerful interface for managing the entire LMS:

```php
public function panel(Panel $panel): Panel
{
    return $panel
        ->id('admin')
        ->path('admin')
        ->authGuard('admin')
        ->authMiddleware(['auth', 'role:admin'])
        ->discoverResources(in: app_path('Filament/Admin/Resources'))
        ->pages([
            Pages\Dashboard::class,
        ])
        ->widgets([
            Widgets\StatsOverview::class,
            Widgets\CourseChart::class,
            Widgets\RecentEnrollments::class,
        ]);
}
```

**Admin Resources for Complete LMS Management:**

```php
// app/Filament/Admin/Resources/CourseResource.php
class CourseResource extends Resource
{
    protected static ?string $model = Course::class;
    protected static ?string $navigationIcon = 'heroicon-o-book-open';
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Course Information')
                    ->schema([
                        TextInput::make('title')
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn ($state, $set) => 
                                $set('code', Str::upper(Str::slug($state, '')))),
                        
                        TextInput::make('code')
                            ->required()
                            ->unique(ignoreRecord: true),
                        
                        Textarea::make('description')
                            ->required()
                            ->rows(3),
                        
                        Select::make('status')
                            ->options([
                                'draft' => 'Draft',
                                'published' => 'Published',
                                'archived' => 'Archived',
                            ])
                            ->default('draft'),
                    ]),
                
                Section::make('Lessons')
                    ->schema([
                        Repeater::make('lessons')
                            ->relationship()
                            ->schema([
                                TextInput::make('title')->required(),
                                Textarea::make('description'),
                                TextInput::make('vimeo_video_id')
                                    ->label('Vimeo Video ID')
                                    ->helperText('Enter the Vimeo video ID (numbers only)'),
                                TextInput::make('order')
                                    ->numeric()
                                    ->default(1),
                                Toggle::make('is_free'),
                            ])
                            ->collapsible()
                            ->itemLabel(fn (array $state): ?string => 
                                $state['title'] ?? null)
                            ->addActionLabel('Add Lesson')
                            ->reorderableWithButtons(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('title')
                    ->searchable()
                    ->sortable()
                    ->limit(50),
                BadgeColumn::make('status')
                    ->colors([
                        'secondary' => 'draft',
                        'success' => 'published',
                        'danger' => 'archived',
                    ]),
                TextColumn::make('lessons_count')
                    ->counts('lessons')
                    ->label('Lessons'),
                TextColumn::make('enrollments_count')
                    ->counts('enrollments')
                    ->label('Students'),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'published' => 'Published',
                        'archived' => 'Archived',
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('upload_video')
                    ->icon('heroicon-o-video-camera')
                    ->action(function (Course $record) {
                        return redirect()->route('admin.courses.upload-video', $record);
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}

// app/Filament/Admin/Resources/UserResource.php  
class UserResource extends Resource
{
    protected static ?string $model = User::class;
    protected static ?string $navigationIcon = 'heroicon-o-users';
    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('name')->required(),
                TextInput::make('email')->email()->required(),
                TextInput::make('phone'),
                Textarea::make('bio'),
                Select::make('role')
                    ->options([
                        'student' => 'Student',
                        'admin' => 'Admin',
                    ])
                    ->default('student'),
                Toggle::make('is_active')->default(true),
                
                Section::make('Enrollments')
                    ->schema([
                        Repeater::make('enrollments')
                            ->relationship()
                            ->schema([
                                Select::make('course_id')
                                    ->relationship('course', 'title')
                                    ->required(),
                                Select::make('status')
                                    ->options([
                                        'active' => 'Active',
                                        'completed' => 'Completed',
                                        'dropped' => 'Dropped',
                                    ])
                                    ->default('active'),
                            ])
                            ->addActionLabel('Add Course Enrollment'),
                    ])
                    ->visibleOn(['edit', 'view']),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('email')->searchable(),
                BadgeColumn::make('role')
                    ->colors([
                        'primary' => 'admin',
                        'secondary' => 'student',
                    ]),
                TextColumn::make('enrollments_count')
                    ->counts('enrollments')
                    ->label('Courses'),
                IconColumn::make('is_active')->boolean(),
                TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('role')
                    ->options([
                        'student' => 'Student',
                        'admin' => 'Admin',
                    ]),
                TernaryFilter::make('is_active'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ]);
    }
}
```

### Enhanced Course Management Forms

FilamentPHP provides sophisticated form builders for complex course structures:

```php
public static function form(Form $form): Form
{
    return $form
        ->schema([
            Section::make('Course Information')
                ->schema([
                    TextInput::make('title')
                        ->required()
                        ->live()
                        ->afterStateUpdated(fn ($state, $set) => 
                            $set('slug', Str::slug($state))),
                    
                    Repeater::make('lessons')
                        ->relationship()
                        ->schema([
                            TextInput::make('title')->required(),
                            FileUpload::make('video')
                                ->directory('lessons')
                                ->acceptedFileTypes(['video/mp4']),
                            Toggle::make('is_free'),
                        ])
                        ->collapsible()
                        ->itemLabel(fn (array $state): ?string => 
                            $state['title'] ?? null),
                ]),
        ]);
}
```

### Implementation Benefits

**Simplified Architecture:**
- **No unnecessary complexity** - FilamentPHP only where it adds value (admin panel)
- **Clear separation of concerns** - Students use Livewire frontend, admins use FilamentPHP backend
- **Easier maintenance** - Two distinct codebases with different purposes
- **Better performance** - Students don't load admin panel assets

**Development Efficiency:**
- **Faster development** - FilamentPHP handles all admin CRUD operations automatically
- **Consistent admin UX** - Professional admin interface out of the box
- **Easy customization** - Livewire frontend can be fully customized for student experience
- **Role-based routing** - Clear distinction between student and admin functionality

## Vimeo API Integration Architecture

### ✅ Complete Video Upload Implementation

**Current Status**: The application already has full Vimeo integration with direct video upload capabilities for admin users.

#### Existing VimeoService Features
The `VimeoService` class provides comprehensive video management:

```php
// Complete video upload with metadata and domain restrictions
public function uploadVideo(string $filePath, array $metadata = []): array
{
    $defaultMetadata = [
        'privacy' => [
            'view' => 'unlisted',
            'embed' => 'whitelist',
            'download' => false,
        ],
        'embed' => [
            'domains' => [config('app.url')],
        ],
    ];
    
    $videoData = array_merge($defaultMetadata, $metadata);
    $response = Vimeo::upload($filePath, $videoData);
    
    return [
        'success' => true,
        'video_id' => $this->extractVideoId($response),
        'uri' => $response,
    ];
}
```

**Available Operations**:
- ✅ Video upload with privacy controls
- ✅ Update video metadata  
- ✅ Delete videos from Vimeo
- ✅ Get video information and status
- ✅ Generate embed codes
- ✅ Set domain restrictions
- ✅ Check upload/transcode status

#### FilamentPHP Admin Integration

The `LessonResource` includes sophisticated upload functionality:

**Upload Action** (lines 108-136):
```php
Tables\Actions\Action::make('upload_video')
    ->icon('heroicon-o-arrow-up-tray')
    ->form([
        Forms\Components\FileUpload::make('video')
            ->acceptedFileTypes(['video/mp4', 'video/mov', 'video/avi'])
            ->maxSize(5120 * 1024) // 5GB limit
            ->directory('temp-videos')
            ->required(),
    ])
    ->action(function (array $data, Lesson $record): void {
        dispatch(new \App\Jobs\ProcessVideoUpload(
            $record,
            $data['video'],
            $record->title,
            $record->description
        ));
    })
    ->visible(fn (Lesson $record) => empty($record->vimeo_video_id))
```

**Additional Actions**:
- ✅ View video on Vimeo (external link)
- ✅ Remove video with confirmation
- ✅ Automatic cleanup on deletion

### Queue-Based Video Processing

**ProcessVideoUpload Job** handles complete upload workflow:

```php
class ProcessVideoUpload implements ShouldQueue
{
    public $tries = 3;
    public $timeout = 1800; // 30 minutes
    
    public function handle(VimeoService $vimeoService): void
    {
        $result = $vimeoService->uploadVideo($this->videoPath, $metadata);
        
        if ($result['success']) {
            $this->lesson->update(['vimeo_video_id' => $result['video_id']]);
            
            // Chain status checking
            dispatch(new CheckVideoStatus($this->lesson))
                ->delay(now()->addMinutes(2));
        }
    }
}
```

**CheckVideoStatus Job** monitors processing:
- ✅ Polls Vimeo API for transcode status
- ✅ Updates lesson metadata when ready
- ✅ Handles processing errors gracefully
- ✅ Progressive backoff retry strategy

### Enhanced Database Schema

**Lessons Table** with video support:
```php
Schema::create('lessons', function (Blueprint $table) {
    $table->id();
    $table->foreignId('course_id')->constrained()->cascadeOnDelete();
    $table->string('title');
    $table->text('description')->nullable();
    $table->string('vimeo_video_id')->nullable(); // ✅ Vimeo integration
    $table->integer('order')->default(0);
    $table->boolean('is_free')->default(false);
    $table->json('metadata')->nullable(); // ✅ Video metadata storage
    $table->timestamps();
});
```

### Recommended Enhancements

#### 1. Environment Configuration Template
Add to `.env.example`:
```env
# Vimeo API Configuration
VIMEO_CLIENT_ID=your_client_id
VIMEO_CLIENT_SECRET=your_client_secret
VIMEO_ACCESS_TOKEN=your_access_token
VIMEO_WEBHOOK_SECRET=your_webhook_secret
```

#### 2. Upload Progress Indicators
Enhance FilamentPHP form with real-time progress:
```php
Forms\Components\FileUpload::make('video')
    ->progressIndicator()
    ->uploadingMessage('Uploading video...')
    ->afterStateUpdated(fn ($state) => 
        $this->js('window.showUploadProgress()'))
```

#### 3. Advanced Video Options
Add video quality and processing options:
```php
Forms\Components\Select::make('quality')
    ->options([
        'auto' => 'Auto (Recommended)',
        'hd' => 'HD (720p)',
        'fhd' => '1080p',
        '4k' => '4K (if available)'
    ])
    ->default('auto')
```

#### 4. Bulk Upload Interface
Create dedicated bulk upload resource:
```php
Tables\Actions\BulkAction::make('bulk_upload')
    ->form([
        Forms\Components\Repeater::make('videos')
            ->schema([
                Forms\Components\FileUpload::make('file'),
                Forms\Components\TextInput::make('title'),
            ])
    ])
```

### Cost Optimization Strategies

For educational platforms, consider Vimeo's pricing tiers:
- **Plus ($20/month)**: Basic privacy controls, custom player
- **Pro ($50/month)**: Advanced analytics, team collaboration  
- **Business ($65/month)**: Live streaming capabilities
- **Enterprise**: Custom pricing with white-label options

## Scalable Database Architecture

### Core Schema Design with Performance Optimization

The database design prioritizes scalability through auto-incrementing integers and strategic indexing:

```php
Schema::create('courses', function (Blueprint $table) {
    $table->id();
    $table->string('code')->unique();
    $table->string('title');
    $table->text('description');
    $table->enum('status', ['draft', 'published', 'archived'])->default('draft');
    $table->foreignId('created_by')->constrained('users')->cascadeOnDelete(); // Admin who created the course
    $table->json('metadata')->nullable();
    $table->timestamps();
    
    // Performance-critical indexes
    $table->index(['status', 'created_at']);
    $table->index('created_by');
    $table->fullText(['title', 'description'], 'course_search_idx');
});

Schema::create('enrollments', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->foreignId('course_id')->constrained()->cascadeOnDelete();
    $table->timestamp('enrolled_at');
    $table->enum('status', ['active', 'completed', 'dropped'])->default('active');
    $table->decimal('progress_percentage', 5, 2)->default(0);
    
    // Composite indexes for common queries
    $table->unique(['user_id', 'course_id']);
    $table->index(['course_id', 'status', 'progress_percentage']);
});
```

### Polymorphic Relationships for Flexible Content

Enable diverse content types through polymorphic relationships:

```php
Schema::create('contents', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->morphs('contentable'); // Creates contentable_id (bigint) and contentable_type (string)
    $table->timestamps();
    
    // Critical composite index automatically created by morphs() method
});

Schema::create('lessons', function (Blueprint $table) {
    $table->id();
    $table->foreignId('course_id')->constrained()->cascadeOnDelete();
    $table->string('title');
    $table->text('description')->nullable();
    $table->string('vimeo_video_id')->nullable();
    $table->integer('order')->default(0);
    $table->boolean('is_free')->default(false);
    $table->timestamps();
    
    $table->index(['course_id', 'order']);
});

Schema::create('user_lesson_progress', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->foreignId('lesson_id')->constrained()->cascadeOnDelete();
    $table->boolean('completed')->default(false);
    $table->timestamp('completed_at')->nullable();
    $table->integer('watch_time_seconds')->default(0);
    $table->timestamps();
    
    $table->unique(['user_id', 'lesson_id']);
    $table->index(['lesson_id', 'completed']);
});
```

### Strategic Denormalization for Performance

Implement calculated fields for dashboard performance:

```php
Schema::create('course_stats', function (Blueprint $table) {
    $table->id();
    $table->foreignId('course_id')->constrained()->cascadeOnDelete();
    $table->integer('total_enrollments')->default(0);
    $table->integer('active_enrollments')->default(0);
    $table->decimal('average_completion_rate', 5, 2)->default(0);
    $table->timestamp('last_updated');
    $table->timestamps();
    
    $table->unique('course_id');
});

// Enhanced users table migration based on Laravel 12 default schema
Schema::table('users', function (Blueprint $table) {
    $table->enum('role', ['student', 'admin'])->default('student')->after('password');
    $table->string('phone')->nullable()->after('email');
    $table->text('bio')->nullable()->after('phone');
    $table->string('avatar')->nullable()->after('bio');
    $table->boolean('is_active')->default(true)->after('avatar');
    
    $table->index('role');
    $table->index(['is_active', 'role']);
});

// Supporting tables that work with Laravel 12's default structure
Schema::create('password_reset_tokens', function (Blueprint $table) {
    $table->string('email')->primary();
    $table->string('token');
    $table->timestamp('created_at')->nullable();
});

Schema::create('sessions', function (Blueprint $table) {
    $table->string('id')->primary();
    $table->foreignId('user_id')->nullable()->index();
    $table->string('ip_address', 45)->nullable();
    $table->text('user_agent')->nullable();
    $table->longText('payload');
    $table->integer('last_activity')->index();
});
```

## Enterprise Performance Optimization

### Laravel Octane for High Concurrency

Deploy Laravel Octane to eliminate request bootstrapping overhead:

```bash
composer require laravel/octane
php artisan octane:install
php artisan octane:start
```

Real-world benchmarks show **100%+ performance improvements** with proper OPcache configuration.

### Redis-Powered Caching Strategy

Implement multi-tiered caching for course content:

```php
class CourseService
{
    public function getUserCourses($userId)
    {
        return Cache::tags(['courses', "user:{$userId}"])
            ->remember("user_courses_{$userId}", 3600, function () use ($userId) {
                return Course::with(['lessons', 'instructors'])
                    ->whereHas('enrollments', function ($query) use ($userId) {
                        $query->where('user_id', $userId)->where('status', 'active');
                    })->get();
            });
    }
    
    public function getCourseProgress($userId, $courseId)
    {
        return Cache::remember("course_progress_{$userId}_{$courseId}", 1800, function () use ($userId, $courseId) {
            $totalLessons = Lesson::where('course_id', $courseId)->count();
            $completedLessons = UserLessonProgress::where('user_id', $userId)
                ->whereHas('lesson', fn($q) => $q->where('course_id', $courseId))
                ->where('completed', true)
                ->count();
                
            return $totalLessons > 0 ? ($completedLessons / $totalLessons) * 100 : 0;
        });
    }
}
```

### Queue Architecture with Laravel Horizon

Configure priority-based queue processing:

```php
// High-priority video processing
VideoProcessingJob::dispatch($video)->onQueue('high');

// Background notifications
SendEmailNotification::dispatch($user, $message)->onQueue('notifications');
```

## Security Implementation

### OWASP Compliance Framework

Implement comprehensive security measures following OWASP Top 10:

```php
// Secure validation with Laravel 12
$validated = $request->secureValidate([
    'course_title' => 'required|string|min:3|max:255',
    'content' => 'required|string|min:10',
    'published' => 'sometimes|boolean'
]);

// Policy-based authorization for student/admin roles
public function view(User $user, Course $course)
{
    // Students can view enrolled courses, admins can view all
    return $user->isAdmin() || $user->hasEnrolledIn($course);
}

public function update(User $user, Course $course)
{
    // Only admins can update courses
    return $user->isAdmin();
}

public function enroll(User $user, Course $course)
{
    // Only students can enroll in courses
    return $user->isStudent() && $course->status === 'published';
}
```

### GDPR Compliance Architecture

Implement data portability and privacy controls:

```php
class User extends Authenticatable implements PortableContract
{
    use Portable;
    
    protected $gdprWith = ['courses', 'assignments', 'grades'];
    protected $gdprHidden = ['password', 'remember_token'];
}
```

### API Rate Limiting

Protect API endpoints with intelligent rate limiting:

```php
RateLimiter::for('api', function (Request $request) {
    return $request->user()
        ? Limit::perMinute(100)->by($request->user()->id)
        : Limit::perMinute(20)->by($request->ip());
});
```

## Frontend Architecture with Livewire and Flux UI

### Modern Livewire + Flux UI Stack

Laravel 12's Livewire starter kit now comes with Flux UI pre-installed, providing a robust, hand-crafted UI component library built specifically for Livewire applications using Tailwind CSS. This eliminates the need for complex JavaScript frameworks while delivering enterprise-grade user experiences.

### Installation and Setup

Install Flux UI in your Laravel 12 project:

```bash
# Install Flux UI (free tier)
composer require livewire/flux

# Optional: Install Flux Pro for advanced components
php artisan flux:activate

# Setup Tailwind CSS integration
```

**Layout Configuration:**

```blade
<!-- resources/views/layouts/app.blade.php -->
<head>
    @fluxAppearance
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600&display=swap" rel="stylesheet" />
</head>
<body>
    <!-- Your content -->
    @fluxScripts
</body>
```

**Tailwind CSS Configuration:**

```css
/* resources/css/app.css */
@import 'tailwindcss';
@import '../../vendor/livewire/flux/dist/flux.css';
@custom-variant dark (&:where(.dark, .dark *));

@theme {
    --font-sans: Inter, sans-serif;
}
```

### Student Dashboard with Flux Components

Create a comprehensive student dashboard using Flux UI components:

```php
// app/Livewire/Student/Dashboard.php
class Dashboard extends Component
{
    public $enrolledCourses;
    public $recentProgress;
    public $completionStats;
    
    public function mount()
    {
        $this->loadStudentData();
    }
    
    public function loadStudentData()
    {
        $user = auth()->user();
        $this->enrolledCourses = $user->enrollments()
            ->with(['course.lessons'])
            ->where('status', 'active')
            ->get();
            
        $this->recentProgress = UserLessonProgress::where('user_id', $user->id)
            ->with('lesson.course')
            ->latest()
            ->limit(5)
            ->get();
            
        $this->completionStats = $this->calculateStats();
    }
    
    public function render()
    {
        return view('livewire.student.dashboard');
    }
}
```

**Dashboard Blade Template with Flux UI:**

```blade
<!-- resources/views/livewire/student/dashboard.blade.php -->
<div>
    <flux:header container class="border-b">
        <flux:navbar class="max-w-7xl mx-auto">
            <flux:navbar.brand href="/" wire:navigate>
                <flux:icon name="academic-cap" class="size-6" />
                LMS Platform
            </flux:navbar.brand>
            
            <flux:spacer />
            
            <flux:navbar.item icon="bell" badge="3" />
            <flux:dropdown>
                <flux:dropdown.trigger>
                    <flux:avatar size="sm" src="{{ auth()->user()->avatar }}" />
                </flux:dropdown.trigger>
                <flux:dropdown.menu>
                    <flux:dropdown.item icon="user-circle" href="/profile">Profile</flux:dropdown.item>
                    <flux:dropdown.item icon="cog" href="/settings">Settings</flux:dropdown.item>
                    <flux:dropdown.item separator />
                    <flux:dropdown.item icon="arrow-left-start-on-rectangle" wire:click="logout">Logout</flux:dropdown.item>
                </flux:dropdown.menu>
            </flux:dropdown>
        </flux:navbar>
    </flux:header>

    <main class="max-w-7xl mx-auto py-6">
        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <flux:card>
                <div class="flex items-center justify-between">
                    <div>
                        <flux:text size="sm" class="text-gray-600">Enrolled Courses</flux:text>
                        <flux:heading size="xl">{{ $enrolledCourses->count() }}</flux:heading>
                    </div>
                    <flux:icon name="book-open" class="size-8 text-blue-500" />
                </div>
            </flux:card>
            
            <flux:card>
                <div class="flex items-center justify-between">
                    <div>
                        <flux:text size="sm" class="text-gray-600">Completion Rate</flux:text>
                        <flux:heading size="xl">{{ number_format($completionStats['rate'], 1) }}%</flux:heading>
                    </div>
                    <flux:icon name="chart-bar" class="size-8 text-green-500" />
                </div>
            </flux:card>
            
            <flux:card>
                <div class="flex items-center justify-between">
                    <div>
                        <flux:text size="sm" class="text-gray-600">Hours Watched</flux:text>
                        <flux:heading size="xl">{{ $completionStats['hours'] }}</flux:heading>
                    </div>
                    <flux:icon name="clock" class="size-8 text-purple-500" />
                </div>
            </flux:card>
        </div>

        <!-- Course Grid -->
        <flux:card class="p-6">
            <flux:heading size="lg" class="mb-6">My Courses</flux:heading>
            
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                @foreach($enrolledCourses as $enrollment)
                    <div class="border rounded-lg overflow-hidden">
                        <div class="h-48 bg-gradient-to-br from-blue-400 to-blue-600 relative">
                            <div class="absolute inset-0 flex items-center justify-center">
                                <flux:icon name="play-circle" class="size-16 text-white opacity-80" />
                            </div>
                        </div>
                        
                        <div class="p-4">
                            <flux:heading size="md" class="mb-2">{{ $enrollment->course->title }}</flux:heading>
                            <flux:text size="sm" class="text-gray-600 mb-4">
                                {{ Str::limit($enrollment->course->description, 100) }}
                            </flux:text>
                            
                            <!-- Progress Bar -->
                            <div class="mb-4">
                                <div class="flex justify-between items-center mb-2">
                                    <flux:text size="sm">Progress</flux:text>
                                    <flux:text size="sm">{{ number_format($enrollment->progress_percentage) }}%</flux:text>
                                </div>
                                <div class="w-full bg-gray-200 rounded-full h-2">
                                    <div class="bg-blue-600 h-2 rounded-full" 
                                         style="width: {{ $enrollment->progress_percentage }}%"></div>
                                </div>
                            </div>
                            
                            <flux:button wire:click="viewCourse({{ $enrollment->course->id }})" 
                                       variant="primary" 
                                       class="w-full">
                                Continue Learning
                            </flux:button>
                        </div>
                    </div>
                @endforeach
            </div>
        </flux:card>
    </main>
</div>
```

### Course Viewing Interface

Create an immersive course viewing experience:

```php
// app/Livewire/Student/CourseViewer.php
class CourseViewer extends Component
{
    public Course $course;
    public $currentLesson;
    public $lessons;
    public $userProgress;
    public $showCompletionModal = false;
    
    public function mount(Course $course)
    {
        $this->course = $course;
        $this->lessons = $course->lessons()->orderBy('order')->get();
        $this->loadCurrentLesson();
        $this->loadProgress();
    }
    
    public function selectLesson($lessonId)
    {
        $this->currentLesson = $this->lessons->find($lessonId);
    }
    
    public function markComplete()
    {
        UserLessonProgress::updateOrCreate(
            ['user_id' => auth()->id(), 'lesson_id' => $this->currentLesson->id],
            ['completed' => true, 'completed_at' => now()]
        );
        
        $this->loadProgress();
        $this->showCompletionModal = true;
        
        // Update enrollment progress
        $this->updateCourseProgress();
    }
    
    public function render()
    {
        return view('livewire.student.course-viewer');
    }
}
```

**Course Viewer Template:**

```blade
<!-- resources/views/livewire/student/course-viewer.blade.php -->
<div class="min-h-screen bg-gray-50">
    <!-- Course Header -->
    <flux:header class="bg-white border-b">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex items-center justify-between py-4">
                <div class="flex items-center space-x-4">
                    <flux:button href="/dashboard" wire:navigate variant="ghost" icon="arrow-left">
                        Back to Dashboard
                    </flux:button>
                    <flux:heading size="lg">{{ $course->title }}</flux:heading>
                </div>
                
                <div class="flex items-center space-x-4">
                    <flux:text size="sm" class="text-gray-600">
                        {{ $userProgress->where('completed', true)->count() }} / {{ $lessons->count() }} completed
                    </flux:text>
                    <div class="w-32 bg-gray-200 rounded-full h-2">
                        <div class="bg-green-600 h-2 rounded-full" 
                             style="width: {{ ($userProgress->where('completed', true)->count() / $lessons->count()) * 100 }}%"></div>
                    </div>
                </div>
            </div>
        </div>
    </flux:header>

    <div class="flex">
        <!-- Sidebar - Lesson List -->
        <div class="w-80 bg-white border-r h-screen overflow-y-auto">
            <div class="p-4">
                <flux:heading size="md" class="mb-4">Course Content</flux:heading>
                
                <div class="space-y-2">
                    @foreach($lessons as $lesson)
                        <div class="border rounded-lg p-3 cursor-pointer transition-colors
                                    {{ $currentLesson?->id === $lesson->id ? 'bg-blue-50 border-blue-200' : 'hover:bg-gray-50' }}"
                             wire:click="selectLesson({{ $lesson->id }})">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center space-x-3">
                                    @if($userProgress->where('lesson_id', $lesson->id)->first()?->completed)
                                        <flux:icon name="check-circle" class="size-5 text-green-500" />
                                    @else
                                        <flux:icon name="play-circle" class="size-5 text-gray-400" />
                                    @endif
                                    
                                    <div>
                                        <flux:text class="font-medium">{{ $lesson->title }}</flux:text>
                                        <flux:text size="sm" class="text-gray-500">Lesson {{ $lesson->order }}</flux:text>
                                    </div>
                                </div>
                                
                                @if($lesson->is_free)
                                    <flux:badge color="green" size="sm">Free</flux:badge>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <!-- Main Content - Video Player -->
        <div class="flex-1">
            @if($currentLesson)
                <div class="p-6">
                    <!-- Video Player -->
                    <div class="bg-black rounded-lg mb-6" style="aspect-ratio: 16/9;">
                        @if($currentLesson->vimeo_video_id)
                            <iframe src="https://player.vimeo.com/video/{{ $currentLesson->vimeo_video_id }}?badge=0&autopause=0&player_id=0&app_id=58479"
                                    class="w-full h-full rounded-lg"
                                    frameborder="0"
                                    allow="autoplay; fullscreen; picture-in-picture"
                                    title="{{ $currentLesson->title }}">
                            </iframe>
                        @else
                            <div class="flex items-center justify-center h-full text-white">
                                <flux:text>Video not available</flux:text>
                            </div>
                        @endif
                    </div>

                    <!-- Lesson Info -->
                    <div class="flex justify-between items-start mb-6">
                        <div>
                            <flux:heading size="xl" class="mb-2">{{ $currentLesson->title }}</flux:heading>
                            <flux:text class="text-gray-600">{{ $currentLesson->description }}</flux:text>
                        </div>
                        
                        @if(!$userProgress->where('lesson_id', $currentLesson->id)->first()?->completed)
                            <flux:button wire:click="markComplete" variant="primary" icon="check">
                                Mark Complete
                            </flux:button>
                        @else
                            <flux:badge color="green" icon="check">Completed</flux:badge>
                        @endif
                    </div>

                    <!-- Navigation -->
                    <div class="flex justify-between">
                        @php
                            $currentIndex = $lessons->search(fn($l) => $l->id === $currentLesson->id);
                            $prevLesson = $currentIndex > 0 ? $lessons[$currentIndex - 1] : null;
                            $nextLesson = $currentIndex < $lessons->count() - 1 ? $lessons[$currentIndex + 1] : null;
                        @endphp
                        
                        @if($prevLesson)
                            <flux:button wire:click="selectLesson({{ $prevLesson->id }})" 
                                       variant="outline" 
                                       icon="arrow-left">
                                Previous: {{ $prevLesson->title }}
                            </flux:button>
                        @else
                            <div></div>
                        @endif
                        
                        @if($nextLesson)
                            <flux:button wire:click="selectLesson({{ $nextLesson->id }})" 
                                       variant="primary" 
                                       icon:trailing="arrow-right">
                                Next: {{ $nextLesson->title }}
                            </flux:button>
                        @endif
                    </div>
                </div>
            @else
                <div class="flex items-center justify-center h-full">
                    <flux:text class="text-gray-500">Select a lesson to start learning</flux:text>
                </div>
            @endif
        </div>
    </div>

    <!-- Completion Modal -->
    <flux:modal name="completion" :show="$showCompletionModal" wire:model="showCompletionModal">
        <div class="text-center p-6">
            <flux:icon name="check-circle" class="size-16 text-green-500 mx-auto mb-4" />
            <flux:heading size="lg" class="mb-2">Lesson Complete!</flux:heading>
            <flux:text class="text-gray-600 mb-6">
                Great job! You've completed "{{ $currentLesson?->title }}".
            </flux:text>
            <flux:button wire:click="$set('showCompletionModal', false)" variant="primary">
                Continue Learning
            </flux:button>
        </div>
    </flux:modal>
</div>
```

### Advanced Flux UI Features

**Real-time Progress Updates with Laravel Reverb:**

```php
// In your CourseViewer component
public function markComplete()
{
    // ... existing logic ...
    
    // Broadcast progress update
    broadcast(new LessonCompleted(auth()->user(), $this->currentLesson));
}
```

**Advanced Components with Flux Pro:**

```blade
<!-- Data Tables for Admin Dashboard -->
<flux:table :paginate="$students" searchable>
    <flux:table.columns>
        <flux:table.column sortable>Name</flux:table.column>
        <flux:table.column sortable>Enrolled Courses</flux:table.column>
        <flux:table.column sortable>Progress</flux:table.column>
        <flux:table.column>Actions</flux:table.column>
    </flux:table.columns>
    
    <flux:table.rows>
        @foreach($students as $student)
            <flux:table.row>
                <flux:table.cell>
                    <div class="flex items-center space-x-3">
                        <flux:avatar size="sm" :src="$student->avatar" />
                        {{ $student->name }}
                    </div>
                </flux:table.cell>
                <flux:table.cell>{{ $student->enrollments_count }}</flux:table.cell>
                <flux:table.cell>
                    <div class="flex items-center space-x-2">
                        <div class="w-20 bg-gray-200 rounded-full h-2">
                            <div class="bg-blue-600 h-2 rounded-full" 
                                 style="width: {{ $student->avg_progress }}%"></div>
                        </div>
                        <span class="text-sm">{{ number_format($student->avg_progress) }}%</span>
                    </div>
                </flux:table.cell>
                <flux:table.cell>
                    <flux:dropdown>
                        <flux:dropdown.trigger>
                            <flux:button variant="ghost" icon="ellipsis-horizontal" />
                        </flux:dropdown.trigger>
                        <flux:dropdown.menu>
                            <flux:dropdown.item wire:click="viewStudent({{ $student->id }})">View Details</flux:dropdown.item>
                            <flux:dropdown.item wire:click="resetProgress({{ $student->id }})">Reset Progress</flux:dropdown.item>
                        </flux:dropdown.menu>
                    </flux:dropdown>
                </flux:table.cell>
            </flux:table.row>
        @endforeach
    </flux:table.rows>
</flux:table>

<!-- Advanced Form Components -->
<flux:fieldset>
    <flux:legend>Course Settings</flux:legend>
    
    <div class="space-y-6">
        <flux:field>
            <flux:label>Course Duration</flux:label>
            <flux:input.group>
                <flux:input wire:model="duration" type="number" placeholder="0" />
                <flux:input.group.suffix>hours</flux:input.group.suffix>
            </flux:input.group>
        </flux:field>
        
        <flux:field>
            <flux:label>Start Date</flux:label>
            <flux:input wire:model="startDate" type="date" />
        </flux:field>
        
        <flux:field>
            <flux:label>Instructor</flux:label>
            <flux:select wire:model="instructorId" placeholder="Select instructor...">
                @foreach($instructors as $instructor)
                    <option value="{{ $instructor->id }}">{{ $instructor->name }}</option>
                @endforeach
            </flux:select>
        </flux:field>
    </div>
</flux:fieldset>
```

### Performance Optimizations for Livewire

**Efficient Data Loading:**

```php
// Use lazy loading for expensive operations
public function loadCourseData()
{
    $this->readyToLoad = true;
}

public function render()
{
    return view('livewire.student.dashboard', [
        'courses' => $this->readyToLoad 
            ? $this->getCachedCourses() 
            : collect()
    ]);
}

private function getCachedCourses()
{
    return Cache::remember("user_courses_" . auth()->id(), 300, function () {
        return auth()->user()->enrollments()
            ->with(['course.lessons'])
            ->get();
    });
}
```

**Leverage Flux UI's Design Principles:**

- **Simplicity**: Use single-word component names like `flux:input`, `flux:button` instead of verbose alternatives
- **Composability**: Mix and match core components to create robust interfaces
- **Modern CSS**: Flux leverages native browser features like the popover attribute and dialog element for better performance

This Livewire + Flux UI architecture provides a complete, modern frontend solution that eliminates the complexity of separate JavaScript frameworks while delivering enterprise-grade user experiences optimized for educational platforms.

## Deployment and Scaling Strategy

### Containerization with Docker

```dockerfile
FROM php:8.2-fpm

# Install dependencies
RUN apt-get update && apt-get install -y \
    git curl libpng-dev libonig-dev libxml2-dev zip unzip

# Install PHP extensions
RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd

# Copy application
COPY . /var/www
WORKDIR /var/www

# Install dependencies
RUN composer install --optimize-autoloader --no-dev
```

### Kubernetes Orchestration

Deploy with horizontal scaling capabilities:

```yaml
apiVersion: apps/v1
kind: Deployment
metadata:
  name: laravel-lms
spec:
  replicas: 3
  template:
    spec:
      containers:
      - name: laravel-lms
        image: your-registry/laravel-lms:latest
        resources:
          requests:
            cpu: 500m
            memory: 512Mi
          limits:
            cpu: 1000m
            memory: 1Gi
```

## Implementation Roadmap

### Phase 1: Foundation (Weeks 1-2)
- Laravel 12 setup with authentication guards
- FilamentPHP multi-panel configuration
- Basic database schema implementation
- Redis caching infrastructure

### Phase 2: Core Features (Weeks 3-4)
- Course management with FilamentPHP
- Vimeo API integration
- Student enrollment system
- Progress tracking implementation

### Phase 3: Advanced Features (Weeks 5-6)
- Real-time updates with Laravel Reverb
- Video processing queues
- Advanced reporting dashboards
- Mobile-responsive frontend

### Phase 4: Enterprise Readiness (Weeks 7-8)
- Performance optimization with Octane
- Security hardening and OWASP compliance
- Load testing and optimization
- Deployment and monitoring setup

## Key Recommendations

1. **Start with Laravel 12's new starter kits** for modern frontend integration with TypeScript and Tailwind CSS support

2. **Leverage FilamentPHP's caching mechanisms** early to ensure admin panel performance at scale

3. **Implement strategic database denormalization** for frequently accessed data like course statistics and user progress

4. **Use Laravel Reverb for real-time features** instead of third-party WebSocket solutions for better integration

5. **Plan for horizontal scaling from the beginning** with stateless architecture and external session storage

6. **Prioritize security with Laravel 12's secureValidate()** method and comprehensive policy-based authorization

7. **Consider Vimeo's educational pricing** and implement caching to minimize API calls

Building an enterprise LMS with Laravel 12 and FilamentPHP provides a solid foundation for scalable educational platforms. The combination of Laravel's mature ecosystem, FilamentPHP's powerful admin capabilities, and modern frontend approaches enables the creation of feature-rich learning management systems that can serve thousands of concurrent users while maintaining excellent performance and user experience.
