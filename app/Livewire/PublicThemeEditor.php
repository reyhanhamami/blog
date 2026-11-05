<?php

namespace App\Livewire;

use App\Support\PublicTheme;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class PublicThemeEditor extends Component
{
    public array $colors = [];

    public function mount(): void
    {
        Gate::authorize('settings.manage');
        $this->colors = PublicTheme::resolve();
    }

    public function save(): void
    {
        Gate::authorize('settings.manage');
        $this->validate(PublicTheme::rules(), ['regex' => 'Gunakan warna HEX enam digit, misalnya #E8AF4B.']);
        $blocked = false;
        foreach (PublicTheme::contrastChecks($this->colors) as $check) {
            if ($check['critical']) {
                $this->addError('colors.'.$check['foreground'], $check['label'].': kontras di bawah 1.5:1. Perbaiki sebelum menyimpan.');
                $blocked = true;
            }
        }
        if ($blocked) {
            return;
        }
        PublicTheme::save($this->colors);
        $this->colors = PublicTheme::resolve();
        $this->dispatch('public-theme-saved');
    }

    public function render()
    {
        Gate::authorize('settings.manage');

        return view('livewire.public-theme-editor', [
            'defaults' => PublicTheme::defaults(),
            'presets' => PublicTheme::presets(),
            'groups' => config('public_theme.groups'),
        ]);
    }
}
