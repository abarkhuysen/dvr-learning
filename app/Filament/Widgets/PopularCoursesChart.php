<?php

namespace App\Filament\Widgets;

use App\Models\Course;
use Filament\Widgets\ChartWidget;

class PopularCoursesChart extends ChartWidget
{
    protected static ?string $heading = 'Popular Courses';

    protected static ?int $sort = 4;

    protected function getData(): array
    {
        $courses = Course::withCount('enrollments')
            ->where('status', 'published')
            ->orderByDesc('enrollments_count')
            ->limit(5)
            ->get();

        return [
            'datasets' => [
                [
                    'label' => 'Enrollments',
                    'data' => $courses->pluck('enrollments_count')->toArray(),
                    'backgroundColor' => [
                        'rgba(59, 130, 246, 0.8)',
                        'rgba(16, 185, 129, 0.8)',
                        'rgba(251, 191, 36, 0.8)',
                        'rgba(239, 68, 68, 0.8)',
                        'rgba(139, 92, 246, 0.8)',
                    ],
                ],
            ],
            'labels' => $courses->pluck('title')->map(fn ($title) => \Illuminate\Support\Str::limit($title, 20)
            )->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => [
                    'position' => 'bottom',
                ],
            ],
        ];
    }
}
