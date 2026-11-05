# Spec Delta

## Purpose

Agrupar colectivos que comparten recorrido en una línea, para que la búsqueda de horarios y destinos al vender cubra los colectivos regulares y sus refuerzos sin cambiar los datos existentes.

## ADDED Requirements

### Requirement: Agrupación de colectivos en líneas
El sistema SHALL permitir asignar cada colectivo a una línea, y varios colectivos MAY pertenecer a la misma línea. Un colectivo sin línea MUST comportarse como una línea propia que contiene solo a sí mismo.

#### Scenario: Colectivo de refuerzo en una línea
- **WHEN** el colectivo "Oran - Salta (Refuerzo)" se asigna a la línea "Oran - Salta" que ya contiene al colectivo "Oran - Salta"
- **THEN** ambos colectivos forman parte de la línea "Oran - Salta" y el refuerzo conserva su nombre

#### Scenario: Colectivo sin línea
- **WHEN** un colectivo no tiene línea asignada
- **THEN** su búsqueda de horarios y destinos considera solo ese colectivo

### Requirement: Colectivos regulares de una línea
El sistema SHALL identificar un colectivo regular por línea como el que se ofrece en el selector de colectivo de la venta. Los colectivos de refuerzo de la línea MUST NOT aparecer en ese selector.

#### Scenario: Selector de colectivo con línea
- **WHEN** el vendedor abre el selector de colectivo y la línea tiene un regular y un refuerzo
- **THEN** el selector lista solo el regular

#### Scenario: Colectivo sin línea en el selector
- **WHEN** el vendedor abre el selector de colectivo y existe un colectivo sin línea
- **THEN** el selector lista ese colectivo como antes

### Requirement: Línea sin cambios en ventas existentes
La asignación de líneas MUST NOT modificar ventas, boletos, asientos, viajes ni nombres de colectivos ya existentes. Un colectivo sin línea MUST seguir ofreciendo los mismos horarios, paradas y destinos que antes de la introducción de líneas.

#### Scenario: Migración de datos existentes
- **WHEN** se aplica la migración que crea las líneas y el campo de línea de colectivos
- **THEN** todos los colectivos existentes quedan sin línea y ningún boleto, asiento o viaje cambia

### Requirement: Reprogramación de tramos con colectivo de la línea
La reprogramación de un tramo que tiene colectivo guardado MUST ofrecer como horarios solo los de los colectivos de la misma línea, o del propio colectivo si no tiene línea, igual que la venta. Cada opción MUST mostrar el nombre del colectivo al que pertenece. La validación del horario elegido MUST aceptar un horario de cualquier colectivo de la línea y rechazar uno de otra línea.

#### Scenario: Tramo del regular con horario de refuerzo
- **WHEN** se reprograma un tramo vendido con el colectivo "Oran - Salta" y la línea tiene un horario activo del refuerzo que cubre el tramo
- **THEN** ese horario aparece entre las opciones con el nombre "Oran - Salta (Refuerzo)" y puede elegirse

#### Scenario: Horario de otra línea
- **WHEN** se intenta reprogramar un tramo vendido con el colectivo "Oran - Salta" a un horario de un colectivo de otra línea
- **THEN** el sistema rechaza el horario con "El horario elegido no corresponde al colectivo del boleto."

#### Scenario: Tramo sin colectivo guardado
- **WHEN** el boleto es anterior a las líneas y no tiene colectivo guardado
- **THEN** la reprogramación no restringe horarios por colectivo, como antes
