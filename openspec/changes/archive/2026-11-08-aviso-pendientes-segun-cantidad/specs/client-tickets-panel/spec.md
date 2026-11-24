# Spec Delta

## MODIFIED Requirements

### Requirement: Aviso de boletos pendientes de fecha
"Mis boletos" SHALL mostrar, encima de las pestañas y en cualquiera de ellas, un aviso de los boletos sin fecha, solo si el cliente tiene alguno. Con uno solo, el aviso MUST nombrar su tramo y su número y ofrecer "Elegir fecha", que abre el formulario de ese boleto. Con dos o más, MUST decir cuántos son y ofrecer "Ver pendientes", que lleva a la pestaña "Pendientes" sin abrir ningún formulario, salvo que ya esté en ella. Sin boletos sin fecha no se muestra.

#### Scenario: Con boletos pendientes
- **GIVEN** un cliente con 2 boletos sin fecha
- **WHEN** abre "Mis boletos"
- **THEN** el aviso dice "Tenés 2 boletos sin fecha" y ofrece "Ver pendientes", no "Elegir fecha"

#### Scenario: Un solo pendiente
- **GIVEN** un cliente con el boleto N°39114 sin fecha, de Yrigoyen a Salta
- **WHEN** abre "Mis boletos"
- **THEN** el aviso lo dice en singular, con su tramo y su número, y ofrece "Elegir fecha"

#### Scenario: Botón del aviso
- **GIVEN** un cliente con un solo boleto sin fecha
- **WHEN** toca "Elegir fecha" en el aviso
- **THEN** se abre el formulario de "Elegir fecha" de ese boleto

#### Scenario: Ver pendientes
- **GIVEN** un cliente con 2 boletos sin fecha que está en la pestaña "Próximos"
- **WHEN** toca "Ver pendientes"
- **THEN** pasa a la pestaña "Pendientes" y no se abre ningún formulario

#### Scenario: Ya en Pendientes
- **GIVEN** un cliente con 2 boletos sin fecha que está en la pestaña "Pendientes"
- **WHEN** mira el aviso
- **THEN** ve la cantidad pero no el botón "Ver pendientes"

#### Scenario: Aviso en cualquier pestaña
- **GIVEN** un cliente con boletos sin fecha
- **WHEN** está en la pestaña "Pendientes", "Próximos" o "Historial"
- **THEN** ve el aviso en las tres

#### Scenario: Sin pendientes
- **GIVEN** un cliente sin boletos sin fecha
- **WHEN** abre "Mis boletos"
- **THEN** no ve el aviso
