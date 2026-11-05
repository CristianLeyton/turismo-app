<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Support\BrandPalette;
use Filament\Support\Colors\Color;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * El color principal de la empresa (Parámetros > Apariencia) llega a los
 * dos paneles como paleta `--primary-*` y como `--primary-text`.
 */
class BrandColorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Setting::flushCache();
        Setting::set(Setting::PANEL_CLIENTES_ACTIVO, '1');
    }

    protected function tearDown(): void
    {
        Setting::flushCache();

        parent::tearDown();
    }

    public function test_sin_configurar_los_paneles_usan_el_fucsia_por_defecto(): void
    {
        $palette = BrandPalette::fromHex(Setting::PRIMARY_COLOR_DEFAULT);

        foreach (['/admin/login', '/clientes/login'] as $url) {
            $this->get($url)
                ->assertOk()
                ->assertSee("--primary-600:{$palette[600]}", false)
                ->assertSee('--primary-text: #ffffff;', false);
        }
    }

    public function test_con_un_color_configurado_los_paneles_usan_su_paleta(): void
    {
        Setting::set(Setting::PRIMARY_COLOR, '#10288c');

        $palette = BrandPalette::fromHex('#10288c');

        foreach (['/admin/login', '/clientes/login'] as $url) {
            $this->get($url)
                ->assertOk()
                ->assertSee("--primary-600:{$palette[600]}", false)
                ->assertSee("--primary-50:{$palette[50]}", false)
                ->assertDontSee('--primary-600:'.Color::Fuchsia[600], false)
                ->assertSee('--primary-text: #ffffff;', false);
        }
    }

    public function test_con_un_color_claro_el_texto_sobre_el_color_es_oscuro(): void
    {
        Setting::set(Setting::PRIMARY_COLOR, '#fde047');

        $this->get('/admin/login')
            ->assertOk()
            ->assertSee('--primary-text: '.BrandPalette::DARK_TEXT.';', false);
    }
}
