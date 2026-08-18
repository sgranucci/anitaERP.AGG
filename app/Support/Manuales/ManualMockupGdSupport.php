<?php

declare(strict_types=1);

namespace App\Support\Manuales;

use RuntimeException;

/**
 * Generador de mockups PNG para manuales (estilo AdminLTE, 1280×760).
 * Referencia de calidad: capturas huecos-arca de gastronomía.
 */
final class ManualMockupGdSupport
{
    public const WIDTH = 1280;

    public const HEIGHT = 760;

    private string $font;

    private string $fontBold;

    /** @var array<string, int> */
    private array $c = [];

    private \GdImage $img;

    public function __construct()
    {
        if (! extension_loaded('gd')) {
            throw new RuntimeException('Se requiere la extensión GD.');
        }
        $this->font = '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf';
        $this->fontBold = '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf';
        if (! is_file($this->font) || ! is_file($this->fontBold)) {
            throw new RuntimeException('Se requieren fuentes DejaVu Sans.');
        }
    }

    /**
     * @param  array{
     *   modulo:string,
     *   pantalla:string,
     *   card_titulo:string,
     *   card_color?:string,
     *   breadcrumb?:string,
     *   tipo?:string,
     *   columnas?:list<string>,
     *   filas?:list<list<string>>,
     *   campos?:list<array{label:string,valor:string}>,
     *   tabs?:list<string>,
     *   tab_activa?:int,
     *   botones?:list<array{texto:string,estilo?:string}>,
     *   alertas?:list<array{texto:string,tipo?:string}>,
     *   sidebar?:list<string>,
     *   nota?:string,
     *   filtros?:list<string>,
     *   tools?:list<string>,
     *   modal?:array{titulo:string,filas?:list<list<string>>,columnas?:list<string>,texto?:string,botones?:list<array{texto:string,estilo?:string}>},
     *   pdf?:bool
     * }  $escena
     */
    public function render(array $escena, string $destinoPng): void
    {
        $dir = dirname($destinoPng);
        if (! is_dir($dir) && ! mkdir($dir, 0775, true) && ! is_dir($dir)) {
            throw new RuntimeException('No se pudo crear '.$dir);
        }

        $this->img = imagecreatetruecolor(self::WIDTH, self::HEIGHT);
        imageantialias($this->img, true);
        $this->palette();

        if (! empty($escena['pdf'])) {
            $this->drawPdf($escena);
        } elseif (($escena['tipo'] ?? '') === 'login') {
            $this->drawLogin($escena);
        } else {
            $this->drawShell($escena);
            if (! empty($escena['modal'])) {
                $this->drawModal($escena['modal']);
            }
        }

        imagepng($this->img, $destinoPng, 8);
        imagedestroy($this->img);
    }

    private function palette(): void
    {
        $i = $this->img;
        $this->c = [
            'fondo' => imagecolorallocate($i, 235, 238, 242),
            'blanco' => imagecolorallocate($i, 255, 255, 255),
            'texto' => imagecolorallocate($i, 23, 32, 42),
            'gris' => imagecolorallocate($i, 108, 117, 125),
            'borde' => imagecolorallocate($i, 206, 212, 218),
            'azul' => imagecolorallocate($i, 0, 123, 255),
            'azulOscuro' => imagecolorallocate($i, 52, 58, 64),
            'sidebar' => imagecolorallocate($i, 52, 58, 64),
            'sidebarActivo' => imagecolorallocate($i, 0, 123, 255),
            'celeste' => imagecolorallocate($i, 133, 193, 233),
            'primary' => imagecolorallocate($i, 0, 123, 255),
            'info' => imagecolorallocate($i, 23, 162, 184),
            'success' => imagecolorallocate($i, 40, 167, 69),
            'warning' => imagecolorallocate($i, 255, 193, 7),
            'danger' => imagecolorallocate($i, 220, 53, 69),
            'warningSuave' => imagecolorallocate($i, 255, 243, 205),
            'infoSuave' => imagecolorallocate($i, 209, 236, 241),
            'dangerSuave' => imagecolorallocate($i, 248, 215, 218),
            'successSuave' => imagecolorallocate($i, 212, 237, 218),
            'filaPar' => imagecolorallocate($i, 248, 249, 250),
            'muted' => imagecolorallocate($i, 173, 181, 189),
        ];
    }

    /**
     * @param  array<string, mixed>  $escena
     */
    private function drawShell(array $escena): void
    {
        $c = $this->c;
        imagefilledrectangle($this->img, 0, 0, 1279, 759, $c['fondo']);

        // top navbar
        imagefilledrectangle($this->img, 0, 0, 1279, 52, $c['azulOscuro']);
        $this->text('Anita ERP', 18, 34, 16, $c['blanco'], true);
        $this->text($escena['modulo'] ?? 'Módulo', 140, 33, 13, $c['muted']);
        $this->text('Ayuda operativa', 1100, 33, 12, $c['blanco']);

        // sidebar
        imagefilledrectangle($this->img, 0, 52, 210, 759, $c['sidebar']);
        $items = $escena['sidebar'] ?? ['Inicio', 'Listados', 'Consultas', 'Configuración'];
        $activo = $escena['pantalla'] ?? ($items[0] ?? '');
        $y = 80;
        foreach ($items as $item) {
            $isActivo = mb_stripos($item, (string) $activo) !== false || $item === $activo;
            if ($isActivo) {
                imagefilledrectangle($this->img, 8, $y - 18, 202, $y + 14, $c['sidebarActivo']);
            }
            $this->text($item, 22, $y, 12, $c['blanco'], $isActivo);
            $y += 42;
        }

        // content card
        $cardColor = $this->cardColor($escena['card_color'] ?? 'info');
        imagefilledrectangle($this->img, 230, 70, 1255, 730, $c['blanco']);
        imagerectangle($this->img, 230, 70, 1255, 730, $c['borde']);
        imagefilledrectangle($this->img, 230, 70, 1255, 118, $cardColor);
        $this->text((string) ($escena['card_titulo'] ?? 'Pantalla'), 250, 102, 16, $c['blanco'], true);

        if (! empty($escena['breadcrumb'])) {
            $this->text((string) $escena['breadcrumb'], 250, 145, 11, $c['gris']);
        }

        $yBody = ! empty($escena['breadcrumb']) ? 165 : 140;

        foreach ($escena['alertas'] ?? [] as $alerta) {
            $tipo = $alerta['tipo'] ?? 'warning';
            [$bg, $fg, $bd] = $this->alertaColors($tipo);
            imagefilledrectangle($this->img, 250, $yBody, 1235, $yBody + 42, $bg);
            imagerectangle($this->img, 250, $yBody, 1235, $yBody + 42, $bd);
            $this->text((string) $alerta['texto'], 265, $yBody + 28, 12, $fg, true);
            $yBody += 54;
        }

        if (! empty($escena['filtros'])) {
            imagefilledrectangle($this->img, 250, $yBody, 1235, $yBody + 58, $c['filaPar']);
            imagerectangle($this->img, 250, $yBody, 1235, $yBody + 58, $c['borde']);
            $fx = 265;
            foreach ($escena['filtros'] as $filtro) {
                $this->text((string) $filtro, $fx, $yBody + 36, 11, $c['texto']);
                $fx += 180;
            }
            $this->button('Aplicar filtros', 1080, $yBody + 12, 1230, $yBody + 46, 'primary');
            $yBody += 72;
        }

        if (! empty($escena['tools'])) {
            $tx = 250;
            foreach ($escena['tools'] as $tool) {
                $w = max(90, (int) (mb_strlen((string) $tool) * 8) + 28);
                $this->button((string) $tool, $tx, $yBody, $tx + $w, $yBody + 34, 'outline');
                $tx += $w + 10;
            }
            $yBody += 48;
        }

        if (! empty($escena['tabs'])) {
            $tx = 250;
            $activa = (int) ($escena['tab_activa'] ?? 0);
            foreach ($escena['tabs'] as $i => $tab) {
                $w = max(100, (int) (mb_strlen((string) $tab) * 8) + 30);
                $fill = $i === $activa ? $c['celeste'] : $c['filaPar'];
                imagefilledrectangle($this->img, $tx, $yBody, $tx + $w, $yBody + 34, $fill);
                imagerectangle($this->img, $tx, $yBody, $tx + $w, $yBody + 34, $c['borde']);
                $this->text((string) $tab, $tx + 12, $yBody + 23, 11, $c['texto'], $i === $activa);
                $tx += $w + 4;
            }
            $yBody += 48;
        }

        if (! empty($escena['campos'])) {
            $col = 0;
            $rowY = $yBody;
            foreach ($escena['campos'] as $campo) {
                $x = $col === 0 ? 250 : 760;
                $this->text((string) $campo['label'], $x, $rowY + 14, 11, $c['gris'], true);
                imagefilledrectangle($this->img, $x, $rowY + 22, $x + 450, $rowY + 54, $c['blanco']);
                imagerectangle($this->img, $x, $rowY + 22, $x + 450, $rowY + 54, $c['borde']);
                $this->text((string) $campo['valor'], $x + 10, $rowY + 44, 12, $c['texto']);
                $col++;
                if ($col > 1) {
                    $col = 0;
                    $rowY += 70;
                }
            }
            if ($col === 1) {
                $rowY += 70;
            }
            $yBody = $rowY + 8;
        }

        if (! empty($escena['columnas']) && ! empty($escena['filas'])) {
            $yBody = $this->drawTable(250, $yBody, 1235, $escena['columnas'], $escena['filas']);
        }

        if (! empty($escena['nota'])) {
            $this->text((string) $escena['nota'], 250, min(700, $yBody + 28), 11, $c['gris']);
        }

        if (! empty($escena['botones'])) {
            $bx = 1235;
            foreach (array_reverse($escena['botones']) as $btn) {
                $label = (string) ($btn['texto'] ?? 'Acción');
                $w = max(100, (int) (mb_strlen($label) * 8) + 28);
                $bx -= $w;
                $this->button($label, $bx, 685, $bx + $w - 8, 722, $btn['estilo'] ?? 'primary');
                $bx -= 12;
            }
        }
    }

    /**
     * @param  array<string, mixed>  $escena
     */
    private function drawLogin(array $escena): void
    {
        $c = $this->c;
        imagefilledrectangle($this->img, 0, 0, 1279, 759, $c['azulOscuro']);
        imagefilledrectangle($this->img, 420, 160, 860, 560, $c['blanco']);
        imagerectangle($this->img, 420, 160, 860, 560, $c['borde']);
        $this->text('Anita ERP', 545, 220, 24, $c['texto'], true);
        $this->text((string) ($escena['card_titulo'] ?? 'Inicio de sesión'), 500, 260, 14, $c['gris']);
        $this->text('Usuario', 470, 320, 12, $c['gris'], true);
        imagefilledrectangle($this->img, 470, 330, 810, 368, $c['filaPar']);
        imagerectangle($this->img, 470, 330, 810, 368, $c['borde']);
        $this->text('operador', 485, 355, 13, $c['texto']);
        $this->text('Contraseña', 470, 410, 12, $c['gris'], true);
        imagefilledrectangle($this->img, 470, 420, 810, 458, $c['filaPar']);
        imagerectangle($this->img, 470, 420, 810, 458, $c['borde']);
        $this->text('••••••••', 485, 445, 13, $c['texto']);
        $this->button('Ingresar', 470, 490, 810, 530, 'primary');
    }

    /**
     * @param  array<string, mixed>  $escena
     */
    private function drawPdf(array $escena): void
    {
        $c = $this->c;
        imagefilledrectangle($this->img, 0, 0, 1279, 759, $c['fondo']);
        imagefilledrectangle($this->img, 180, 40, 1100, 720, $c['blanco']);
        imagerectangle($this->img, 180, 40, 1100, 720, $c['borde']);
        $this->text('Anita ERP', 210, 85, 14, $c['gris']);
        $this->text((string) ($escena['card_titulo'] ?? 'Comprobante'), 210, 130, 20, $c['texto'], true);
        if (! empty($escena['nota'])) {
            $this->text((string) $escena['nota'], 210, 165, 12, $c['gris']);
        }
        if (! empty($escena['columnas']) && ! empty($escena['filas'])) {
            $this->drawTable(210, 200, 1070, $escena['columnas'], $escena['filas']);
        }
        $this->text('Documento de ejemplo para el manual — datos ficticios', 210, 690, 11, $c['muted']);
    }

    /**
     * @param  array{
     *   titulo:string,
     *   filas?:list<list<string>>,
     *   columnas?:list<string>,
     *   texto?:string,
     *   botones?:list<array{texto:string,estilo?:string}>
     * }  $modal
     */
    private function drawModal(array $modal): void
    {
        $c = $this->c;
        // dim overlay
        $overlay = imagecolorallocatealpha($this->img, 0, 0, 0, 70);
        imagefilledrectangle($this->img, 0, 0, 1279, 759, $overlay);

        imagefilledrectangle($this->img, 250, 120, 1030, 620, $c['blanco']);
        imagerectangle($this->img, 250, 120, 1030, 620, $c['borde']);
        imagefilledrectangle($this->img, 250, 120, 1030, 175, $c['warning']);
        $this->text((string) $modal['titulo'], 275, 155, 16, $c['texto'], true);
        $this->text('×', 1000, 155, 18, $c['texto']);

        $y = 210;
        if (! empty($modal['texto'])) {
            $this->text((string) $modal['texto'], 275, $y, 12, $c['texto']);
            $y += 36;
        }
        if (! empty($modal['columnas']) && ! empty($modal['filas'])) {
            $this->drawTable(275, $y, 1005, $modal['columnas'], $modal['filas']);
        }

        $bx = 1005;
        foreach (array_reverse($modal['botones'] ?? [['texto' => 'Cerrar', 'estilo' => 'outline']]) as $btn) {
            $label = (string) ($btn['texto'] ?? 'OK');
            $w = max(100, (int) (mb_strlen($label) * 8) + 28);
            $bx -= $w;
            $this->button($label, $bx, 560, $bx + $w - 8, 598, $btn['estilo'] ?? 'primary');
            $bx -= 12;
        }
    }

    /**
     * @param  list<string>  $cols
     * @param  list<list<string>>  $rows
     */
    private function drawTable(int $x1, int $y1, int $x2, array $cols, array $rows): int
    {
        $c = $this->c;
        $n = max(1, count($cols));
        $w = (int) (($x2 - $x1) / $n);
        $headerH = 40;
        $rowH = 36;
        $maxRows = min(count($rows), 8);
        $h = $headerH + $maxRows * $rowH;

        imagefilledrectangle($this->img, $x1, $y1, $x2, $y1 + $headerH, $c['celeste']);
        imagerectangle($this->img, $x1, $y1, $x2, $y1 + $h, $c['borde']);

        for ($i = 0; $i < $n; $i++) {
            $xx = $x1 + $i * $w;
            if ($i > 0) {
                imageline($this->img, $xx, $y1, $xx, $y1 + $h, $c['borde']);
            }
            $this->text($this->clip((string) $cols[$i], (int) ($w / 7)), $xx + 10, $y1 + 27, 11, $c['texto'], true);
        }

        for ($r = 0; $r < $maxRows; $r++) {
            $top = $y1 + $headerH + $r * $rowH;
            if ($r % 2 === 1) {
                imagefilledrectangle($this->img, $x1 + 1, $top, $x2 - 1, $top + $rowH - 1, $c['filaPar']);
            }
            imageline($this->img, $x1, $top + $rowH, $x2, $top + $rowH, $c['borde']);
            $row = $rows[$r];
            for ($i = 0; $i < $n; $i++) {
                $xx = $x1 + $i * $w;
                $val = (string) ($row[$i] ?? '');
                $this->text($this->clip($val, (int) ($w / 7)), $xx + 10, $top + 24, 11, $c['texto']);
            }
        }

        return $y1 + $h + 12;
    }

    private function button(string $label, int $x1, int $y1, int $x2, int $y2, string $estilo): void
    {
        $c = $this->c;
        [$fill, $border, $text] = match ($estilo) {
            'primary' => [$c['primary'], $c['primary'], $c['blanco']],
            'success' => [$c['success'], $c['success'], $c['blanco']],
            'danger' => [$c['danger'], $c['danger'], $c['blanco']],
            'warning' => [$c['warning'], $c['warning'], $c['texto']],
            'info' => [$c['info'], $c['info'], $c['blanco']],
            default => [$c['blanco'], $c['gris'], $c['texto']],
        };
        imagefilledrectangle($this->img, $x1, $y1, $x2, $y2, $fill);
        imagerectangle($this->img, $x1, $y1, $x2, $y2, $border);
        $size = mb_strlen($label) > 22 ? 10 : 12;
        $this->text($label, $x1 + 12, $y1 + (int) (($y2 - $y1) / 2) + 5, $size, $text, true);
    }

    private function text(string $value, int $x, int $y, int $size, int $color, bool $bold = false): void
    {
        imagettftext(
            $this->img,
            $size,
            0,
            $x,
            $y,
            $color,
            $bold ? $this->fontBold : $this->font,
            $value,
        );
    }

    private function cardColor(string $name): int
    {
        return match ($name) {
            'primary' => $this->c['primary'],
            'success' => $this->c['success'],
            'warning' => $this->c['warning'],
            'danger' => $this->c['danger'],
            default => $this->c['info'],
        };
    }

    /**
     * @return array{0:int,1:int,2:int}
     */
    private function alertaColors(string $tipo): array
    {
        return match ($tipo) {
            'info' => [$this->c['infoSuave'], $this->c['info'], $this->c['info']],
            'success' => [$this->c['successSuave'], $this->c['success'], $this->c['success']],
            'danger' => [$this->c['dangerSuave'], $this->c['danger'], $this->c['danger']],
            default => [$this->c['warningSuave'], $this->c['texto'], $this->c['warning']],
        };
    }

    private function clip(string $value, int $maxChars): string
    {
        $maxChars = max(6, $maxChars);
        if (mb_strlen($value) <= $maxChars) {
            return $value;
        }

        return mb_substr($value, 0, $maxChars - 1).'…';
    }
}
