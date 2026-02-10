<!-- resources/views/components/button.blade.php -->
<button 
    type="{{ $type }}"
    {{ $attributes->merge([
        'class' => 'px-4 py-2 rounded-lg font-medium transition-colors duration-200 ' . $getVariantClasses()
    ]) }}
>
    {{ $slot }}
</button>
