# Spec Delta

## Purpose

Define quién ve los boletos sin fecha en el panel del personal y quién ve el switch para filtrarlos, según los permisos "Vender sin fecha/horario" y "Reprogramar" de Boletos.

## ADDED Requirements

### Requirement: Boletos sin fecha visibles para todo el personal
Los boletos sin fecha SHALL verse en el listado de Boletos, en la búsqueda por N° y en "Ver boleto" para cualquier usuario con acceso a Boletos, tenga o no "Vender sin fecha/horario" o "Reprogramar".

#### Scenario: Listado sin los permisos
- **GIVEN** un boleto con fecha y un boleto sin fecha
- **WHEN** un usuario sin "Vender sin fecha/horario" ni "Reprogramar" abre el listado de Boletos
- **THEN** ve los dos boletos

#### Scenario: Búsqueda por N°
- **GIVEN** el boleto N°500 sin fecha
- **WHEN** un usuario sin ninguno de los dos permisos filtra el listado por el N°500
- **THEN** el listado muestra ese boleto

#### Scenario: Detalle del boleto
- **GIVEN** el boleto N°500 sin fecha
- **WHEN** un usuario sin ninguno de los dos permisos abre "Ver boleto"
- **THEN** la pantalla se abre

### Requirement: Asignar fecha exige "Reprogramar"
Asignarle fecha a un boleto sin fecha SHALL exigir el permiso "Reprogramar". Sin ese permiso, la pantalla "Reprogramar boleto" MUST rechazar el acceso, aunque el usuario tenga "Vender sin fecha/horario".

#### Scenario: Solo con "Vender sin fecha/horario"
- **GIVEN** el boleto N°500 sin fecha
- **WHEN** un usuario con "Vender sin fecha/horario" y sin "Reprogramar" abre "Reprogramar boleto"
- **THEN** se le rechaza el acceso

### Requirement: Switch "Ver únicamente boletos sin fecha"
El switch "Ver únicamente boletos sin fecha" del listado SHALL mostrarse solo a quien tiene "Vender sin fecha/horario" y "Reprogramar" a la vez, y solo si algún rol que no sea SUPER tiene asignado "Vender sin fecha/horario". Si no se cumple alguna de estas condiciones, el switch MUST NOT mostrarse.

#### Scenario: Con los dos permisos y la función activada
- **GIVEN** algún rol que no es SUPER tiene "Vender sin fecha/horario"
- **WHEN** un usuario con "Vender sin fecha/horario" y "Reprogramar" abre el listado y prende el switch
- **THEN** ve solo los boletos sin fecha

#### Scenario: Sin ninguno de los dos permisos
- **WHEN** un usuario sin "Vender sin fecha/horario" ni "Reprogramar" abre el listado
- **THEN** no ve el switch

#### Scenario: Con un solo permiso
- **WHEN** un usuario con solo uno de los dos permisos abre el listado
- **THEN** no ve el switch

#### Scenario: Función desactivada
- **GIVEN** ningún rol que no sea SUPER tiene "Vender sin fecha/horario"
- **WHEN** el SUPER abre el listado
- **THEN** no ve el switch

### Requirement: Filtro guardado sin el switch
Si el switch quedó prendido en la sesión de un usuario que ya no cumple las condiciones para verlo, el listado MUST NOT aplicarlo.

#### Scenario: Usuario que pierde el permiso
- **GIVEN** un usuario con el switch prendido en su sesión
- **WHEN** pierde el permiso "Reprogramar" y vuelve a abrir el listado
- **THEN** no ve el switch y el listado muestra los boletos con fecha y sin fecha
