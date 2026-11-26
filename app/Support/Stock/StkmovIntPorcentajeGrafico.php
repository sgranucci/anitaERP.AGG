<?php

namespace App\Support\Stock;

/**
 * Barras agrupadas del cuadro de porcentajes de l-stkmovint (PNG para el mail y el PDF).
 */
final class StkmovIntPorcentajeGrafico
{
    private const SERIES = [
        ['clave' => 'pct_kg', 'titulo' => '% Kg. venta', 'rgb' => [36, 113, 163]],
        ['clave' => 'pct_pesos', 'titulo' => '% $ venta', 'rgb' => [26, 188, 156]],
        ['clave' => 'pct_bonif', 'titulo' => '% Bonific.', 'rgb' => [230, 126, 34]],
        ['clave' => 'pct_bonif_abs', 'titulo' => '% Bonif. ABS', 'rgb' => [142, 68, 173]],
    ];

    /**
     * @param  array{filas?: list<array<string, mixed>>, totales?: array<string, mixed>}  $porcentajes
     */
    public static function png(array $porcentajes): string
    {
        if (! function_exists('imagecreatetruecolor')) {
            return '';
        }
        $filas = array_values($porcentajes['filas'] ?? []);
        if ($filas === []) {
            return '';
        }

        $fuente = is_file('/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf')
            ? '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf'
            : null;

        $ancho = 920;
        $alto = 420;
        $img = imagecreatetruecolor($ancho, $alto);
        $blanco = imagecolorallocate($img, 255, 255, 255);
        $tinta = imagecolorallocate($img, 23, 32, 42);
        $eje = imagecolorallocate($img, 180, 190, 198);
        $guia = imagecolorallocate($img, 232, 236, 240);
        imagefill($img, 0, 0, $blanco);
        imageantialias($img, true);

        $max = 10.0;
        foreach ($filas as $fila) {
            foreach (self::SERIES as $serie) {
                $max = max($max, (float) ($fila[$serie['clave']] ?? 0));
            }
        }
        $max = (float) (ceil($max / 10) * 10);

        $margenIzq = 54;
        $margenDer = 24;
        $margenSup = 78;
        $margenInf = 64;
        $plotX = $margenIzq;
        $plotY = $margenSup;
        $plotW = $ancho - $margenIzq - $margenDer;
        $plotH = $alto - $margenSup - $margenInf;

        self::texto($img, $fuente, 13, $tinta, 16, 22, 'Porcentajes por agrupación');

        $leyendaX = 16;
        foreach (self::SERIES as $serie) {
            $color = imagecolorallocate($img, $serie['rgb'][0], $serie['rgb'][1], $serie['rgb'][2]);
            imagefilledrectangle($img, $leyendaX, 44, $leyendaX + 14, 56, $color);
            self::texto($img, $fuente, 9, $tinta, $leyendaX + 18, 55, $serie['titulo']);
            $leyendaX += 150;
        }

        $paso = $max <= 20 ? 5.0 : 10.0;
        for ($valor = 0.0; $valor <= $max + 0.01; $valor += $paso) {
            $y = (int) round($plotY + $plotH - ($valor / $max) * $plotH);
            imageline($img, $plotX, $y, $plotX + $plotW, $y, $valor < 0.01 ? $eje : $guia);
            self::texto($img, $fuente, 8, $tinta, 8, $y + 4, (string) (int) round($valor));
        }

        $n = count($filas);
        $grupoW = $plotW / $n;
        $barraW = max(8, (int) floor(($grupoW - 28) / count(self::SERIES)));
        $colores = [];
        foreach (self::SERIES as $i => $serie) {
            $colores[$i] = imagecolorallocate($img, $serie['rgb'][0], $serie['rgb'][1], $serie['rgb'][2]);
        }

        foreach ($filas as $i => $fila) {
            $origen = $plotX + ($i * $grupoW) + 12;
            foreach (self::SERIES as $s => $serie) {
                $valor = (float) ($fila[$serie['clave']] ?? 0);
                $altoBarra = $max > 0 ? ($valor / $max) * $plotH : 0;
                $x1 = (int) round($origen + $s * ($barraW + 2));
                $y1 = (int) round($plotY + $plotH - $altoBarra);
                $x2 = $x1 + $barraW;
                $y2 = $plotY + $plotH;
                if ($y2 > $y1) {
                    imagefilledrectangle($img, $x1, $y1, $x2, $y2, $colores[$s]);
                }
                $texto = $serie['clave'] === 'pct_bonif_abs'
                    ? number_format($valor, 1, ',', '')
                    : (string) (int) round($valor);
                self::texto($img, $fuente, 7, $tinta, $x1 - 1, max($plotY + 10, $y1 - 3), $texto);
            }
            $etiqueta = (string) ($fila['etiqueta'] ?? '');
            self::texto($img, $fuente, 9, $tinta, (int) round($origen + 8), $plotY + $plotH + 22, $etiqueta);
        }

        ob_start();
        imagepng($img);
        $png = (string) ob_get_clean();
        imagedestroy($img);

        return $png;
    }

    /**
     * @param  \GdImage  $img
     */
    private static function texto($img, ?string $fuente, int $size, int $color, int $x, int $y, string $texto): void
    {
        if ($fuente !== null) {
            imagettftext($img, $size, 0, $x, $y, $color, $fuente, $texto);

            return;
        }
        imagestring($img, 2, $x, $y - 10, $texto, $color);
    }
}
