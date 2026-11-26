<?php

declare(strict_types=1);

namespace App\Services\Contable\Suss;

use App\Models\Configuracion\Empresa;
use App\Models\Contable\Suss_Empresa_Config;
use App\Repositories\Contable\Suss_Presentacion_ConfigRepositoryInterface;
use App\Support\Contable\Sicore\SicoreLiquidacionTipoSupport;
use App\Support\Contable\Sicore\SicoreSaldoEjercicioSupport;
use App\Support\Contable\Suss\SussFormatoF2004Support;
use App\Support\Contable\Suss\SussListadoFiltros;
use App\Support\Contable\Suss\SussLiquidacionTipoSupport;
use Illuminate\Support\Facades\View;
use RuntimeException;
use ZipArchive;

/**
 * Liquidación SUSS 1Q (pago a cuenta) y DJ mensual (2Q + F.996).
 *
 * 1Q: F2004 de la 1ra quincena + PDF con detalle y total (= pago a cuenta).
 * DJ: F2004 de la 2da quincena (la 1Q ya está en SIRE) + PDF F.996 armado (determinado − SAF − pago a cuenta).
 */
final class SussLiquidacionService
{
    public function __construct(
        private readonly SussReporteService $reporteService,
        private readonly Suss_Presentacion_ConfigRepositoryInterface $configRepository,
        private readonly SicoreSaldoEjercicioSupport $saldoEjercicioSupport,
    ) {}

    /**
     * Calcula el cuadro (sin archivos). Lo usan la liquidación ZIP y la solicitud de pago.
     *
     * Entradas en $filtros (además de los de SussListadoFiltros):
     * tipo_liquidacion, saldo_favor_anterior, pago_cuenta, pago_cuenta_fecha (Y-m-d o d/m/Y).
     *
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    public function calcularCuadro(array $filtros): array
    {
        $empresaId = (int) ($filtros['empresa_id'] ?? 0);
        $periodo = (string) ($filtros['periodo'] ?? '');
        if ($empresaId <= 0 || strlen($periodo) !== 6) {
            throw new RuntimeException('Falta la empresa o el período para la liquidación SUSS.');
        }

        $tipo = SussLiquidacionTipoSupport::normalizarTipo((string) ($filtros['tipo_liquidacion'] ?? ''));
        $esDj = SussLiquidacionTipoSupport::esDj($tipo);
        $liq = SussLiquidacionTipoSupport::liquidacionParaTipo($tipo);
        $filtrosLiq = $this->filtrosLiquidacion($filtros, $liq);

        $res = $this->reporteService->generar($filtrosLiq);
        if (! empty($res['mensaje_config']) || ! isset($res['config'])) {
            throw new RuntimeException((string) ($res['mensaje_config'] ?? 'No hay configuración activa de SUSS.'));
        }

        $registros = $res['registros'] ?? [];
        $partes = SussLiquidacionTipoSupport::separarQuincenas($registros);
        $totalQ1 = SussLiquidacionTipoSupport::sumaImporte($partes['q1']);
        $totalQ2 = $esDj ? SussLiquidacionTipoSupport::sumaImporte($partes['q2']) : 0.0;

        $saf = $esDj ? SussLiquidacionTipoSupport::parseImporte($filtros['saldo_favor_anterior'] ?? 0) : 0.0;
        $pago = $esDj ? SussLiquidacionTipoSupport::parseImporte($filtros['pago_cuenta'] ?? 0) : 0.0;
        $pagoFecha = $esDj ? $this->fechaIso((string) ($filtros['pago_cuenta_fecha'] ?? '')) : '';
        $pagoAplicaHasta = $pagoFecha !== '' && $pagoFecha <= (string) $filtrosLiq['fecha_hasta'];

        $cuadro = SussLiquidacionTipoSupport::armarCuadro($tipo, $totalQ1, $totalQ2, $saf, $pago, $pagoFecha);

        $tolerancia = SussFormatoF2004Support::tolerancia();
        $controles = ['bloquea' => false, 'mensaje' => null, 'avisos' => []];
        $conciliacion = $res['conciliacion'] ?? ['habilitada' => false, 'items' => []];
        if ($esDj) {
            $pagoEnMayor = $this->pagoCuentaEnMayorQ1(
                $empresaId,
                $periodo,
                $this->codigosCuentas($res['config']),
            );
            $controles = SussLiquidacionTipoSupport::controlPagoCuentaDj($pago, $totalQ1, $pagoEnMayor, $tolerancia);
            $conciliacion = SussLiquidacionTipoSupport::ajustarConciliacionDj(
                $conciliacion,
                $saf,
                $pago,
                $pagoAplicaHasta,
                $tolerancia,
            );
        }

        $empresa = Empresa::query()->find($empresaId);
        $cfg = Suss_Empresa_Config::paraEmpresa($empresaId);
        $sigla = trim((string) ($cfg?->sigla ?? ''));
        if ($sigla === '') {
            $sigla = SicoreLiquidacionTipoSupport::empresaCorta((string) ($empresa->nombre ?? ''));
        }

        $cuit = (string) ($res['cuit_agente'] ?? '');
        $sufijo = SussLiquidacionTipoSupport::sufijoQuincena($tipo);
        if ($esDj) {
            $registrosF2004 = $partes['q2'];
            $archivoF2004 = SussFormatoF2004Support::generarArchivo($registrosF2004, $cuit);
            $nombreF2004 = SussFormatoF2004Support::nombreArchivo($cuit, $periodo, '2Q');
        } else {
            $registrosF2004 = $registros;
            $archivoF2004 = (string) ($res['archivo_f2004'] ?? '');
            $nombreF2004 = (string) ($res['nombre_archivo'] ?? SussFormatoF2004Support::nombreArchivo($cuit, $periodo, '1Q'));
        }

        return [
            'empresa_id' => $empresaId,
            'empresa' => $empresa,
            'empresa_label' => $this->etiquetaEmpresa($empresa),
            'sigla' => $sigla,
            'cuit' => $cuit,
            'tipo' => $tipo,
            'es_dj' => $esDj,
            'periodo' => $periodo,
            'periodo_label' => SussLiquidacionTipoSupport::etiquetaPeriodo($periodo),
            'periodo_mmyyyy' => SussLiquidacionTipoSupport::periodoMmYyyy($periodo),
            'sufijo' => $sufijo,
            'fecha_desde' => $filtrosLiq['fecha_desde'],
            'fecha_hasta' => $filtrosLiq['fecha_hasta'],
            'registros_q1' => $partes['q1'],
            'registros_q2' => $partes['q2'],
            'registros_f2004' => $registrosF2004,
            'cantidad_q1' => count($partes['q1']),
            'cantidad_q2' => count($partes['q2']),
            'total_q1' => $totalQ1,
            'total_q2' => $totalQ2,
            'cuadro' => $cuadro,
            'vep_esperado' => (float) $cuadro['vep'],
            'pago_aplica_hasta' => $pagoAplicaHasta,
            'controles' => $controles,
            'conciliacion' => $conciliacion,
            'archivo_f2004' => $archivoF2004,
            'nombre_f2004' => $nombreF2004,
            'nombre_pdf_zip' => $this->nombrePdfZip($sigla, $tipo, $periodo),
            'nombre_zip' => SussLiquidacionTipoSupport::nombreZip($sigla, $tipo, $periodo),
        ];
    }

    /**
     * Liquidación completa: ZIP con F2004 + PDF de liquidación.
     *
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    public function generarCompleta(array $filtros): array
    {
        $calc = $this->calcularCuadro($filtros);
        if (! empty($calc['controles']['bloquea'])) {
            throw new RuntimeException((string) $calc['controles']['mensaje']);
        }
        $rutaPdf = $this->generarPdfLiquidacion($calc);

        $dir = storage_path('pdf/listados/suss_liquidacion');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $rutaZip = $dir.'/suss_liq_'.$calc['empresa_id'].'_'.date('Ymd_His').'_'.uniqid().'.zip';

        $this->empaquetarZip($rutaZip, [
            (string) $calc['nombre_f2004'] => (string) $calc['archivo_f2004'],
            (string) $calc['nombre_pdf_zip'] => $rutaPdf,
        ]);

        @unlink($rutaPdf);

        return [
            'ruta_zip' => $rutaZip,
            'nombre_zip' => $calc['nombre_zip'],
            'calculo' => $calc,
        ];
    }

    /**
     * PDF de liquidación en storage temporal (lo usa el ZIP y se adjunta a la solicitud de pago).
     *
     * @param  array<string, mixed>  $calc  salida de calcularCuadro()
     */
    public function generarPdfLiquidacion(array $calc): string
    {
        $dir = storage_path('pdf/listados/suss_liquidacion');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $ruta = $dir.'/suss_pdf_'.$calc['empresa_id'].'_'.date('Ymd_His').'_'.uniqid().'.pdf';

        $html = View::make('contable.suss.liquidacion', [
            'calc' => $calc,
            'titulo' => $calc['es_dj'] ? 'Liquidación SUSS — DJ mensual (F.996)' : 'Liquidación SUSS — 1Q (pago a cuenta)',
        ])->render();

        $pdf = \App::make('dompdf.wrapper');
        $pdf->setPaper('a4', 'portrait');
        $pdf->loadHTML($html, 'UTF-8')->save($ruta);

        return $ruta;
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    private function filtrosLiquidacion(array $filtros, int $liquidacion): array
    {
        [$desde, $hasta] = SussListadoFiltros::rangoDesdeLiquidacion((string) $filtros['periodo'], $liquidacion);

        return array_merge($filtros, [
            'liquidacion' => $liquidacion,
            'fecha_desde' => $desde,
            'fecha_hasta' => $hasta,
            'conciliar_contable' => true,
        ]);
    }

    /**
     * Pago a cuenta de la 1Q ya asentado en el mayor del mes (detalle «Retenciones SUSS 1Q MM.AAAA»).
     *
     * @param  list<int>  $codigos
     */
    public function pagoCuentaEnMayorQ1(int $empresaId, string $periodoYm, array $codigos): float
    {
        if ($codigos === [] || strlen($periodoYm) !== 6) {
            return 0.0;
        }
        $desde = sprintf('%s-%s-01', substr($periodoYm, 0, 4), substr($periodoYm, 4, 2));
        $hasta = date('Y-m-t', strtotime($desde));
        $clave = SussLiquidacionTipoSupport::claveMes($periodoYm);

        $suma = 0.0;
        foreach ($this->saldoEjercicioSupport->lineasPeriodo($empresaId, $desde, $hasta, $codigos) as $linea) {
            $debe = (float) ($linea['debe'] ?? 0);
            if ($debe < 0.005) {
                continue;
            }
            $p = SussLiquidacionTipoSupport::periodoDeDetalleSuss((string) ($linea['detalle'] ?? ''));
            if ($p !== null && $p['quincena'] === '1Q' && ($p['anio'] * 100 + $p['mes']) === $clave) {
                $suma += $debe;
            }
        }

        return round($suma, 2);
    }

    /**
     * @return list<int>
     */
    private function codigosCuentas(object $config): array
    {
        $cuentas = $config->cuentas
            ->map(static fn ($c) => ['codigo' => $c->cuentacontable?->codigo ?? ''])
            ->values()
            ->all();

        return $this->saldoEjercicioSupport->codigosNumericos($cuentas);
    }

    /**
     * @param  array<string, string>  $archivos  nombre en zip => contenido o ruta de archivo
     */
    private function empaquetarZip(string $rutaZip, array $archivos): void
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('ZipArchive no está disponible en este servidor.');
        }
        $zip = new ZipArchive;
        if ($zip->open($rutaZip, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('No se pudo crear el ZIP de liquidación SUSS.');
        }
        foreach ($archivos as $nombre => $origen) {
            if (is_file($origen)) {
                $zip->addFile($origen, $nombre);
            } else {
                $zip->addFromString($nombre, $origen);
            }
        }
        $zip->close();
    }

    private function nombrePdfZip(string $sigla, string $tipo, string $periodoYm): string
    {
        $etiqueta = SussLiquidacionTipoSupport::esDj($tipo)
            ? '2Q (DJ)'
            : '1Q (pago a cuenta)';

        return sprintf(
            'Liquidacion SUSS %s %s %s.pdf',
            $sigla,
            $etiqueta,
            SussLiquidacionTipoSupport::periodoMmYyyy($periodoYm, '-'),
        );
    }

    private function fechaIso(string $valor): string
    {
        $valor = trim($valor);
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $valor)) {
            return $valor;
        }
        if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $valor, $m)) {
            return sprintf('%04d-%02d-%02d', (int) $m[3], (int) $m[2], (int) $m[1]);
        }

        return '';
    }

    private function etiquetaEmpresa(?Empresa $empresa): string
    {
        if ($empresa === null) {
            return 'Empresa';
        }
        $nombre = trim((string) ($empresa->nombre ?? 'Empresa'));
        $cuit = preg_replace('/\D/', '', (string) ($empresa->nroinscripcion ?? '')) ?? '';
        if (strlen($cuit) === 11) {
            return $nombre.' ('.substr($cuit, 0, 2).'-'.substr($cuit, 2, 8).'-'.substr($cuit, 10, 1).')';
        }

        return $nombre;
    }
}
