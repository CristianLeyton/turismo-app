<x-filament-panels::page>
    <div class="space-y-6">
        <!-- Filtros -->
        <div
            class="fi-section rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <h3 class="text-lg font-semibold text-gray-950 dark:text-white mb-4">Filtros</h3>

            <form id="filtrosForm" class="grid md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Fecha</label>
                    <input type="date" id="filtroFecha" name="fecha"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Horario</label>
                    <select id="filtroHorario" name="horario"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary-500">
                        <option value="">Todos los horarios</option>
                        <option value="02:00-06:00">02:00 - 06:00</option>
                        <option value="19:00-23:00">19:00 - 23:00</option>
                    </select>
                </div>
                <div class="flex items-end gap-2">
                    <button type="button" id="btnFiltrar" class="fi-btn fi-btn-color-primary fi-btn-size-lg flex-1">
                        <span class="fi-btn-label">Filtrar</span>
                    </button>
                    <button type="button" id="btnLimpiar" class="fi-btn fi-btn-color-gray fi-btn-size-lg">
                        <span class="fi-btn-label">Limpiar</span>
                    </button>
                </div>
            </form>
        </div>

        <!-- Tabla de Viajes -->
        <div
            class="fi-section rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <h3 class="text-lg font-semibold text-gray-950 dark:text-white mb-4">Próximos Viajes</h3>

            <div class="overflow-x-auto">
                <table class="w-full border-collapse">
                    <thead>
                        <tr class="bg-gray-100 dark:bg-gray-800">
                            <th class="border border-gray-300 px-4 py-2 text-left text-sm font-semibold">Fecha</th>
                            <th class="border border-gray-300 px-4 py-2 text-left text-sm font-semibold">Día</th>
                            <th class="border border-gray-300 px-4 py-2 text-left text-sm font-semibold">Horario</th>
                            <th class="border border-gray-300 px-4 py-2 text-left text-sm font-semibold">Origen</th>
                            <th class="border border-gray-300 px-4 py-2 text-left text-sm font-semibold">Destino</th>
                            <th class="border border-gray-300 px-4 py-2 text-left text-sm font-semibold">Pasajeros</th>
                            <th class="border border-gray-300 px-4 py-2 text-left text-sm font-semibold">Acciones</th>
                            <th class="border border-gray-300 px-4 py-2 text-left text-sm font-semibold">Exportar</th>
                        </tr>
                    </thead>
                    <tbody id="tablaViajes">
                        <!-- Se llenará dinámicamente -->
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Modal de Detalles -->
        <div id="modalDetalles" class="fixed inset-0 z-50" style="display: none; background-color: rgba(0, 0, 0, 0.5);">
            <div class="flex items-center justify-center h-full w-full p-4">
                <div class="bg-white rounded-lg shadow-xl max-w-5xl w-full max-h-[90vh] overflow-hidden flex flex-col">
                    <div class="flex justify-between items-center p-6 border-b border-gray-200">
                        <h3 class="text-xl font-semibold text-gray-900">Detalles del Viaje</h3>
                        <button id="cerrarModal" class="text-gray-500 hover:text-gray-700 transition-colors">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>
                    <div id="contenidoModal" class="p-6 overflow-y-auto flex-1">
                        <!-- Se llenará dinámicamente -->
                    </div>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
        <script>
            (function() {
                // Datos hardcodeados de viajes
                const viajes = @json($this->viajes ?? []);

                window.renderizarTabla = function(viajesFiltrados = null) {
                    const viajesAMostrar = viajesFiltrados || viajes;
                    const tbody = document.getElementById('tablaViajes');
                    tbody.innerHTML = '';

                    if (viajesAMostrar.length === 0) {
                        tbody.innerHTML =
                            '<tr><td colspan="8" class="border border-gray-300 px-4 py-2 text-center text-gray-500">No hay viajes que coincidan con los filtros</td></tr>';
                        return;
                    }

                    viajesAMostrar.forEach(viaje => {
                        const tr = document.createElement('tr');
                        tr.className = 'hover:bg-gray-50 dark:hover:bg-gray-800';
                        tr.innerHTML = `
                    <td class="border border-gray-300 px-4 py-2">${viaje.fecha_formateada}</td>
                    <td class="border border-gray-300 px-4 py-2">${viaje.dia_semana}</td>
                    <td class="border border-gray-300 px-4 py-2">${viaje.horario}</td>
                    <td class="border border-gray-300 px-4 py-2">${viaje.origen}</td>
                    <td class="border border-gray-300 px-4 py-2">${viaje.destino}</td>
                    <td class="border border-gray-300 px-4 py-2">${viaje.pasajeros.length}</td>
                    <td class="border border-gray-300 px-4 py-2">
                        <button onclick="mostrarDetalles(${viaje.id})" 
                            class="text-primary-600 hover:text-primary-800 text-sm font-medium">
                            Ver Detalles
                        </button>
                    </td>
                    <td class="border border-gray-300 px-4 py-2">
                        <div class="flex gap-2">
                            <button onclick="exportarPdfViaje(${viaje.id})" 
                                class="text-red-600 hover:text-red-800 text-sm font-medium px-2 py-1 border border-red-300 rounded">
                                PDF
                            </button>
                            <button onclick="exportarExcelViaje(${viaje.id})" 
                                class="text-green-600 hover:text-green-800 text-sm font-medium px-2 py-1 border border-green-300 rounded">
                                Excel
                            </button>
                        </div>
                    </td>
                `;
                        tbody.appendChild(tr);
                    });
                }

                window.mostrarDetalles = function(viajeId) {
                    const viaje = viajes.find(v => v.id === viajeId);
                    if (!viaje) return;

                    const modal = document.getElementById('modalDetalles');
                    const contenido = document.getElementById('contenidoModal');

                    let html = `
                <div class="mb-6">
                    <h4 class="font-semibold text-lg mb-4 text-gray-900">Información del Viaje</h4>
                    <div class="grid grid-cols-2 gap-4 text-sm bg-gray-50 p-4 rounded-lg">
                        <div><span class="font-semibold text-gray-700">Fecha:</span> <span class="text-gray-900">${viaje.fecha_formateada}</span></div>
                        <div><span class="font-semibold text-gray-700">Día:</span> <span class="text-gray-900">${viaje.dia_semana}</span></div>
                        <div><span class="font-semibold text-gray-700">Horario:</span> <span class="text-gray-900">${viaje.horario}</span></div>
                        <div><span class="font-semibold text-gray-700">Ruta:</span> <span class="text-gray-900">${viaje.origen} → ${viaje.destino}</span></div>
                    </div>
                </div>
                <div>
                    <h4 class="font-semibold text-lg mb-4 text-gray-900">Pasajeros (${viaje.pasajeros.length})</h4>
                    <div class="overflow-x-auto">
                        <table class="w-full border-collapse text-sm">
                            <thead>
                                <tr class="bg-gray-100">
                                    <th class="border border-gray-300 px-3 py-2 text-left font-semibold text-gray-700">#</th>
                                    <th class="border border-gray-300 px-3 py-2 text-left font-semibold text-gray-700">Nombre</th>
                                    <th class="border border-gray-300 px-3 py-2 text-left font-semibold text-gray-700">DNI</th>
                                    <th class="border border-gray-300 px-3 py-2 text-left font-semibold text-gray-700">Teléfono</th>
                                    <th class="border border-gray-300 px-3 py-2 text-left font-semibold text-gray-700">Email</th>
                                    <th class="border border-gray-300 px-3 py-2 text-left font-semibold text-gray-700">Asiento</th>
                                </tr>
                            </thead>
                            <tbody>
            `;

                    viaje.pasajeros.forEach((pasajero, index) => {
                        html += `
                        <tr class="hover:bg-gray-50">
                            <td class="border border-gray-300 px-3 py-2 text-gray-900">${index + 1}</td>
                            <td class="border border-gray-300 px-3 py-2 text-gray-900">${pasajero.nombre}</td>
                            <td class="border border-gray-300 px-3 py-2 text-gray-900">${pasajero.dni}</td>
                            <td class="border border-gray-300 px-3 py-2 text-gray-900">${pasajero.telefono}</td>
                            <td class="border border-gray-300 px-3 py-2 text-gray-900 text-xs">${pasajero.email}</td>
                            <td class="border border-gray-300 px-3 py-2 text-gray-900">${pasajero.asiento}</td>
                        </tr>
                    `;
                    });

                    html += `
                            </tbody>
                        </table>
                    </div>
                </div>
                `;

                    contenido.innerHTML = html;
                    modal.style.display = 'flex';
                }

                window.filtrarViajes = function() {
                    const fecha = document.getElementById('filtroFecha').value;
                    const horario = document.getElementById('filtroHorario').value;

                    let viajesFiltrados = viajes;

                    if (fecha) {
                        viajesFiltrados = viajesFiltrados.filter(v => v.fecha === fecha);
                    }

                    if (horario) {
                        viajesFiltrados = viajesFiltrados.filter(v => v.horario === horario);
                    }

                    renderizarTabla(viajesFiltrados);
                }

                window.exportarPdfViaje = function(viajeId) {
                    const url = '/admin/planillas-viajes/exportar-pdf-viaje?viaje_id=' + viajeId;
                    window.open(url, '_blank');
                };

                window.exportarExcelViaje = function(viajeId) {
                    const url = '/admin/planillas-viajes/exportar-excel-viaje?viaje_id=' + viajeId;
                    window.location.href = url;
                };

                // Event listeners
                const btnFiltrar = document.getElementById('btnFiltrar');
                const btnLimpiar = document.getElementById('btnLimpiar');
                const cerrarModal = document.getElementById('cerrarModal');
                const modalDetalles = document.getElementById('modalDetalles');

                if (btnFiltrar) {
                    btnFiltrar.addEventListener('click', window.filtrarViajes);
                }
                if (btnLimpiar) {
                    btnLimpiar.addEventListener('click', function() {
                        document.getElementById('filtroFecha').value = '';
                        document.getElementById('filtroHorario').value = '';
                        window.renderizarTabla();
                    });
                }
                if (cerrarModal) {
                    cerrarModal.addEventListener('click', function() {
                        modalDetalles.style.display = 'none';
                    });
                }
                if (modalDetalles) {
                    // Cerrar modal al hacer clic fuera
                    modalDetalles.addEventListener('click', function(e) {
                        if (e.target === this) {
                            this.style.display = 'none';
                        }
                    });
                }

                // Inicializar tabla cuando el DOM esté listo
                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', function() {
                        if (document.getElementById('tablaViajes')) {
                            window.renderizarTabla();
                        }
                    });
                } else {
                    if (document.getElementById('tablaViajes')) {
                        window.renderizarTabla();
                    }
                }
            })();
        </script>
    @endpush
</x-filament-panels::page>
