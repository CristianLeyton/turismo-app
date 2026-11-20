# payment-method-colors Specification

## Purpose
Define el color con el que se muestra cada método de pago en el panel: una paleta acorde a los badges existentes, asignación automática al crear un método, edición desde "Métodos de pago" y el mismo color en todas las pantallas.

## Requirements

### Requirement: Paleta de colores de los métodos de pago
El color de un método de pago SHALL elegirse de una paleta fija con el estilo de los badges del panel (fondo suave y texto del mismo tono, legible en modo claro y oscuro). La paleta MUST NOT incluir rojo, gris ni el color de la empresa.

#### Scenario: Opciones disponibles
- **WHEN** un usuario elige el color de un método de pago
- **THEN** solo puede elegir colores de la paleta
- **AND** ninguno es rojo, gris ni el color de la empresa

#### Scenario: Color desconocido
- **GIVEN** un método de pago con un color guardado que no pertenece a la paleta
- **WHEN** se muestra su badge
- **THEN** se muestra en gris neutro sin error

### Requirement: Asignación automática al crear
Al crear un método de pago sin elegir color, el sistema SHALL asignarle el primer color de la paleta que no use ningún otro método no eliminado. Si todos están en uso, MUST asignarle el color de la paleta menos repetido entre los métodos no eliminados.

#### Scenario: Primer color libre
- **GIVEN** métodos existentes con verde y celeste
- **WHEN** se crea el método "Posnet" sin elegir color
- **THEN** "Posnet" recibe el primer color de la paleta distinto de verde y celeste

#### Scenario: Paleta completa
- **GIVEN** un método por cada color de la paleta y un segundo método en verde
- **WHEN** se crea un método nuevo sin elegir color
- **THEN** recibe uno de los colores usados una sola vez, no el verde

#### Scenario: Color de un método eliminado
- **GIVEN** un método eliminado que tenía el color ámbar y ningún otro método con ámbar
- **WHEN** se crea un método nuevo y ámbar es el primero libre de la paleta
- **THEN** el método nuevo puede recibir ámbar

### Requirement: Edición del color desde "Métodos de pago"
El formulario de crear y editar métodos de pago SHALL ofrecer un campo "Color" con la paleta, mostrando cada opción como el badge resultante. Al crear, el campo MUST venir con el color que se asignaría automáticamente. El listado SHALL mostrar el nombre de cada método como un badge de su color.

#### Scenario: Crear con el color sugerido
- **WHEN** un usuario abre "Crear método de pago"
- **THEN** el campo "Color" viene con el color que se asignaría automáticamente

#### Scenario: Cambiar el color
- **GIVEN** el método "Posnet" con color ámbar
- **WHEN** un usuario lo edita y elige índigo
- **THEN** "Posnet" se muestra en índigo en todas las pantallas

#### Scenario: Color inválido
- **WHEN** se intenta guardar un método con un color fuera de la paleta
- **THEN** no se guarda y se muestra un mensaje pidiendo elegir un color de la lista

#### Scenario: Listado con badges
- **WHEN** un usuario abre "Métodos de pago"
- **THEN** cada método se ve como un badge con su color

### Requirement: Mismo color en todas las pantallas
Toda pantalla del panel que muestre el método de pago con color SHALL usar el color guardado del método: tabla de Pagos, detalle de ventas y "Mis ventas" (filas y desglose), detalle del boleto y resumen de la venta.

#### Scenario: Método personalizado en el detalle de ventas
- **GIVEN** el método "Posnet" con color ámbar y una venta pagada con Posnet
- **WHEN** se abre el detalle de ventas del vendedor
- **THEN** el badge de Posnet se muestra en ámbar

#### Scenario: Método personalizado en el resumen de la venta
- **GIVEN** un boleto pagado con un método personalizado de color índigo
- **WHEN** se muestra el resumen de la venta
- **THEN** el método se muestra en índigo, no en gris

### Requirement: Colores para los métodos existentes
Al actualizar el sistema, los métodos de pago existentes que tienen el color gris por defecto SHALL recibir un color de la paleta con el mismo criterio de asignación automática: primero los métodos vigentes y después los eliminados, cada grupo en orden de creación. Los métodos que ya tienen un color de la paleta MUST conservarlo.

#### Scenario: Métodos en gris
- **GIVEN** Efectivo (verde), Transferencia (celeste) y tres métodos personalizados en gris
- **WHEN** se corre la actualización
- **THEN** los tres métodos personalizados reciben tres colores distintos de la paleta
- **AND** Efectivo y Transferencia conservan su color
