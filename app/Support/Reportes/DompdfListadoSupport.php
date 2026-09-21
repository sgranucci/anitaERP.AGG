<?php

namespace App\Support\Reportes;

use Dompdf\Dompdf;

/**
 * Listados PDF con DomPDF: numeración de hojas y guardado unificado.
 *
 * El encabezado de título/logos se repite vía &lt;thead&gt; en la vista
 * (display: table-header-group). La numeración va en el canvas de cada hoja.
 */
final class DompdfListadoSupport
{
    /**
     * Renderiza HTML, numera hojas y guarda el PDF.
     *
     * @param  array{
     *   paper?: string,
     *   orientation?: string,
     *   titulo_corto?: string,
     *   page_text?: string,
     *   page_font_size?: float,
     *   margin_footer_y?: float,
     *   margin_header_y?: float,
     * }  $opciones
     */
    public static function guardar(string $html, string $rutaAbsoluta, array $opciones = []): void
    {
        $dir = dirname($rutaAbsoluta);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        /** @var \Barryvdh\DomPDF\PDF $pdf */
        $pdf = app('dompdf.wrapper');
        $pdf->setPaper(
            (string) ($opciones['paper'] ?? 'legal'),
            (string) ($opciones['orientation'] ?? 'landscape')
        );
        $pdf->loadHTML($html);

        $dompdf = $pdf->getDomPDF();
        $dompdf->render();
        self::aplicarEncabezadoYNumeracion($dompdf, $opciones);
        file_put_contents($rutaAbsoluta, $dompdf->output());
    }

    /**
     * Legal landscape con numeración (listados de consulta).
     *
     * @param  array<string, mixed>  $opciones
     */
    public static function guardarLegalLandscape(string $html, string $rutaAbsoluta, array $opciones = []): void
    {
        $opciones['paper'] = $opciones['paper'] ?? 'legal';
        $opciones['orientation'] = $opciones['orientation'] ?? 'landscape';
        self::guardar($html, $rutaAbsoluta, $opciones);
    }

    /**
     * @param  array<string, mixed>  $opciones
     */
    public static function aplicarEncabezadoYNumeracion(Dompdf $dompdf, array $opciones = []): void
    {
        $canvas = $dompdf->getCanvas();
        $fontMetrics = $dompdf->getFontMetrics();
        $font = $fontMetrics->getFont('DejaVu Sans', 'normal');
        if ($font === null) {
            $font = $fontMetrics->getFont('Helvetica', 'normal');
        }
        $size = (float) ($opciones['page_font_size'] ?? 8);
        $color = [0.15, 0.15, 0.15];

        $w = (float) $canvas->get_width();
        $h = (float) $canvas->get_height();

        $tituloCorto = trim((string) ($opciones['titulo_corto'] ?? ''));
        if ($tituloCorto !== '') {
            $yHeader = (float) ($opciones['margin_header_y'] ?? 12);
            $canvas->page_text(24, $yHeader, $tituloCorto, $font, $size, $color);
        }

        $texto = (string) ($opciones['page_text'] ?? 'Hoja {PAGE_NUM} de {PAGE_COUNT}');
        $yFooter = $h - (float) ($opciones['margin_footer_y'] ?? 16);
        $xFooter = max(24.0, $w - 130);
        $canvas->page_text($xFooter, $yFooter, $texto, $font, $size, $color);
    }
}
