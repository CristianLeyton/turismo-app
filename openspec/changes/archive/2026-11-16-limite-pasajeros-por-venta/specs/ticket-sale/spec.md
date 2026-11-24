## MODIFIED Requirements

### Requirement: Cantidad de pasajeros por venta
El sistema SHALL permitir entre 1 y el límite configurado en "Pasajeros por venta" de pasajeros por venta (por defecto 1). Sin configurar, el límite MUST ser 4. Al cambiar la cantidad, la lista de pasajeros MUST ajustarse a exactamente esa cantidad, y se descartan el viaje buscado y la configuración de ida y vuelta.

#### Scenario: Cantidad fuera de rango
- **GIVEN** el límite de pasajeros por venta en 4
- **WHEN** se envía una cantidad distinta de 1, 2, 3 o 4
- **THEN** el sistema rechaza el valor con el mensaje "La cantidad de pasajeros debe ser entre 1 y 4"

#### Scenario: Reducir pasajeros
- **WHEN** el vendedor tenía 3 pasajeros cargados y cambia la cantidad a 2
- **THEN** se conservan los 2 primeros pasajeros y se descarta el tercero

#### Scenario: Límite sin configurar
- **GIVEN** que nadie configuró el límite de pasajeros por venta
- **WHEN** el vendedor abre el selector "Cantidad de pasajeros"
- **THEN** ve las opciones de 1 a 4, como hasta ahora

#### Scenario: Límite configurado en 6
- **GIVEN** el límite de pasajeros por venta en 6
- **WHEN** el vendedor abre el selector "Cantidad de pasajeros"
- **THEN** ve las opciones de 1 a 6 y puede emitir una venta de 6 pasajeros

#### Scenario: Límite configurado en 2
- **GIVEN** el límite de pasajeros por venta en 2
- **WHEN** se envía una venta con 3 pasajeros, saltando el selector
- **THEN** la venta se rechaza con "La cantidad de pasajeros debe ser entre 1 y 2" y no se crea ningún boleto

#### Scenario: El límite baja durante una venta
- **GIVEN** un vendedor con una venta a medio hacer de 4 pasajeros
- **WHEN** alguien baja el límite a 3 y el vendedor emite
- **THEN** la emisión se rechaza con "La cantidad de pasajeros debe ser entre 1 y 3" y no se crea ningún boleto
