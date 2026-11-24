# Spec Delta

## Purpose

Permite a quien administra los colectivos diseñar desde el panel la grilla de asientos y zonas de cada colectivo, sin poner en riesgo los boletos ya vendidos.

## ADDED Requirements

### Requirement: Modo de edición "Mover"
El editor de layout SHALL ofrecer un modo de edición "Mover", además de "Asientos" y "Zonas". En modo "Mover", un clic en un asiento SHALL marcarlo como seleccionado y resaltarlo, sin agregar ni quitar asientos.

#### Scenario: Seleccionar un asiento
- **WHEN** el usuario está en modo "Mover" y hace clic en un asiento
- **THEN** el asiento queda resaltado como seleccionado
- **AND** no se agrega ni se quita ningún asiento

#### Scenario: El modo "Asientos" no cambia
- **WHEN** el usuario está en modo "Asientos" y hace clic en un asiento sin boletos
- **THEN** el asiento se quita del layout, como hasta ahora

### Requirement: Mover un asiento a una celda vacía
Con un asiento seleccionado en modo "Mover", un clic en una celda vacía del mismo piso SHALL ubicar el asiento en esa celda y liberar la anterior.

#### Scenario: Mover a celda libre
- **WHEN** el asiento 12 está seleccionado en la fila 2, columna 0, y el usuario hace clic en la celda vacía de la fila 5, columna 3
- **THEN** el asiento 12 aparece en la fila 5, columna 3
- **AND** la fila 2, columna 0 queda vacía
- **AND** ningún asiento queda seleccionado

### Requirement: Intercambiar dos asientos
Con un asiento seleccionado en modo "Mover", un clic en otro asiento del mismo piso SHALL intercambiar las ubicaciones de ambos asientos.

#### Scenario: Intercambio
- **WHEN** el asiento 12 (fila 2, columna 0) está seleccionado y el usuario hace clic en el asiento 30 (fila 7, columna 3)
- **THEN** el asiento 12 queda en la fila 7, columna 3
- **AND** el asiento 30 queda en la fila 2, columna 0
- **AND** ningún asiento queda seleccionado

### Requirement: No se puede mover un asiento sobre una zona
Un asiento MUST NOT ubicarse en una celda ocupada por una zona. El intento SHALL dejar el layout sin cambios y mostrar un aviso.

#### Scenario: Destino dentro de una zona
- **WHEN** hay un asiento seleccionado y el usuario hace clic en una celda de la zona "BAÑO"
- **THEN** el asiento no cambia de ubicación
- **AND** se muestra un aviso indicando que la celda está ocupada por una zona

### Requirement: Cancelar la selección
La selección de un asiento en modo "Mover" SHALL cancelarse sin cambios cuando el usuario vuelve a hacer clic en el asiento seleccionado, presiona Esc, cambia de modo o cambia de piso.

#### Scenario: Cancelar con Esc
- **WHEN** hay un asiento seleccionado y el usuario presiona Esc
- **THEN** el asiento deja de estar seleccionado y el layout no cambia

#### Scenario: Cambiar de piso
- **WHEN** hay un asiento seleccionado en la planta baja y el usuario cambia a la planta alta
- **THEN** el asiento deja de estar seleccionado y conserva su ubicación

### Requirement: Mover conserva la identidad del asiento
Mover o intercambiar un asiento SHALL cambiar solo su fila y columna. El asiento MUST conservar su número, tipo, estado activo/inactivo y todos sus boletos, incluidos los asientos con boletos vendidos y los inactivos.

#### Scenario: Mover un asiento vendido
- **GIVEN** el asiento 12 tiene un boleto vendido
- **WHEN** el usuario lo mueve a otra celda y guarda el layout
- **THEN** el layout se guarda sin errores
- **AND** el boleto sigue asignado al mismo asiento, con el número 12
- **AND** el asiento aparece en la nueva ubicación

#### Scenario: Mover un asiento inactivo
- **WHEN** el usuario mueve un asiento inactivo y guarda
- **THEN** el asiento queda en la nueva ubicación y sigue inactivo

### Requirement: El movimiento es un borrador hasta guardar
Los movimientos e intercambios SHALL aplicarse solo al guardar el layout. "Descartar cambios" SHALL devolver todos los asientos a su última ubicación guardada.

#### Scenario: Descartar un movimiento
- **WHEN** el usuario mueve un asiento y luego presiona "Descartar cambios"
- **THEN** el asiento vuelve a su ubicación guardada

#### Scenario: Recargar sin guardar
- **WHEN** el usuario mueve un asiento y sale de la pantalla sin guardar
- **THEN** la ubicación guardada del asiento no cambia
