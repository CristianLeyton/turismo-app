# Proposal

## Why

Los dos paneles comparten una sesión y Laravel guarda una sola URL "intended" en la sesión (`url.intended`). Cuando alguien toca la zona de administración sin sesión web (por ejemplo, abre la raíz del sitio, que redirige a `/admin`), esa excepción de login deja `url.intended = /admin`. Después, si esa misma persona ingresa correctamente en el panel de clientes, la respuesta del login de Filament usa `redirect()->intended(...)`, que prefiere la URL guardada: el cliente es enviado a `/admin`, donde no tiene sesión, y cae en `/admin/login`. Confirmado: el rebote ocurre al llegar al ingreso de clientes desde el login de administración (que es justo lo que el aviso "¿Sos cliente?" de `/admin/login` invita a hacer).

## What Changes

- Tras el ingreso de un cliente, la redirección solo considera la URL "intended" guardada si pertenece al panel de clientes (ruta bajo `/clientes`); en cualquier otro caso va a "Mis boletos".
- Lo mismo aplica al `mount()` del login de clientes cuando ya tiene sesión: no debe rebajar a una URL de administración por un `intended` ajeno.
- La administración no cambia: su login sigue usando el `intended` de Laravel tal como está (es su comportamiento esperado, incluido volver a la página protegida que disparó el pedido).

## Capabilities

### New Capabilities

- (ninguna)

### Modified Capabilities

- `client-access`: nuevo requisito sobre el destino tras el ingreso: los enlaces "intended" guardados en la sesión solo se respetan si apuntan a `clientes`, y el ingreso desde el login de administración no reenvía a la administración.

## Impact

- `app/Filament/Client/Pages/Auth/ClientLogin.php`: override de `getRedirectUrl()`/respuesta del login para filtrar el `intended`.
- Posible helper pequeño en `App\Support` (ya existe `ClientSession`) para el criterio de "es una URL del panel de clientes".
- Tests en `tests/Feature/` para el panel de clientes (login con y sin `url.intended` en la sesión).
- No se toca el panel de administración ni el login por defecto de Filament.
