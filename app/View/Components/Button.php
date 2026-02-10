<?php

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

class Button extends Component
{
    public function __construct(
        public string $type = 'button',
        public string $variant = 'primary'
    ) {}

    public function render(): View
    {
        return view('components.button');
    }

    public function getVariantClasses(): string
    {
        return match($this->variant) {
            'primary' => 'bg-blue-600 hover:bg-blue-700 text-white',
            'secondary' => 'bg-gray-200 hover:bg-gray-300 text-gray-800',
            'danger' => 'bg-red-600 hover:bg-red-700 text-white',
            default => 'bg-blue-600 hover:bg-blue-700 text-white',
        };
    }
}
