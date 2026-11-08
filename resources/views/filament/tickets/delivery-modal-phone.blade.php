{{--
    Número de WhatsApp y botón de envío de una tarjeta del modal de entrega
    (o de "Enviar todos"). Con teléfonos en la venta: selector con
    "Otro número…", que muestra el campo. Sin teléfonos: el campo directo.

    Recibe $key (clave del estado Alpine), $label, $buttonLabel ("Enviar" o
    "Enviar todos": el nombre accesible le suma " por WhatsApp"), $phones y
    $replacement (expresión JS con lo que reemplaza a {link} en el mensaje).

    El botón no recibe href como propiedad (lo arma Alpine), así que el
    target va por x-bind: el componente de Filament solo lo emite con href.
--}}
<div class="flex flex-col gap-2">
    <span class="text-xs font-medium uppercase text-gray-500 dark:text-gray-400">{{ $label }}</span>

    <div class="flex flex-col gap-2 sm:flex-row sm:items-start">
        <div class="flex flex-1 flex-col gap-2">
            @if (count($phones) > 0)
                <x-filament::input.wrapper>
                    <x-filament::input.select x-model="state['{{ $key }}'].selected" aria-label="{{ $label }}">
                        @foreach ($phones as $phone)
                            <option value="{{ $phone['phone'] }}">{{ $phone['label'] }}</option>
                        @endforeach
                        <option value="other">Otro número…</option>
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            @endif

            <div @if (count($phones) > 0) x-show="state['{{ $key }}'].selected === 'other'" x-cloak @endif>
                <x-filament::input.wrapper
                    x-bind:class="{ 'fi-invalid': state['{{ $key }}'].manual !== '' && ! isValid('{{ $key }}') }">
                    <x-filament::input type="tel" inputmode="tel" autocomplete="off"
                        x-model="state['{{ $key }}'].manual"
                        placeholder="Ej: 3881234567"
                        aria-label="Número de WhatsApp" />
                </x-filament::input.wrapper>

                <p x-show="state['{{ $key }}'].manual !== '' && ! isValid('{{ $key }}')" x-cloak
                    class="mt-1 text-xs text-danger-600 dark:text-danger-400">
                    Ingresá un número con código de área, de al menos 8 dígitos.
                </p>
            </div>
        </div>

        <x-filament::button tag="a" color="success"
            class="shrink-0 text-white!"
            title="{{ $buttonLabel }} por WhatsApp" aria-label="{{ $buttonLabel }} por WhatsApp"
            x-bind:href="whatsappUrl('{{ $key }}', {{ $replacement }})"
            x-bind:target="'_blank'"
            rel="noopener noreferrer"
            x-bind:class="{ 'pointer-events-none opacity-50': ! isValid('{{ $key }}') }"
            x-bind:aria-disabled="! isValid('{{ $key }}')">
            <x-icons.whatsapp class="size-4 text-white" />
            {{ $buttonLabel }}
        </x-filament::button>
    </div>
</div>
