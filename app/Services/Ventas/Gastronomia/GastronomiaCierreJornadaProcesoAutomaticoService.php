<?php

namespace App\Services\Ventas\Gastronomia;

use App\Models\Configuracion\Empresa;
use App\Models\Seguridad\Usuario;
use App\Models\Ventas\GastronomiaCierreJornadaProcesoSnapshot;
use App\Models\Ventas\JornadaGastronomia;
use App\Support\Ventas\Gastronomia\CierreJornadaProcesoAutomaticoSupport;
use App\Support\Ventas\Gastronomia\CierreJornadaProcesoConfigSupport;
use App\Support\Ventas\Gastronomia\CierreJornadaProcesoJornadaSupport;
use App\Support\Ventas\Gastronomia\CierreJornadaProcesoPuntoventaSupport;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * Orquesta el proceso completo de cierre Waitry sin intervención del usuario:
 * analizar → recalcular % → emitir facturas → grabar asientos (+ rendición Anita).
 */
final class GastronomiaCierreJornadaProcesoAutomaticoService
{
    public function __construct(
        private readonly GastronomiaCierreJornadaProcesoService $procesoService,
    ) {
    }

    /**
     * Ejecuta el proceso automático para todas las empresas habilitadas.
     *
     * @return array<string, mixed>
     */
    public function ejecutarTodasEmpresas(bool $enviarMail = true): array
    {
        $informe = [
            'ejecutado_en' => now()->toIso8601String(),
            'empresas' => [],
            'resumen' => [
                'procesadas' => 0,
                'omitidas' => 0,
                'errores' => 0,
            ],
        ];

        foreach (CierreJornadaProcesoAutomaticoSupport::empresasHabilitadas() as $empresaId) {
            try {
                $resultado = $this->ejecutarEmpresa($empresaId);
            } catch (Throwable $e) {
                $resultado = [
                    'ok' => false,
                    'empresa_id' => $empresaId,
                    'estado' => 'error',
                    'error' => $e->getMessage(),
                ];
            }

            $informe['empresas'][] = $resultado;
            $estado = (string) ($resultado['estado'] ?? '');
            if ($estado === 'completado' || $estado === 'reanudado') {
                $informe['resumen']['procesadas']++;
            } elseif ($estado === 'omitido' || $estado === 'sin_pendiente') {
                $informe['resumen']['omitidas']++;
            } else {
                $informe['resumen']['errores']++;
            }
        }

        $informe['ok'] = $informe['resumen']['errores'] === 0;

        if ($enviarMail) {
            $this->enviarMailInforme($informe);
        }

        return $informe;
    }

    /**
     * Ejecuta para una empresa la jornada pendiente más reciente (o la fecha indicada).
     *
     * @return array<string, mixed>
     */
    public function ejecutarEmpresa(int $empresaId, ?string $fechaJornada = null): array
    {
        if (! $this->procesoService->habilitado()) {
            throw new InvalidArgumentException('El proceso de cierre Waitry no está habilitado en este entorno.');
        }

        $this->asegurarUsuarioSistema();
        $this->prepararEntorno();

        $empresa = Empresa::query()->find($empresaId);
        $empresaNombre = (string) ($empresa?->nombre ?? 'Empresa '.$empresaId);

        $jornada = $this->resolverJornadaObjetivo($empresaId, $fechaJornada);
        if ($jornada === null) {
            return [
                'ok' => true,
                'empresa_id' => $empresaId,
                'empresa_nombre' => $empresaNombre,
                'estado' => 'sin_pendiente',
                'mensaje' => 'No hay jornadas cerradas pendientes de proceso Waitry.',
            ];
        }

        return $this->ejecutarJornada($jornada, $empresaNombre);
    }

    /**
     * @return array<string, mixed>
     */
    public function ejecutarJornada(JornadaGastronomia $jornada, ?string $empresaNombre = null): array
    {
        $this->asegurarUsuarioSistema();
        $this->prepararEntorno();

        $empresaId = (int) $jornada->empresa_id;
        $fechaJornada = $jornada->fecha_jornada?->format('Y-m-d') ?? '';
        $empresaNombre = $empresaNombre ?? (string) (Empresa::query()->find($empresaId)?->nombre ?? 'Empresa '.$empresaId);

        $snapshot = $this->snapshotDeJornada((int) $jornada->id);
        $ctxInicial = CierreJornadaProcesoJornadaSupport::contexto($jornada, $snapshot);

        if ($ctxInicial['proceso_cierre_completado'] ?? false) {
            return [
                'ok' => true,
                'empresa_id' => $empresaId,
                'empresa_nombre' => $empresaNombre,
                'jornada_id' => (int) $jornada->id,
                'fecha_jornada' => $fechaJornada,
                'estado' => 'omitido',
                'mensaje' => 'El proceso ya estaba completado para esta jornada.',
                'resultado' => $ctxInicial['resultado_grabado'] ?? [],
            ];
        }

        if (! ($ctxInicial['cerrada'] ?? false)) {
            throw new InvalidArgumentException(
                'La jornada '.$fechaJornada.' no está cerrada. Cierre la jornada en Ventas → Gastronomía antes del proceso automático.',
            );
        }

        $cfg = CierreJornadaProcesoConfigSupport::paraEmpresaConDetalle($empresaId);
        $faltantes = CierreJornadaProcesoConfigSupport::faltantes($cfg, $empresaId);
        if ($faltantes !== []) {
            throw new InvalidArgumentException(
                'Configuración incompleta para empresa '.$empresaNombre.': '.implode(', ', $faltantes).'.',
            );
        }

        $pv = CierreJornadaProcesoPuntoventaSupport::resolverOError($empresaId);

        $pasos = [];
        $porcentaje = 0.0;

        try {
            $snapshot = $this->snapshotDeJornada((int) $jornada->id);
            if (CierreJornadaProcesoAutomaticoSupport::necesitaAnalizarDefinitivo($jornada, $snapshot)) {
                $pasos['analizar'] = $this->procesoService->analizarPorEmpresaYFecha($empresaId, $fechaJornada, true);
            }

            $porcentaje = $this->procesoService->porcentajeAplicarParaJornada($jornada);

            $snapshot = $this->snapshotDeJornada((int) $jornada->id);
            $ctx = CierreJornadaProcesoJornadaSupport::contexto($jornada, $snapshot);
            $facturaEmitida = (bool) (($ctx['factura_proceso_emitida'] ?? false) || ($ctx['factura_proceso_omitida'] ?? false));

            if (CierreJornadaProcesoAutomaticoSupport::necesitaRecalcular($snapshot, $porcentaje, $facturaEmitida)) {
                $pasos['recalcular'] = $this->procesoService->recalcularPorEmpresaYFecha($empresaId, $fechaJornada, $porcentaje);
            }

            $snapshot = $this->snapshotDeJornada((int) $jornada->id);
            $ctx = CierreJornadaProcesoJornadaSupport::contexto($jornada, $snapshot);

            if (($ctx['puede_facturar_proceso'] ?? false)) {
                $pasos['emitir_factura'] = $this->procesoService->emitirFacturaProcesoPorEmpresaYFecha(
                    $empresaId,
                    $fechaJornada,
                    $porcentaje,
                    (int) $pv['id'],
                    $fechaJornada,
                );
            } elseif (! ($ctx['factura_proceso_emitida'] ?? false) && ! ($ctx['factura_proceso_omitida'] ?? false)) {
                $motivo = (string) ($ctx['motivo_factura_bloqueada'] ?? 'No puede emitir facturas del proceso.');
                throw new InvalidArgumentException($motivo);
            }

            $snapshot = $this->snapshotDeJornada((int) $jornada->id);
            $ctx = CierreJornadaProcesoJornadaSupport::contexto($jornada, $snapshot);

            if ($ctx['puede_grabar_asientos_proceso'] ?? false) {
                $pasos['grabar_asientos'] = $this->procesoService->grabarAsientosProcesoPorEmpresaYFecha(
                    $empresaId,
                    $fechaJornada,
                    $porcentaje,
                    $fechaJornada,
                );
            } elseif (! ($ctx['asientos_grabados'] ?? false)) {
                $motivo = (string) ($ctx['motivo_asientos_bloqueados'] ?? 'No puede grabar asientos del proceso.');
                throw new InvalidArgumentException($motivo);
            }
        } catch (Throwable $e) {
            Log::error('gastronomia.cierre_jornada_automatico.fallo', [
                'empresa_id' => $empresaId,
                'jornada_id' => $jornada->id,
                'fecha_jornada' => $fechaJornada,
                'pasos' => array_keys($pasos),
                'error' => $e->getMessage(),
            ]);

            return [
                'ok' => false,
                'empresa_id' => $empresaId,
                'empresa_nombre' => $empresaNombre,
                'jornada_id' => (int) $jornada->id,
                'fecha_jornada' => $fechaJornada,
                'estado' => 'error',
                'porcentaje' => $porcentaje,
                'puntoventa' => $pv,
                'pasos' => $this->resumirPasos($pasos),
                'error' => $e->getMessage(),
            ];
        }

        $snapshot = $this->snapshotDeJornada((int) $jornada->id);
        $ctxFinal = CierreJornadaProcesoJornadaSupport::contexto($jornada, $snapshot);
        $completado = (bool) ($ctxFinal['proceso_cierre_completado'] ?? false);

        Log::info('gastronomia.cierre_jornada_automatico.ok', [
            'empresa_id' => $empresaId,
            'jornada_id' => $jornada->id,
            'fecha_jornada' => $fechaJornada,
            'porcentaje' => $porcentaje,
            'completado' => $completado,
            'pasos' => array_keys($pasos),
        ]);

        return [
            'ok' => $completado,
            'empresa_id' => $empresaId,
            'empresa_nombre' => $empresaNombre,
            'jornada_id' => (int) $jornada->id,
            'fecha_jornada' => $fechaJornada,
            'estado' => $completado ? 'completado' : 'parcial',
            'porcentaje' => $porcentaje,
            'puntoventa' => $pv,
            'pasos' => $this->resumirPasos($pasos),
            'resultado' => $ctxFinal['resultado_grabado'] ?? [],
            'mensaje' => $completado
                ? 'Proceso automático completado (facturas + asientos + rendición Anita).'
                : 'Proceso ejecutado parcialmente; revise el detalle.',
        ];
    }

    /**
     * @param  array<string, mixed>  $informe
     */
    public function enviarMailInforme(array $informe): void
    {
        $destinatarios = CierreJornadaProcesoAutomaticoSupport::destinatariosEmail();
        if ($destinatarios === []) {
            Log::warning('gastronomia.cierre_jornada_automatico.mail_sin_destinatarios');

            return;
        }

        try {
            \Illuminate\Support\Facades\Mail::to($destinatarios)
                ->send(new \App\Mail\Caja\WaitryCierreJornadaProcesoAutomatico($informe));
        } catch (Throwable $e) {
            Log::error('gastronomia.cierre_jornada_automatico.mail_fallo', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function resolverJornadaObjetivo(int $empresaId, ?string $fechaJornada): ?JornadaGastronomia
    {
        if ($fechaJornada !== null && trim($fechaJornada) !== '') {
            $jornada = JornadaGastronomia::query()
                ->where('empresa_id', $empresaId)
                ->whereDate('fecha_jornada', $fechaJornada)
                ->orderByDesc('id')
                ->first();

            if ($jornada === null) {
                throw new InvalidArgumentException('No hay jornada registrada para '.$fechaJornada.'.');
            }

            return $jornada;
        }

        return CierreJornadaProcesoAutomaticoSupport::jornadaPendienteMasReciente($empresaId);
    }

    private function snapshotDeJornada(int $jornadaId): ?GastronomiaCierreJornadaProcesoSnapshot
    {
        return GastronomiaCierreJornadaProcesoSnapshot::query()
            ->where('jornada_gastronomia_id', $jornadaId)
            ->first();
    }

    private function asegurarUsuarioSistema(): void
    {
        if (Auth::check()) {
            return;
        }

        $usuarioId = (int) config('gastronomia.cierre_jornada_automatico.usuario_id', 0);
        if ($usuarioId <= 0) {
            $usuarioId = (int) (Usuario::query()->orderBy('id')->value('id') ?? 1);
        }

        if ($usuarioId <= 0 || ! Auth::loginUsingId($usuarioId)) {
            throw new RuntimeException('No se pudo autenticar usuario para el proceso automático.');
        }
    }

    private function prepararEntorno(): void
    {
        $memory = (string) config('gastronomia.cierre_jornada_proceso_memory_limit', '1024M');
        if ($memory !== '') {
            @ini_set('memory_limit', $memory);
        }
        @set_time_limit(0);
    }

    /**
     * @param  array<string, mixed>  $pasos
     * @return array<string, mixed>
     */
    private function resumirPasos(array $pasos): array
    {
        $resumen = [];

        if (isset($pasos['analizar'])) {
            $resumen['analizar'] = [
                'ok' => (bool) ($pasos['analizar']['ok'] ?? false),
                'total_pendiente_facturar' => (float) ($pasos['analizar']['total_pendiente_facturar'] ?? 0),
            ];
        }

        if (isset($pasos['recalcular'])) {
            $resumen['recalcular'] = [
                'ok' => (bool) ($pasos['recalcular']['ok'] ?? false),
                'porcentaje' => (float) ($pasos['recalcular']['porcentaje'] ?? 0),
                'objetivo_importe' => (float) ($pasos['recalcular']['objetivo_importe'] ?? 0),
            ];
        }

        if (isset($pasos['emitir_factura'])) {
            $em = $pasos['emitir_factura'];
            $resumen['emitir_factura'] = [
                'ok' => (bool) ($em['ok'] ?? false),
                'mensaje' => (string) ($em['mensaje'] ?? ''),
                'omitida' => (bool) ($em['omitida'] ?? false),
                'cantidad_facturas' => count($em['facturas'] ?? []),
                'facturas' => array_values(array_map(
                    static fn (array $f) => [
                        'factura' => (string) ($f['factura'] ?? ''),
                        'total' => round((float) ($f['total'] ?? 0), 2),
                        'venta_id' => (int) ($f['venta_id'] ?? 0),
                    ],
                    array_filter($em['facturas'] ?? [], 'is_array'),
                )),
                'total_factura' => round((float) ($em['total_factura'] ?? 0), 2),
            ];
        }

        if (isset($pasos['grabar_asientos'])) {
            $ga = $pasos['grabar_asientos'];
            $resumen['grabar_asientos'] = [
                'ok' => (bool) ($ga['ok'] ?? false),
                'mensaje' => (string) ($ga['mensaje'] ?? ''),
                'cantidad_asientos' => (int) ($ga['cantidad_asientos'] ?? 0),
                'asientos' => array_values(array_map(
                    static fn (array $a) => [
                        'codigo' => (string) ($a['codigo'] ?? ''),
                        'titulo' => (string) ($a['titulo'] ?? ''),
                        'numeroasiento' => (string) ($a['numeroasiento'] ?? ''),
                    ],
                    array_filter($ga['asientos'] ?? [], 'is_array'),
                )),
                'rendicion_anita' => is_array($ga['rendicion_anita'] ?? null)
                    ? [
                        'nro_oper' => $ga['rendicion_anita']['nro_oper'] ?? null,
                        'ya_existia' => (bool) ($ga['rendicion_anita']['ya_existia'] ?? false),
                    ]
                    : null,
            ];
        }

        return $resumen;
    }
}
