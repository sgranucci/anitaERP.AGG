<?php

declare(strict_types=1);

namespace App\Services\Contable\Sicore;

use App\Models\Configuracion\Empresa;
use App\Support\Contable\Sicore\SicoreCriteriosSupport;
use App\Support\Contable\Sicore\SicoreFormatoV8Support;
use App\Support\Contable\Sicore\SicoreLiquidacionCuadroSupport;
use App\Support\Contable\Sicore\SicoreLiquidacionQuincenasSupport;
use Illuminate\Support\Facades\View;
use Jurosh\PDFMerge\PDFMerger;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

final class SicoreLiquidacionService
{
    public function __construct(
        private readonly SicoreReporteService $reporteService,
    ) {
    }

    /**
     * Arma liquidación quincenal + PDF combinado (liquidación + compras + sueldos).
     *
     * Rangos:
     * - compras: fecha_desde/fecha_hasta o compras_fecha_*
     * - sueldos: sueldos_fecha_* (obligatorio vía modal; fallback mes anterior)
     *
     * @param  array<string, mixed>  $filtros
     * @return array{ruta_pdf: string, ruta_xlsx: string, nombre_pdf: string, valores: array, autocontrol: array, periodo: string, empresa: string, desde_cache_compras: bool, desde_cache_sueldos: bool}
     */
    public function generarCompleta(array $filtros): array
    {
        $empresaId = (int) ($filtros['empresa_id'] ?? 0);
        $rangos = SicoreLiquidacionQuincenasSupport::resolverRangosLiquidacion($filtros);
        $comprasDesde = $rangos['compras_desde'];
        $comprasHasta = $rangos['compras_hasta'];
        $sueldosDesde = $rangos['sueldos_desde'];
        $sueldosHasta = $rangos['sueldos_hasta'];

        if ($empresaId <= 0 || $comprasDesde === '' || $comprasHasta === ''
            || $sueldosDesde === '' || $sueldosHasta === '') {
            throw new \InvalidArgumentException(
                'Empresa y rangos de Compras y Sueldos (4ta categoría) son obligatorios para la liquidación.'
            );
        }

        $filtrosCompras = array_merge($filtros, [
            'criterio' => SicoreCriteriosSupport::COMPRAS,
            'conciliar_contable' => true,
            'fecha_desde' => $comprasDesde,
            'fecha_hasta' => $comprasHasta,
        ]);
        $filtrosSueldos = array_merge($filtros, [
            'criterio' => SicoreCriteriosSupport::SUELDOS,
            'conciliar_contable' => true,
            'fecha_desde' => $sueldosDesde,
            'fecha_hasta' => $sueldosHasta,
        ]);

        $resCompras = $this->reporteService->generarOCache($filtrosCompras);
        $resSueldos = $this->reporteService->generarOCache($filtrosSueldos);

        $regsCompras = $resCompras['registros'] ?? [];
        $regsSueldos = $resSueldos['registros'] ?? [];

        $part767 = SicoreLiquidacionQuincenasSupport::repartirCodigo($regsCompras, SicoreLiquidacionQuincenasSupport::CODIGO_IVA);
        $part217 = SicoreLiquidacionQuincenasSupport::repartirCodigo($regsCompras, SicoreLiquidacionQuincenasSupport::CODIGO_GANANCIAS);
        $part787 = SicoreLiquidacionQuincenasSupport::repartirCodigo($regsSueldos, SicoreLiquidacionQuincenasSupport::CODIGO_SUELDOS);

        $valores = [
            '767' => [$part767['q1'], $part767['q2']],
            '217' => [$part217['q1'], $part217['q2']],
            '787' => [$part787['q1'], $part787['q2']],
        ];

        $autocontrol = $this->autocontrol(
            [
                '767' => ['part' => $part767, 'conciliacion' => $resCompras['conciliacion'] ?? []],
                '217' => ['part' => $part217, 'conciliacion' => $resCompras['conciliacion'] ?? []],
                '787' => ['part' => $part787, 'conciliacion' => $resSueldos['conciliacion'] ?? []],
            ],
        );

        $empresa = Empresa::query()->find($empresaId);
        $empresaLabel = $this->etiquetaEmpresa($empresa);
        $periodoLabel = SicoreLiquidacionQuincenasSupport::etiquetaPeriodo($comprasDesde, $comprasHasta);
        $estructura = SicoreLiquidacionCuadroSupport::armarEstructura($valores);

        $dir = storage_path('pdf/listados/sicore_liquidacion');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $stamp = date('Ymd_His');
        $base = 'sicore_liq_'.$empresaId.'_'.$stamp;
        $rutaXlsx = $dir.'/'.$base.'.xlsx';
        $rutaLiqPdf = $dir.'/'.$base.'_liquidacion.pdf';
        $rutaComprasPdf = $dir.'/'.$base.'_compras.pdf';
        $rutaSueldosPdf = $dir.'/'.$base.'_sueldos.pdf';
        $rutaFinal = $dir.'/'.$base.'_completa.pdf';

        $wb = SicoreLiquidacionCuadroSupport::buildWorkbook($empresaLabel, $periodoLabel, $valores);
        (new Xlsx($wb))->save($rutaXlsx);

        $this->guardarPdfLiquidacion($rutaLiqPdf, $empresaLabel, $periodoLabel, $estructura, $autocontrol);
        $this->guardarPdfListado(
            $rutaComprasPdf,
            $resCompras,
            $empresa,
            'SICORE — Compras (retenciones IVA y ganancias)',
            $comprasDesde,
            $comprasHasta,
            false,
        );
        $this->guardarPdfListado(
            $rutaSueldosPdf,
            $resSueldos,
            $empresa,
            'SICORE — Sueldos (4ta categoría)',
            $sueldosDesde,
            $sueldosHasta,
            true,
        );

        // jurosh/pdf-merge default = vertical: si se fuerza P sobre listados legal landscape, corta el borde derecho.
        $merger = new PDFMerger;
        $merger->addPDF($rutaLiqPdf, 'all', 'vertical');
        $merger->addPDF($rutaComprasPdf, 'all', 'horizontal');
        $merger->addPDF($rutaSueldosPdf, 'all', 'horizontal');
        $merger->merge('file', $rutaFinal);

        foreach ([$rutaLiqPdf, $rutaComprasPdf, $rutaSueldosPdf] as $tmp) {
            if (is_file($tmp)) {
                @unlink($tmp);
            }
        }

        return [
            'ruta_pdf' => $rutaFinal,
            'ruta_xlsx' => $rutaXlsx,
            'nombre_pdf' => 'SICORE - Liquidacion completa.pdf',
            'valores' => $valores,
            'autocontrol' => $autocontrol,
            'periodo' => $periodoLabel,
            'empresa' => $empresaLabel,
            'desde_cache_compras' => ! empty($resCompras['desde_cache']),
            'desde_cache_sueldos' => ! empty($resSueldos['desde_cache']),
            'compras_desde' => $comprasDesde,
            'compras_hasta' => $comprasHasta,
            'sueldos_desde' => $sueldosDesde,
            'sueldos_hasta' => $sueldosHasta,
        ];
    }

    /**
     * @param  array<string, array{part: array, conciliacion: array}>  $porCodigo
     * @return array{ok: bool, items: list<array<string, mixed>>}
     */
    private function autocontrol(array $porCodigo): array
    {
        $items = [];
        $okGlobal = true;
        foreach ($porCodigo as $cod => $data) {
            $part = $data['part'];
            $totalDetalle = (float) ($part['total'] ?? 0);
            $itemConc = $this->itemConciliacionPorCodigo($data['conciliacion'], (int) $cod);
            $totalSicore = (float) ($itemConc['total_sicore'] ?? $totalDetalle);
            $totalMayor = (float) ($itemConc['total_mayor'] ?? 0);
            $estado = ! empty($itemConc['cuadra']) ? 'Cuadra' : 'Diferencia';
            $okDet = SicoreFormatoV8Support::cuadra($totalDetalle, $totalSicore);
            $okMay = $itemConc === [] || SicoreFormatoV8Support::cuadra($totalSicore, $totalMayor);
            $ok = $okDet && $okMay;
            $okGlobal = $okGlobal && $ok;
            $items[] = [
                'codigo' => $cod,
                'total_detalle' => $totalDetalle,
                'total_sicore' => $totalSicore,
                'total_mayor' => $totalMayor,
                'estado' => $estado,
                'ok' => $ok,
            ];
        }

        return ['ok' => $okGlobal, 'items' => $items];
    }

    /**
     * @param  array<string, mixed>  $conciliacion
     * @return array<string, mixed>
     */
    private function itemConciliacionPorCodigo(array $conciliacion, int $codigo): array
    {
        foreach ($conciliacion['items'] ?? [] as $item) {
            if ((int) ($item['codigo_impuesto'] ?? 0) === $codigo) {
                return $item;
            }
        }

        return [];
    }

    private function etiquetaEmpresa(?Empresa $empresa): string
    {
        if ($empresa === null) {
            return 'Empresa';
        }
        $nombre = trim((string) ($empresa->nombre ?? 'Empresa'));
        $cuit = preg_replace('/\D/', '', (string) ($empresa->nroinscripcion ?? '')) ?? '';
        if (strlen($cuit) === 11) {
            $cuitFmt = substr($cuit, 0, 2).'-'.substr($cuit, 2, 8).'-'.substr($cuit, 10, 1);

            return $nombre.' ('.$cuitFmt.')';
        }

        return $nombre;
    }

    /**
     * @param  array<string, mixed>  $estructura
     * @param  array<string, mixed>  $autocontrol
     */
    private function guardarPdfLiquidacion(
        string $ruta,
        string $empresaLabel,
        string $periodoLabel,
        array $estructura,
        array $autocontrol,
    ): void {
        $html = View::make('contable.sicore.liquidacion', [
            'empresaLabel' => $empresaLabel,
            'periodoLabel' => $periodoLabel,
            'estructura' => $estructura,
            'autocontrol' => $autocontrol,
        ])->render();
        $pdf = \App::make('dompdf.wrapper');
        $pdf->setPaper('a4', 'portrait');
        $pdf->loadHTML($html, 'UTF-8')->save($ruta);
    }

    /**
     * @param  array<string, mixed>  $resultado
     */
    private function guardarPdfListado(
        string $ruta,
        array $resultado,
        ?Empresa $empresa,
        string $titulo,
        string $fechaDesde,
        string $fechaHasta,
        bool $ocultarRazonSocial = false,
    ): void {
        $nombreEmpresa = (string) ($empresa->nombre ?? '');
        $filasParaLogo = [(object) ['nombreempresa' => $nombreEmpresa]];
        $periodo = \Carbon\Carbon::parse($fechaDesde)->format('d/m/Y')
            .' — '.\Carbon\Carbon::parse($fechaHasta)->format('d/m/Y');
        $html = View::make('contable.sicore.listado', [
            'filasParaLogo' => $filasParaLogo,
            'registros' => $resultado['registros'] ?? [],
            'totales' => $resultado['totales'] ?? [],
            'conciliacion' => $resultado['conciliacion'] ?? [],
            'titulo' => $titulo,
            'subtitulo' => trim($nombreEmpresa.' — '.$periodo),
            'ocultarRazonSocial' => $ocultarRazonSocial,
        ])->render();
        $pdf = \App::make('dompdf.wrapper');
        $pdf->setPaper('legal', 'landscape');
        $pdf->loadHTML($html, 'UTF-8')->save($ruta);
    }
}
