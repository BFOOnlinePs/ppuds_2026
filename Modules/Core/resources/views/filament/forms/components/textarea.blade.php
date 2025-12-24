<div>
    <label for="{{ $getId() }}">{{ $getLabel() }}</label>
    <textarea
        id="{{ $getId() }}"
        rows="{{ $getRows() ?? 3 }}"
        class="form-textarea"
        placeholder="{{ $getPlaceholder() }}"
    {{ $applyStateBindingModifiers('wire:model') }}="{{ $getStatePath() }}"
    {{ $isRequired() ? 'required' : '' }}
    ></textarea>
</div>
