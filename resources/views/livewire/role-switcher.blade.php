<div>
    @if(auth()->check() && auth()->user()->canSwitchRoles())
        @if(auth()->user()->isActingAsStudent())
            <flux:menu.item as="button" wire:click="exitStudentView" icon="x-mark"  class="w-full">
                {{ __('Exit Student View') }}
            </flux:menu.item>
        @endif
    @endif
</div>
