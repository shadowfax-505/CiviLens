@props([
    'name' => 'file',
    'accept' => null,
    'label' => 'Choose file',
    'hint' => null,
    'multiple' => false,
    'required' => false,
])

@php
    $acceptString = is_array($accept) ? \App\Support\Http\MimeMapper::toAcceptString($accept) : $accept;
    $inputId = $attributes->get('id') ?: \Illuminate\Support\Str::slug(str_replace(['[', ']'], ' ', $name), '_').'_'.\Illuminate\Support\Str::random(6);
@endphp

<label {{ $attributes->except('id')->merge(['class' => 'cl-upload-zone']) }} data-upload-zone for="{{ $inputId }}" tabindex="0">
    <input id="{{ $inputId }}" class="cl-upload-zone-input" name="{{ $name }}" type="file" @if ($acceptString) accept="{{ $acceptString }}" @endif @if ($multiple) multiple @endif @if ($required) required @endif>
    <span class="cl-upload-zone-icon" aria-hidden="true">
        <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 16V4m0 0 4 4m-4-4-4 4" />
            <path stroke-linecap="round" stroke-linejoin="round" d="M20 16.5V18a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-1.5" />
        </svg>
    </span>
    <span class="cl-upload-zone-title">{{ $label }}</span>
    <span class="cl-upload-zone-hint">{{ $hint ?: 'Click to browse or drag and drop a file here.' }}</span>
    <span class="cl-upload-zone-filename" data-upload-filename></span>
    @error($name)
        <span class="text-xs font-bold text-red-600 dark:text-red-300">{{ $message }}</span>
    @enderror
</label>
