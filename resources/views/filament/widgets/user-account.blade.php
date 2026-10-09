
@php
    $user = filament()->auth()->user();
@endphp

<x-filament-widgets::widget class="fi-account-widget">
    <x-filament::section>
        <x-filament-panels::avatar.user
            size="lg"
            :user="$user"
            loading='lazy'
        />

        <div class="fi-account-widget-main">
            <h2 class="fi-account-widget-heading">
                {{ __('Bienvenido/a, :name', ['name' => Auth::user()->name]) }}
                {{-- {{ __('filament-panels::widgets/account-widget.welcome', [
                    'app' => config('app.name'),
                ]) }} --}}
            </h2>

            <p class="fi-account-widget-user-name">
                {{ Auth::user()->roles->pluck('name')->implode(', ') }}
            </p>
        </div>

        <form
            action="{{ filament()->getLogoutUrl() }}"
            method="post"
            class="fi-account-widget-logout-form"
        >
            @csrf

            <x-filament::button
                color="gray"
                icon="heroicon-o-arrow-left-start-on-rectangle"
                labeled-from="sm"
                tag="button"
                type="submit"
            >
                {{ __('filament-panels::widgets/account-widget.actions.logout.label') }}
            </x-filament::button>
        </form>
    </x-filament::section>
</x-filament-widgets::widget>
