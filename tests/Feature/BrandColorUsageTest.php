<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Guardia: el color de marca sale siempre de la paleta `primary` (el color
 * de la empresa, Parámetros > Apariencia). Una clase `fuchsia-*` o un hex de
 * la paleta fucsia anterior escrito a mano dejaría esa pantalla fucsia en la
 * instalación de otra empresa.
 *
 * `purple` también se bloquea (el asiento seleccionado usa `primary-800`),
 * salvo en el diseñador de colectivos, donde `purple`/`pink` distinguen
 * categorías de áreas y no la marca.
 */
class BrandColorUsageTest extends TestCase
{
    /**
     * Hex de la paleta fucsia/violeta que se usaba como color de marca.
     *
     * @var list<string>
     */
    private const BRAND_HEXES = [
        '#c026d3', '#d946ef', '#d87fee', '#f5ecf7', '#a21caf', '#86198f',
        '#f5d0fe', '#fdf4ff', '#faf5ff', '#f3e8ff',
        '#fcecff', '#6b21a8', '#cedaf1',
    ];

    /**
     * Carpetas revisadas, relativas a la raíz del proyecto.
     *
     * @var list<string>
     */
    private const PATHS = ['resources/views', 'resources/css/filament', 'app/Filament'];

    /**
     * Archivos que quedan fuera: plantillas sin uso o de terceros.
     *
     * @var list<string>
     */
    private const EXCLUDED = ['resources/views/welcome.blade.php', 'resources/views/vendor/'];

    /**
     * Archivos donde `purple` es un color de categoría, no de marca.
     *
     * @var list<string>
     */
    private const PURPLE_ALLOWED = ['resources/views/buses/design-layout.blade.php'];

    public function test_no_hay_colores_de_marca_fijos(): void
    {
        $hexPattern = implode('|', array_map(fn (string $hex): string => preg_quote($hex, '/'), self::BRAND_HEXES));
        $pattern = '/\b(?:fuchsia|purple)-\d{2,3}\b|(?:'.$hexPattern.')(?![0-9a-f])/i';
        $patternWithoutPurple = '/\bfuchsia-\d{2,3}\b|(?:'.$hexPattern.')(?![0-9a-f])/i';

        $offenders = [];

        foreach (self::PATHS as $path) {
            foreach (File::allFiles(base_path($path)) as $file) {
                $relativePath = str_replace('\\', '/', $file->getRelativePathname());
                $relativePath = $path.'/'.$relativePath;

                if ($this->isExcluded($relativePath)) {
                    continue;
                }

                $filePattern = in_array($relativePath, self::PURPLE_ALLOWED, true) ? $patternWithoutPurple : $pattern;

                foreach (preg_split('/\R/', $file->getContents()) as $index => $line) {
                    if (preg_match_all($filePattern, $line, $matches) > 0) {
                        $offenders[] = $relativePath.':'.($index + 1).' → '.implode(', ', array_unique($matches[0]));
                    }
                }
            }
        }

        $this->assertSame(
            [],
            $offenders,
            "Colores de marca fijos: usá primary (Tailwind), var(--primary-N) (CSS) o \$brand[N] (PDF).\n".implode("\n", $offenders),
        );
    }

    /**
     * Un texto con un tono fuerte de la marca (500 a 900) se pierde sobre el
     * fondo oscuro con colores de marca oscuros (un azul marino): necesita su
     * `dark:` en la misma línea. Las páginas de descarga no tienen modo oscuro.
     */
    public function test_los_textos_de_la_marca_tienen_variante_para_modo_oscuro(): void
    {
        $offenders = [];

        foreach (File::allFiles(resource_path('views')) as $file) {
            $relativePath = 'resources/views/'.str_replace('\\', '/', $file->getRelativePathname());

            if ($this->isExcluded($relativePath) || str_starts_with($relativePath, 'resources/views/tickets/download')) {
                continue;
            }

            foreach (preg_split('/\R/', $file->getContents()) as $index => $line) {
                if (preg_match('/(?<![:\w-])text-primary-[5-9]00\b/', $line) === 1 && ! str_contains($line, 'dark:text-primary')) {
                    $offenders[] = $relativePath.':'.($index + 1);
                }
            }
        }

        $this->assertSame([], $offenders, "Agregá dark:text-primary-400 a estos textos:\n".implode("\n", $offenders));
    }

    public function test_el_asiento_seleccionado_usa_un_tono_oscuro_de_la_marca(): void
    {
        $seatSelector = File::get(resource_path('views/tickets/seat-selector.blade.php'));

        $this->assertStringContainsString("'bg-primary-800 border-primary-900 text-white hover:bg-primary-900': isSelected(", $seatSelector);
        $this->assertStringContainsString('bg-primary-800 rounded border border-primary-900', $seatSelector, 'La referencia "Seleccionado" usa el mismo tono.');
    }

    private function isExcluded(string $relativePath): bool
    {
        foreach (self::EXCLUDED as $excluded) {
            if (str_starts_with($relativePath, $excluded)) {
                return true;
            }
        }

        return false;
    }
}
