<x-filament-panels::page>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <div class="space-y-6">
        <form id="ventaForm" class="space-y-6">
            @csrf

            <!-- Selección de Destino y Paradas -->
            <div
                class="fi-section rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <h3 class="text-lg font-semibold text-gray-950 dark:text-white mb-4">Selección de Ruta</h3>

                <div class="grid md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Origen</label>
                        <select id="origen" name="origen"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary-500"
                            required>
                            <option value="">Seleccione origen</option>
                            <option value="Salta Capital">Salta Capital</option>
                            <option value="Oran">Oran</option>
                            <option value="Yrigoyen">Yrigoyen</option>
                            <option value="Pichanal">Pichanal</option>
                            <option value="Colonia Santa Rosa">Colonia Santa Rosa</option>
                            <option value="Urundel">Urundel</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Destino</label>
                        <select id="destino" name="destino"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary-500"
                            required>
                            <option value="">Seleccione destino</option>
                            <option value="Salta Capital">Salta Capital</option>
                            <option value="Oran">Oran</option>
                            <option value="Yrigoyen">Yrigoyen</option>
                            <option value="Pichanal">Pichanal</option>
                            <option value="Colonia Santa Rosa">Colonia Santa Rosa</option>
                            <option value="Urundel">Urundel</option>
                        </select>
                    </div>
                </div>

            </div>

            <!-- Selección de Fecha y Horario -->
            <div
                class="fi-section rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <h3 class="text-lg font-semibold text-gray-950 dark:text-white mb-4">Fecha y Horario</h3>

                <div class="grid md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Fecha de Viaje</label>
                        <input type="date" id="fecha" name="fecha"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary-500"
                            required>
                        <p class="text-xs text-gray-500 mt-1">Solo se permiten fechas de lunes a viernes</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Horario</label>
                        <div class="grid grid-cols-2 gap-3">
                            <label
                                class="flex items-center p-3 border-2 border-gray-300 rounded-lg cursor-pointer hover:border-primary-500 transition">
                                <input type="radio" name="horario" value="02:00-06:00" class="mr-2" required>
                                <span class="text-sm font-semibold">02:00 - 06:00</span>
                            </label>
                            <label
                                class="flex items-center p-3 border-2 border-gray-300 rounded-lg cursor-pointer hover:border-primary-500 transition">
                                <input type="radio" name="horario" value="19:00-23:00" class="mr-2" required>
                                <span class="text-sm font-semibold">19:00 - 23:00</span>
                            </label>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Selección de Asientos - Ida -->
            <div
                class="fi-section gap-4 rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">

                <div class="flex md:flex-row flex-col items-center justify-between mb-4">

                    <h3 class="text-lg font-semibold text-gray-950 dark:text-white">Selección de Asientos - Viaje de
                        Ida</h3>

                    <div class="p-3 bg-primary-50 rounded-lg">
                        <p class="text-sm text-gray-700 flex flex-wrap gap-4">
                            <span class="flex items-center gap-2">
                                <span class="inline-block w-4 h-4 bg-gray-300 border-2 border-gray-400 rounded"></span>
                                Disponible
                            </span>
                            <span class="flex items-center gap-2">
                                <span
                                    class="inline-block w-4 h-4 bg-purple-500 border-2 border-purple-600 rounded"></span>
                                Seleccionado
                            </span>
                            <span class="flex items-center gap-2">
                                <span class="inline-block w-4 h-4 bg-red-500 border-2 border-red-600 rounded"></span>
                                Ocupado
                            </span>
                        </p>
                    </div>
                </div>
                <div class="grid md:grid-cols-2 gap-4">
                    <!-- Primer Piso -->
                    <div class="">
                        <div class="text-sm font-semibold text-gray-700 mb-2">Primer Piso (48 asientos)</div>
                        <div class="bg-gray-50 p-3 rounded-lg flex items-center justify-center">
                            <div id="primerPiso" class="space-y-2">
                                <!-- Se generarán dinámicamente -->
                            </div>
                        </div>
                    </div>

                    <!-- Segundo Piso -->
                    <div class="self-end">
                        <div class="text-sm font-semibold text-gray-700 mb-2">Segundo Piso (12 asientos)</div>
                        <div class="bg-gray-50 p-3 rounded-lg flex items-center justify-center">
                            <div id="segundoPiso" class="space-y-2">
                                <!-- Se generarán dinámicamente -->
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Opción Diferido -->
            <div
                class="fi-section rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <div class="flex items-center">
                    <input type="checkbox" id="diferido" name="diferido"
                        class="w-4 h-4 text-primary-600 border-gray-300 rounded focus:ring-primary-500">
                    <label for="diferido" class="ml-2 text-sm font-medium text-gray-700">Incluir viaje de vuelta
                        (Diferido)</label>
                </div>
            </div>

            <!-- Sección Completa de Vuelta (oculta por defecto) -->
            <div id="vueltaSection" class="hidden space-y-6">
                <!-- Fecha y Horario de Vuelta -->
                <div
                    class="fi-section rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                    <h3 class="text-lg font-semibold text-gray-950 dark:text-white mb-4">Fecha y Horario - Viaje de
                        Vuelta</h3>

                    <div class="grid md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Fecha de Vuelta</label>
                            <input type="date" id="fechaVuelta" name="fechaVuelta"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary-500">
                            <p class="text-xs text-gray-500 mt-1">Solo se permiten fechas de lunes a viernes</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Horario de Vuelta</label>
                            <div class="grid grid-cols-2 gap-3">
                                <label
                                    class="flex items-center p-3 border-2 border-gray-300 rounded-lg cursor-pointer hover:border-primary-500 transition">
                                    <input type="radio" name="horarioVuelta" value="02:00-06:00" class="mr-2">
                                    <span class="text-sm font-semibold">02:00 - 06:00</span>
                                </label>
                                <label
                                    class="flex items-center p-3 border-2 border-gray-300 rounded-lg cursor-pointer hover:border-primary-500 transition">
                                    <input type="radio" name="horarioVuelta" value="19:00-23:00" class="mr-2">
                                    <span class="text-sm font-semibold">19:00 - 23:00</span>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Selección de Asientos Vuelta -->
                <div id="asientosVueltaSection"
                    class="fi-section gap-4 rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">

                    <div class="flex md:flex-row flex-col items-center justify-between mb-4">

                        <h3 class="text-lg font-semibold text-gray-950 dark:text-white">Selección de Asientos - Viaje
                            de Vuelta</h3>

                        <div class="p-3 bg-primary-50 rounded-lg">
                            <p class="text-sm text-gray-700 flex flex-wrap gap-4">
                                <span class="flex items-center gap-2">
                                    <span
                                        class="inline-block w-4 h-4 bg-gray-300 border-2 border-gray-400 rounded"></span>
                                    Disponible
                                </span>
                                <span class="flex items-center gap-2">
                                    <span
                                        class="inline-block w-4 h-4 bg-purple-500 border-2 border-purple-600 rounded"></span>
                                    Seleccionado
                                </span>
                                <span class="flex items-center gap-2">
                                    <span
                                        class="inline-block w-4 h-4 bg-red-500 border-2 border-red-600 rounded"></span>
                                    Ocupado
                                </span>
                            </p>
                        </div>
                    </div>
                    <div class="grid md:grid-cols-2 gap-4">
                        <!-- Primer Piso Vuelta -->
                        <div class="">
                            <div class="text-sm font-semibold text-gray-700 mb-2">Primer Piso (48 asientos)</div>
                            <div class="bg-gray-50 p-3 rounded-lg flex items-center justify-center">
                                <div id="primerPisoVuelta" class="space-y-2">
                                    <!-- Se generarán dinámicamente -->
                                </div>
                            </div>
                        </div>

                        <!-- Segundo Piso Vuelta -->
                        <div class="self-end">
                            <div class="text-sm font-semibold text-gray-700 mb-2">Segundo Piso (12 asientos)</div>
                            <div class="bg-gray-50 p-3 rounded-lg flex items-center justify-center">
                                <div id="segundoPisoVuelta" class="space-y-2">
                                    <!-- Se generarán dinámicamente -->
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Datos del Cliente -->
            <div
                class="fi-section rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <h3 class="text-lg font-semibold text-gray-950 dark:text-white mb-4">Datos del Cliente</h3>

                <div class="grid md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Nombre Completo</label>
                        <input type="text" name="nombre"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary-500"
                            required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">DNI</label>
                        <input type="text" name="dni"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary-500"
                            required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Teléfono</label>
                        <input type="tel" name="telefono"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary-500"
                            required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Email</label>
                        <input type="email" name="email"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary-500"
                            required>
                    </div>
                </div>
            </div>

            <!-- Resumen -->
            <div id="resumen"
                class="hidden fi-section rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <h3 class="text-lg font-semibold text-gray-950 dark:text-white mb-4">Resumen de Compra</h3>

                <div class="space-y-2">
                    <div class="flex justify-between text-sm">
                        <span class="font-semibold">Origen:</span>
                        <span id="resumenOrigen"></span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="font-semibold">Destino:</span>
                        <span id="resumenDestino"></span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="font-semibold">Fecha:</span>
                        <span id="resumenFecha"></span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="font-semibold">Horario:</span>
                        <span id="resumenHorario"></span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="font-semibold">Asientos:</span>
                        <span id="resumenAsientos"></span>
                    </div>
                    <div class="border-t pt-2 mt-2">
                        <div class="flex justify-between text-lg font-bold">
                            <span>Total:</span>
                            <span class="text-primary-600">$<span id="resumenTotal">0</span></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Campos ocultos para asientos -->
            <input type="hidden" id="asientoIda" name="asientoIda" value="">
            <input type="hidden" id="asientoVuelta" name="asientoVuelta" value="">

            <!-- Botón de Enviar -->
            <div class="flex justify-end">
                <button type="submit" id="btnConfirmarCompra" class="fi-btn fi-btn-color-primary fi-btn-size-lg">
                    <span class="fi-btn-label" id="btnConfirmarCompraLabel">Confirmar Compra</span>
                </button>
            </div>
        </form>
    </div>

    <!-- Modal de Confirmación -->
    <div id="modalConfirmacion" class="modal-overlay hidden">
        <div class="modal-container">
            <div class="modal-header">
                <h3 class="modal-title">Confirmar Compra</h3>
            </div>
            <div class="modal-body">
                <p>¿Está seguro que desea confirmar esta compra? Se generará y descargará el boleto en PDF.</p>
            </div>
            <div class="modal-footer">
                <button type="button" id="btnCancelarCompra" class="btn-cancelar">Cancelar</button>
                <button type="button" id="btnConfirmarModal" class="btn-confirmar">Sí, Confirmar</button>
            </div>
        </div>
    </div>

    <style>
        .seat {
            width: 35px;
            height: 35px;
            border: 2px solid #9ca3af;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s;
            font-size: 10px;
            font-weight: 600;
            margin: 0;
        }

        .seat:hover {
            transform: scale(1.05);
        }

        .seat.available {
            background-color: #e5e7eb !important;
            border-color: #9ca3af !important;
            color: #374151 !important;
        }

        .seat.selected {
            background-color: #a855f7 !important;
            border-color: #9333ea !important;
            color: white !important;
        }

        .seat.occupied {
            background-color: #ef4444 !important;
            border-color: #dc2626 !important;
            color: white !important;
            cursor: not-allowed;
        }

        .pasillo {
            background-color: #fff;
            border: 1px solid #ccc;
            width: 30px;
            height: 35px;
            flex-shrink: 0;
            border-radius: 4px;
        }

        .ventana {
            width: 35px;
            height: 35px;
            border: 2px solid #3b82f6;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(to bottom, #93c5fd, #dbeafe);
            font-size: 12px;
            color: #1e40af;
            margin: 0;
            flex-shrink: 0;
        }

        .bano,
        .cafetera {
            width: 75px;
            height: 50px;
            border: 2px solid #6b7280;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: #f3f4f6;
            font-size: 12px;
            font-weight: bold;
            color: #374151;
            margin: 0;
            flex-shrink: 0;
        }

        .bano {
            width: 75px;
            height: 40px;
        }

        .cafetera {
            width: 76px;
            height: 40px;
        }


        .escalera {
            width: 34px;
            height: 34px;
            font-size: 9px;
        }

        .fila-bus {
            display: flex;
            align-items: center;
            gap: 6px;
            margin-bottom: 6px;
        }

        select option:disabled {
            color: #9ca3af;
            background-color: #f3f4f6;
            cursor: not-allowed;
        }

        /* Modal de Confirmación */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: rgba(0, 0, 0, 0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
        }

        .modal-overlay.hidden {
            display: none;
        }

        .modal-container {
            background-color: white;
            border-radius: 0.5rem;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
            max-width: 500px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
        }

        .modal-header {
            padding: 1.5rem;
            border-bottom: 1px solid #e5e7eb;
        }

        .modal-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: #111827;
            margin: 0;
        }

        .modal-body {
            padding: 1.5rem;
            color: #374151;
        }

        .modal-footer {
            padding: 1.5rem;
            border-top: 1px solid #e5e7eb;
            display: flex;
            justify-content: flex-end;
            gap: 0.75rem;
        }

        .btn-cancelar,
        .btn-confirmar {
            padding: 0.625rem 1.25rem;
            border-radius: 0.375rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            border: none;
        }

        .btn-cancelar {
            background-color: #f3f4f6;
            color: #374151;
        }

        .btn-cancelar:hover {
            background-color: #e5e7eb;
        }

        .btn-confirmar {
            background-color: #3b82f6;
            color: white;
        }

        .btn-confirmar:hover {
            background-color: #2563eb;
        }

        .btn-confirmar:disabled {
            background-color: #9ca3af;
            cursor: not-allowed;
        }

        #btnConfirmarCompra:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
    </style>

    <script>
        (function() {

            const asientosOcupados = [5, 12, 23, 34, 45, 56, 58, 59];

            let asientoIda = null;
            let asientoVuelta = null;

            /* =========================
               ELEMENTOS BASE
            ========================= */

            function crearAsiento(nro, ocupado, onSelect) {
                const d = document.createElement('div');
                d.className = `seat ${ocupado ? 'occupied' : 'available'}`;
                d.textContent = nro;
                d.dataset.numero = nro;

                if (!ocupado) {
                    d.onclick = () => onSelect(d);
                }
                return d;
            }

            function el(clase, texto = '') {
                const d = document.createElement('div');
                d.className = clase;
                if (texto) d.textContent = texto;
                return d;
            }

            function espacio() {
                const d = document.createElement('div');
                d.style.width = '35px';
                d.style.height = '35px';
                return d;
            }

            function fila(...items) {
                const f = document.createElement('div');
                f.className = 'fila-bus';
                items.forEach(i => f.appendChild(i));
                return f;
            }

            /* =========================
               GENERADOR DE BUS (ÚNICO)
            ========================= */

            function generarBus({
                primerPisoId,
                segundoPisoId,
                onSelect
            }) {

                const primer = document.getElementById(primerPisoId);
                const segundo = document.getElementById(segundoPisoId);

                primer.innerHTML = '';
                segundo.innerHTML = '';

                let n = 1;

                /* === FILAS 1 y 2 === */
                primer.appendChild(fila(

                    crearAsiento(n++, asientosOcupados.includes(n - 1), onSelect),
                    crearAsiento(n++, asientosOcupados.includes(n - 1), onSelect),
                    el('pasillo'),
                    crearAsiento(n++, asientosOcupados.includes(n - 1), onSelect),
                    crearAsiento(n++, asientosOcupados.includes(n - 1), onSelect),
                ));

                primer.appendChild(fila(

                    crearAsiento(n++, asientosOcupados.includes(n - 1), onSelect),
                    crearAsiento(n++, asientosOcupados.includes(n - 1), onSelect),
                    el('pasillo'),
                    espacio(),
                    espacio(),
                ));

                /* === FILA CAFETERA === */
                primer.appendChild(fila(

                    crearAsiento(n++, asientosOcupados.includes(n - 1), onSelect),
                    crearAsiento(n++, asientosOcupados.includes(n - 1), onSelect),
                    el('pasillo'),
                    el('cafetera', 'CAFETERA'),
                ));

                /* === RESTO PRIMER PISO === */
                while (n <= 48) {
                    primer.appendChild(fila(

                        crearAsiento(n++, asientosOcupados.includes(n - 1), onSelect),
                        crearAsiento(n++, asientosOcupados.includes(n - 1), onSelect),
                        el('pasillo'),
                        crearAsiento(n++, asientosOcupados.includes(n - 1), onSelect),
                        crearAsiento(n++, asientosOcupados.includes(n - 1), onSelect),
                    ));
                }

                /* === SEGUNDO PISO === */
                segundo.appendChild(fila(el('bano', 'BAÑO')));

                n = 49;
                for (let i = 0; i < 4; i++) {
                    segundo.appendChild(fila(
                        crearAsiento(n++, asientosOcupados.includes(n - 1), onSelect),
                        crearAsiento(n++, asientosOcupados.includes(n - 1), onSelect),
                        el('pasillo'),
                        crearAsiento(n++, asientosOcupados.includes(n - 1), onSelect)
                    ));
                }
            }


            /* =========================
               SELECCIÓN IDA / VUELTA
            ========================= */

            function limpiarSeleccion(contenedor, nro) {
                if (!nro) return;
                const el = contenedor.querySelector(`[data-numero="${nro}"]`);
                if (el) {
                    el.classList.remove('selected');
                    el.classList.add('available');
                }
            }

            function selectIda(seat) {
                limpiarSeleccion(document.getElementById('primerPiso'), asientoIda);
                limpiarSeleccion(document.getElementById('segundoPiso'), asientoIda);

                asientoIda = Number(seat.dataset.numero);
                seat.classList.remove('available');
                seat.classList.add('selected');
                const asientoIdaInput = document.getElementById('asientoIda');
                if (asientoIdaInput) {
                    asientoIdaInput.value = asientoIda;
                }
                actualizarResumen();
            }

            function selectVuelta(seat) {
                limpiarSeleccion(document.getElementById('primerPisoVuelta'), asientoVuelta);
                limpiarSeleccion(document.getElementById('segundoPisoVuelta'), asientoVuelta);

                asientoVuelta = Number(seat.dataset.numero);
                seat.classList.remove('available');
                seat.classList.add('selected');
                const asientoVueltaInput = document.getElementById('asientoVuelta');
                if (asientoVueltaInput) {
                    asientoVueltaInput.value = asientoVuelta;
                }
                actualizarResumen();
            }

            /* =========================
               VALIDACIÓN ORIGEN/DESTINO
            ========================= */

            function actualizarOpcionesDestino() {
                const origenSelect = document.getElementById('origen');
                const destinoSelect = document.getElementById('destino');
                const origenSeleccionado = origenSelect.value;

                // Habilitar todas las opciones primero
                Array.from(destinoSelect.options).forEach(option => {
                    option.disabled = false;
                });

                // Deshabilitar la opción que coincide con el origen seleccionado
                if (origenSeleccionado) {
                    Array.from(destinoSelect.options).forEach(option => {
                        if (option.value === origenSeleccionado) {
                            option.disabled = true;
                            // Si estaba seleccionada, limpiar la selección
                            if (destinoSelect.value === origenSeleccionado) {
                                destinoSelect.value = '';
                            }
                        }
                    });
                }
            }

            function actualizarOpcionesOrigen() {
                const origenSelect = document.getElementById('origen');
                const destinoSelect = document.getElementById('destino');
                const destinoSeleccionado = destinoSelect.value;

                // Habilitar todas las opciones primero
                Array.from(origenSelect.options).forEach(option => {
                    option.disabled = false;
                });

                // Deshabilitar la opción que coincide con el destino seleccionado
                if (destinoSeleccionado) {
                    Array.from(origenSelect.options).forEach(option => {
                        if (option.value === destinoSeleccionado) {
                            option.disabled = true;
                            // Si estaba seleccionada, limpiar la selección
                            if (origenSelect.value === destinoSeleccionado) {
                                origenSelect.value = '';
                            }
                        }
                    });
                }
            }

            /* =========================
               VALIDACIÓN DE FECHAS
            ========================= */

            function esFinDeSemana(fecha) {
                if (!fecha) return false;
                const date = new Date(fecha);
                const dia = date.getDay();
                // 0 = domingo, 6 = sábado
                return dia === 5 || dia === 6;
            }

            function validarFecha(inputFecha, nombreCampo) {
                const fecha = inputFecha.value;
                if (fecha && esFinDeSemana(fecha)) {
                    alert(
                        `No se pueden seleccionar sábados o domingos. Por favor, seleccione una fecha de lunes a viernes para ${nombreCampo}.`
                    );
                    inputFecha.value = '';
                    inputFecha.focus();
                    return false;
                }
                return true;
            }

            /* =========================
               ACTUALIZAR RESUMEN
            ========================= */

            function actualizarResumen() {
                const resumenDiv = document.getElementById('resumen');
                if (!resumenDiv) return;

                const origen = document.getElementById('origen')?.value || '';
                const destino = document.getElementById('destino')?.value || '';
                const fecha = document.getElementById('fecha')?.value || '';
                const horario = document.querySelector('input[name="horario"]:checked')?.value || '';
                const diferido = document.getElementById('diferido')?.checked || false;
                const fechaVuelta = document.getElementById('fechaVuelta')?.value || '';
                const horarioVuelta = document.querySelector('input[name="horarioVuelta"]:checked')?.value || '';

                // Validar que haya datos básicos
                if (!origen || !destino || !fecha || !horario || !asientoIda) {
                    resumenDiv.classList.add('hidden');
                    return;
                }

                // Formatear fecha
                let fechaFormateada = '';
                if (fecha) {
                    const date = new Date(fecha + 'T00:00:00');
                    fechaFormateada = date.toLocaleDateString('es-ES', {
                        weekday: 'long',
                        year: 'numeric',
                        month: 'long',
                        day: 'numeric'
                    });
                    fechaFormateada = fechaFormateada.charAt(0).toUpperCase() + fechaFormateada.slice(1);
                }

                // Asientos
                let asientosTexto = `Asiento ${asientoIda}`;
                if (diferido && asientoVuelta) {
                    asientosTexto += ` (Ida) / Asiento ${asientoVuelta} (Vuelta)`;
                }

                // Calcular total (precio de ejemplo, ajustar según necesidad)
                const precioPorBoleto = 10000; // Ajustar precio
                let total = precioPorBoleto;
                if (diferido && asientoVuelta) {
                    total = precioPorBoleto * 2;
                }

                // Actualizar resumen
                document.getElementById('resumenOrigen').textContent = origen;
                document.getElementById('resumenDestino').textContent = destino;
                document.getElementById('resumenFecha').textContent = fechaFormateada;
                document.getElementById('resumenHorario').textContent = horario;
                document.getElementById('resumenAsientos').textContent = asientosTexto;
                document.getElementById('resumenTotal').textContent = total.toLocaleString('es-AR');

                // Mostrar resumen
                resumenDiv.classList.remove('hidden');
            }

            /* =========================
               INIT
            ========================= */

            document.addEventListener('DOMContentLoaded', () => {

                generarBus({
                    primerPisoId: 'primerPiso',
                    segundoPisoId: 'segundoPiso',
                    onSelect: selectIda
                });

                // Validación origen/destino
                const origenSelect = document.getElementById('origen');
                const destinoSelect = document.getElementById('destino');

                if (origenSelect) {
                    origenSelect.addEventListener('change', actualizarOpcionesDestino);
                }

                if (destinoSelect) {
                    destinoSelect.addEventListener('change', actualizarOpcionesOrigen);
                }

                // Validación de fechas (sin fines de semana)
                const fechaIda = document.getElementById('fecha');
                const fechaVuelta = document.getElementById('fechaVuelta');

                // Establecer fecha mínima (hoy)
                const hoy = new Date().toISOString().split('T')[0];
                if (fechaIda) {
                    fechaIda.setAttribute('min', hoy);
                    fechaIda.addEventListener('change', () => {
                        validarFecha(fechaIda, 'la fecha de viaje');
                        actualizarResumen();
                    });
                }

                if (fechaVuelta) {
                    fechaVuelta.setAttribute('min', hoy);
                    fechaVuelta.addEventListener('change', () => {
                        validarFecha(fechaVuelta, 'la fecha de vuelta');
                        actualizarResumen();
                    });
                }

                // Event listeners para actualizar resumen
                if (origenSelect) {
                    origenSelect.addEventListener('change', () => {
                        actualizarOpcionesDestino();
                        actualizarResumen();
                    });
                }

                if (destinoSelect) {
                    destinoSelect.addEventListener('change', () => {
                        actualizarOpcionesOrigen();
                        actualizarResumen();
                    });
                }

                // Actualizar resumen cuando cambian horarios
                document.querySelectorAll('input[name="horario"]').forEach(radio => {
                    radio.addEventListener('change', actualizarResumen);
                });

                document.querySelectorAll('input[name="horarioVuelta"]').forEach(radio => {
                    radio.addEventListener('change', actualizarResumen);
                });

                const chk = document.getElementById('diferido');
                const vueltaSection = document.getElementById('vueltaSection');
                if (chk && vueltaSection) {
                    chk.addEventListener('change', () => {
                        if (chk.checked) {
                            vueltaSection.classList.remove('hidden');
                            generarBus({
                                primerPisoId: 'primerPisoVuelta',
                                segundoPisoId: 'segundoPisoVuelta',
                                onSelect: selectVuelta
                            });
                        } else {
                            vueltaSection.classList.add('hidden');
                            asientoVuelta = null;
                            const asientoVueltaInput = document.getElementById('asientoVuelta');
                            if (asientoVueltaInput) {
                                asientoVueltaInput.value = '';
                            }
                            document.getElementById('fechaVuelta').value = '';
                            document.querySelectorAll('input[name="horarioVuelta"]').forEach(radio => {
                                radio.checked = false;
                            });
                        }
                        actualizarResumen();
                    });
                }

                // Función para descargar el PDF
                async function descargarPDF() {
                    const ventaForm = document.getElementById('ventaForm');
                    const btnConfirmarCompra = document.getElementById('btnConfirmarCompra');
                    const btnConfirmarCompraLabel = document.getElementById('btnConfirmarCompraLabel');
                    const btnConfirmarModal = document.getElementById('btnConfirmarModal');
                    const modalConfirmacion = document.getElementById('modalConfirmacion');

                    // Obtener datos del formulario
                    const formData = new FormData(ventaForm);
                    const diferido = chk && chk.checked;

                    // Preparar datos para enviar
                    const data = {
                        origen: formData.get('origen'),
                        destino: formData.get('destino'),
                        fecha: formData.get('fecha'),
                        horario: formData.get('horario'),
                        diferido: diferido,
                        asientos: [asientoIda],
                        cliente: {
                            nombre: formData.get('nombre'),
                            dni: formData.get('dni'),
                            telefono: formData.get('telefono'),
                            email: formData.get('email')
                        }
                    };

                    // Agregar datos de vuelta si es diferido
                    if (diferido) {
                        data.fechaVuelta = formData.get('fechaVuelta');
                        data.horarioVuelta = formData.get('horarioVuelta');
                        data.asientosVuelta = [asientoVuelta];
                    }

                    // Obtener token CSRF
                    const csrfToken = document.querySelector('meta[name="csrf-token"]')
                        ?.getAttribute('content') ||
                        formData.get('_token');

                    // Mostrar estado de carga
                    btnConfirmarCompra.disabled = true;
                    btnConfirmarCompraLabel.textContent = 'Cargando...';
                    btnConfirmarModal.disabled = true;
                    btnConfirmarModal.textContent = 'Cargando...';
                    modalConfirmacion.classList.add('hidden');

                    try {
                        const response = await fetch(
                            '{{ route('venta-de-pasajes.generar-pdf') }}', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-CSRF-TOKEN': csrfToken,
                                    'Accept': 'application/pdf',
                                    'X-Requested-With': 'XMLHttpRequest'
                                },
                                body: JSON.stringify(data)
                            });

                        if (response.ok) {
                            const contentType = response.headers.get('content-type');
                            if (contentType && contentType.includes('application/pdf')) {
                                // Descargar el PDF
                                const blob = await response.blob();
                                const url = window.URL.createObjectURL(blob);
                                const a = document.createElement('a');
                                a.href = url;
                                a.download = `boleto-${Date.now()}.pdf`;
                                document.body.appendChild(a);
                                a.click();
                                window.URL.revokeObjectURL(url);
                                document.body.removeChild(a);
                            } else {
                                // Si no es PDF, intentar leer como JSON para mostrar el error
                                const errorData = await response.json().catch(() => ({
                                    message: 'Error desconocido'
                                }));
                                alert('Error al generar el boleto: ' + (errorData.message ||
                                    'Error desconocido'));
                            }
                        } else {
                            // Intentar leer el error como JSON
                            let errorMessage = 'Error al generar el boleto';
                            try {
                                const errorData = await response.json();
                                errorMessage = errorData.message || errorData.error ||
                                    errorMessage;
                            } catch (e) {
                                errorMessage =
                                    `Error ${response.status}: ${response.statusText}`;
                            }
                            alert(errorMessage);
                        }
                    } catch (error) {
                        console.error('Error:', error);
                        alert('Error al procesar la solicitud. Por favor, intente nuevamente.');
                    } finally {
                        // Restaurar estado del botón
                        btnConfirmarCompra.disabled = false;
                        btnConfirmarCompraLabel.textContent = 'Confirmar Compra';
                        btnConfirmarModal.disabled = false;
                        btnConfirmarModal.textContent = 'Sí, Confirmar';
                    }
                }

                // Manejar envío del formulario
                const ventaForm = document.getElementById('ventaForm');
                const modalConfirmacion = document.getElementById('modalConfirmacion');
                const btnCancelarCompra = document.getElementById('btnCancelarCompra');
                const btnConfirmarModal = document.getElementById('btnConfirmarModal');

                if (ventaForm) {
                    ventaForm.addEventListener('submit', (e) => {
                        e.preventDefault();

                        // Validar que se haya seleccionado un asiento
                        if (!asientoIda) {
                            alert('Por favor, seleccione un asiento para el viaje de ida.');
                            return;
                        }

                        // Validar si es diferido, debe tener asiento de vuelta
                        if (chk && chk.checked && !asientoVuelta) {
                            alert('Por favor, seleccione un asiento para el viaje de vuelta.');
                            return;
                        }

                        // Mostrar modal de confirmación
                        if (modalConfirmacion) {
                            modalConfirmacion.classList.remove('hidden');
                        }
                    });
                }

                // Manejar botones del modal
                if (btnCancelarCompra) {
                    btnCancelarCompra.addEventListener('click', () => {
                        modalConfirmacion.classList.add('hidden');
                    });
                }

                if (btnConfirmarModal) {
                    btnConfirmarModal.addEventListener('click', () => {
                        descargarPDF();
                    });
                }

                // Cerrar modal al hacer clic fuera de él
                if (modalConfirmacion) {
                    modalConfirmacion.addEventListener('click', (e) => {
                        if (e.target === modalConfirmacion) {
                            modalConfirmacion.classList.add('hidden');
                        }
                    });
                }

                // Actualizar resumen cuando cambian los datos del cliente (opcional)
                document.querySelectorAll(
                        'input[name="nombre"], input[name="dni"], input[name="telefono"], input[name="email"]')
                    .forEach(input => {
                        input.addEventListener('blur', actualizarResumen);
                    });
            });

        })();
    </script>


</x-filament-panels::page>
