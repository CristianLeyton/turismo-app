# Spec Delta

## MODIFIED Requirements

### Requirement: Fechas de viaje seleccionables
Los selectores de fecha de ida y de vuelta MUST deshabilitar las fechas sin servicio según la configuración de días de servicio, salvo para un usuario con el permiso para operar en días sin servicio. Con ese permiso, esas fechas se pueden elegir y se avisa que no tienen servicio. La fecha mínima de ida es hoy, o un año atrás si al usuario no se le aplica el corte de venta. La fecha mínima de vuelta es la fecha de ida.

#### Scenario: Domingo
- **WHEN** con la configuración inicial, un vendedor sin el permiso abre el calendario de ida
- **THEN** los domingos aparecen deshabilitados, excepto el 13/09/2026

#### Scenario: Feriado configurado
- **WHEN** el 25/12/2026 está configurado "sin servicio" y un vendedor sin el permiso abre el calendario de vuelta
- **THEN** el 25/12/2026 aparece deshabilitado

#### Scenario: Avanzar el paso 1 con fecha sin servicio
- **WHEN** un vendedor sin el permiso intenta avanzar del paso 1 con una fecha de ida o de vuelta sin servicio
- **THEN** no puede avanzar y se le informa que la fecha es un día sin servicio
