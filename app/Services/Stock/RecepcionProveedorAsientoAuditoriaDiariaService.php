<?php

declare(strict_types=1);

namespace App\Services\Stock;

use App\Mail\Stock\RecepcionProveedorAsientoAuditoriaDiaria;
use App\Models\Seguridad\Usuario;
use App\Models\Stock\Recepcion_Proveedor;
use App\Support\Contable\PeriodoContableCierreSupport;
use App\Support\Stock\RecepcionProveedorAnitaClaveSupport;
use App\Support\Stock\RecepcionProveedorAsientoAuditoriaSupport;
use App\Support\Stock\RecepcionProveedorCuadreContableSupport;
use App\Support\Stock\RecepcionProveedorEstados;
use App\Support\Stock\RecepcionProveedorImpuestoInternoSupport;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Auditoría diaria de asientos contables ERP ↔ ctamov Anita para recepciones/devoluciones COM.
 */
final class RecepcionProveedorAsientoAuditoriaDiariaService
{
    public function __construct(
        private readonly RecepcionProveedorAsientoService $asientoService,
        private readonly RecepcionProveedorAnitaBridgeService $anitaBridge,
        private readonly RecepcionProveedorAnitaTrasConfirmacionService $anitaTrasConfirmacion,
        private readonly RecepcionProveedorImpuestoInternoDevolucionReparacionService $impuestoInternoDevolucionReparacion,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function ejecutar(
        ?string $fechaCalendario = null,
        bool $enviarMail = true,
        ?int $empresaId = null,
        ?string $fechaDesde = null,
        ?string $fechaHasta = null,
        bool $todas = false,
        ?bool $autoReparar = null,
    ): array {
        $config = config('recepcion_proveedor.auditoria_asientos_com_diaria', []);
        $fecha = $fechaCalendario ?? Carbon::yesterday()->toDateString();
        $tol = max(0.0, (float) ($config['tolerancia'] ?? RecepcionProveedorCuadreContableSupport::tolerancia()));
        $incluirImportadas = filter_var($config['incluir_importadas_anita'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $autoReparar ??= filter_var($config['auto_reparar'] ?? false, FILTER_VALIDATE_BOOLEAN);

        $this->autenticarUsuarioSistema($config);

        $query = Recepcion_Proveedor::query()
            ->where('estado', RecepcionProveedorEstados::CONFIRMADA)
            ->orderBy('empresa_id')
            ->orderBy('numerorecepcion');

        if ($todas) {
            $fecha = 'todas';
        } elseif ($fechaDesde !== null || $fechaHasta !== null) {
            if ($fechaDesde !== null && $fechaDesde !== '') {
                $query->whereDate('fecha', '>=', $fechaDesde);
            }
            if ($fechaHasta !== null && $fechaHasta !== '') {
                $query->whereDate('fecha', '<=', $fechaHasta);
            }
            $fecha = trim(($fechaDesde ?? '…').' → '.($fechaHasta ?? '…'));
        } else {
            $query->whereDate('fecha', $fecha);
        }

        if ($empresaId !== null && $empresaId > 0) {
            $query->where('empresa_id', $empresaId);
        }

        if (! $incluirImportadas) {
            $query->where('origen_carga', '!=', 'ANITA_IMPORT');
        }

        $recepciones = $query->get();

        $informe = [
            'fecha_calendario' => $fecha,
            'empresa_id' => $empresaId,
            'tolerancia' => $tol,
            'total_com' => $recepciones->count(),
            'ok' => 0,
            'omitidas' => 0,
            'reparadas' => 0,
            'sin_reparar_periodo_cerrado' => 0,
            'discrepancias' => [],
            'errores_lectura' => [],
            'filas' => [],
        ];

        foreach ($recepciones as $recepcion) {
            try {
                $resultado = $this->auditarRecepcion($recepcion, $tol);
            } catch (\Throwable $e) {
                $informe['errores_lectura'][] = [
                    'recepcion_id' => (int) $recepcion->id,
                    'com' => (int) $recepcion->numerorecepcion,
                    'mensaje' => $e->getMessage(),
                ];

                continue;
            }

            if (($resultado['estado'] ?? '') === 'omitida') {
                $informe['omitidas']++;
                $informe['filas'][] = $resultado;

                continue;
            }

            if (($resultado['estado'] ?? '') === 'ok') {
                $informe['ok']++;
                $informe['filas'][] = $resultado;

                continue;
            }

            if ($autoReparar && ($resultado['estado'] ?? '') === 'discrepancia') {
                $avisoPeriodo = $this->avisoPeriodoCerradoSinReparar($recepcion);
                if ($avisoPeriodo !== null) {
                    $resultado['reparacion_omitida_periodo_cerrado'] = true;
                    $resultado['problemas'][] = $avisoPeriodo;
                    $resultado['problemas'] = array_values(array_unique($resultado['problemas']));
                    $informe['sin_reparar_periodo_cerrado']++;
                    Log::warning('recepcion_proveedor.auditoria_asientos_com.sin_reparar_periodo_cerrado', [
                        'recepcion_id' => (int) $recepcion->id,
                        'com' => (int) $recepcion->numerorecepcion,
                        'fecha' => optional($recepcion->fecha)->format('Y-m-d'),
                    ]);

                    $informe['discrepancias'][] = $resultado;
                    $informe['filas'][] = $resultado;

                    continue;
                }

                $problemasIniciales = $resultado['problemas'] ?? [];
                try {
                    $reparacionIi = null;
                    if (! empty($resultado['impuesto_interno_devolucion_falla'])) {
                        $statsIi = $this->impuestoInternoDevolucionReparacion->ejecutar([
                            'id' => (int) $recepcion->id,
                            'forzar' => true,
                        ]);
                        $reparacionIi = $statsIi;
                    }

                    $reparacion = $this->anitaTrasConfirmacion->verificarYReparar((int) $recepcion->id);
                    $resultadoPost = $this->auditarRecepcion($recepcion->fresh(), $tol);
                    if (($resultadoPost['estado'] ?? '') === 'ok') {
                        $resultadoPost['reparada_en_auditoria'] = true;
                        $resultadoPost['problemas_iniciales'] = $problemasIniciales;
                        $resultadoPost['reparacion_estado'] = $reparacion['estado'] ?? null;
                        if ($reparacionIi !== null) {
                            $resultadoPost['reparacion_impuesto_interno'] = $reparacionIi;
                        }
                        $informe['ok']++;
                        $informe['reparadas'] = (int) ($informe['reparadas'] ?? 0) + 1;
                        $informe['filas'][] = $resultadoPost;
                        Log::warning('recepcion_proveedor.auditoria_asientos_com.reparada', [
                            'recepcion_id' => (int) $recepcion->id,
                            'com' => (int) $recepcion->numerorecepcion,
                            'problemas' => $problemasIniciales,
                            'reparacion_impuesto_interno' => $reparacionIi !== null,
                        ]);

                        continue;
                    }
                    $resultado = $resultadoPost;
                    $resultado['reparacion_fallida'] = true;
                    $resultado['problemas_iniciales'] = $problemasIniciales;
                } catch (\Throwable $e) {
                    $resultado['reparacion_fallida'] = true;
                    $resultado['reparacion_error'] = $e->getMessage();
                    $resultado['problemas_iniciales'] = $problemasIniciales;
                    Log::error('recepcion_proveedor.auditoria_asientos_com.reparacion_fallo', [
                        'recepcion_id' => (int) $recepcion->id,
                        'com' => (int) $recepcion->numerorecepcion,
                        'mensaje' => $e->getMessage(),
                    ]);
                }
            }

            $informe['discrepancias'][] = $resultado;
            $informe['filas'][] = $resultado;
        }

        $informe['requiere_alerta'] = $informe['discrepancias'] !== [] || $informe['errores_lectura'] !== [];

        if ($enviarMail && $informe['requiere_alerta']) {
            $destino = trim((string) ($config['email'] ?? ''));
            if ($destino !== '') {
                try {
                    Mail::to($destino)->send(new RecepcionProveedorAsientoAuditoriaDiaria($informe));
                    $informe['mail_enviado'] = true;
                    $informe['mail_destino'] = $destino;
                } catch (\Throwable $e) {
                    $informe['mail_enviado'] = false;
                    $informe['mail_error'] = $e->getMessage();
                    Log::error('recepcion_proveedor.auditoria_asientos_com.mail_fallo', [
                        'fecha' => $fecha,
                        'destino' => $destino,
                        'msg' => $e->getMessage(),
                    ]);
                }
            }
        }

        Log::info('recepcion_proveedor.auditoria_asientos_com.ok', [
            'fecha_calendario' => $fecha,
            'total_com' => $informe['total_com'],
            'ok' => $informe['ok'],
            'discrepancias' => count($informe['discrepancias']),
            'reparadas' => (int) ($informe['reparadas'] ?? 0),
            'sin_reparar_periodo_cerrado' => (int) ($informe['sin_reparar_periodo_cerrado'] ?? 0),
            'errores_lectura' => count($informe['errores_lectura']),
            'requiere_alerta' => $informe['requiere_alerta'],
            'auto_reparar' => $autoReparar,
        ]);

        return $informe;
    }

    /**
     * @return array<string, mixed>
     */
    private function auditarRecepcion(Recepcion_Proveedor $recepcion, float $tol): array
    {
        $recepcion->loadMissing([
            'empresas',
            'asientos.tipoasientos',
            'asientos.asiento_movimientos.cuentacontables',
            'asientos.asiento_movimientos.centrocostos',
            'asientos.asiento_movimientos.monedas',
        ]);

        $base = [
            'recepcion_id' => (int) $recepcion->id,
            'com' => (int) $recepcion->numerorecepcion,
            'tipo' => (string) ($recepcion->tipo ?? Recepcion_Proveedor::TIPO_RECEPCION),
            'empresa_id' => (int) $recepcion->empresa_id,
            'empresa_codigo' => (int) ($recepcion->empresas->codigo ?? 0),
            'fecha' => optional($recepcion->fecha)->format('Y-m-d'),
            'asiento_id' => (int) ($recepcion->asiento_id ?? 0),
            'problemas' => [],
            'impuesto_interno_devolucion_falla' => false,
        ];

        // Control específico: devolución debe revertir II de la recepción origen (cigarrillos).
        $diagIi = RecepcionProveedorImpuestoInternoSupport::diagnosticoImpuestoInternoDevolucion(
            $recepcion,
            $tol
        );
        if ($diagIi !== null) {
            $base['problemas'][] = $diagIi['mensaje'];
            $base['impuesto_interno_devolucion_falla'] = true;
            $base['impuesto_interno_esperado'] = $diagIi['ii_esperado'];
            $base['impuesto_interno_actual'] = $diagIi['ii_actual'];
            $base['impuesto_interno_origen'] = $diagIi['ii_origen'];
            $base['recepcion_origen_com'] = $diagIi['origen_nro'];
        }

        if (! $this->asientoService->debeGenerarAsiento((int) $recepcion->empresa_id)) {
            if ($base['problemas'] !== []) {
                return array_merge($base, ['estado' => 'discrepancia']);
            }

            return array_merge($base, ['estado' => 'omitida']);
        }

        if ($this->asientoService->recepcionSinImporteContable($recepcion)) {
            // Devolución con II faltante: el importe "sin II" puede ser > 0 por mercadería;
            // si además el preview da 0, igual debemos alertar el II.
            if ($base['problemas'] !== []) {
                return array_merge($base, ['estado' => 'discrepancia']);
            }

            return array_merge($base, [
                'estado' => 'omitida',
                'problemas' => ['Recepción sin importe contable: no requiere asiento COM.'],
            ]);
        }

        $asientoId = (int) ($recepcion->asiento_id ?? 0);
        if ($asientoId <= 0) {
            $base['problemas'][] = 'Falta asiento contable en el ERP (contabilidad activa).';

            return array_merge($base, ['estado' => 'discrepancia']);
        }

        $asiento = $recepcion->asientos;
        if (! $asiento) {
            $base['problemas'][] = 'La recepción referencia asiento id '.$asientoId.' inexistente en el ERP.';

            return array_merge($base, ['estado' => 'discrepancia']);
        }

        $numeroAsiento = trim((string) ($asiento->numeroasiento ?? ''));
        $fechaAsiento = $asiento->fecha instanceof \DateTimeInterface
            ? $asiento->fecha->format('Y-m-d')
            : (string) $asiento->fecha;

        $base['numero_asiento'] = $numeroAsiento;
        $base['fecha_asiento'] = $fechaAsiento;

        $claveAnita = RecepcionProveedorAnitaClaveSupport::resolver($recepcion);
        $base['clave_anita'] = sprintf(
            '%s %s %d %d',
            $claveAnita['tipo'],
            $claveAnita['letra'],
            $claveAnita['sucursal'],
            $claveAnita['nro'],
        );

        $cabecerasRecepmae = $this->anitaBridge->listarRecepmaePorClaveAuditoria($recepcion);
        $base['recepmae_anita'] = count($cabecerasRecepmae);
        if ($cabecerasRecepmae === []) {
            $base['problemas'][] = sprintf(
                'Falta cabecera recepmae en Anita (clave %s no encontrada en compras).',
                $base['clave_anita'],
            );
        }

        $detalleAnita = $this->anitaBridge->diagnosticoDetalleComAnita($recepcion);
        $base['items_erp'] = (int) ($detalleAnita['lineas_erp'] ?? 0);
        $base['recepmov_anita'] = (int) ($detalleAnita['recepmov'] ?? 0);
        if (array_key_exists('stkmov', $detalleAnita) && $detalleAnita['stkmov'] !== null) {
            $base['stkmov_anita'] = (int) $detalleAnita['stkmov'];
        }
        if ($detalleAnita['incompleto'] ?? false) {
            $base['problemas'][] = (string) ($detalleAnita['mensaje']
                ?? 'Detalle Anita incompleto (recepmov/stkmov con menos ítems que el ERP).');
        }

        $movimientos = $asiento->asiento_movimientos ?? collect();
        if ($movimientos->isEmpty()) {
            $base['problemas'][] = 'El asiento ERP no tiene movimientos contables.';
        }

        $totalesErp = RecepcionProveedorCuadreContableSupport::totalesDesdeMovimientos($movimientos);
        $base['debe_erp'] = $totalesErp['debe'];
        $base['haber_erp'] = $totalesErp['haber'];

        if (abs($totalesErp['debe'] - $totalesErp['haber']) >= $tol) {
            $base['problemas'][] = sprintf(
                'Asiento ERP desbalanceado: debe %s vs haber %s.',
                number_format($totalesErp['debe'], 2, ',', '.'),
                number_format($totalesErp['haber'], 2, ',', '.'),
            );
        }

        try {
            $preview = $this->asientoService->previewAsientoContable($recepcion);
            $debeEsperado = round((float) ($preview['total_debe'] ?? 0), 2);
            $totalRecepcion = round((float) ($preview['total_recepcion'] ?? $debeEsperado), 2);
            $base['total_recepcion'] = $totalRecepcion;
            $base['debe_esperado'] = $debeEsperado;

            if (abs($debeEsperado - $totalesErp['debe']) >= $tol) {
                $base['problemas'][] = sprintf(
                    'Importe ERP (%s) distinto del esperado por la recepción (%s).',
                    number_format($totalesErp['debe'], 2, ',', '.'),
                    number_format($debeEsperado, 2, ',', '.'),
                );
            }
        } catch (\Throwable $e) {
            $base['problemas'][] = 'No se pudo calcular preview contable: '.$e->getMessage();
        }

        $fechaRecepcion = optional($recepcion->fecha)->format('Y-m-d');
        if ($fechaRecepcion !== null && $fechaAsiento !== '' && $fechaRecepcion !== $fechaAsiento) {
            $base['problemas'][] = 'Fecha asiento ERP '.$fechaAsiento.' distinta de fecha recepción '.$fechaRecepcion.'.';
        }

        $filasCtamov = RecepcionProveedorAsientoAuditoriaSupport::lineasCtamovPorCom($recepcion);
        $origenCtamov = 'com';

        if ($filasCtamov === [] && $numeroAsiento !== '') {
            $filasCtamov = RecepcionProveedorAsientoAuditoriaSupport::lineasCtamovPorNumeroAsiento(
                (int) ($recepcion->empresas->codigo ?? 0),
                $numeroAsiento,
            );
            $origenCtamov = 'numero_asiento';
        }

        $base['ctamov_origen'] = $origenCtamov;
        $base['ctamov_lineas'] = count($filasCtamov);

        if ($filasCtamov === []) {
            $base['problemas'][] = 'No hay movimientos ctamov en Anita para esta COM.';
        } else {
            $totalesAnita = RecepcionProveedorAsientoAuditoriaSupport::totalesDesdeCtamov($filasCtamov);
            $base['debe_anita'] = $totalesAnita['debe'];
            $base['haber_anita'] = $totalesAnita['haber'];

            if (abs($totalesAnita['debe'] - $totalesAnita['haber']) >= $tol) {
                $base['problemas'][] = sprintf(
                    'ctamov Anita desbalanceado: debe %s vs haber %s.',
                    number_format($totalesAnita['debe'], 2, ',', '.'),
                    number_format($totalesAnita['haber'], 2, ',', '.'),
                );
            }

            if (abs($totalesAnita['debe'] - $totalesErp['debe']) >= $tol) {
                $base['problemas'][] = sprintf(
                    'Importe ERP (%s) distinto de ctamov Anita (%s).',
                    number_format($totalesErp['debe'], 2, ',', '.'),
                    number_format($totalesAnita['debe'], 2, ',', '.'),
                );
            }

            $cabecera = RecepcionProveedorAsientoAuditoriaSupport::validarCabeceraCtamov(
                $recepcion,
                $filasCtamov,
                $numeroAsiento,
                $fechaAsiento,
                (int) ($recepcion->empresas->codigo ?? 0),
            );
            $base['problemas'] = array_merge($base['problemas'], $cabecera);

            $lineasErp = RecepcionProveedorAsientoAuditoriaSupport::normalizarLineasErp($movimientos);
            $lineasAnita = RecepcionProveedorAsientoAuditoriaSupport::normalizarLineasAnita($filasCtamov);
            $diffLineas = RecepcionProveedorAsientoAuditoriaSupport::diferenciasLineas($lineasErp, $lineasAnita, $tol);
            $base['problemas'] = array_merge($base['problemas'], $diffLineas);
        }

        $base['problemas'] = array_values(array_unique($base['problemas']));

        return array_merge($base, [
            'estado' => $base['problemas'] === [] ? 'ok' : 'discrepancia',
        ]);
    }

    /**
     * No reescribe ERP/Anita si la fecha de la COM está en período cerrado
     * (recepción proveedor o asientos), aunque auto_reparar esté activo.
     */
    private function avisoPeriodoCerradoSinReparar(Recepcion_Proveedor $recepcion): ?string
    {
        $fecha = optional($recepcion->fecha)->format('Y-m-d');
        if ($fecha === null || $fecha === '') {
            return null;
        }

        $empresaId = (int) $recepcion->empresa_id;
        $alcances = [
            PeriodoContableCierreSupport::ALCANCE_RECEPCION_PROVEEDOR,
            PeriodoContableCierreSupport::ALCANCE_CONTABLE,
        ];

        foreach ($alcances as $alcance) {
            if (! PeriodoContableCierreSupport::fechaEnPeriodoCerrado($empresaId, $fecha, $alcance)) {
                continue;
            }

            $cierre = PeriodoContableCierreSupport::fechaCierreVigente($empresaId, $alcance);
            $hasta = $cierre !== null ? $cierre->format('d/m/Y') : '—';

            return 'Período contable cerrado hasta el '.$hasta
                .' ('.PeriodoContableCierreSupport::etiquetaAlcance($alcance).').'
                .' No se modificó ERP ni Anita; revisar a mano.';
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function autenticarUsuarioSistema(array $config): void
    {
        if (Auth::check()) {
            return;
        }

        $usuarioId = (int) ($config['usuario_id'] ?? 0);
        if ($usuarioId <= 0) {
            $usuarioId = (int) (Usuario::query()->orderBy('id')->value('id') ?? 1);
        }

        if ($usuarioId <= 0 || ! Auth::loginUsingId($usuarioId)) {
            throw new \RuntimeException('No se pudo autenticar usuario de sistema para auditoría COM.');
        }
    }
}
