<x-dynamic-component
    :component="$getFieldWrapperView()"
    :field="$field"
    :label-sr-only="$isLabelHidden()"
>

@foreach ($this->categories as $item)
    <x-filament::input.checkbox

/>

@endforeach

</x-dynamic-component>
