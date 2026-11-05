# Spec Delta

## ADDED Requirements

### Requirement: DNI únicos dentro de la venta
Una venta MUST NOT incluir el mismo DNI más de una vez, sumando los DNI de los pasajeros adultos y de sus menores acompañantes. Al validarse, un campo DNI cuyo valor ya está cargado en otro campo DNI de la venta SHALL mostrar "Este DNI ya está cargado en otro pasajero de la venta.". La emisión MUST rechazar la venta si llega con DNI repetidos, sin crear nada.

#### Scenario: Dos adultos con el mismo DNI
- **WHEN** el vendedor carga el mismo DNI en dos pasajeros de la venta
- **THEN** el campo DNI que se está cargando muestra "Este DNI ya está cargado en otro pasajero de la venta." y no se puede finalizar

#### Scenario: Menor con el DNI de un adulto de la venta
- **WHEN** el DNI del menor acompañante de un pasajero coincide con el DNI de otro pasajero de la venta
- **THEN** el campo DNI del menor muestra el error y no se puede finalizar

#### Scenario: Emisión forzada con DNI repetidos
- **WHEN** llega una emisión con dos pasajeros con el mismo DNI, sin pasar por la validación del formulario
- **THEN** la venta se rechaza y no se crea venta, pasajeros ni boletos

### Requirement: Aviso de asientos liberados al finalizar
Si al presionar "Finalizar" la venta no tiene los asientos de ida o de vuelta, por ejemplo porque la reserva venció y se liberaron, el sistema SHALL mostrar una notificación persistente. Su título es "Faltan los asientos de ida", "Faltan los asientos de vuelta" o "Faltan los asientos" si faltan ambos. Su texto explica que la reserva venció e indica presionar «Anterior» hasta el paso de asientos, elegirlos de nuevo y volver a finalizar. No se emite nada.

#### Scenario: Reserva de ida vencida
- **WHEN** los asientos de ida se liberaron y el vendedor presiona "Finalizar"
- **THEN** ve "Faltan los asientos de ida" con el texto "La reserva de los asientos venció y se liberaron. Presioná «Anterior» hasta el paso «Asientos (Ida)», elegí los asientos de nuevo y volvé a finalizar la venta." y no se crea la venta

#### Scenario: Solo la vuelta liberada
- **WHEN** solo los asientos de vuelta se liberaron y el vendedor presiona "Finalizar"
- **THEN** ve "Faltan los asientos de vuelta" y el texto nombra el paso «Asientos (Vuelta)»

#### Scenario: Asientos reservados
- **WHEN** los asientos siguen reservados y el vendedor presiona "Finalizar"
- **THEN** no se muestra este aviso
