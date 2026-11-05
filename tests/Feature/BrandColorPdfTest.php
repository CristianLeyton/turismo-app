<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Support\BrandPalette;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Los PDF de ventas y de pagos toman los acentos de marca de la paleta del
 * color de la empresa (en hex, para dompdf), no del fucsia anterior.
 *
 * El boleto y el detalle de viaje se cubren en TicketContactLinesPdfTest y
 * TripExportTest, que ya arman esos datos.
 */
class BrandColorPdfTest extends TestCase
{
    use RefreshDatabase;

    /** Hex de la paleta fucsia que usaban los PDF antes del cambio. */
    public const OLD_BRAND_HEXES = ['#c026d3', '#d946ef', '#a21caf', '#86198f', '#f5d0fe', '#fdf4ff', '#faf5ff', '#f3e8ff'];

    protected function setUp(): void
    {
        parent::setUp();

        Setting::flushCache();
        Setting::set(Setting::PRIMARY_COLOR, '#0e7490');
    }

    protected function tearDown(): void
    {
        Setting::flushCache();

        parent::tearDown();
    }

    public function test_el_pdf_de_ventas_usa_el_color_de_la_empresa(): void
    {
        $html = view('pdf.sales-tickets', [
            'records' => collect(),
            'user' => User::factory()->create(['name' => 'Ana', 'surname' => 'Pérez']),
            'filters' => ['from' => null, 'to' => null, 'payment' => 'all', 'type' => 'all'],
            'totals' => [
                'count' => 0, 'tickets_count' => 0, 'payments_count' => 0, 'cash' => 0, 'transfer' => 0,
                'ventas_total' => 0, 'payments_cash' => 0, 'payments_transfer' => 0, 'payments_total' => 0,
                'saldo' => 0, 'method_breakdown' => [],
            ],
        ])->render();

        self::assertUsesBrandPalette($this, $html);
    }

    public function test_el_pdf_de_pagos_usa_el_color_de_la_empresa(): void
    {
        $html = view('pdf.payments', [
            'payments' => collect(),
            'filters' => ['from' => null, 'to' => null],
            'totals' => ['count' => 0, 'total' => 0],
        ])->render();

        self::assertUsesBrandPalette($this, $html);
    }

    /**
     * El HTML usa algún escalón de la paleta del color configurado (#0e7490)
     * y ningún hex fucsia.
     */
    public static function assertUsesBrandPalette(TestCase $test, string $html): void
    {
        $usedShades = array_filter(
            BrandPalette::fromHexAsHex('#0e7490'),
            fn (string $hex): bool => str_contains($html, $hex),
        );

        $test->assertNotEmpty($usedShades, 'El PDF no usa ningún color de la paleta de la empresa.');

        foreach (self::OLD_BRAND_HEXES as $oldHex) {
            $test->assertStringNotContainsStringIgnoringCase($oldHex, $html);
        }
    }
}
