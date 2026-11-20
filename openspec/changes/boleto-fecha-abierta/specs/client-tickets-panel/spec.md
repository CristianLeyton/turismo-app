## ADDED Requirements

### Requirement: Boleto con fecha abierta por el personal
Un boleto que el personal dejó con fecha abierta SHALL aparecer en "Mis boletos" como cualquier boleto sin fecha: en "Pendientes", en su conteo y en el aviso de boletos sin fecha, y MUST dejar de aparecer en "Próximos", en "Historial" y en la tarjeta "Próximo viaje". El cliente MUST poder volver a usar "Elegir fecha" en ese boleto, con las mismas reglas que para un boleto vendido sin fecha. Una vez elegida, el boleto vuelve a quedar bloqueado para el cliente.

#### Scenario: Vuelve a Pendientes
- **GIVEN** un cliente con el boleto N°500 con fecha futura
- **WHEN** el personal lo deja con fecha abierta y el cliente abre "Mis boletos"
- **THEN** el boleto aparece en "Pendientes" con "Elegir fecha", y no en "Próximos"

#### Scenario: Deja de ser el próximo viaje
- **GIVEN** un cliente cuyo próximo viaje era el del boleto N°500
- **WHEN** el personal deja ese boleto con fecha abierta
- **THEN** la tarjeta "Próximo viaje" muestra el siguiente boleto con fecha, o no se muestra si no hay otro

#### Scenario: Elegir la fecha nueva
- **GIVEN** un boleto que el personal dejó con fecha abierta
- **WHEN** el cliente elige fecha, horario y asiento respetando la anticipación y los días de servicio
- **THEN** el boleto pasa a "Próximos" y el cliente ya no puede cambiar su fecha

#### Scenario: Vuelta abierta con la ida fechada
- **GIVEN** un ida y vuelta con la ida fechada para el 25/11 y la vuelta dejada con fecha abierta
- **WHEN** el cliente elige para la vuelta una fecha anterior a la llegada de la ida
- **THEN** se rechaza indicando que la vuelta debe ser posterior a la ida, y el formulario queda abierto con lo que eligió
