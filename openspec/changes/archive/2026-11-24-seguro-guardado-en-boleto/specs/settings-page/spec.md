## ADDED Requirements

### Requirement: Confirmación al borrar un período de precio
Borrar una fila de la grilla "Períodos de precio" SHALL pedir siempre confirmación antes de quitarla. Si al cancelar no se confirma, la fila MUST quedar como estaba. Como con cualquier cambio de la página, el borrado se aplica recién al guardar.

#### Scenario: Confirmar el borrado
- **GIVEN** el precio fijo prendido con dos períodos
- **WHEN** el usuario toca borrar en uno y confirma
- **THEN** la fila se quita de la grilla, y al guardar deja de existir

#### Scenario: Cancelar el borrado
- **GIVEN** el precio fijo prendido con dos períodos
- **WHEN** el usuario toca borrar en uno y cancela
- **THEN** la grilla sigue con los dos períodos

### Requirement: Aviso de boletos anteriores que dependen del período
Si al borrar un período existen boletos de ida sin modo de precio registrado, no eliminados, vendidos dentro del rango de ese período y con su ida o su vuelta todavía por viajar o sin fecha, la confirmación MUST avisar cuántos son y que van a tomar el seguro de otro período. El rango va desde su "Desde" hasta el día anterior al período siguiente de la grilla; si es el más antiguo, incluye también las ventas anteriores. Sin esos boletos, la confirmación MUST ser la simple.

#### Scenario: Período con boletos anteriores por viajar
- **GIVEN** un período desde el 01/11/2026 y otro desde el 20/11/2026, y 3 boletos de ida sin modo de precio vendidos el 10/11/2026 para viajes del 15/12/2026
- **WHEN** el usuario toca borrar el período del 01/11/2026
- **THEN** la confirmación avisa que 3 boletos vendidos en ese período van a tomar el seguro de otro período

#### Scenario: Boletos anteriores ya viajados
- **GIVEN** un período desde el 01/11/2026 y otro desde el 20/11/2026, y solo boletos sin modo de precio vendidos el 10/11/2026 para viajes ya realizados, solo ida
- **WHEN** el usuario toca borrar el período del 01/11/2026
- **THEN** aparece la confirmación simple, sin el aviso

#### Scenario: Ida viajada con vuelta por viajar
- **GIVEN** un período desde el 01/11/2026 y otro desde el 20/11/2026, y una ida y vuelta sin modo de precio vendida el 10/11/2026 cuya ida ya viajó y cuya vuelta es el 15/12/2026
- **WHEN** el usuario toca borrar el período del 01/11/2026
- **THEN** la confirmación avisa que 1 boleto va a tomar el seguro de otro período

#### Scenario: Boleto anterior sin fecha
- **GIVEN** un período desde el 01/11/2026 y otro desde el 20/11/2026, y un boleto sin modo de precio vendido el 10/11/2026 sin fecha asignada
- **WHEN** el usuario toca borrar el período del 01/11/2026
- **THEN** la confirmación muestra el aviso con 1 boleto

#### Scenario: Período más antiguo con ventas anteriores
- **GIVEN** el período más antiguo desde el 20/11/2026 y un boleto sin modo de precio vendido el 10/10/2026 para un viaje futuro
- **WHEN** el usuario toca borrar el período del 20/11/2026
- **THEN** la confirmación muestra el aviso con ese boleto

#### Scenario: Solo boletos con modo registrado
- **GIVEN** un período con boletos vendidos solo después de este cambio, registrados como "fijo" o "manual"
- **WHEN** el usuario toca borrar ese período
- **THEN** aparece la confirmación simple, sin el aviso

#### Scenario: Boletos anteriores eliminados
- **GIVEN** un período cuyos únicos boletos sin modo de precio por viajar están eliminados
- **WHEN** el usuario toca borrar ese período
- **THEN** aparece la confirmación simple, sin el aviso

## MODIFIED Requirements

### Requirement: Configurar el precio fijo de los boletos
La sección "Precio de los boletos" de la pestaña Venta SHALL ofrecer el interruptor "Precio fijo activo", que sin configurar aparece apagado, y, solo con el interruptor prendido, la grilla "Períodos de precio". Cada fila MUST tener "Desde", "Precio del boleto" y "Seguro para menores", con prefijo `$`, y mostrar sin poder editarlo el "Hasta": el día anterior al "Desde" de la fila siguiente, o "en adelante" en la última. La grilla MUST ordenarse por "Desde" al guardar y marcar como "Vigente" el período que rige hoy. Los períodos pasados MUST conservarse hasta que un usuario los borre: el sistema no los depura solo. La ayuda de la grilla MUST explicar que los boletos vendidos guardan su propio seguro y que solo los vendidos antes de este cambio dependen de la grilla.

#### Scenario: Valor inicial
- **WHEN** nadie configuró el precio fijo y un usuario con el permiso abre la pestaña Venta
- **THEN** el interruptor aparece apagado y la grilla no se ve

#### Scenario: Prender el precio fijo
- **WHEN** un usuario con el permiso prende "Precio fijo activo", carga un período desde hoy con boleto 5000 y seguro 800, y guarda
- **THEN** el interruptor y el período quedan guardados y la venta calcula el precio con esos montos

#### Scenario: Hasta calculado
- **GIVEN** períodos desde el 20/11/2026 y desde el 02/12/2026
- **WHEN** el usuario abre la pestaña Venta
- **THEN** el primero muestra "Hasta 01/12/2026" y el segundo "en adelante"

#### Scenario: Orden al guardar
- **WHEN** el usuario carga primero un período desde el 02/12/2026 y después uno desde el 20/11/2026, y guarda
- **THEN** la grilla muestra primero el del 20/11/2026

#### Scenario: Período vigente marcado
- **GIVEN** hoy es el 25/11/2026 y hay períodos desde el 01/11/2026, el 20/11/2026 y el 02/12/2026
- **WHEN** el usuario abre la pestaña Venta
- **THEN** solo el período del 20/11/2026 aparece como "Vigente"

#### Scenario: Apagar conserva los montos
- **GIVEN** el precio fijo prendido con dos períodos
- **WHEN** el usuario apaga el interruptor, guarda y lo vuelve a prender
- **THEN** la grilla muestra los mismos dos períodos

#### Scenario: Borrar un período pasado
- **GIVEN** hoy es el 25/11/2026 y hay períodos desde el 01/11/2026 y el 20/11/2026
- **WHEN** el usuario borra el período del 01/11/2026, confirma y guarda
- **THEN** la grilla queda solo con el período del 20/11/2026
