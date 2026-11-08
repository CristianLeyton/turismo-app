# Spec Delta

## ADDED Requirements

### Requirement: Aviso de elegir fecha en el PDF de un boleto sin fecha
El PDF de un boleto sin fecha SHALL mostrar el aviso de que, al adquirir el pasaje, queda en responsabilidad del pasajero elegir fecha y horario con al menos las horas de anticipación configuradas en "Anticipación mínima para elegir fecha" (por ejemplo, "con al menos 48hs de anticipación"), y que la asignación se realiza solo si la unidad cuenta con asientos disponibles. Sin configurar, el aviso MUST decir 24hs.

#### Scenario: Valor por defecto
- **GIVEN** que nadie configuró la anticipación
- **WHEN** se genera el PDF de un boleto sin fecha
- **THEN** el aviso dice "con al menos 24hs de anticipación"

#### Scenario: Valor configurado
- **GIVEN** la anticipación configurada en 48
- **WHEN** se genera el PDF de un boleto sin fecha
- **THEN** el aviso dice "con al menos 48hs de anticipación"

#### Scenario: Boleto con fecha
- **WHEN** se genera el PDF de un boleto que ya tiene fecha
- **THEN** no aparece el aviso de elegir fecha

### Requirement: El aviso refleja el valor vigente
El número de horas del aviso MUST ser el mismo en todos los PDF del boleto y MUST reflejar el valor vigente al generarlo, sin importar si el panel de clientes está activo. Con 0 horas, el aviso MUST omitir la condición de anticipación y mantener la aclaración sobre los asientos disponibles. Las condiciones de cambios y devoluciones del PDF MUST NOT cambiar con este valor.

#### Scenario: Cambio de configuración
- **GIVEN** un boleto sin fecha cuyo PDF decía 24hs
- **WHEN** la empresa cambia la anticipación a 72 y se vuelve a generar el PDF
- **THEN** el aviso dice 72hs

#### Scenario: Sin anticipación
- **GIVEN** la anticipación configurada en 0
- **WHEN** se genera el PDF de un boleto sin fecha
- **THEN** el aviso no menciona una anticipación mínima y conserva la aclaración sobre los asientos disponibles

#### Scenario: Panel de clientes apagado
- **GIVEN** el panel de clientes apagado y la anticipación guardada en 48
- **WHEN** se genera el PDF de un boleto sin fecha
- **THEN** el aviso dice 48hs

#### Scenario: Política de cambios y devoluciones
- **WHEN** se genera cualquier PDF de boleto con la anticipación configurada en un valor distinto de 24
- **THEN** las condiciones de cambios y devoluciones del PDF no cambian
