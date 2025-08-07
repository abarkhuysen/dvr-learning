@props(['currentLesson', 'lessons', 'userProgress'])
<div>
    <flux:navlist class="w-full">
        @foreach($lessons as $lesson)
            <flux:navlist.item
                class="mb-4 truncate"
                wire:click="selectLesson({{ $lesson->id }})"
                :current="$currentLesson?->id === $lesson->id"
                icon="{{ $userProgress->where('lesson_id', $lesson->id)->first()?->completed ? 'check' : 'x-mark' }}">
                {{ $lesson->title }}
            </flux:navlist.item>
        @endforeach
    </flux:navlist>
</div>
