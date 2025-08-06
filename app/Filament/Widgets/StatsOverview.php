<?php

namespace App\Filament\Widgets;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverview extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $totalStudents = User::where('role', 'student')->count();
        $activeEnrollments = Enrollment::where('status', 'active')->count();
        $totalCourses = Course::where('status', 'published')->count();
        $totalLessons = Lesson::count();

        // Calculate completion rate
        $completedEnrollments = Enrollment::where('status', 'completed')->count();
        $totalEnrollments = Enrollment::count();
        $completionRate = $totalEnrollments > 0
            ? round(($completedEnrollments / $totalEnrollments) * 100, 1)
            : 0;

        return [
            Stat::make('Total Students', $totalStudents)
                ->description('Registered students')
                ->descriptionIcon('heroicon-m-user-group')
                ->color('primary')
                ->chart([7, 2, 10, 3, 15, 4, 17]),

            Stat::make('Active Enrollments', $activeEnrollments)
                ->description('Currently enrolled')
                ->descriptionIcon('heroicon-m-academic-cap')
                ->color('success'),

            Stat::make('Published Courses', $totalCourses)
                ->description($totalLessons.' total lessons')
                ->descriptionIcon('heroicon-m-book-open')
                ->color('info'),

            Stat::make('Completion Rate', $completionRate.'%')
                ->description('Average course completion')
                ->descriptionIcon('heroicon-m-chart-bar')
                ->color($completionRate >= 70 ? 'success' : ($completionRate >= 50 ? 'warning' : 'danger')),
        ];
    }
}
