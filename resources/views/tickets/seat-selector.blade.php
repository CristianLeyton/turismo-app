@php
    $tripId = $trip_id ?? null;
    $requiredSeats = (int) ($passengers_count ?? 0);
    $sessionId = $session_id ?? session()->getId();
    $enableReservation = $enable_reservation ?? false;
    $reservationTimeout = $reservation_timeout ?? 5;

    $trip = $trip ?? ($tripId ? \App\Models\Trip::find($tripId) : null);
    $layoutData = $trip ? $trip->getFullLayoutData() : null;

    // Obtener pisos disponibles
    $floors = $layoutData['seats'] ?? [];
@endphp


<div x-data="{
    selected: @entangle('data.' . $fieldId),
    required: {{ (int) $passengers_count }},
    tripId: {{ $tripId ?? 'null' }},
    sessionId: '{{ $sessionId }}',
    enableReservation: {{ $enableReservation ? 'true' : 'false' }},
    reservationTimeout: {{ $reservationTimeout }},
    keepAliveInterval: null,
    reservationExpiresAt: null,
    countdownInterval: null,
    currentTime: new Date(),
    timerText: null, // Propiedad reactiva directa para el timer
    timerKey: 0, // Key para forzar actualización del DOM
    csrfToken: document.querySelector('meta[name=\'csrf-token\']')?.getAttribute('content'),
    componentKey: '{{ time() }}_{{ $fieldId }}_{{ rand() }}',
    seats: {{ $layoutData['seats'] ? json_encode($layoutData['seats']) : '{}' }},

    init() {
        console.log('🚀 Inicializando seat selector:', {
            enableReservation: this.enableReservation,
            tripId: this.tripId,
            sessionId: this.sessionId,
            required: this.required,
            selectedSeats: this.selected,
            selectedLength: this.selected?.length || 0,
            fieldId: '{{ $fieldId }}',
            seatsCount: Object.keys(this.seats).length,
            sampleSeats: Object.keys(this.seats).slice(0, 2).map(key => ({ floor: key, count: this.seats[key]?.length || 0 }))
        });
        
        // Limpiar cualquier intervalo anterior antes de iniciar
        this.stopCountdown();
        
        if (!Array.isArray(this.selected)) {
            this.selected = [];
        }
        
        // Si hay asientos seleccionados, iniciar timer global
        if (this.selected.length > 0) {
            console.log('⏰ Hay asientos seleccionados, iniciando timer global. Asientos:', this.selected);
            this.startGlobalTimer();
        } else {
            console.log('🧹 No hay asientos seleccionados, no se inicia timer');
        }
        
        // Sincronizar estado actual de reservas con el backend (solo para limpiar expiradas)
        if (this.enableReservation && this.tripId) {
            this.syncReservationStatus();
        }
        
        // Limpiar reservas al salir de la página (solo si el usuario confirma)
        let isPageUnloading = false;
        
        window.addEventListener('beforeunload', (event) => {
            // Marcar que la página se está descargando
            isPageUnloading = true;
            
            // No liberar reservas aquí, esperar a 'unload'
            // Filament manejará la confirmación con unsavedChangesAlerts()
        });
        
        // Liberar reservas solo cuando la página realmente se descarga
        window.addEventListener('unload', () => {
            if (isPageUnloading) {
                this.releaseReservations();
            }
        });
        
        // Escuchar evento global de expiración de reservas
        window.addEventListener('seatReservationExpired', (event) => {
            console.log('📢 Recibido evento de expiración global:', event.detail);
            
            // Limpiar asientos seleccionados en este componente
            this.selected = [];
            
            // Limpiar tiempo de expiración local
            this.reservationExpiresAt = null;
            this.timerText = null;
        });
    },

    destroy() {
        console.log('🗑️ Destruyendo componente seat selector:', {
            fieldId: '{{ $fieldId }}',
            selectedCount: this.selected?.length || 0
        });
        
        // Limpiar todos los intervalos al destruir el componente
        this.stopCountdown();
        this.stopKeepAlive();
        
        // Si no hay asientos seleccionados, detener timer global
        if (!this.selected || this.selected.length === 0) {
            this.stopGlobalTimer();
        }
        
        console.log('✅ Componente destruido y limpio');
    },

    // Timer global compartido entre todos los selectores
    startGlobalTimer() {
        // Detener timer global anterior si existe
        this.stopGlobalTimer();
        
        // Establecer tiempo de expiración global
        window.seatReservationGlobalExpiresAt = new Date(Date.now() + 5 * 60 * 1000);
        
        console.log('🌐 Iniciando timer global:', {
            expiresAt: window.seatReservationGlobalExpiresAt,
            component: '{{ $fieldId }}'
        });
        
        // Iniciar contador global
        window.seatReservationGlobalInterval = setInterval(() => {
            const now = new Date();
            const timeRemaining = window.seatReservationGlobalExpiresAt - now;
            
            if (timeRemaining <= 0) {
                console.log('⏰ Timer global expirado');
                this.handleGlobalReservationExpired();
                return;
            }
            
            const minutes = Math.floor(timeRemaining / 60000);
            const seconds = Math.floor((timeRemaining % 60000) / 1000);
            this.timerText = `${minutes}:${seconds.toString().padStart(2, '0')}`;
            this.timerKey++;
            
            console.log('⏰ Timer global actualizado:', this.timerText, 'key:', this.timerKey);
        }, 1000);
    },
    
    stopGlobalTimer() {
        if (window.seatReservationGlobalInterval) {
            clearInterval(window.seatReservationGlobalInterval);
            window.seatReservationGlobalInterval = null;
            console.log('🛑 Timer global detenido');
        }
    },
    
    handleGlobalReservationExpired() {
        console.log('⏰ La reserva global ha expirado, esperando 10 segundos antes de limpiar...');
        
        // Detener timer global
        this.stopGlobalTimer();
        
        // Limpiar el tiempo de expiración
        this.reservationExpiresAt = null;
        this.timerText = null;
        
        // Esperar 10 segundos antes de limpiar
        setTimeout(async () => {
            console.log('⏰ Han pasado 10 segundos, limpiando reservas globales...');
            
            // Notificar a todos los componentes que limpien sus asientos
            window.dispatchEvent(new CustomEvent('seatReservationExpired', {
                detail: { source: '{{ $fieldId }}' }
            }));
            
            // Liberar reservas en el backend
            try {
                await this.releaseReservations();
            } catch (error) {
                console.error('Error al liberar reservas expiradas:', error);
            }
            
            // Notificar al usuario usando el sistema de Filament
            console.log('📢 Enviando notificación de reserva expirada a Filament');
            
            // Llamar al método Livewire para mostrar notificación de Filament
            this.$wire.call('notifyReservationExpired').then(() => {
                console.log('✅ Notificación de Filament enviada exitosamente');
            }).catch((error) => {
                console.error('❌ Error al enviar notificación de Filament:', error);
                
                // Fallback a notificación del navegador
                if ('Notification' in window && Notification.permission === 'granted') {
                    new Notification('Reserva expirada', {
                        body: 'Los asientos han sido liberados.',
                        icon: '/favicon.ico'
                    });
                }
            });
        }, 10000); // 10 segundos
    },

    startCountdown() {
        console.log('🚀 Iniciando contador:', {
            reservationExpiresAt: this.reservationExpiresAt,
            currentTime: this.currentTime
        });
        
        if (this.countdownInterval) {
            clearInterval(this.countdownInterval);
        }
        
        this.countdownInterval = setInterval(() => {
            // Actualizar tiempo actual para forzar reactividad
            this.currentTime = new Date();
            
            // Incrementar timerKey para forzar actualización del DOM
            this.timerKey++;
            
            // Calcular y actualizar timerText directamente
            if (!this.selected || this.selected.length === 0 || !this.reservationExpiresAt) {
                this.timerText = null;
            } else {
                const diff = this.reservationExpiresAt - this.currentTime;
                if (diff <= 0) {
                    this.timerText = null;
                    this.handleReservationExpired();
                    return;
                } else {
                    const minutes = Math.floor(diff / 60000);
                    const seconds = Math.floor((diff % 60000) / 1000);
                    this.timerText = `${minutes}:${seconds.toString().padStart(2, '0')}`;
                    console.log('⏰ Timer actualizado:', this.timerText, 'key:', this.timerKey);
                }
            }
        }, 1000);
        
        console.log('⏰ Contador iniciado con intervalo:', this.countdownInterval);
    },

    async handleReservationExpired() {
        console.log('⏰ La reserva ha expirado, esperando 10 segundos antes de limpiar...');
        
        // Detener el contador
        this.stopCountdown();
        
        // Limpiar el tiempo de expiración para mostrar 00:00 o que desaparezca
        this.reservationExpiresAt = null;
        
        // Esperar 10 segundos antes de limpiar
        setTimeout(async () => {
            console.log('⏰ Han pasado 10 segundos, limpiando reservas...');
            
            // Deseleccionar todos los asientos
            this.selected = [];
            
            // Liberar reservas en el backend
            try {
                await this.releaseReservations();
            } catch (error) {
                console.error('Error al liberar reservas expiradas:', error);
            }
            
            // Notificar al usuario usando el sistema de Filament
            console.log('📢 Enviando notificación de reserva expirada a Filament');
            
            // Llamar al método Livewire para mostrar notificación de Filament
            this.$wire.call('notifyReservationExpired').then(() => {
                console.log('✅ Notificación de Filament enviada exitosamente');
            }).catch((error) => {
                console.error('❌ Error al enviar notificación de Filament:', error);
                
                // Fallback a notificación del navegador
                if ('Notification' in window && Notification.permission === 'granted') {
                    new Notification('Reserva expirada', {
                        body: 'Los asientos han sido liberados.',
                        icon: '/favicon.ico'
                    });
                }
            });
        }, 10000); // 10 segundos
    },

    stopCountdown() {
        console.log('🛑 Deteniendo contador:', {
            intervalId: this.countdownInterval,
            tieneIntervalo: !!this.countdownInterval
        });
        
        if (this.countdownInterval) {
            clearInterval(this.countdownInterval);
            this.countdownInterval = null;
            console.log('✅ Contador detenido y limpiado');
        } else {
            console.log('ℹ️ No había contador activo para detener');
        }
    },

    resetTimerTo5Minutes() {
        console.log('⏰ REINICIANDO TIMER GLOBAL A 5 MINUTOS desde:', '{{ $fieldId }}');
        
        // Reiniciar timer global
        this.startGlobalTimer();
        
        console.log('⏰ Timer global reiniciado completamente');
    },

    updateReservationTime(newTime) {
        console.log('⏰ Actualizando tiempo de reserva global:', {
            nuevoTiempo: newTime,
            componente: '{{ $fieldId }}'
        });
        
        // Actualizar tiempo de expiración global
        window.seatReservationGlobalExpiresAt = new Date(newTime);
        this.reservationExpiresAt = new Date(newTime);
        
        console.log('⏰ Timer global actualizado:', {
            globalExpiresAt: window.seatReservationGlobalExpiresAt,
            localExpiresAt: this.reservationExpiresAt
        });
    },

    async toggleSeat(seatId) {
        console.log('🪑 Click en asiento:', seatId, 'componente:', '{{ $fieldId }}');
        
        if (!Array.isArray(this.selected)) {
            this.selected = [];
        }

        // Buscar asiento en todos los pisos
        let seat = null;
        for (const floorKey in this.seats) {
            const floorSeats = this.seats[floorKey];
            seat = floorSeats.find(s => s.id === seatId);
            if (seat) break;
        }
        
        if (!seat) {
            console.log('❌ Asiento no encontrado:', seatId);
            return;
        }

        const isCurrentlySelected = this.selected.includes(seatId);
        const isReserved = seat.reserved;
        const isOccupied = seat.occupied;

        if (isCurrentlySelected) {
            // Deseleccionar asiento
            this.selected = this.selected.filter(id => id !== seatId);
            console.log('➖ Deseleccionando asiento:', seatId);
            
            // Si no quedan asientos seleccionados en ningún componente, detener timer global
            const totalSelected = this.getTotalSelectedSeats();
            if (totalSelected === 0) {
                console.log('🧹 No quedan asientos seleccionados, deteniendo timer global');
                this.stopGlobalTimer();
            }
        } else {
            // Verificar si ya se alcanzó el límite de asientos requeridos
            if (this.selected.length >= this.required) {
                console.log('❌ Límite de asientos alcanzado:', {
                    current: this.selected.length,
                    required: this.required,
                    attemptingToAdd: seatId
                });
                
                // Mostrar notificación al usuario
                if ('Notification' in window && Notification.permission === 'granted') {
                    new Notification('Límite de asientos', {
                        body: `Solo puedes seleccionar ${this.required} asiento(s). Ya has seleccionado ${this.selected.length}.`,
                        icon: '/favicon.ico'
                    });
                }
                
                return;
            }
            
            // Verificar si el asiento está disponible
            if (isReserved || isOccupied) {
                console.log('❌ Asiento no disponible:', { isReserved, isOccupied });
                return;
            }

            // Seleccionar asiento
            this.selected.push(seatId);
            console.log('➕ Seleccionando asiento:', seatId);
            
            // Iniciar/reiniciar timer global
            this.startGlobalTimer();
        }

        // Actualizar reservación en el backend
        await this.updateReservation();
    },

    // Método para obtener el total de asientos seleccionados en todos los componentes
    getTotalSelectedSeats() {
        // Obtener todos los componentes de selección de asientos
        const seatSelectors = document.querySelectorAll('[x-data*=\'seat_selector\']');
        let total = 0;
        
        seatSelectors.forEach(selector => {
            const alpineData = selector.__x;
            if (alpineData && alpineData.selected) {
                total += alpineData.selected.length;
            }
        });
        
        return total;
    },

    isSelected(seatId) {
        const result = Array.isArray(this.selected) && this.selected.includes(seatId);
        console.log('🔍 Verificando isSelected:', {
            seatId: seatId,
            selected: this.selected,
            result: result,
            fieldId: '{{ $fieldId }}'
        });
        return result;
    },

    async updateReservation() {
        if (!this.enableReservation || !this.tripId) {
            console.log('❌ Reservación deshabilitada o sin tripId', {
                enableReservation: this.enableReservation,
                tripId: this.tripId
            });
            return;
        }
        
        console.log('🔄 Actualizando reservación:', {
            tripId: this.tripId,
            selected: this.selected,
            sessionId: this.sessionId
        });
        
        try {
            const response = await fetch('/api/seat-reservations/reserve', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': this.csrfToken
                },
                body: JSON.stringify({
                    trip_id: this.tripId,
                    seat_ids: this.selected,
                    session_id: this.sessionId
                })
            });
            
            console.log('📡 Response status:', response.status);
            
            const result = await response.json();
            console.log('📦 Response data:', result);
            
            if (result.success) {
                if (result.expires_at) {
                    this.updateReservationTime(result.expires_at);
                }
                console.log('✅ Reservación exitosa, expira:', result.expires_at);
            } else {
                console.log('❌ Error en reservación:', result.message);
                // Si falla la reservación, mostrar notificación
                this.$wire.dispatch('notify', {
                    type: 'warning',
                    title: 'Advertencia',
                    body: result.message || 'No se pudieron reservar los asientos seleccionados'
                });
            }
        } catch (error) {
            console.error('💥 Error al actualizar reservación:', error);
        }
    },

    async releaseReservations() {
        if (!this.enableReservation || !this.sessionId) return;
        
        try {
            await fetch('/api/seat-reservations/release', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': this.csrfToken
                },
                body: JSON.stringify({
                    session_id: this.sessionId
                })
            });
        } catch (error) {
            console.error('Error al liberar reservas:', error);
        }
    },

    startKeepAlive() {
        // Enviar keep-alive cada 2 minutos (antes de que expiren las reservas de 5 minutos)
        this.keepAliveInterval = setInterval(async () => {
            try {
                const response = await fetch('/api/seat-reservations/keep-alive', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': this.csrfToken
                    },
                    body: JSON.stringify({
                        session_id: this.sessionId
                    })
                });
                
                if (response.ok) {
                    const result = await response.json();
                    if (result.success && result.expires_at) {
                        this.updateReservationTime(result.expires_at);
                        console.log('⏰ Keep-alive extendió la reserva hasta:', result.expires_at);
                    }
                }
            } catch (error) {
                console.error('Error en keep-alive:', error);
            }
        }, 120000); // 2 minutos
    },

    stopKeepAlive() {
        if (this.keepAliveInterval) {
            clearInterval(this.keepAliveInterval);
            this.keepAliveInterval = null;
        }
    },

    async syncReservationStatus() {
        try {
            console.log('🔄 Sincronizando estado de reservas (solo limpieza)...');
            
            const response = await fetch('/api/seat-reservations/status', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': this.csrfToken
                },
                body: JSON.stringify({
                    session_id: this.sessionId,
                    trip_id: this.tripId
                })
            });

            if (response.ok) {
                const data = await response.json();
                console.log('📡 Estado de reservas sincronizado (solo limpieza):', {
                    success: data.success,
                    reserved_seats: data.reserved_seats,
                    reservation_count: data.reservation_count
                });
                
                // NO actualizar el timer aquí - el timer se maneja localmente
                // Solo usamos esto para limpiar reservas expiradas en el backend
                
                console.log('🔄 Estado sincronizado, manteniendo selección actual:', this.selected);
            } else {
                console.error('❌ Error en syncReservationStatus:', response.status, response.statusText);
            }
        } catch (error) {
            console.error('Error al sincronizar estado de reservas:', error);
        }
    },

    get timeRemaining() {
        // Si no hay asientos seleccionados, no mostrar el timer
        if (!this.selected || this.selected.length === 0) {
            return null;
        }
        
        if (!this.reservationExpiresAt) {
            return null;
        }
        
        const diff = this.reservationExpiresAt - this.currentTime;
        
        if (diff <= 0) {
            return null;
        }
        
        const minutes = Math.floor(diff / 60000);
        const seconds = Math.floor((diff % 60000) / 1000);
        const result = `${minutes}:${seconds.toString().padStart(2, '0')}`;
        
        // Forzar actualización reactiva
        return result;
    },

    // Watcher para forzar actualización del DOM
    get timerDisplay() {
        return this.timeRemaining;
    }
}" class="seat-selector-container grid grid-cols-1 gap-4" :key="componentKey">
    @if (!$trip || !$layoutData)
        <div class="p-4 bg-gray-50 dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 w-full">
            <p class="text-sm text-gray-600 dark:text-gray-400">
                Primero debe buscar y seleccionar un viaje disponible.
            </p>
        </div>
    @else
        <div class="mb-0 p-4 bg-gray-50 dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 w-full">
            <div class="flex flex-wrap gap-4 w-full">
                <div class="flex items-center gap-2">
                    <div class="w-6 h-6 bg-gray-300 dark:bg-gray-600 rounded border border-gray-400"></div>
                    <span class="text-sm text-gray-700 dark:text-gray-300">Disponible</span>
                </div>
                <div class="flex items-center gap-2">
                    <div class="w-6 h-6 bg-purple-500 rounded border border-purple-600"></div>
                    <span class="text-sm text-gray-700 dark:text-gray-300">Seleccionado</span>
                </div>
                <div class="flex items-center gap-2">
                    <div class="w-6 h-6 bg-red-500 rounded border border-red-600"></div>
                    <span class="text-sm text-gray-700 dark:text-gray-300">Ocupado</span>
                </div>
                @if ($enableReservation)
                    <div class="flex items-center gap-2">
                        <div class="w-6 h-6 bg-orange-500 rounded border border-orange-600"></div>
                        <span class="text-sm text-gray-700 dark:text-gray-300">Reservado</span>
                    </div>
                @endif
            </div>
            @if ($requiredSeats > 0)
                <div class="mt-3 text-sm text-gray-600 dark:text-gray-400">
                    <span x-text="`Asientos seleccionados: ${selected.length} de ${required}`"></span>
                </div>
            @endif
        </div>

        <h3 class="text-xl font-semibold text-primary text-fuchsia-600 flex justify-between">
            <p>Seleccionar asientos</p> 
            
            <p class="font-bold text-xl uppercase">
            {{ strpos($fieldId, 'return') !== false ? 'Vuelta ↓' : 'Ida ↑' }}</h3>
            </p>
        <!-- Contenedor de pisos con flexbox para cambiar orden -->
        <div class="flex flex-col-reverse md:flex-row-reverse md:justify-center items-end *:w-full gap-4">
            @foreach ($floors as $floorKey => $seats)
                @php
                    $floorNumber = str_replace('floor_', '', $floorKey);
                    $floorName =
                        $floorNumber == '1'
                            ? 'Planta baja'
                            : ($floorNumber == '2'
                                ? 'Planta alta'
                                : "Piso {$floorNumber}");
                @endphp

                <div class="mb-2 -mt-6">
                    <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-4">
                        {{ $floorName }} {{-- ({{ count($seats) }} asientos) --}}
                    </h3>

                    @php
                        // Obtener áreas especiales para este piso
                        $areas = $layoutData['areas'][$floorKey] ?? [];

                        // Organizar asientos por fila y columna
                        $seatsByRow = [];
                        $minRow = PHP_INT_MAX;
                        $maxRow = 0;
                        $maxColumn = 0;

                        foreach ($seats as $seat) {
                            $row = (int) ($seat['row'] ?? 0);
                            $column = (int) ($seat['column'] ?? 0);
                            if ($row > 0) {
                                $minRow = min($minRow, $row);
                                $maxRow = max($maxRow, $row);
                            }
                            $maxColumn = max($maxColumn, $column);

                            if (!isset($seatsByRow[$row])) {
                                $seatsByRow[$row] = [];
                            }
                            $seatsByRow[$row][$column] = $seat;
                        }

                        // Incluir filas de áreas en el cálculo de maxRow y minRow
                        foreach ($areas as $area) {
                            $areaRowStart = (int) ($area['row_start'] ?? 0);
                            $areaRowEnd = (int) ($area['row_end'] ?? $areaRowStart);
                            if ($areaRowStart > 0) {
                                $minRow = min($minRow, $areaRowStart);
                                $maxRow = max($maxRow, $areaRowEnd);
                            }
                            $areaColStart = (int) ($area['column_start'] ?? 0);
                            $areaColEnd = (int) ($area['column_end'] ?? $areaColStart);
                            $maxColumn = max($maxColumn, $areaColEnd);
                        }

                        // Normalizar: si las filas empiezan en 1 (o más), ajustar para que empiecen en 0 para el grid
                        $rowOffset = 0;
                        if ($minRow !== PHP_INT_MAX && $minRow > 0) {
                            $rowOffset = $minRow;
                            $adjustedSeatsByRow = [];
                            foreach ($seatsByRow as $row => $rowSeats) {
                                $adjustedRow = $row - $rowOffset;
                                $adjustedSeatsByRow[$adjustedRow] = $rowSeats;
                            }
                            $seatsByRow = $adjustedSeatsByRow;
                            $maxRow = $maxRow - $rowOffset;
                        } else {
                            $minRow = 0;
                        }

                        // Si no hay configuración de filas/columnas, usar layout simple por número
                        if ($maxRow === 0 && $maxColumn === 0 && count($seats) > 0) {
                            // Layout simple: mostrar asientos en filas de 4
                            $seatsPerRow = 4;
                            $currentRow = 0;
                            $currentCol = 0;
                            $rowOffset = 0; // Reset para layout simple

                            $seatsByRow = [];
                            foreach ($seats as $seat) {
                                if (!isset($seatsByRow[$currentRow])) {
                                    $seatsByRow[$currentRow] = [];
                                }
                                $seatsByRow[$currentRow][$currentCol] = $seat;
                                $currentCol++;
                                if ($currentCol >= $seatsPerRow) {
                                    $currentCol = 0;
                                    $currentRow++;
                                }
                            }
                            $maxRow = $currentRow;
                            $maxColumn = $seatsPerRow - 1;
                        }
                    @endphp

                    @php
                        // Asegurar que rowOffset esté definido para el loop
                        $rowOffset = $rowOffset ?? 0;
                    @endphp

                    <div
                        class="flex justify-center items-center bg-white dark:bg-gray-900 p-4 rounded-lg border border-gray-200 dark:border-gray-700 overflow-x-auto">
                        <div class="seat-grid inline-grid"
                            style="--grid-columns: {{ max($maxColumn + 1, 4) }}; grid-template-columns: repeat({{ max($maxColumn + 1, 4) }}, 45px); gap: 4px; width: fit-content;">
                            @for ($row = 0; $row <= $maxRow; $row++)
                                @php
                                    $rowSeats = $seatsByRow[$row] ?? [];
                                @endphp

                                @for ($col = 0; $col <= $maxColumn; $col++)
                                    @php
                                        $seat = $rowSeats[$col] ?? null;
                                        $isArea = false;
                                        $areaInfo = null;

                                        // Verificar si esta posición es un área especial (ajustar row para comparación)
                                        $actualRow = $row + $rowOffset;
                                        foreach ($areas as $area) {
                                            $areaRowStart = (int) ($area['row_start'] ?? 0);
                                            $areaRowEnd = (int) ($area['row_end'] ?? $areaRowStart);
                                            $areaColStart = (int) ($area['column_start'] ?? 0);
                                            $areaColEnd = (int) ($area['column_end'] ?? $areaColStart);

                                            if (
                                                $areaRowStart <= $actualRow &&
                                                $areaRowEnd >= $actualRow &&
                                                $areaColStart <= $col &&
                                                $areaColEnd >= $col
                                            ) {
                                                $isArea = true;
                                                $areaInfo = $area;
                                                break;
                                            }
                                        }
                                    @endphp

                                    @if ($seat)
                                        @php
                                            $seatId = $seat['id'];
                                            $seatNumber = $seat['seat_number'];
                                            $isOccupied = $seat['is_occupied'] ?? false;
                                            
                                            // Verificar si el asiento está reservado (para pre-reservaciones)
                                            $isReserved = false;
                                            $isReservedByCurrentUser = false;
                                            if ($enableReservation && $tripId) {
                                                $isReserved = \App\Models\SeatReservation::isSeatReserved($tripId, $seatId);
                                                
                                                // Verificar si está reservado por el usuario actual
                                                if ($isReserved) {
                                                    $isReservedByCurrentUser = \App\Models\SeatReservation::where('trip_id', $tripId)
                                                        ->where('seat_id', $seatId)
                                                        ->where('user_session_id', $sessionId)
                                                        ->where('expires_at', '>', now())
                                                        ->exists();
                                                        
                                                    // Debug: Log para verificar el estado de la reserva
                                                    \Log::info("Asiento {$seatId} - Trip {$tripId} - Session {$sessionId}", [
                                                        'isReserved' => $isReserved,
                                                        'isReservedByCurrentUser' => $isReservedByCurrentUser,
                                                        'fieldId' => $fieldId
                                                    ]);
                                                }
                                            }
                                        @endphp

                                        @php
                                            $tooltipText = 'Asiento ' . $seatNumber;
                                            if ($isReservedByCurrentUser) {
                                                $tooltipText = 'Tu asiento reservado';
                                            } elseif ($isReserved) {
                                                $tooltipText = 'Asiento reservado por otro usuario';
                                            } elseif ($isOccupied) {
                                                $tooltipText = 'Asiento ocupado';
                                            }
                                        @endphp

                                        <button type="button" @click="toggleSeat({{ $seatId }})"
                                            @if ($isOccupied || ($isReserved && !$isReservedByCurrentUser)) disabled @endif
                                            :disabled="!isSelected({{ $seatId }}) && selected.length >= required"
                                            :class="{
                                                'bg-gray-300 dark:bg-gray-600 border-gray-400 hover:bg-gray-400 dark:hover:bg-gray-500':
                                                    !isSelected({{ $seatId }}) && !@js($isOccupied),
                                            
                                                'bg-purple-500 border-purple-600 text-white hover:bg-purple-600': isSelected(
                                                    {{ $seatId }}),
                                            
                                                'bg-red-500 border-red-600 cursor-not-allowed': @js($isOccupied),
                                                
                                                'bg-orange-500 border-orange-600 cursor-not-allowed': @js($isReserved) && !@js($isReservedByCurrentUser)
                                            }"
                                            class="seat-button rounded border-2 flex items-center justify-center text-xs font-semibold text-gray-800 dark:text-gray-200 transition-colors duration-200 disabled disabled:cursor-not-allowed"
                                            title="{{ $tooltipText }}">
                                            <span>{{ $seatNumber }}</span>
                                        </button>
                                    @elseif($isArea && $areaInfo)
                                        @php
                                            // Renderizar el área en cada celda que ocupa
                                            // Solo mostrar el label en la primera celda (esquina superior izquierda)
                                            $isFirstCellOfArea =
                                                $actualRow == (int) ($areaInfo['row_start'] ?? 0) &&
                                                $col == (int) ($areaInfo['column_start'] ?? 0);
                                            $areaType = $areaInfo['area_type'] ?? '';
                                            $isPasillo = $areaType === 'pasillo';
                                        @endphp
                                        @if ($isPasillo)
                                            {{-- Pasillo blanco --}}
                                            <div
                                                class="area-cell flex items-center justify-center p-1 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded">
                                            </div>
                                        @else
                                            {{-- Otras áreas especiales (BAÑO, CAFETERA, etc.) --}}
                                            <div
                                                class="area-cell flex items-center justify-center p-1 bg-amber-100 dark:bg-amber-900/30 border border-amber-300 dark:border-amber-700 rounded text-xs font-semibold text-amber-800 dark:text-amber-200 text-center">
                                                @if ($isFirstCellOfArea)
                                                    {{ $areaInfo['label'] }}
                                                @endif
                                            </div>
                                        @endif
                                    @else
                                        <div class="empty-cell"></div>
                                    @endif
                                @endfor
                            @endfor
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div x-show="selected.length < required"
            class="w-full mt-0 p-3 bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg">
            <p class="text-sm text-yellow-800 dark:text-yellow-200">
                Debe seleccionar <span x-text="required - selected.length"></span> asiento(s) más.
            </p>
        </div>
    @endif
</div>


<style>
    .seat-selector-container {
        width: 100%;
    }

    .seat-selector-container [x-cloak] {
        display: none !important;
    }

    .seat-grid {
        width: fit-content;
        display: inline-grid;
    }

    .seat-button {
        width: 45px;
        height: 32px;
        min-width: 45px;
        max-width: 45px;
        font-size: 0.7rem;
        padding: 0.125rem;
        line-height: 1;
    }

    .empty-cell {
        width: 45px;
        height: 32px;
        min-width: 45px;
    }

    .area-cell {
        width: 45px;
        min-height: 32px;
        font-size: 0.65rem;
        padding: 0.25rem 0.125rem;
        line-height: 1.2;
    }

    @media (max-width: 768px) {
        .seat-grid {
            grid-template-columns: repeat(var(--grid-columns, 4), 40px) !important;
            width: max-content;
            margin-left: auto;
            margin-right: auto;
        }

        .seat-button {
            width: 40px;
            height: 28px;
            min-width: 40px;
            max-width: 40px;
            font-size: 0.65rem;
        }

        .empty-cell {
            width: 40px;
            height: 28px;
            min-width: 40px;
        }

        .area-cell {
            width: 40px;
            min-height: 28px;
            font-size: 0.6rem;
            padding: 0.2rem 0.1rem;
        }
    }
</style>
