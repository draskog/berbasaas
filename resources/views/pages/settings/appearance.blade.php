<?php

use Livewire\Component;
use Livewire\Attributes\Title;

new #[Title('Appearance settings')]
class extends Component {
    //
}; ?>
<flux:main>
    <section class="w-full">
        @include('partials.settings-heading')

        <flux:heading class="sr-only">{{ __('Appearance settings') }}</flux:heading>

        <x-pages::settings.layout :heading="__('Appearance')" :subheading="__('Update the appearance settings for your account')">
            <flux:radio.group x-data variant="segmented" x-model="$flux.appearance">
                <flux:radio value="light" icon="sun" label="{{ __('Light') }}" />
                <flux:radio value="dark" icon="moon" label="{{ __('Dark') }}" />
                <flux:radio value="system" icon="computer-desktop" label="{{ __('System') }}" />
            </flux:radio.group>
        </x-pages::settings.layout>
    </section>
</flux:main>
