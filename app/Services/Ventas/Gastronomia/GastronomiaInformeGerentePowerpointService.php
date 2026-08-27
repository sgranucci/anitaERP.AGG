<?php

namespace App\Services\Ventas\Gastronomia;

use PhpOffice\PhpPresentation\IOFactory;
use PhpOffice\PhpPresentation\PhpPresentation;
use PhpOffice\PhpPresentation\Shape\Chart\Series;
use PhpOffice\PhpPresentation\Shape\Chart\Type\Bar;
use PhpOffice\PhpPresentation\Shape\Chart\Type\Pie;
use PhpOffice\PhpPresentation\Shape\Table\Cell;
use PhpOffice\PhpPresentation\Style\Alignment;
use PhpOffice\PhpPresentation\Style\Border;
use PhpOffice\PhpPresentation\Style\Color;
use PhpOffice\PhpPresentation\Style\Fill;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Exporta el informe gerente a PowerPoint (.pptx): gráficos nativos + tablas de detalle.
 */
final class GastronomiaInformeGerentePowerpointService
{
    /** @var list<string> ARGB */
    private const PALETA = [
        'FF3C8DBC', 'FF00A65A', 'FFF39C12', 'FFF56954', 'FF605CA8',
        'FFD81B60', 'FF39CCCC', 'FFFF851B', 'FF001F3F', 'FF01FF70',
    ];

    private const HEADER_BG = 'FF85C1E9';

    private const HEADER_FG = 'FF17202A';

    private const ROW_ALT_BG = 'FFF5F5F5';

    /** Filas de datos por slide (además del encabezado). */
    private const MAX_FILAS_TABLA = 12;

    /**
     * @param  array<string, mixed>  $informe
     */
    public function descargar(
        array $informe,
        string $titulo,
        string $subtitulo,
        string $empresaNombre,
    ): BinaryFileResponse {
        $presentation = $this->armar($informe, $titulo, $subtitulo, $empresaNombre);

        // ZipArchive::close() crea un temporal junto al destino; usar un dir
        // escribible por PHP-FPM (PrivateTmp / permisos). Preferir storage/framework/cache.
        $dir = storage_path('framework/cache');
        if (! is_dir($dir) || ! is_writable($dir)) {
            $dir = sys_get_temp_dir();
        }
        if (! is_writable($dir)) {
            abort(500, 'No hay directorio temporal escribible para generar el PowerPoint.');
        }

        $tmpBase = tempnam($dir, 'igpptx_');
        if ($tmpBase === false) {
            abort(500, 'No se pudo crear archivo temporal para PowerPoint.');
        }
        @unlink($tmpBase);
        $path = $tmpBase.'.pptx';

        $writer = IOFactory::createWriter($presentation, 'PowerPoint2007');
        try {
            $writer->save($path);
        } catch (\Throwable $e) {
            @unlink($path);
            report($e);
            abort(500, 'Error al generar PowerPoint: '.$e->getMessage());
        }

        if (! is_file($path) || filesize($path) <= 0) {
            @unlink($path);
            abort(500, 'No se pudo generar el archivo PowerPoint.');
        }

        return response()
            ->download($path, 'informe_gerente_gastronomia.pptx', [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            ])
            ->deleteFileAfterSend(true);
    }

    /**
     * @param  array<string, mixed>  $informe
     */
    public function armar(
        array $informe,
        string $titulo,
        string $subtitulo,
        string $empresaNombre,
    ): PhpPresentation {
        $presentation = new PhpPresentation();
        $presentation->getDocumentProperties()
            ->setCreator('anitaERP')
            ->setCompany(trim($empresaNombre) !== '' ? $empresaNombre : 'anitaERP')
            ->setTitle($titulo)
            ->setDescription($subtitulo);

        $slide = $presentation->getActiveSlide();
        $this->slidePortada($slide, $informe, $titulo, $subtitulo, $empresaNombre);

        $charts = $informe['charts'] ?? [];
        $periodoLabel = (string) ($informe['periodo_label'] ?? $informe['fecha_jornada_label'] ?? '');
        $mesLabel = (string) ($informe['mes_jornada_label'] ?? '');

        $this->agregarSlidePie(
            $presentation,
            'Ventas por turno',
            $periodoLabel,
            $charts['turno'] ?? [],
            'Total',
        );
        $this->agregarSlidePie(
            $presentation,
            'Ventas por punto de venta',
            $periodoLabel,
            $charts['puntoventa'] ?? [],
            'Total',
        );
        $this->agregarSlidePie(
            $presentation,
            'Facturación por medio de pago',
            $periodoLabel,
            $charts['medio_pago'] ?? [],
            'Total cobrado',
        );
        $this->agregarSlidePie(
            $presentation,
            'Facturas por código de descuento',
            $periodoLabel,
            $charts['descuento'] ?? [],
            'Importe',
        );
        $this->agregarSlideBar(
            $presentation,
            'Top 10 artículos vendidos — cantidad (período)',
            $periodoLabel,
            $charts['articulos_dia'] ?? [],
            'Cantidad',
            true,
        );
        $this->agregarSlideBar(
            $presentation,
            'Top 10 artículos vendidos — cantidad (mes)',
            $mesLabel !== '' ? $mesLabel : $periodoLabel,
            $charts['articulos_mes'] ?? [],
            'Cantidad',
            true,
        );
        $this->agregarSlidePie(
            $presentation,
            'Recepciones del período — importe por proveedor',
            $periodoLabel,
            $charts['recepciones_dia'] ?? [],
            'Importe',
        );
        $this->agregarSlidePie(
            $presentation,
            'Recepciones del mes — importe por proveedor',
            $mesLabel !== '' ? $mesLabel : $periodoLabel,
            $charts['recepciones_mes'] ?? [],
            'Importe',
        );

        $this->agregarSeparador($presentation, 'Detalle tabular', $periodoLabel);
        $this->agregarSlidesTablas($presentation, $informe, $periodoLabel, $mesLabel);

        return $presentation;
    }

    /**
     * @param  array<string, mixed>  $informe
     */
    private function slidePortada(
        \PhpOffice\PhpPresentation\Slide $slide,
        array $informe,
        string $titulo,
        string $subtitulo,
        string $empresaNombre,
    ): void {
        $tituloShape = $slide->createRichTextShape()
            ->setHeight(80)
            ->setWidth(900)
            ->setOffsetX(40)
            ->setOffsetY(120);
        $tituloShape->getActiveParagraph()->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $run = $tituloShape->createTextRun($titulo);
        $run->getFont()->setBold(true)->setSize(28)->setColor(new Color('FF1B4F72'));

        $meta = $slide->createRichTextShape()
            ->setHeight(200)
            ->setWidth(860)
            ->setOffsetX(60)
            ->setOffsetY(230);
        $meta->getActiveParagraph()->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $lineas = [];
        if (trim($empresaNombre) !== '') {
            $lineas[] = 'Empresa: '.$empresaNombre;
        }
        if (trim($subtitulo) !== '') {
            $lineas[] = $subtitulo;
        }
        $periodo = (string) ($informe['periodo_label'] ?? '');
        if ($periodo !== '' && ! str_contains($subtitulo, $periodo)) {
            $lineas[] = 'Período: '.$periodo;
        }
        $total = (float) ($informe['total_ventas_periodo'] ?? $informe['total_ventas_jornada'] ?? 0);
        $lineas[] = 'Total ventas (neto): $'.number_format($total, 2, ',', '.');
        if (! empty($informe['waitry_sin_facturar']['total'])) {
            $lineas[] = 'Waitry pagado s/facturar: $'
                .number_format((float) $informe['waitry_sin_facturar']['total'], 2, ',', '.');
        }
        $lineas[] = 'Generado: '.date('d/m/Y H:i');

        $primero = true;
        foreach ($lineas as $linea) {
            if (! $primero) {
                $meta->createBreak();
            }
            $primero = false;
            $run = $meta->createTextRun($linea);
            $run->getFont()->setSize(16)->setColor(new Color('FF333333'));
        }

        $pie = $slide->createRichTextShape()
            ->setHeight(40)
            ->setWidth(900)
            ->setOffsetX(40)
            ->setOffsetY(480);
        $pie->getActiveParagraph()->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $run = $pie->createTextRun('Incluye gráficos nativos y tablas de detalle del informe');
        $run->getFont()->setSize(12)->setItalic(true)->setColor(new Color('FF666666'));
    }

    private function agregarSeparador(
        PhpPresentation $presentation,
        string $titulo,
        string $subtitulo,
    ): void {
        $slide = $presentation->createSlide();
        $shape = $slide->createRichTextShape()
            ->setHeight(120)
            ->setWidth(900)
            ->setOffsetX(30)
            ->setOffsetY(250);
        $shape->getActiveParagraph()->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $run = $shape->createTextRun($titulo);
        $run->getFont()->setBold(true)->setSize(26)->setColor(new Color('FF1B4F72'));
        if (trim($subtitulo) !== '') {
            $shape->createBreak();
            $runSub = $shape->createTextRun($subtitulo);
            $runSub->getFont()->setSize(14)->setColor(new Color('FF555555'));
        }
    }

    /**
     * @param  array<string, mixed>  $informe
     */
    private function agregarSlidesTablas(
        PhpPresentation $presentation,
        array $informe,
        string $periodoLabel,
        string $mesLabel,
    ): void {
        $this->agregarSlideTabla(
            $presentation,
            'Top 10 artículos — cantidad',
            $periodoLabel,
            ['#', 'SKU', 'Artículo', 'Cantidad', 'Importe'],
            $this->filasTop10($informe['top10_cantidad'] ?? []),
            [50, 110, 420, 140, 160],
            [false, false, false, true, true],
        );

        $this->agregarSlideTabla(
            $presentation,
            'Top 10 artículos — valor',
            $periodoLabel,
            ['#', 'SKU', 'Artículo', 'Cantidad', 'Importe'],
            $this->filasTop10($informe['top10_valor'] ?? []),
            [50, 110, 420, 140, 160],
            [false, false, false, true, true],
        );

        $mesSub = $mesLabel !== '' ? $mesLabel : $periodoLabel;
        $this->agregarSlideTabla(
            $presentation,
            'Top 10 artículos — cantidad (mes)',
            $mesSub,
            ['#', 'SKU', 'Artículo', 'Cantidad', 'Importe'],
            $this->filasTop10($informe['top10_mes_cantidad'] ?? []),
            [50, 110, 420, 140, 160],
            [false, false, false, true, true],
        );

        $this->agregarSlideTabla(
            $presentation,
            'Ventas por turno',
            $periodoLabel,
            ['Turno', 'Comprobantes', 'Total'],
            $this->filasTurno($informe['ventas_por_turno'] ?? []),
            [420, 200, 260],
            [false, true, true],
        );

        $this->agregarSlideTabla(
            $presentation,
            'Ventas por punto de venta',
            $periodoLabel,
            ['PV', 'Nombre', 'Facturas', 'NC', 'Waitry s/f', 'Total neto'],
            $this->filasPuntoventa($informe['ventas_por_puntoventa'] ?? []),
            [70, 280, 100, 80, 150, 160],
            [false, false, true, true, true, true],
        );

        $this->agregarSlideTabla(
            $presentation,
            'Facturación por medio de pago',
            $periodoLabel,
            ['Código', 'Medio de pago', 'Cobranzas', '%', 'Total cobrado'],
            $this->filasMedioPago($informe['ventas_por_medio_pago'] ?? []),
            [100, 340, 140, 90, 210],
            [false, false, true, true, true],
        );

        $this->agregarSlideTabla(
            $presentation,
            'Facturas por código de descuento',
            $periodoLabel,
            ['Código', 'Descuento', 'Facturas', 'Importe'],
            $this->filasDescuentos($informe['facturas_por_descuento'] ?? []),
            [120, 380, 140, 200],
            [false, false, true, true],
        );

        $recFuente = (string) ($informe['recepciones']['fuente'] ?? 'erp');
        $recLabel = $recFuente === 'erp'
            ? 'ERP'
            : ($recFuente === 'hibrido' ? 'ERP + Anita' : 'Anita');
        $recCc = $informe['recepciones']['centro_costo_codigo'] ?? null;
        $recSub = $periodoLabel.' · '.$recLabel.($recCc ? ' · CC '.$recCc : '');

        $this->agregarSlideTabla(
            $presentation,
            'Recepciones del período',
            $recSub,
            ['Proveedor', 'Comprobante', 'Fecha', 'Est.', 'Líneas', 'Importe'],
            $this->filasRecepciones($informe['recepciones']['dia']['filas'] ?? []),
            [260, 200, 100, 90, 80, 150],
            [false, false, false, false, true, true],
        );

        $recMesSub = ($mesLabel !== '' ? $mesLabel : $periodoLabel)
            .' · '.$recLabel.($recCc ? ' · CC '.$recCc : '');
        $this->agregarSlideTabla(
            $presentation,
            'Recepciones acumuladas del mes',
            $recMesSub,
            ['Proveedor', 'Comprobante', 'Fecha', 'Est.', 'Líneas', 'Importe'],
            $this->filasRecepciones($informe['recepciones']['mes']['filas'] ?? []),
            [260, 200, 100, 90, 80, 150],
            [false, false, false, false, true, true],
        );

        $top20 = $informe['top20_articulos_costo'] ?? [];
        $listas = $top20['listas'] ?? [];
        $mesAnt = (string) ($listas['mes_anterior_label'] ?? 'Mes ant.');
        $mesAct = (string) ($listas['mes_actual_label'] ?? 'Mes act.');
        $this->agregarSlideTabla(
            $presentation,
            'Top 20 artículos — precio y costo',
            $periodoLabel,
            ['#', 'SKU', 'Artículo', 'Cant.', 'P.venta', $mesAnt, $mesAct, 'Δ %'],
            $this->filasTop20Costos($top20['filas'] ?? []),
            [40, 90, 280, 90, 100, 100, 100, 80],
            [false, false, false, true, true, true, true, true],
        );
    }

    /**
     * @param  list<string>  $headers
     * @param  list<list<string>>  $filas
     * @param  list<int>  $anchos
     * @param  list<bool>  $derecha
     */
    private function agregarSlideTabla(
        PhpPresentation $presentation,
        string $titulo,
        string $subtitulo,
        array $headers,
        array $filas,
        array $anchos,
        array $derecha,
    ): void {
        if ($filas === []) {
            $slide = $presentation->createSlide();
            $this->encabezadoSlide($slide, $titulo, $subtitulo);
            $this->slideSinDatosTabla($slide);

            return;
        }

        $chunks = array_chunk($filas, self::MAX_FILAS_TABLA);
        $totalPaginas = count($chunks);
        foreach ($chunks as $pagina => $chunk) {
            $tituloPagina = $titulo;
            if ($totalPaginas > 1) {
                $tituloPagina .= ' ('.($pagina + 1).'/'.$totalPaginas.')';
            }
            $slide = $presentation->createSlide();
            $this->encabezadoSlide($slide, $tituloPagina, $subtitulo);
            $this->dibujarTabla($slide, $headers, $chunk, $anchos, $derecha);
        }
    }

    /**
     * @param  list<string>  $headers
     * @param  list<list<string>>  $filas
     * @param  list<int>  $anchos
     * @param  list<bool>  $derecha
     */
    private function dibujarTabla(
        \PhpOffice\PhpPresentation\Slide $slide,
        array $headers,
        array $filas,
        array $anchos,
        array $derecha,
    ): void {
        $cols = count($headers);
        $table = $slide->createTableShape($cols);
        $table->setResizeProportional(false)
            ->setWidth(array_sum($anchos))
            ->setOffsetX(30)
            ->setOffsetY(78);

        $headerRow = $table->createRow();
        $headerRow->setHeight(28);
        foreach ($headers as $i => $header) {
            $cell = $headerRow->getCell($i);
            $cell->setWidth($anchos[$i] ?? 100);
            $this->celdaTexto($cell, $header, true, ! empty($derecha[$i]));
            $cell->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->setStartColor(new Color(self::HEADER_BG));
        }

        foreach ($filas as $r => $fila) {
            $row = $table->createRow();
            $row->setHeight(26);
            foreach ($headers as $i => $_) {
                $cell = $row->getCell($i);
                if ($r === 0) {
                    $cell->setWidth($anchos[$i] ?? 100);
                }
                $texto = (string) ($fila[$i] ?? '');
                $this->celdaTexto($cell, $texto, false, ! empty($derecha[$i]));
                if ($r % 2 === 1) {
                    $cell->getFill()
                        ->setFillType(Fill::FILL_SOLID)
                        ->setStartColor(new Color(self::ROW_ALT_BG));
                }
            }
        }
    }

    private function celdaTexto(Cell $cell, string $texto, bool $bold, bool $derecha): void
    {
        $this->aplicarBordeCelda($cell);
        $cell->getActiveParagraph()->getAlignment()
            ->setHorizontal($derecha ? Alignment::HORIZONTAL_RIGHT : Alignment::HORIZONTAL_LEFT)
            ->setVertical(Alignment::VERTICAL_CENTER);
        $run = $cell->createTextRun($texto);
        $run->getFont()
            ->setBold($bold)
            ->setSize($bold ? 10 : 9)
            ->setColor(new Color($bold ? self::HEADER_FG : 'FF17202A'));
    }

    private function aplicarBordeCelda(Cell $cell): void
    {
        $color = new Color('FFCCCCCC');
        foreach (['getTop', 'getBottom', 'getLeft', 'getRight'] as $lado) {
            $cell->getBorders()->{$lado}()
                ->setLineWidth(1)
                ->setLineStyle(Border::LINE_SINGLE)
                ->setColor($color);
        }
    }

    private function slideSinDatosTabla(\PhpOffice\PhpPresentation\Slide $slide): void
    {
        $shape = $slide->createRichTextShape()
            ->setHeight(80)
            ->setWidth(800)
            ->setOffsetX(80)
            ->setOffsetY(250);
        $shape->getActiveParagraph()->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $run = $shape->createTextRun('Sin datos para este detalle en el período.');
        $run->getFont()->setSize(16)->setItalic(true)->setColor(new Color('FF888888'));
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     * @return list<list<string>>
     */
    private function filasTop10(array $filas): array
    {
        $out = [];
        foreach ($filas as $i => $fila) {
            $out[] = [
                (string) ($i + 1),
                (string) ($fila['sku'] ?? ''),
                $this->truncar((string) ($fila['descripcion'] ?? ''), 48),
                $this->num((float) ($fila['cantidad'] ?? 0)),
                $this->moneda((float) ($fila['importe'] ?? 0)),
            ];
        }

        return $out;
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     * @return list<list<string>>
     */
    private function filasTurno(array $filas): array
    {
        $out = [];
        foreach ($filas as $fila) {
            $out[] = [
                (string) ($fila['etiqueta'] ?? ''),
                (string) ((int) ($fila['cantidad'] ?? 0)),
                $this->moneda((float) ($fila['total'] ?? 0)),
            ];
        }

        return $out;
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     * @return list<list<string>>
     */
    private function filasPuntoventa(array $filas): array
    {
        $out = [];
        foreach ($filas as $fila) {
            $waitry = (float) ($fila['waitry_sin_facturar'] ?? 0);
            $out[] = [
                (string) ($fila['codigo'] ?? ''),
                $this->truncar((string) ($fila['nombre'] ?? ''), 36),
                (string) ((int) ($fila['cantidad_facturas'] ?? 0)),
                (string) ((int) ($fila['cantidad_notas_credito'] ?? 0)),
                $waitry > 0 ? $this->moneda($waitry) : '—',
                $this->moneda((float) ($fila['total'] ?? 0)),
            ];
        }

        return $out;
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     * @return list<list<string>>
     */
    private function filasMedioPago(array $filas): array
    {
        $out = [];
        foreach ($filas as $fila) {
            $codigo = trim((string) ($fila['codigo'] ?? ''));
            $out[] = [
                $codigo !== '' ? $codigo : '—',
                $this->truncar((string) ($fila['nombre'] ?? ''), 42),
                (string) ((int) ($fila['cantidad'] ?? 0)),
                number_format((float) ($fila['porcentaje'] ?? 0), 1, ',', '.').'%',
                $this->moneda((float) ($fila['total'] ?? 0)),
            ];
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $datos
     * @return list<list<string>>
     */
    private function filasDescuentos(array $datos): array
    {
        $out = [];
        foreach ($datos['filas'] ?? [] as $fila) {
            $out[] = [
                (string) ($fila['codigo'] ?? ''),
                $this->truncar((string) ($fila['nombre'] ?? ''), 42),
                (string) ((int) ($fila['cantidad'] ?? 0)),
                $this->moneda((float) ($fila['importe'] ?? 0)),
            ];
        }
        $sin = $datos['sin_descuento'] ?? [];
        if ((int) ($sin['cantidad'] ?? 0) > 0) {
            $out[] = [
                '—',
                'Sin descuento',
                (string) ((int) $sin['cantidad']),
                $this->moneda((float) ($sin['importe'] ?? 0)),
            ];
        }

        return $out;
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     * @return list<list<string>>
     */
    private function filasRecepciones(array $filas): array
    {
        $out = [];
        foreach ($filas as $fila) {
            $nombre = trim((string) ($fila['proveedor_nombre'] ?? ''));
            if ($nombre === '') {
                $nombre = (string) ($fila['proveedor'] ?? '');
            }
            $out[] = [
                $this->truncar($nombre, 32),
                $this->truncar((string) ($fila['comprobante'] ?? ''), 24),
                (string) ($fila['fecha'] ?? ''),
                (string) ($fila['estado'] ?? ''),
                (string) ((int) ($fila['cantidad_lineas'] ?? 0)),
                $this->moneda((float) ($fila['importe'] ?? 0)),
            ];
        }

        return $out;
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     * @return list<list<string>>
     */
    private function filasTop20Costos(array $filas): array
    {
        $out = [];
        foreach ($filas as $fila) {
            $pct = $fila['pct_diferencia_costo'] ?? null;
            $pctTxt = '—';
            if ($pct !== null) {
                $pctTxt = ((float) $pct > 0 ? '+' : '').$this->num((float) $pct).'%';
            }
            $out[] = [
                (string) ($fila['posicion'] ?? ''),
                (string) ($fila['sku'] ?? ''),
                $this->truncar((string) ($fila['descripcion'] ?? ''), 34),
                $this->num((float) ($fila['cantidad'] ?? 0)),
                ($fila['precio_venta'] ?? null) !== null ? $this->moneda((float) $fila['precio_venta']) : '—',
                ($fila['costo_mes_anterior'] ?? null) !== null ? $this->moneda((float) $fila['costo_mes_anterior']) : '—',
                ($fila['costo_mes_actual'] ?? null) !== null ? $this->moneda((float) $fila['costo_mes_actual']) : '—',
                $pctTxt,
            ];
        }

        return $out;
    }

    private function moneda(float $valor): string
    {
        return '$'.number_format($valor, 2, ',', '.');
    }

    private function num(float $valor): string
    {
        return number_format($valor, 2, ',', '.');
    }

    private function truncar(string $texto, int $max): string
    {
        $texto = trim($texto);
        if (mb_strlen($texto) <= $max) {
            return $texto;
        }

        return rtrim(mb_substr($texto, 0, max(1, $max - 1))).'…';
    }

    /**
     * @param  array{labels?:list<string>,values?:list<float|int|string>}  $chartData
     */
    private function agregarSlidePie(
        PhpPresentation $presentation,
        string $tituloSlide,
        string $subtitulo,
        array $chartData,
        string $serieTitulo,
    ): void {
        $slide = $presentation->createSlide();
        $this->encabezadoSlide($slide, $tituloSlide, $subtitulo);

        $seriesData = $this->seriesDesdeChart($chartData);
        if ($seriesData === []) {
            $this->slideSinDatos($slide);

            return;
        }

        $pie = new Pie();
        $series = new Series($serieTitulo, $seriesData);
        $series->setShowSeriesName(false);
        $series->setShowValue(true);
        $series->setShowPercentage(true);
        $series->setShowCategoryName(false);
        $series->setSeparator("\n");
        $this->aplicarPaleta($series, count($seriesData));
        $pie->addSeries($series);

        $chart = $slide->createChartShape();
        $chart->setName($tituloSlide)
            ->setResizeProportional(false)
            ->setHeight(420)
            ->setWidth(880)
            ->setOffsetX(40)
            ->setOffsetY(90);
        $chart->getTitle()->setVisible(false);
        $chart->getLegend()->setVisible(true);
        $chart->getPlotArea()->setType($pie);
    }

    /**
     * @param  array{labels?:list<string>,values?:list<float|int|string>}  $chartData
     */
    private function agregarSlideBar(
        PhpPresentation $presentation,
        string $tituloSlide,
        string $subtitulo,
        array $chartData,
        string $serieTitulo,
        bool $horizontal = true,
    ): void {
        $slide = $presentation->createSlide();
        $this->encabezadoSlide($slide, $tituloSlide, $subtitulo);

        $seriesData = $this->seriesDesdeChart($chartData);
        if ($seriesData === []) {
            $this->slideSinDatos($slide);

            return;
        }

        // Chart.js horizontal deja el top abajo; en PPT conviene el mayor arriba.
        if ($horizontal) {
            $seriesData = array_reverse($seriesData, true);
        }

        $bar = new Bar();
        $bar->setBarDirection($horizontal ? Bar::DIRECTION_HORIZONTAL : Bar::DIRECTION_VERTICAL);
        $series = new Series($serieTitulo, $seriesData);
        $series->setShowSeriesName(false);
        $series->setShowValue(true);
        $series->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->setStartColor(new Color(self::PALETA[0]));
        $bar->addSeries($series);

        $chart = $slide->createChartShape();
        $chart->setName($tituloSlide)
            ->setResizeProportional(false)
            ->setHeight(420)
            ->setWidth(880)
            ->setOffsetX(40)
            ->setOffsetY(90);
        $chart->getTitle()->setVisible(false);
        $chart->getLegend()->setVisible(false);
        $chart->getPlotArea()->setType($bar);
        $chart->getPlotArea()->getAxisX()->setTitle('');
        $chart->getPlotArea()->getAxisY()->setTitle('');
    }

    private function encabezadoSlide(
        \PhpOffice\PhpPresentation\Slide $slide,
        string $titulo,
        string $subtitulo,
    ): void {
        $shape = $slide->createRichTextShape()
            ->setHeight(60)
            ->setWidth(900)
            ->setOffsetX(30)
            ->setOffsetY(15);
        $run = $shape->createTextRun($titulo);
        $run->getFont()->setBold(true)->setSize(18)->setColor(new Color('FF1B4F72'));
        if (trim($subtitulo) !== '') {
            $shape->createBreak();
            $runSub = $shape->createTextRun($subtitulo);
            $runSub->getFont()->setSize(12)->setColor(new Color('FF555555'));
        }
    }

    private function slideSinDatos(\PhpOffice\PhpPresentation\Slide $slide): void
    {
        $shape = $slide->createRichTextShape()
            ->setHeight(80)
            ->setWidth(800)
            ->setOffsetX(80)
            ->setOffsetY(250);
        $shape->getActiveParagraph()->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $run = $shape->createTextRun('Sin datos para graficar en este período.');
        $run->getFont()->setSize(16)->setItalic(true)->setColor(new Color('FF888888'));
    }

    /**
     * @param  array{labels?:list<string>,values?:list<float|int|string>}  $chartData
     * @return array<string, string>
     */
    private function seriesDesdeChart(array $chartData): array
    {
        $labels = $chartData['labels'] ?? [];
        $values = $chartData['values'] ?? [];
        if (! is_array($labels) || ! is_array($values) || $labels === [] || $values === []) {
            return [];
        }

        $out = [];
        $n = min(count($labels), count($values));
        for ($i = 0; $i < $n; $i++) {
            $label = trim((string) $labels[$i]);
            if ($label === '') {
                $label = 'Ítem '.($i + 1);
            }
            // Claves únicas: Series usa el label como categoría.
            if (array_key_exists($label, $out)) {
                $label = $label.' ('.($i + 1).')';
            }
            $valor = (float) $values[$i];
            if (abs($valor) <= 0.0001) {
                continue;
            }
            // Truncar etiquetas muy largas para el eje.
            if (mb_strlen($label) > 48) {
                $label = rtrim(mb_substr($label, 0, 47)).'…';
            }
            $out[$label] = (string) round($valor, 2);
        }

        return $out;
    }

    private function aplicarPaleta(Series $series, int $cantidad): void
    {
        for ($i = 0; $i < $cantidad; $i++) {
            $color = self::PALETA[$i % count(self::PALETA)];
            $series->getDataPointFill($i)
                ->setFillType(Fill::FILL_SOLID)
                ->setStartColor(new Color($color));
        }
    }
}
