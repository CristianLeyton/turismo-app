<?php

namespace App\Filament\Client\Auth;

use App\Support\ClientSession;
use Filament\Auth\Http\Responses\LoginResponse as BaseLoginResponse;
use Filament\Facades\Filament;
use Illuminate\Http\RedirectResponse;
use Livewire\Features\SupportRedirects\Redirector;

/**
 * Respuesta del ingreso del panel de clientes.
 *
 * La respuesta por defecto de Filament hace `redirect()->intended(...)`,
 * que prefiere la URL prevista de la sesión; como esa sesión es compartida
 * con el panel de administración, alguien que tocó la zona admin sin sesión
 * (por ejemplo, entrando por la raíz del sitio) era devuelto al login de
 * admin después de ingresar bien por aquí. Acá la URL prevista sólo se
 * respeta si es una página de este panel; si no, a "Mis boletos".
 *
 * Los demás paneles (admin) quedan igual: delega en el padre.
 */
class ClientLoginResponse extends BaseLoginResponse
{
    public function toResponse($request): RedirectResponse|Redirector
    {
        if (Filament::getCurrentOrDefaultPanel()->getId() !== ClientSession::PANEL_ID) {
            return parent::toResponse($request);
        }

        return redirect()->to(ClientSession::intendedLoginUrl(Filament::getUrl()));
    }
}
