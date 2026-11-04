# Spec Delta

## MODIFIED Requirements

### Requirement: Configuración de días de servicio
Un usuario con acceso a Configuración > Parámetros SHALL poder elegir qué días de la semana tienen servicio y administrar las excepciones por fecha (fecha, tipo "con servicio" o "sin servicio" y motivo opcional). Al menos un día de la semana MUST tener servicio. Cada fecha MUST tener como máximo una excepción y MUST NOT ser anterior a hoy. Al guardar, las excepciones de fechas ya pasadas se eliminan. Los cambios se aplican desde que se guardan.

#### Scenario: Agregar un feriado
- **WHEN** el administrador agrega la excepción 25/12/2026 "sin servicio" con motivo "Navidad" y guarda
- **THEN** el 25/12/2026 deja de tener servicio

#### Scenario: Fecha repetida
- **WHEN** el administrador intenta guardar dos excepciones para la misma fecha
- **THEN** el guardado se rechaza con un error de validación y la configuración no cambia

#### Scenario: Ningún día de la semana
- **WHEN** el administrador destilda todos los días de la semana y guarda
- **THEN** el campo muestra "Elegí al menos un día con servicio." y la configuración no cambia

#### Scenario: Excepción nueva con fecha pasada
- **WHEN** hoy es 04/11/2026 y el administrador agrega una excepción para el 03/11/2026 y guarda
- **THEN** el campo de la fecha muestra un error que indica que no puede ser anterior a hoy y la configuración no cambia

#### Scenario: Excepción para hoy
- **WHEN** hoy es 04/11/2026 y el administrador agrega una excepción "sin servicio" para el 04/11/2026 y guarda
- **THEN** la excepción se guarda y hoy deja de tener servicio

#### Scenario: Excepción vencida ya guardada
- **GIVEN** hay guardada una excepción para el 13/09/2026 y hoy es 04/11/2026
- **WHEN** el administrador cambia cualquier parámetro y guarda
- **THEN** el guardado no falla por esa excepción, la excepción se elimina y la notificación de guardado informa que se quitó una excepción de una fecha que ya pasó
