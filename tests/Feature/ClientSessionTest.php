<?php

namespace Tests\Feature;

use App\Support\ClientSession;
use Filament\Facades\Filament;
use Tests\TestCase;

/**
 * Pertinencia de URLs al panel de clientes: decide si una URL prevista
 * guardada en la sesión (change fix-client-login-redirect) puede ser el
 * destino de un ingreso al panel.
 */
class ClientSessionTest extends TestCase
{
    public function test_urls_del_panel_de_clientes(): void
    {
        Filament::setCurrentPanel('clientes');

        $proprias = [
            '/clientes',
            '/clientes/',
            '/clientes/boletos/5',
            'http://localhost/clientes',
            'http://localhost/clientes/boletos/5?accion=ver',
            'clientes/perfil', // relativa con y sin barra inicial se normaliza
        ];

        foreach ($proprias as $url) {
            $this->assertTrue(
                ClientSession::isPanelUrl($url),
                "Deberían ser del panel: {$url}",
            );
        }
    }

    public function test_urls_ajenas_al_panel_de_clientes(): void
    {
        $ajenas = [
            '/',
            '',
            '/admin',
            'http://localhost/admin/tickets',
            '/boletos/t_um-boleto-compartido',
            '/cliente/que-no-es-el-panel',
        ];

        foreach ($ajenas as $url) {
            $this->assertFalse(
                ClientSession::isPanelUrl($url),
                "No debería ser del panel: {$url}",
            );
        }
    }
}
