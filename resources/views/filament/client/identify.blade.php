<x-filament-panels::page>
    <div class="max-w-md mx-auto py-10 flex flex-col gap-6 text-center">
        <div class="flex flex-col gap-1">
            <h2 class="text-2xl font-semibold tracking-tight">Consultá tus boletos</h2>
            <p class="text-gray-500 dark:text-gray-400">
                Ingresá el DNI con el que compraste para ver tus boletos sin fecha
                y asignarles viaje.
            </p>
        </div>

        <form wire:submit="buscar" class="flex flex-col gap-4">
            <div>
                <x-filament::input.wrapper :valid="! $errors->has('dni')">
                    <x-filament::input
                        type="text"
                        name="dni"
                        inputmode="numeric"
                        autocomplete="off"
                        placeholder="DNI"
                        aria-label="DNI"
                        wire:model="dni"
                    />
                </x-filament::input.wrapper>

                <p x-show="$errors->has('dni')" class="fi-fo-field-wrp-error-message mt-1.5 text-sm" data-validation-error>
                    {{ $errors->first('dni') }}
                </p>
            </div>

            <div>
                <div class="relative" x-data="{ ver: false }">
                    <x-filament::input.wrapper :valid="! $errors->has('password')">
                        <x-filament::input
                            type="password"
                            name="password"
                            autocomplete="current-password"
                            placeholder="Contraseña"
                            aria-label="Contraseña"
                            wire:model="password"
                            x-bind:type="ver ? 'text' : 'password'"
                        />
                    </x-filament::input.wrapper>
                <button
                    type="button"
                    tabindex="-1"
                    x-on:click="ver = ! ver"
                    class="absolute right-3 top-1/2 -translate-y-1/2 z-10 text-gray-400 hover:text-gray-600 dark:text-gray-500 dark:hover:text-gray-300"
                    :title="ver ? 'Ocultar contraseña' : 'Mostrar contraseña'"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" x-show="! ver" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z"/><path d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/></svg>
                    <svg xmlns="http://www.w3.org/2000/svg" x-show="ver" x-cloak class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88"/></svg>
                </button>
                </div>

                <p x-show="$errors->has('password')" class="fi-fo-field-wrp-error-message mt-1.5 text-sm" data-validation-error>
                    {{ $errors->first('password') }}
                </p>
            </div>

            <x-filament::button type="submit" color="primary" size="lg">
                Ingresar
            </x-filament::button>
        </form>

        <p class="text-sm text-gray-400 dark:text-gray-500">
            ¿Dudas? Consultá en boletería.
        </p>
    </div>
</x-filament-panels::page>
