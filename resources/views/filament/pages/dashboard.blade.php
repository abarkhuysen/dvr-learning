<x-filament-panels::page :title="__('Dashboard')">
    <x-filament-widgets::widgets
        :columns="$this->getColumns()"
        :widgets="$this->getVisibleWidgets()"
    />
</x-filament-panels::page>
