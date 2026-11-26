<?php

declare(strict_types=1);

namespace App\Services\Contable\IngresosBrutos;

use App\Models\Compras\Proveedor;
use App\Models\Configuracion\Empresa;
use App\Models\Contable\Iibb_Presentacion_Config;
use App\Models\Contable\IibbLiquidacionArba;
use App\Models\Contable\IibbNeteoAplicacion;
use App\Models\Contable\IibbNeteoDevolucion;
use App\Repositories\Contable\IibbSolicitudpagoConfigRepositoryInterface;
use App\Support\Contable\IngresosBrutos\IngresosBrutosFormatoArbaSupport;
use App\Support\Contable\IngresosBrutos\IngresosBrutosListadoFiltros;
use App\Support\Contable\IngresosBrutos\IngresosBrutosNeteoCalculoSupport as Calc;
use App\Support\Database\EloquentAuditDeleteSupport;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;
use RuntimeException;
use ZipArchive;

/**
 * Liquidación ARBA quincenal con neteo de devoluciones de retenciones.
 *
 * - calcular(): cálculo puro sobre el reporte ya generado. No escribe nada.
 * - generar(): persiste devoluciones, neteos y la liquidación; arma TXT, PDF y ZIP.
 */
final class IngresosBrutosLiquidacionArbaService
{
    public function __construct(
        private readonly IngresosBrutosNeteoService $neteo,
        private readonly IibbSolicitudpagoConfigRepositoryInterface $configArba,
    ) {
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @param  array<string, mixed>  $reporte  salida de IngresosBrutosReporteService::generar()
     * @param  array<string, array<string, mixed>>  $decisiones  por asiento_movimiento_id:
     *                                                           proveedor_id, cert, pendiente (bool), confirmo (bool)
     * @return array<string, mixed>
     */
    public function calcular(array $filtros, array $reporte, array $decisiones = []): array
    {
        $bloqueos = [];
        $advertencias = [];

        $empresaId = (int) ($filtros['empresa_id'] ?? 0);
        $periodo = substr((string) ($filtros['periodo'] ?? ''), 0, 6);
        $quincena = IngresosBrutosListadoFiltros::quincenaLote((int) ($filtros['liquidacion'] ?? 0));
        $desde = (string) ($filtros['fecha_desde'] ?? '');
        $hasta = (string) ($filtros['fecha_hasta'] ?? '');

        /** @var Iibb_Presentacion_Config|null $config */
        $config = $reporte['config'] ?? null;

        if ($quincena === 0) {
            $bloqueos[] = 'La liquidación ARBA es por quincena (1.ª o 2.ª).';
        }
        if ($empresaId <= 0 || $desde === '' || $hasta === '') {
            $bloqueos[] = 'Falta empresa o período.';
        }
        if (! $config instanceof Iibb_Presentacion_Config) {
            $bloqueos[] = 'No hay configuración IIBB activa para la provincia y el tipo.';

            return $this->respuesta(false, $bloqueos, $advertencias, $empresaId, $config, $periodo, $quincena, $desde, $hasta);
        }

        // Parámetros ARBA por empresa: días de aviso de pendientes (la tolerancia viene de la configuración IIBB).
        $cfgArba = $empresaId > 0 ? $this->configArba->vigentePorEmpresa($empresaId) : null;
        if ($cfgArba === null) {
            $bloqueos[] = 'Falta la configuración ARBA activa de esta empresa (Contable → Configuración por módulo → Configuración ARBA).';

            return $this->respuesta(false, $bloqueos, $advertencias, $empresaId, $config, $periodo, $quincena, $desde, $hasta);
        }
        $diasAviso = (int) $cfgArba->dias_aviso_pendiente;

        // Mismo criterio que generarArchivo(): los negativos no van al TXT.
        $positivos = [];
        foreach ((array) ($reporte['registros'] ?? []) as $r) {
            if ((float) ($r['importe'] ?? 0) >= -0.001) {
                $positivos[] = $r;
            }
        }

        $conc = $reporte['conciliacion']['items'][0] ?? null;
        $conciliacionHabilitada = ! empty($reporte['conciliacion']['habilitada']) && is_array($conc);
        if (! $conciliacionHabilitada) {
            $bloqueos[] = 'La conciliación contra el mayor no está habilitada (cargue las cuentas de la configuración IIBB).';
        }

        // 1) Devoluciones de la quincena
        $candidatos = $this->neteo->detectar(
            $empresaId,
            $config,
            $desde,
            $hasta,
            (array) ($reporte['registros'] ?? []),
        );

        $filas = [];
        $devsActuales = [];
        $idsMov = [];
        $sinNeteoPrevio = [];
        $proveedorPorDevolucion = [];

        foreach ($candidatos as $c) {
            $movId = (int) $c['asiento_movimiento_id'];
            $idsMov[] = $movId;
            $dec = (array) ($decisiones[(string) $movId] ?? []);

            $prov = $c['proveedor'];
            $via = $c['identificacion'];
            $certRef = $c['cert_ref'];

            $proveedorManualId = (int) ($dec['proveedor_id'] ?? 0);
            if ($proveedorManualId > 0) {
                $p = Proveedor::query()->find($proveedorManualId);
                if ($p instanceof Proveedor) {
                    $prov = $p;
                    $via = 'manual';
                } else {
                    $bloqueos[] = 'Proveedor elegido inexistente (devolución asiento '.$c['asiento_numero'].').';
                }
            }

            $certManual = (int) preg_replace('/\D+/', '', (string) ($dec['cert'] ?? ''));
            if ($certManual > 0) {
                $certRef = $certManual;
            }

            $pendiente = ! empty($dec['pendiente']);
            $confirmo = ! empty($dec['confirmo']) || in_array($via, ['proveedor', 'codigo', 'op', 'manual'], true);

            if ($pendiente) {
                $estado = 'pendiente';
            } elseif (! $prov instanceof Proveedor) {
                $estado = 'sin_identificar';
                $bloqueos[] = 'Devolución del asiento '.$c['asiento_numero'].' sin identificar: elija el proveedor o márquela como pendiente.';
            } elseif (! $confirmo) {
                $estado = 'confirmar';
                $bloqueos[] = 'Confirme el certificado sugerido para la devolución del asiento '.$c['asiento_numero'].'.';
            } else {
                $estado = 'neteo';
            }

            $devId = 'mov'.$movId;
            if ($estado === 'neteo') {
                $devsActuales[] = [
                    'id' => $devId,
                    'proveedor' => $prov instanceof Proveedor ? Calc::digitos((string) $prov->nroinscripcion) : '',
                    'importe' => $c['importe'],
                    'cert_ref' => (int) ($certRef ?? 0),
                    'fecha' => $c['fecha'],
                ];
            } else {
                $sinNeteoPrevio[$devId] = $c['importe'];
            }

            $proveedorPorDevolucion[$devId] = $prov instanceof Proveedor ? (int) $prov->id : null;

            $filas[] = [
                'asiento_movimiento_id' => $movId,
                'asiento_id' => (int) $c['asiento_id'],
                'asiento_numero' => (int) $c['asiento_numero'],
                'fecha' => $c['fecha'],
                'detalle' => (string) $c['detalle'],
                'importe' => (float) $c['importe'],
                'proveedor_id' => $prov instanceof Proveedor ? (int) $prov->id : null,
                'proveedor_label' => $prov instanceof Proveedor ? trim($prov->codigo.' '.$prov->nombre) : '',
                'identificacion' => $via,
                'cert_ref' => (int) ($certRef ?? 0),
                'estado' => $estado,
                'origen' => 'periodo',
            ];
        }

        // 2) Pendientes de quincenas anteriores (persistidos)
        $prevRows = $this->neteo->pendientesAnteriores($empresaId, (int) $config->id, $periodo, $quincena, $desde, $idsMov);
        $devsPrev = [];
        foreach ($prevRows as $p) {
            $devsPrev[] = [
                'id' => $p['id'],
                'proveedor' => $p['proveedor'],
                'importe' => $p['importe'],
                'cert_ref' => $p['cert_ref'],
                'fecha' => $p['fecha'],
            ];
            $proveedorPorDevolucion[$p['id']] = $p['proveedor_id'];
        }

        // 3) Neteo contra certificados
        $certs = [];
        foreach ($positivos as $i => $r) {
            $certs[] = [
                'key' => (string) $i,
                'proveedor' => (string) ($r['nro_documento'] ?? ''),
                'nro_cert' => (int) ($r['nro_cert'] ?? 0),
                'importe' => (float) $r['importe'],
                'base_calculo' => (float) ($r['base_calculo'] ?? 0),
                'alicuota' => (float) ($r['alicuota'] ?? 0),
            ];
        }

        $res = Calc::aplicar($certs, array_merge($devsPrev, $devsActuales));
        $aplicaciones = $res['aplicaciones'];
        $sinNeteo = $res['sin_neteo'];
        foreach ($sinNeteoPrevio as $id => $imp) {
            $sinNeteo[$id] = round((float) ($sinNeteo[$id] ?? 0) + $imp, 2);
        }

        // 4) Certificados y TXT
        $certsSalida = [];
        $lineasTxt = [];
        foreach ($res['certs'] as $key => $c) {
            $reg = $positivos[(int) $key];
            $certsSalida[] = [
                'nro_cert' => $c['nro_cert'],
                'nro_documento' => $c['proveedor'],
                'razon_social' => (string) ($reg['razon_social'] ?? ''),
                'fecha_retencion' => (string) ($reg['fecha_retencion'] ?? ''),
                'alicuota' => $c['alicuota'],
                'base_original' => $c['base_original'],
                'importe_original' => $c['importe_original'],
                'neteado' => $c['neteado'],
                'importe_nuevo' => $c['importe_nuevo'],
                'base_txt' => $c['base_txt'],
                'estado' => $c['estado'],
            ];

            if ($c['estado'] !== 'eliminado') {
                $linea = $reg;
                $linea['base_calculo'] = $c['base_txt'];
                $lineasTxt[] = $linea;
            }
        }

        // 5) Totales en centavos
        $erpC = 0;
        foreach ($positivos as $r) {
            $erpC += Calc::centavos((float) $r['importe']);
        }
        $neteoQcC = 0;
        $neteoAntC = 0;
        $etiquetas = [];
        foreach ($filas as $f) {
            $etiquetas['mov'.$f['asiento_movimiento_id']] = 'Asiento '.$f['asiento_numero'].' ('.$f['fecha'].')'
                .($f['proveedor_label'] !== '' ? ' — '.$f['proveedor_label'] : '');
        }
        foreach ($prevRows as $p) {
            $etiquetas[$p['id']] = 'Asiento '.$p['asiento_numero'].' ('.$p['fecha'].')'
                .($p['proveedor_label'] !== '' ? ' — '.$p['proveedor_label'] : '');
        }
        $aplicacionesSalida = [];
        foreach ($aplicaciones as $ap) {
            $c = Calc::centavos((float) $ap['importe']);
            if (str_starts_with((string) $ap['devolucion_id'], 'mov')) {
                $neteoQcC += $c;
            } else {
                $neteoAntC += $c;
            }
            $aplicacionesSalida[] = $ap + [
                'etiqueta' => $etiquetas[(string) $ap['devolucion_id']] ?? (string) $ap['devolucion_id'],
            ];
        }
        $pendQcC = 0;
        $pendAntC = 0;
        foreach ($sinNeteo as $id => $imp) {
            $c = Calc::centavos((float) $imp);
            if (str_starts_with((string) $id, 'mov')) {
                $pendQcC += $c;
            } else {
                $pendAntC += $c;
            }
        }
        $txtC = 0;
        foreach ($certsSalida as $c) {
            $txtC += Calc::centavos((float) $c['importe_nuevo']);
        }
        $djC = Calc::djCentavos($lineasTxt);
        $vepC = Calc::vepCentavos($djC);

        // 6) Control contra el mayor
        // Misma tolerancia que la conciliación contra el mayor (configuración IIBB).
        $tolC = Calc::centavos((float) $config->tolerancia_conciliacion);
        $residuoPeriodoC = null;
        $residuoPendientesC = null;
        $saldoMayorC = null;
        $movMayorC = null;
        if ($conciliacionHabilitada) {
            $saldoMayorC = Calc::centavos((float) ($conc['total_mayor'] ?? 0));
            $movMayorC = Calc::centavos((float) ($conc['total_mayor_periodo'] ?? 0));
            // Movimiento del período = TXT − pendientes de la quincena + neteos de anteriores.
            $residuoPeriodoC = $movMayorC - ($txtC - $pendQcC + $neteoAntC);
            // Saldo del mayor = TXT + pendientes (todos).
            $residuoPendientesC = $saldoMayorC - $txtC + ($pendQcC + $pendAntC);
        }
        $controlOk = $residuoPeriodoC !== null
            && $residuoPendientesC !== null
            && abs($residuoPeriodoC) <= $tolC
            && abs($residuoPendientesC) <= $tolC;

        if ($conciliacionHabilitada && ! $controlOk) {
            $bloqueos[] = 'La conciliación no cierra contra el mayor (residuo período '
                .number_format(Calc::pesos($residuoPeriodoC ?? 0), 2, ',', '.')
                .', residuo saldo '.number_format(Calc::pesos($residuoPendientesC ?? 0), 2, ',', '.').').';
        }

        // 7) Avisos de pendientes antiguos
        $hastaCarbon = Carbon::parse($hasta);
        $pendientesSalida = [];
        foreach ($prevRows as $p) {
            $saldo = (float) ($sinNeteo[$p['id']] ?? 0);
            if ($saldo < 0.01) {
                continue;
            }
            $dias = (int) Carbon::parse($p['fecha'])->diffInDays($hastaCarbon, false);
            $aviso = $dias > $diasAviso;
            if ($aviso) {
                $advertencias[] = 'Pendiente de '.$p['proveedor_label'].' ('.$p['fecha'].'): '
                    .$dias.' días sin neteo.';
            }
            $pendientesSalida[] = $p + [
                'pendiente' => $saldo,
                'antiguedad_dias' => $dias,
                'aviso' => $aviso,
                'origen' => 'anterior',
            ];
        }
        // Neteado y saldo sin neteo por devolución del período (para UI y persistencia)
        $neteadoPorDev = [];
        foreach ($aplicacionesSalida as $ap) {
            $neteadoPorDev[$ap['devolucion_id']] = ($neteadoPorDev[$ap['devolucion_id']] ?? 0) + Calc::centavos((float) $ap['importe']);
        }
        foreach ($filas as &$f) {
            $id = 'mov'.$f['asiento_movimiento_id'];
            $f['neteado'] = Calc::pesos((int) ($neteadoPorDev[$id] ?? 0));
            $f['pendiente'] = round((float) ($sinNeteo[$id] ?? 0), 2);
        }
        unset($f);

        foreach ($filas as $f) {
            if ($f['pendiente'] < 0.01) {
                continue;
            }
            $pendientesSalida[] = [
                'id' => 'mov'.$f['asiento_movimiento_id'],
                'asiento_numero' => $f['asiento_numero'],
                'fecha' => $f['fecha'],
                'proveedor_label' => $f['proveedor_label'],
                'pendiente' => $f['pendiente'],
                'antiguedad_dias' => 0,
                'aviso' => false,
                'origen' => 'periodo',
            ];
        }

        $totales = [
            'erp' => Calc::pesos($erpC),
            'neteo_quincena' => Calc::pesos($neteoQcC),
            'neteo_anteriores' => Calc::pesos($neteoAntC),
            'pendientes_quincena' => Calc::pesos($pendQcC),
            'pendientes_anteriores' => Calc::pesos($pendAntC),
            'txt' => Calc::pesos($txtC),
            'dj' => Calc::pesos($djC),
            'vep' => Calc::pesos($vepC),
            'movimiento_mayor' => $movMayorC !== null ? Calc::pesos($movMayorC) : null,
            'saldo_mayor' => $saldoMayorC !== null ? Calc::pesos($saldoMayorC) : null,
        ];

        $nombreTxt = (string) ($reporte['nombre_archivo'] ?? 'iibb.txt');
        $txt = '';
        foreach ($lineasTxt as $linea) {
            $txt .= IngresosBrutosFormatoArbaSupport::formatearLineaRetencion($linea);
        }

        $resultado = $this->respuesta(
            $bloqueos === [],
            $bloqueos,
            $advertencias,
            $empresaId,
            $config,
            $periodo,
            $quincena,
            $desde,
            $hasta,
        );

        return $resultado + [
            'dias_aviso' => $diasAviso,
            'devoluciones' => $filas,
            'pendientes' => $pendientesSalida,
            'aplicaciones' => $aplicacionesSalida,
            'certificados' => $certsSalida,
            'lineas_txt' => $lineasTxt,
            'txt' => $txt,
            'nombre_txt' => $nombreTxt,
            'totales' => $totales,
            'control' => [
                'habilitado' => $conciliacionHabilitada,
                'tolerancia' => Calc::pesos($tolC),
                'residuo_periodo' => $residuoPeriodoC !== null ? Calc::pesos($residuoPeriodoC) : null,
                'residuo_pendientes' => $residuoPendientesC !== null ? Calc::pesos($residuoPendientesC) : null,
                'ok' => $controlOk,
            ],
            'proveedor_por_devolucion' => $proveedorPorDevolucion,
            'devs_actuales' => $devsActuales,
        ];
    }

    /**
     * Persiste y arma los archivos de la liquidación.
     *
     * @param  array<string, mixed>  $filtros
     * @param  array<string, mixed>  $reporte
     * @param  array<string, array<string, mixed>>  $decisiones
     * @return array{liquidacion: IibbLiquidacionArba, zip_path: string, nombre_zip: string, nombre_pdf: string, calculo: array<string, mixed>}
     */
    public function generar(array $filtros, array $reporte, array $decisiones, ?int $usuarioId): array
    {
        $calc = $this->calcular($filtros, $reporte, $decisiones);
        if (! $calc['puede_liquidar']) {
            throw new RuntimeException(implode(' ', $calc['bloqueos']));
        }

        $empresaId = (int) $calc['empresa_id'];
        /** @var Iibb_Presentacion_Config $config */
        $config = $calc['config'];
        $periodo = (string) $calc['periodo'];
        $quincena = (int) $calc['quincena'];
        $desde = (string) $calc['desde'];
        $hasta = (string) $calc['hasta'];

        $empresa = Empresa::query()->find($empresaId);
        $nombreEmpresa = (string) ($empresa?->nombre ?? '');

        return DB::transaction(function () use ($calc, $empresaId, $config, $periodo, $quincena, $desde, $hasta, $usuarioId, $nombreEmpresa, $filtros) {
            $idMap = [];
            foreach ($calc['devoluciones'] as $f) {
                if (! in_array($f['estado'], ['neteo', 'pendiente'], true)) {
                    continue;
                }

                $dev = IibbNeteoDevolucion::query()->updateOrCreate(
                    [
                        'empresa_id' => $empresaId,
                        'iibb_presentacion_config_id' => (int) $config->id,
                        'asiento_movimiento_id' => (int) $f['asiento_movimiento_id'],
                    ],
                    [
                        'asiento_id' => (int) $f['asiento_id'],
                        'asiento_numero' => (int) $f['asiento_numero'],
                        'periodo' => $periodo,
                        'quincena' => $quincena,
                        'fecha' => $f['fecha'],
                        'detalle' => mb_substr((string) $f['detalle'], 0, 255),
                        'importe' => $f['importe'],
                        'proveedor_id' => $f['proveedor_id'],
                        'cert_referencia' => $f['cert_ref'] > 0 ? $f['cert_ref'] : null,
                        'identificacion' => $f['identificacion'],
                    ],
                );
                $idMap['mov'.$f['asiento_movimiento_id']] = (int) $dev->id;
            }

            // Neteos de la quincena: se recalculan completos (instancia a instancia por la auditoría).
            EloquentAuditDeleteSupport::each(
                IibbNeteoAplicacion::query()
                    ->where('empresa_id', $empresaId)
                    ->where('iibb_presentacion_config_id', (int) $config->id)
                    ->where('periodo', $periodo)
                    ->where('quincena', $quincena),
            );

            foreach ($calc['aplicaciones'] as $ap) {
                $devId = (string) $ap['devolucion_id'];
                $dbId = str_starts_with($devId, 'db')
                    ? (int) substr($devId, 2)
                    : (int) ($idMap[$devId] ?? 0);
                if ($dbId <= 0) {
                    throw new RuntimeException('No se pudo registrar el neteo de la devolución '.$devId.'.');
                }

                IibbNeteoAplicacion::query()->create([
                    'iibb_neteo_devolucion_id' => $dbId,
                    'empresa_id' => $empresaId,
                    'iibb_presentacion_config_id' => (int) $config->id,
                    'periodo' => $periodo,
                    'quincena' => $quincena,
                    'nro_certificado' => (int) $ap['nro_cert'],
                    'proveedor_id' => $calc['proveedor_por_devolucion'][$devId] ?? null,
                    'importe_aplicado' => $ap['importe'],
                ]);
            }

            $t = $calc['totales'];
            $liq = IibbLiquidacionArba::query()->create([
                'empresa_id' => $empresaId,
                'iibb_presentacion_config_id' => (int) $config->id,
                'periodo' => $periodo,
                'quincena' => $quincena,
                'nombre_txt' => $calc['nombre_txt'],
                'nombre_pdf' => null,
                'ruta_pdf' => null,
                'total_erp' => $t['erp'],
                'total_neteos' => round($t['neteo_quincena'] + $t['neteo_anteriores'], 2),
                'total_txt' => $t['txt'],
                'total_pendientes' => round($t['pendientes_quincena'] + $t['pendientes_anteriores'], 2),
                'total_dj' => $t['dj'],
                'vep_esperado' => $t['vep'],
                'conciliacion_ok' => (bool) $calc['control']['ok'],
                'usuario_id' => $usuarioId,
            ]);

            $nombrePdf = 'ARBA_liquidacion_'.$liq->id.'_'.$periodo.'Q'.$quincena.'.pdf';
            $dir = storage_path('app/iibb_arba');
            if (! is_dir($dir)) {
                mkdir($dir, 0775, true);
            }
            $pdfPath = $dir.DIRECTORY_SEPARATOR.$nombrePdf;

            $html = View::make('contable.ingresos_brutos.arba_liquidacion_pdf', [
                'calc' => $calc,
                'liquidacion' => $liq,
                'empresa' => $nombreEmpresa,
                'filtros' => $filtros,
            ])->render();

            $pdf = \App::make('dompdf.wrapper');
            $pdf->setPaper('a4', 'portrait');
            $pdf->loadHTML($html, 'UTF-8');
            file_put_contents($pdfPath, $pdf->output());

            $liq->update(['nombre_pdf' => $nombrePdf, 'ruta_pdf' => $pdfPath]);

            $nombreZip = pathinfo($calc['nombre_txt'], PATHINFO_FILENAME).'.zip';
            $zipPath = $dir.DIRECTORY_SEPARATOR.'liquidacion_'.$liq->id.'.zip';

            $zip = new ZipArchive();
            if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException('No se pudo crear el ZIP de la liquidación.');
            }
            $zip->addFromString($calc['nombre_txt'], $calc['txt']);
            $zip->addFile($pdfPath, $nombrePdf);
            $zip->close();

            return [
                'liquidacion' => $liq->fresh(),
                'zip_path' => $zipPath,
                'nombre_zip' => $nombreZip,
                'nombre_pdf' => $nombrePdf,
                'calculo' => $calc,
            ];
        });
    }

    /**
     * Liquidación ARBA: retenciones de Buenos Aires, por quincena (1.ª o 2.ª).
     *
     * @param  array<string, mixed>  $filtros
     */
    public static function esArbaLiquidable(array $filtros): bool
    {
        return (string) ($filtros['tipo'] ?? '') === IngresosBrutosListadoFiltros::TIPO_RETENCIONES
            && IngresosBrutosListadoFiltros::quincenaLote((int) ($filtros['liquidacion'] ?? 0)) > 0;
    }

    /**
     * Clave de sesión para las decisiones (proveedor / certificado / pendiente) de una consulta.
     *
     * @param  array<string, mixed>  $filtros
     */
    public static function claveDecisiones(array $filtros): string
    {
        return 'iibb_arba_decisiones_'.sha1(implode('|', [
            (int) ($filtros['empresa_id'] ?? 0),
            (int) ($filtros['provincia_id'] ?? 0),
            (string) ($filtros['tipo'] ?? ''),
            (string) ($filtros['periodo'] ?? ''),
            (int) ($filtros['liquidacion'] ?? 0),
            (string) ($filtros['fecha_desde'] ?? ''),
            (string) ($filtros['fecha_hasta'] ?? ''),
        ]));
    }

    /**
     * Última liquidación de la quincena para la configuración.
     */
    public function liquidacionVigente(int $empresaId, int $configId, string $periodo, int $quincena): ?IibbLiquidacionArba
    {
        return IibbLiquidacionArba::query()
            ->where('empresa_id', $empresaId)
            ->where('iibb_presentacion_config_id', $configId)
            ->where('periodo', $periodo)
            ->where('quincena', $quincena)
            ->orderByDesc('id')
            ->first();
    }

    /**
     * @param  list<string>  $bloqueos
     * @param  list<string>  $advertencias
     * @return array<string, mixed>
     */
    private function respuesta(
        bool $puede,
        array $bloqueos,
        array $advertencias,
        int $empresaId,
        ?Iibb_Presentacion_Config $config,
        string $periodo,
        int $quincena,
        string $desde,
        string $hasta,
    ): array {
        return [
            'puede_liquidar' => $puede,
            'bloqueos' => $bloqueos,
            'advertencias' => $advertencias,
            'empresa_id' => $empresaId,
            'config' => $config,
            'periodo' => $periodo,
            'quincena' => $quincena,
            'desde' => $desde,
            'hasta' => $hasta,
            'dias_aviso' => null,
            'devoluciones' => [],
            'pendientes' => [],
            'aplicaciones' => [],
            'certificados' => [],
            'lineas_txt' => [],
            'txt' => '',
            'nombre_txt' => '',
            'totales' => [],
            'control' => ['habilitado' => false, 'ok' => false, 'tolerancia' => 0.0, 'residuo_periodo' => null, 'residuo_pendientes' => null],
            'proveedor_por_devolucion' => [],
            'devs_actuales' => [],
        ];
    }
}
