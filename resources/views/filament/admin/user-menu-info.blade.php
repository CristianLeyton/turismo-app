@props(['user'])

@php
    /*
     * Bloque informativo del menú de usuario (se abre al hacer click en el
     * avatar): nombre completo del panel y sus roles asignados como chips.
     * Se inyecta en AdminPanelProvider vía PanelsRenderHook::USER_MENU_PROFILE_BEFORE.
     */
    $fullName = trim(($user->name ?? '') . ' ' . ($user->surname ?? ''));

    $roles = $user->roles->sortBy('name')->values();
@endphp

<div class="px-3 pt-2.5 pb-2.5 min-w-[13rem]">
    <p class="truncate text-sm font-semibold text-gray-700 dark:text-gray-200" title="{{ $fullName }}">
        {{ $fullName !== '' ? $fullName : $user->username }}
    </p>

    <div class="flex flex-wrap items-center gap-1 mt-1.5">
        @forelse ($roles as $role)
            <span class="inline-flex items-center rounded-full bg-fuchsia-100 px-2 py-0.5 text-xs font-medium text-fuchsia-700 dark:bg-fuchsia-900/40 dark:text-fuchsia-200">
                {{ $role->name }}
            </span>
        @empty
            <span class="text-xs text-gray-400 dark:text-gray-500">Sin roles asignados</span>
        @endforelse
    </div>
</div>
