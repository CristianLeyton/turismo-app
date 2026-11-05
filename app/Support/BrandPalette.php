<?php

namespace App\Support;

use App\Models\Setting;
use Filament\Support\Colors\Color;

/**
 * Paleta `primary` (50 … 950) generada a partir del color principal de la
 * empresa (Parámetros > Apariencia).
 *
 * No usamos Color::generatePalette() de Filament porque conserva solo el
 * tono: la luminosidad y la saturación de cada escalón son fijas, y un color
 * de marca oscuro o apagado termina convertido en otro color. Acá el color
 * elegido queda EXACTO en el 600 (el de botones y links en modo claro) y el
 * resto se interpola: los escalones claros hacia casi blanco y los oscuros
 * hacia casi negro, con la misma curva que las paletas de Tailwind.
 */
class BrandPalette
{
    /** Escalones de la paleta, del más claro al más oscuro. */
    public const SHADES = [50, 100, 200, 300, 400, 500, 600, 700, 800, 900, 950];

    /** Texto oscuro que se usa sobre un color principal claro (gray-900). */
    public const DARK_TEXT = '#111827';

    /**
     * Luminosidad y croma de una paleta de referencia de Tailwind: dan la
     * forma de la curva (qué tan lejos del 600 está cada escalón y cuánto
     * se desatura).
     *
     * @var array<int, array{float, float}>
     */
    private const REFERENCE = [
        50 => [0.977, 0.014],
        100 => [0.950, 0.033],
        200 => [0.905, 0.063],
        300 => [0.840, 0.106],
        400 => [0.754, 0.150],
        500 => [0.683, 0.169],
        600 => [0.598, 0.169],
        700 => [0.515, 0.149],
        800 => [0.446, 0.123],
        900 => [0.395, 0.100],
        950 => [0.278, 0.071],
    ];

    /** Por debajo de este croma el color se trata como gris (igual que Filament). */
    private const ACHROMATIC_CHROMA = 0.03;

    /** @var array<string, array<int, string>> */
    private static array $memo = [];

    /**
     * Paleta en oklch, lista para Filament y las variables CSS `--primary-*`.
     *
     * @return array<int, string>
     */
    public static function fromHex(string $hex): array
    {
        $hex = strtolower($hex);

        return self::$memo['oklch'.$hex] ??= self::generate($hex);
    }

    /**
     * La misma paleta en hex, para dompdf (no entiende oklch ni var()).
     * El 600 es el hex original, sin pasar por la conversión.
     *
     * @return array<int, string>
     */
    public static function fromHexAsHex(string $hex): array
    {
        $hex = strtolower($hex);

        return self::$memo['hex'.$hex] ??= array_replace(
            array_map(fn (string $oklch): string => self::oklchToHex($oklch), self::fromHex($hex)),
            [600 => $hex],
        );
    }

    /**
     * Paleta del color principal guardado en Parámetros.
     *
     * @return array<int, string>
     */
    public static function current(): array
    {
        return self::fromHex(Setting::primaryColor());
    }

    /**
     * Paleta del color principal guardado, en hex (PDFs).
     *
     * @return array<int, string>
     */
    public static function currentAsHex(): array
    {
        return self::fromHexAsHex(Setting::primaryColor());
    }

    /**
     * Color de texto para poner sobre un fondo del color dado: blanco u
     * oscuro, el que tenga más contraste. Así un color principal claro
     * (un amarillo) nunca deja texto blanco ilegible.
     */
    public static function textColorOn(string $hex): string
    {
        return Color::calculateContrastRatio($hex, '#ffffff') >= Color::calculateContrastRatio($hex, self::DARK_TEXT)
            ? '#ffffff'
            : self::DARK_TEXT;
    }

    /**
     * Color de texto sobre el color principal guardado.
     */
    public static function currentTextColor(): string
    {
        return self::textColorOn(Setting::primaryColor());
    }

    /**
     * Variables CSS de la paleta (`--primary-50: …;`) y del texto sobre el
     * color (`--primary-text`), para imprimir dentro de un `:root { }`.
     */
    public static function cssVariables(): string
    {
        $declarations = [];

        foreach (self::current() as $shade => $color) {
            $declarations[] = "--primary-{$shade}: {$color};";
        }

        $declarations[] = '--primary-text: '.self::currentTextColor().';';

        return implode(' ', $declarations);
    }

    /**
     * `<style>` con solo `--primary-text`, para los paneles: la paleta
     * `--primary-*` ya la imprime Filament.
     */
    public static function textColorStyleTag(): string
    {
        return '<style>:root { --primary-text: '.self::currentTextColor().'; }</style>';
    }

    /**
     * @return array<int, string>
     */
    private static function generate(string $hex): array
    {
        [$baseLightness, $baseChroma, $hue] = sscanf(Color::convertToOklch($hex), 'oklch(%f %f %f)');

        $isAchromatic = $baseChroma < self::ACHROMATIC_CHROMA;

        [$referenceLightness, $referenceChroma] = self::REFERENCE[600];

        // Extremos de la paleta: casi blanco y casi negro, corridos si el
        // color es tan claro u oscuro que no dejaría lugar para ordenar los
        // escalones.
        $lightest = min(1.0, max(self::REFERENCE[50][0], $baseLightness + 0.06));
        $darkest = min(self::REFERENCE[950][0], $baseLightness * 0.6);

        $palette = [];

        foreach (self::REFERENCE as $shade => [$lightness, $chroma]) {
            if ($shade === 600) {
                $palette[600] = Color::convertToOklch($hex);

                continue;
            }

            if ($shade < 600) {
                $progress = ($lightness - $referenceLightness) / (self::REFERENCE[50][0] - $referenceLightness);
                $shadeLightness = $baseLightness + ($lightest - $baseLightness) * $progress;
            } else {
                $progress = ($referenceLightness - $lightness) / ($referenceLightness - self::REFERENCE[950][0]);
                $shadeLightness = $baseLightness - ($baseLightness - $darkest) * $progress;
            }

            $shadeChroma = $isAchromatic ? 0 : $baseChroma * min(1.0, $chroma / $referenceChroma);

            $palette[$shade] = 'oklch('.round($shadeLightness, 3).' '.round($shadeChroma, 3).' '.round($hue, 3).')';
        }

        return $palette;
    }

    private static function oklchToHex(string $oklch): string
    {
        [$red, $green, $blue] = sscanf(str_replace(' ', '', Color::convertToRgb($oklch)), 'rgb(%d,%d,%d)');

        return sprintf('#%02x%02x%02x', $red, $green, $blue);
    }
}
