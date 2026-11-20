# Design

## Context

Ver proposal.md (por qué). Puntos del código que condicionan el diseño:

- `ClientLogin` extiende `Filament\Auth\Pages\Login`. Tras autenticar, el padre resuelve `app(LoginResponse::class)`, y la clase por defecto hace `redirect()->intended(Filament::getUrl())`.
- `Filament::getUrl()` es la URL del panel actual; el problema no es el panel, sino que `intended()` prefiere la URL de la sesión (`url.intended`), que el handler de excepciones de Laravel deja apuntando a `/admin` cuando alguien pasa por la zona admin sin sesión web.
- Los dos paneles comparten la misma sesión (así lo exige el diseño de sesiones separadas por guard: el logout custom de `ClientPanelProvider` existe justamente para no invalidar la sesión admin). Filament v4 no tiene hook por panel para la respuesta del login, y el `gate` de `canAccessPanel` no entra en juego aquí.

## Goals / Non-Goals

**Goals:**
- Filtrar el `intended` solo para el panel de clientes: URLs de `/clientes` se respetan, el resto se descarta y vuelve "Mis boletos".
- Reproducir lo mismo en `mount()` de `ClientLogin` (cliente ya autenticado que reaparece en el login).
- Dejar intacto el comportamiento del login de administración.

**Non-Goals:**
- No cambiamos el redirect de `/` a `/admin` ni el aviso "¿Sos cliente?" de `/admin/login` (la exploración lo discutió; el alcance acordado es el filtro del intended).
- No movemos los paneles a sesiones `guard`-específicas ni cambiamos el storage de sesión.

## Decisions

### 1. Subclase de `LoginResponse` en lugar de duplicar `authenticate()`
- **Decisión**: crear `App\Filament\Client\Auth\ClientLoginResponse extends LoginResponse` que en `toResponse()` decide: si el `url.intended` de la sesión es una ruta del panel de clientes, redirigir ahí; si no, a `Filament::getUrl()`. Para cualquier otro panel, delega en `parent::toResponse()`.
- **Registro**: en `ClientPanelProvider::register()` (o `AppServiceProvider` si ya existe el patrón), `$this->app->bind(LoginResponse::class, ClientLoginResponse::class)`.
- **Por qué**: `authenticate()` del padre acumula rate limiting, MFA, `attemptWhen`, regeneración de sesión y eventos; sobreescribirlo duplicaría toda esa lógica y es frágil ante upgrades. La respuesta del login es precisamente el punto donde se decide el destino.
- **Alternativa descartada**: un middleware global que limpie `url.intended` al mostrar el login de clientes (opción A de la exploración). Es simple pero falla para el caso "URL prevista legítima del panel de clientes" (por ejemplo, fue enviado a `/clientes` sin sesión, loguearse y volver ahí), y deja el mismo hueco vivo para el `mount()`.

### 2. Criterio de pertenencia: prefijo de la ruta del panel
- **Decisión**: comparar el path de la URL guardada (absoluta o relativa) contra el prefijo del panel clientes, tomando el path de `Filament::getPanel('clientes')`, no un string repetido a mano.
- **Por qué**: el handler de Laravel guarda URL absoluta con query (`generator->full()`); una comparación por prefijo de path ignora query y dominio sin inventar parseo. Con query legítima se respeta (largo `url.intended`, no es un caso que nos interese descartar).
- **Helper**: un método estático pequeño en `App\Support\ClientSession` (`isPanelUrl(string $url): bool`), junto al resto de helpers de sesión de clientes ya ahí.

### 3. `mount()` de ClientLogin con el mismo filtro
- **Decisión**: sobreescribir `mount()` en `ClientLogin`: si el guard cliente ya tiene sesión, aplicar el mismo filtro (intended si es del panel, si no `Filament::getUrl()`), y después `form->fill()` como el padre.
- **Por qué**: son tres líneas, el costo de duplicar es mínimo y el gancho del padre (`redirect()->intended(...)`) repite la trampa para el cliente ya autenticado.

### 4. Tests
- Feature tests con `Livewire::test(ClientLogin::class)`:
  - sesión con `url.intended` = `/admin` → `authenticate` → `assertRedirect` a `/clientes` (la home del panel).
  - sesión con `url.intended` de una página propia del panel → sigue el intended.
  - `url.intended` = `/` y para el link compartible `/boletos/...` → a "Mis boletos".
  - cliente autenticado que entra de nuevo al login con intended ajeno → a "Mis boletos".
  - el login de administración no cambia: un admin con `url.intended` de una página protegida sigue llegando ahí (cubre el `parent::toResponse()`).

## Risks / Trade-offs

- [El binding del `LoginResponse` es global] → La subclase delega en el padre para el resto de los paneles, así que admin queda byte a byte igual; un test lo fija.
- [Prefijo de path como criterio] → Si la URL prevista fuera `clientes/foo` desde otro dominio malicioso, el prefijo lo aceptaría; irrelevante en la práctica (el cliente se está auto-redirigiendo en su propia sesión del mismo hosting). Si algún día el panel cambia de path, la decisión toma el valor del panel configurado, no un literal.
