<?php

namespace Tests\Unit;

use App\Support\BrandPalette;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class BrandPaletteTest extends TestCase
{
    /**
     * @return array<string, array{string}>
     */
    public static function colores(): array
    {
        return [
            'azul marino' => ['#10288c'],
            'casi negro' => ['#0a0a2a'],
            'amarillo claro' => ['#fde047'],
            'gris' => ['#808080'],
            'fucsia por defecto' => ['#c026d3'],
            'turquesa' => ['#0e7490'],
        ];
    }

    #[DataProvider('colores')]
    public function test_la_paleta_tiene_los_once_escalones(string $hex): void
    {
        $this->assertSame(BrandPalette::SHADES, array_keys(BrandPalette::fromHex($hex)));
        $this->assertSame(BrandPalette::SHADES, array_keys(BrandPalette::fromHexAsHex($hex)));
    }

    #[DataProvider('colores')]
    public function test_el_600_es_el_color_exacto(string $hex): void
    {
        $this->assertSame($hex, BrandPalette::fromHexAsHex($hex)[600]);
        $this->assertSame($hex, BrandPalette::fromHexAsHex(strtoupper($hex))[600]);
    }

    #[DataProvider('colores')]
    public function test_los_escalones_van_del_mas_claro_al_mas_oscuro(string $hex): void
    {
        $lightness = array_map(
            fn (string $oklch): float => sscanf($oklch, 'oklch(%f')[0],
            array_values(BrandPalette::fromHex($hex)),
        );

        for ($index = 1; $index < count($lightness); $index++) {
            $this->assertGreaterThan(
                $lightness[$index],
                $lightness[$index - 1],
                "El escalón {$index} no es más oscuro que el anterior para {$hex}.",
            );
        }

        $hexShades = BrandPalette::fromHexAsHex($hex);
        $this->assertCount(11, array_unique($hexShades), "Hay escalones repetidos para {$hex}.");
    }

    public function test_un_gris_genera_una_paleta_gris(): void
    {
        foreach (BrandPalette::fromHex('#808080') as $shade => $oklch) {
            if ($shade === 600) {
                continue;
            }

            $this->assertSame(0.0, (float) sscanf($oklch, 'oklch(%f %f')[1], "El {$shade} tiene croma.");
        }
    }

    public function test_un_color_muy_oscuro_tiene_el_50_casi_blanco(): void
    {
        $lightness = sscanf(BrandPalette::fromHex('#0a0a2a')[50], 'oklch(%f')[0];

        $this->assertGreaterThanOrEqual(0.97, $lightness);
    }

    public function test_el_texto_es_blanco_sobre_un_color_oscuro_y_oscuro_sobre_uno_claro(): void
    {
        $this->assertSame('#ffffff', BrandPalette::textColorOn('#10288c'));
        $this->assertSame('#ffffff', BrandPalette::textColorOn('#c026d3'));
        $this->assertSame(BrandPalette::DARK_TEXT, BrandPalette::textColorOn('#fde047'));
    }
}
