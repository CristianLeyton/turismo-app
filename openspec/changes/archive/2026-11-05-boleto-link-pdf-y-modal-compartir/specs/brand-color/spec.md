# Spec Delta

## MODIFIED Requirements

### Requirement: El color principal se aplica en todo el sitio
El color principal SHALL aplicarse, sin recompilar estilos, en los dos paneles, en el modal de entrega de boletos y en los PDFs del sistema (boleto, ventas, viaje y pagos), incluido el PDF que muestra el link público del boleto. Todo elemento que hoy usa el color de marca (fucsia) MUST tomar el escalón correspondiente de la paleta.

#### Scenario: Cambio inmediato en los paneles
- **WHEN** un usuario con permiso cambia el color principal a `#0e7490` y guarda
- **THEN** al recargar, el panel de administración y el panel de clientes usan la nueva paleta, sin compilar estilos

#### Scenario: Página pública del boleto
- **GIVEN** el color principal guardado es `#0e7490`
- **WHEN** un pasajero abre el link de su boleto
- **THEN** ve el PDF del boleto con los acentos de marca en la nueva paleta y no en fucsia

#### Scenario: PDF del boleto
- **GIVEN** el color principal guardado es `#0e7490`
- **WHEN** se genera el PDF de un boleto
- **THEN** los acentos de marca del PDF usan colores de la nueva paleta y el PDF no contiene los colores fucsia anteriores

#### Scenario: Asiento seleccionado
- **GIVEN** el color principal guardado es `#0e7490`
- **WHEN** el vendedor selecciona un asiento al vender
- **THEN** el asiento y su referencia "Seleccionado" se ven en un tono más oscuro de la paleta de la empresa, distinto del color principal

#### Scenario: Modo oscuro
- **WHEN** un usuario con el modo oscuro activo abre el panel después de cambiar el color principal
- **THEN** los acentos usan la nueva paleta en sus escalones de modo oscuro
