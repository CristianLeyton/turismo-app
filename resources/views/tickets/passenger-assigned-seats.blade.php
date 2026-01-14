@php
    $isRoundTrip = $isRoundTrip ?? false;
    $allSeatNumbers = $allSeatNumbers ?? [];
    $allReturnSeatNumbers = $allReturnSeatNumbers ?? [];
@endphp

<div 
    x-data="{
        seatNumbers: @js($allSeatNumbers),
        returnSeatNumbers: @js($allReturnSeatNumbers),
        isRoundTrip: @js($isRoundTrip),
        displayText: 'Cargando...',
        init() {
            // Usar setTimeout para asegurar que todos los items estén en el DOM
            setTimeout(() => {
                this.calculateAndDisplay();
            }, 100);
        },
        calculateAndDisplay() {
            // Buscar el fieldset contenedor del item del repeater
            let fieldset = this.$el.closest('fieldset');
            let index = 0;
            
            if (fieldset) {
                // Buscar el contenedor padre que debería tener todos los items del repeater
                let parent = fieldset.parentElement;
                
                if (parent) {
                    // Buscar todos los fieldsets que son hijos directos del mismo padre
                    let allFieldsets = [];
                    for (let child of parent.children) {
                        if (child.tagName === 'FIELDSET') {
                            allFieldsets.push(child);
                        }
                    }
                    
                    // Encontrar el índice del fieldset actual
                    index = allFieldsets.indexOf(fieldset);
                    if (index === -1) {
                        // Si no se encuentra, intentar contar los fieldsets anteriores
                        let count = 0;
                        let current = fieldset.previousElementSibling;
                        while (current) {
                            if (current.tagName === 'FIELDSET') {
                                count++;
                            }
                            current = current.previousElementSibling;
                        }
                        index = count;
                    }
                }
            }
            
            // Obtener los asientos para este índice
            const seatNum = this.seatNumbers[index];
            const returnSeatNum = this.isRoundTrip ? (this.returnSeatNumbers[index] || null) : null;
            
            // Construir el texto
            const parts = [];
            if (seatNum) parts.push('Ida: Asiento ' + seatNum);
            if (this.isRoundTrip && returnSeatNum) parts.push('Vuelta: Asiento ' + returnSeatNum);
            
            this.displayText = parts.length > 0 ? parts.join(' | ') : null;
        }
    }"
>
    <div class="text-sm py-1">
        <span class="font-medium text-primary-600" x-show="displayText" x-text="displayText"></span>
        <span class="text-gray-500 italic" x-show="!displayText">No se han asignado asientos</span>
    </div>
</div>
