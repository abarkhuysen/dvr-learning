<div>
    @if(auth()->check() && auth()->user()->canSwitchRoles())
        @if(auth()->user()->isActingAsStudent())

            <flux:menu.separator />

            <flux:menu.item as="button" wire:click="exitStudentView" icon="x-mark" class="w-full">
                {{ __('Exit Student View') }}
            </flux:menu.item>



            <!-- Banner shown when admin is in student view -->
            <div class="bg-amber-50 border-b border-amber-200">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div class="flex items-center justify-between py-2">
                        <div class="flex items-center space-x-2">
                            <flux:icon name="eye" class="size-5 text-amber-600" />
                            <flux:text size="sm" class="text-amber-800 font-medium">
                                You are viewing as a student
                            </flux:text>
                        </div>
                        <flux:button
                            wire:click="exitStudentView"
                            variant="ghost"
                            size="sm"
                            class="text-amber-700 hover:text-amber-900">
                            Exit Student View
                        </flux:button>
                    </div>
                </div>
            </div>
        @else
            <!-- Dropdown for role switching in admin panel -->
            <flux:dropdown>
                <flux:button variant="ghost" icon="arrows-right-left" size="sm">
                    Switch View
                </flux:button>
                <flux:menu>
                    <flux:menu.item
                        wire:click="switchToStudent"
                        icon="academic-cap">
                        View as Student
                    </flux:menu.item>
                </flux:menu>
            </flux:dropdown>
        @endif
    @endif
</div>
