<?php

namespace App\Http\Controllers;

use App\Support\ClientSession;
use Illuminate\Http\RedirectResponse;

/**
 * "Salir" del menú de usuario del panel de clientes.
 *
 * Reemplaza al logout de Filament, que invalida toda la sesión de Laravel y
 * cerraría también la del panel de administración del mismo navegador.
 */
class ClientLogoutController extends Controller
{
    public function __invoke(): RedirectResponse
    {
        ClientSession::logout();

        return redirect()->to(filament()->getPanel('clientes')->getLoginUrl());
    }
}
