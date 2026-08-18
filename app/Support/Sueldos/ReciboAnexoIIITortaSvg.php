<?php

namespace App\Support\Sueldos;

/**
 * Torta SVG para DomPDF (sin JS). Colores alineados al PS Anita C.
 */
class ReciboAnexoIIITortaSvg
{
    /** @var list<string> */
    private const COLORES = [
        '#2E86C1', // neto
        '#28B463', // seg social
        '#F4D03F', // sindical
        '#E67E22', // obra social
        '#8E44AD', // pami
        '#E74C3C', // art
        '#1ABC9C', // scvo
        '#95A5A6', // otros
    ];

    /**
     * @param  array<string, float>  $valores clave => monto (>=0)
     */
    public static function render(array $valores, int $size = 120): string
    {
        $items = [];
        foreach ($valores as $label => $monto) {
            $m = (float) $monto;
            if ($m > 0.0001) {
                $items[] = ['label' => (string) $label, 'valor' => $m];
            }
        }
        if ($items === []) {
            return '<svg xmlns="http://www.w3.org/2000/svg" width="'.$size.'" height="'.$size.'"></svg>';
        }

        $total = array_sum(array_column($items, 'valor'));
        $cx = $size / 2;
        $cy = $size / 2;
        $r = $size / 2 - 2;
        $angle = -90.0;
        $paths = '';
        $i = 0;
        foreach ($items as $item) {
            $sweep = ($item['valor'] / $total) * 360.0;
            $color = self::COLORES[$i % count(self::COLORES)];
            if (count($items) === 1 || $sweep >= 359.9) {
                $paths .= sprintf(
                    '<circle cx="%.1f" cy="%.1f" r="%.1f" fill="%s"/>',
                    $cx,
                    $cy,
                    $r,
                    $color
                );
            } else {
                $paths .= self::slice($cx, $cy, $r, $angle, $angle + $sweep, $color);
            }
            $angle += $sweep;
            $i++;
        }

        return '<svg xmlns="http://www.w3.org/2000/svg" width="'.$size.'" height="'.$size.'" viewBox="0 0 '.$size.' '.$size.'">'
            .$paths
            .'</svg>';
    }

    /**
     * Genera un PNG embebido porque DomPDF no incorpora de forma consistente
     * gráficos SVG, ni inline ni como data URI.
     *
     * @param  array<string, float>  $valores
     */
    public static function dataUri(array $valores, int $size = 120): string
    {
        if (! function_exists('imagecreatetruecolor')) {
            return 'data:image/svg+xml;base64,'.base64_encode(self::render($valores, $size));
        }

        $items = array_values(array_filter(
            $valores,
            static fn ($monto): bool => (float) $monto > 0.0001
        ));
        $imagen = imagecreatetruecolor($size, $size);
        imageantialias($imagen, true);
        $blanco = imagecolorallocate($imagen, 255, 255, 255);
        imagefill($imagen, 0, 0, $blanco);

        $total = array_sum($items);
        if ($total > 0.0001) {
            $inicio = -90.0;
            foreach ($items as $indice => $valor) {
                $barrido = ((float) $valor / $total) * 360.0;
                [$rojo, $verde, $azul] = self::rgb(self::COLORES[$indice % count(self::COLORES)]);
                $color = imagecolorallocate($imagen, $rojo, $verde, $azul);
                imagefilledarc(
                    $imagen,
                    (int) round($size / 2),
                    (int) round($size / 2),
                    $size - 4,
                    $size - 4,
                    $inicio,
                    $inicio + $barrido,
                    $color,
                    IMG_ARC_PIE
                );
                $inicio += $barrido;
            }
        }

        ob_start();
        imagepng($imagen);
        $png = (string) ob_get_clean();
        imagedestroy($imagen);

        return 'data:image/png;base64,'.base64_encode($png);
    }

    /**
     * @return array{int, int, int}
     */
    private static function rgb(string $hex): array
    {
        $hex = ltrim($hex, '#');

        return [
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2)),
        ];
    }

    private static function slice(float $cx, float $cy, float $r, float $a0, float $a1, string $color): string
    {
        $x0 = $cx + $r * cos(deg2rad($a0));
        $y0 = $cy + $r * sin(deg2rad($a0));
        $x1 = $cx + $r * cos(deg2rad($a1));
        $y1 = $cy + $r * sin(deg2rad($a1));
        $large = ($a1 - $a0) > 180 ? 1 : 0;

        return sprintf(
            '<path d="M %.2f %.2f L %.2f %.2f A %.2f %.2f 0 %d 1 %.2f %.2f Z" fill="%s"/>',
            $cx,
            $cy,
            $x0,
            $y0,
            $r,
            $r,
            $large,
            $x1,
            $y1,
            $color
        );
    }
}
