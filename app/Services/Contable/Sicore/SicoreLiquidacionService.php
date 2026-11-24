<?php

declare(strict_types=1);

namespace App\Services\Contable\Sicore;

use App\Models\Configuracion\Empresa;
use App\Support\Contable\Sicore\SicoreCriteriosSupport;
use App\Support\Contable\Sicore\SicoreFormatoV8Support;
use App\Support\Contable\Sicore\SicoreLiquidacionAutocontrolSupport;
use App\Support\Contable\Sicore\SicoreLiquidacionCuadroSupport;
use App\Support\Contable\Sicore\SicoreLiquidacionQuincenasSupport;
use App\Support\Contable\Sicore\SicoreLiquidacionTipoSupport;
use Illuminate\Support\Facades\View;
use Jurosh\PDFMerge\PDFMerger;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use ZipArchive;

final class SicoreLiquidacionService
{
    public function __construct(
        private readonly SicoreReporteService $reporteService,
    ) {}

    /**
     * Calcula el cuadro 1Q/DJ (sin generar archivos). Usado por liquidación ZIP y por solicitud de pago.
     *
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    public function calcularCuadro(array $filtros): array
    {
        $empresaId = (int) ($filtros['empresa_id'] ?? 0);
        $tipo = SicoreLiquidacionTipoSupport::normalizarTipo((string) ($filtros['tipo_liquidacion'] ?? ''));
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

        $safIng = SicoreLiquidacionTipoSupport::parsearSafIngresos($tipo, $filtros);

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

        $resCompras = $this->reporteService->generar($filtrosCompras);
        $resSueldos = $this->reporteService->generar($filtrosSueldos);

        $regsCompras = $resCompras['registros'] ?? [];
        $regsSueldos = $resSueldos['registros'] ?? [];

        $part767 = SicoreLiquidacionQuincenasSupport::repartirCodigo($regsCompras, SicoreLiquidacionQuincenasSupport::CODIGO_IVA);
        $part217 = SicoreLiquidacionQuincenasSupport::repartirCodigo($regsCompras, SicoreLiquidacionQuincenasSupport::CODIGO_GANANCIAS);
        $part787 = SicoreLiquidacionQuincenasSupport::repartirCodigo($regsSueldos, SicoreLiquidacionQuincenasSupport::CODIGO_SUELDOS);

        $porCodigo = [
            '767' => $part767,
            '217' => $part217,
            '787' => $part787,
        ];
        $valores = [
            '767' => [$part767['q1'], $part767['q2']],
            '217' => [$part217['q1'], $part217['q2']],
            '787' => [$part787['q1'], $part787['q2']],
        ];

        $cuadro = SicoreLiquidacionTipoSupport::armarCuadro(
            $tipo,
            $porCodigo,
            $safIng['saf'],
            $safIng['ingresos'],
            $comprasDesde,
            $comprasHasta,
            $sueldosDesde,
            $sueldosHasta,
        );

        $autocontrol = SicoreLiquidacionAutocontrolSupport::evaluar(
            [
                '767' => ['part' => $part767, 'conciliacion' => $resCompras['conciliacion'] ?? []],
                '217' => ['part' => $part217, 'conciliacion' => $resCompras['conciliacion'] ?? []],
                '787' => ['part' => $part787, 'conciliacion' => $resSueldos['conciliacion'] ?? []],
            ],
            $tipo,
            $safIng['saf'],
            $safIng['ingresos'],
        );

        $empresa = Empresa::query()->find($empresaId);
        $empresaLabel = $this->etiquetaEmpresa($empresa);
        $empresaCorta = SicoreLiquidacionTipoSupport::empresaCorta((string) ($empresa->nombre ?? ''));
        $periodoLabel = $this->etiquetaPeriodoTipo($tipo, $comprasDesde, $comprasHasta, $sueldosDesde, $sueldosHasta);

        return [
            'empresa_id' => $empresaId,
            'empresa' => $empresa,
            'empresa_label' => $empresaLabel,
            'empresa_corta' => $empresaCorta,
            'tipo' => $tipo,
            'compras_desde' => $comprasDesde,
            'compras_hasta' => $comprasHasta,
            'sueldos_desde' => $sueldosDesde,
            'sueldos_hasta' => $sueldosHasta,
            'valores' => $valores,
            'cuadro' => $cuadro,
            'saldo_pagar' => $cuadro['saldo_pagar'] ?? [],
            'total_a_pagar' => (float) ($cuadro['total_a_pagar'] ?? 0),
            'autocontrol' => $autocontrol,
            'periodo' => $periodoLabel,
            'res_compras' => $resCompras,
            'res_sueldos' => $resSueldos,
            'regs_compras' => $regsCompras,
            'regs_sueldos' => $regsSueldos,
        ];
    }

    /**
     * Liquidación 1Q / DJ: PDF combinado + TXT compras/sueldos en un ZIP.
     *
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    public function generarCompleta(array $filtros): array
    {
        $calc = $this->calcularCuadro($filtros);
        $empresaId = (int) $calc['empresa_id'];
        $tipo = (string) $calc['tipo'];
        $comprasDesde = (string) $calc['compras_desde'];
        $comprasHasta = (string) $calc['compras_hasta'];
        $sueldosDesde = (string) $calc['sueldos_desde'];
        $sueldosHasta = (string) $calc['sueldos_hasta'];
        $valores = $calc['valores'];
        $cuadro = $calc['cuadro'];
        $autocontrol = $calc['autocontrol'];
        $empresa = $calc['empresa'];
        $empresaLabel = (string) $calc['empresa_label'];
        $empresaCorta = (string) $calc['empresa_corta'];
        $periodoLabel = (string) $calc['periodo'];
        $resCompras = $calc['res_compras'];
        $resSueldos = $calc['res_sueldos'];
        $regsCompras = $calc['regs_compras'];
        $regsSueldos = $calc['regs_sueldos'];
        $estructuraLegacy = SicoreLiquidacionCuadroSupport::armarEstructura($valores);

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
        $rutaFinalPdf = $dir.'/'.$base.'_completa.pdf';
        $rutaZip = $dir.'/'.$base.'.zip';

        $wb = SicoreLiquidacionCuadroSupport::buildWorkbook($empresaLabel, $periodoLabel, $valores);
        (new Xlsx($wb))->save($rutaXlsx);

        $this->guardarPdfLiquidacion(
            $rutaLiqPdf,
            $empresaLabel,
            $periodoLabel,
            $cuadro,
            $estructuraLegacy,
            $autocontrol,
        );
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

        $merger = new PDFMerger;
        $merger->addPDF($rutaLiqPdf, 'all', 'vertical');
        $merger->addPDF($rutaComprasPdf, 'all', 'horizontal');
        $merger->addPDF($rutaSueldosPdf, 'all', 'horizontal');
        $merger->merge('file', $rutaFinalPdf);

        $txtCompras = (string) ($resCompras['archivo_v8'] ?? SicoreFormatoV8Support::generarArchivo($regsCompras));
        $txtSueldos = (string) ($resSueldos['archivo_v8'] ?? SicoreFormatoV8Support::generarArchivo($regsSueldos));
        $this->empaquetarZip($rutaZip, [
            'liquidacion_sicore.pdf' => $rutaFinalPdf,
            'csicore.dat' => $txtCompras,
            'ssicore.dat' => $txtSueldos,
        ]);

        foreach ([$rutaLiqPdf, $rutaComprasPdf, $rutaSueldosPdf] as $tmp) {
            if (is_file($tmp)) {
                @unlink($tmp);
            }
        }

        $nombreZip = SicoreLiquidacionTipoSupport::nombreZip($empresaCorta, $tipo, $comprasDesde);

        return [
            'ruta_zip' => $rutaZip,
            'nombre_zip' => $nombreZip,
            'ruta_pdf' => $rutaFinalPdf,
            'ruta_xlsx' => $rutaXlsx,
            'nombre_pdf' => 'SICORE - Liquidacion completa.pdf',
            'valores' => $valores,
            'cuadro' => $cuadro,
            'saldo_pagar' => $cuadro['saldo_pagar'] ?? [],
            'tipo' => $tipo,
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
     * @param  array<string, string>  $archivos  nombre en zip => ruta archivo o contenido string
     */
    private function empaquetarZip(string $rutaZip, array $archivos): void
    {
        if (! class_exists(ZipArchive::class)) {
            throw new \RuntimeException('ZipArchive no está disponible en este servidor.');
        }
        $zip = new ZipArchive;
        if ($zip->open($rutaZip, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('No se pudo crear el ZIP de liquidación SICORE.');
        }
        foreach ($archivos as $nombre => $origen) {
            if (is_file($origen)) {
                $zip->addFile($origen, $nombre);
            } else {
                $zip->addFromString($nombre, (string) $origen);
            }
        }
        $zip->close();
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

    private function etiquetaPeriodoTipo(
        string $tipo,
        string $comprasDesde,
        string $comprasHasta,
        string $sueldosDesde,
        string $sueldosHasta,
    ): string {
        $base = SicoreLiquidacionQuincenasSupport::etiquetaPeriodo($comprasDesde, $comprasHasta);
        $sueldosLabel = '';
        try {
            $sueldosLabel = \Carbon\Carbon::parse($sueldosDesde !== '' ? $sueldosDesde : $sueldosHasta)->format('m/Y');
        } catch (\Throwable) {
            $sueldosLabel = $sueldosDesde;
        }

        if (SicoreLiquidacionTipoSupport::esDj($tipo)) {
            return $base.'  ·  DJ mensual (sueldos '.$sueldosLabel.')';
        }

        return $base.'  ·  Pago a cuenta (sueldos '.$sueldosLabel.')';
    }

    /**
     * @param  array<string, mixed>  $cuadro
     * @param  array<string, mixed>  $estructuraLegacy
     * @param  array<string, mixed>  $autocontrol
     */
    private function guardarPdfLiquidacion(
        string $ruta,
        string $empresaLabel,
        string $periodoLabel,
        array $cuadro,
        array $estructuraLegacy,
        array $autocontrol,
    ): void {
        $html = View::make('contable.sicore.liquidacion', [
            'empresaLabel' => $empresaLabel,
            'periodoLabel' => $periodoLabel,
            'cuadro' => $cuadro,
            'estructura' => $estructuraLegacy,
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
