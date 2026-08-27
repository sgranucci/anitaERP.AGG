<?php

namespace App\Services\Ventas\Gastronomia;

use App\Models\Ventas\GastronomiaCierreJornadaProcesoSnapshot;
use App\Models\Ventas\JornadaGastronomia;
use App\Repositories\Contable\AsientoRepositoryInterface;
use App\Repositories\Contable\Asiento_MovimientoRepositoryInterface;
use App\Repositories\Contable\TipoasientoRepositoryInterface;
use App\Support\Ventas\Gastronomia\CierreJornadaProcesoAsientosGrabacionSupport;
use App\Support\Ventas\Gastronomia\CierreJornadaProcesoAsientosPreviewSupport;
use App\Support\Ventas\Gastronomia\CierreJornadaProcesoConfigSupport;
use App\Support\Ventas\Gastronomia\CierreJornadaProcesoJornadaSupport;
use App\Support\Ventas\Gastronomia\GastronomiaConciliacionMedioPagoSupport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * Persiste los asientos contables del proceso de cierre Waitry (preview → ERP + bridge ctamov).
 */
final class GastronomiaCierreJornadaAsientosGrabacionService
{
    public function __construct(
        private readonly GastronomiaCierreJornadaProcesoService $procesoService,
        private readonly AsientoRepositoryInterface $asientoRepository,
        private readonly Asiento_MovimientoRepositoryInterface $asientoMovimientoRepository,
        private readonly TipoasientoRepositoryInterface $tipoasientoRepository,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function grabar(int $jornadaId, float $porcentaje, ?string $fechaAsiento = null): array
    {
        $jornada = JornadaGastronomia::query()->findOrFail($jornadaId);
        $snapshot = GastronomiaCierreJornadaProcesoSnapshot::query()
            ->where('jornada_gastronomia_id', $jornadaId)
            ->first();

        CierreJornadaProcesoJornadaSupport::assertPuedeGrabarAsientosProceso($jornada, $snapshot);

        $payloadSnap = is_array($snapshot?->payload) ? $snapshot->payload : [];
        if (! empty($payloadSnap['asientos_proceso_grabacion']['asientos'])) {
            if (empty($payloadSnap['rendicion_proceso_anita']['nro_oper'])) {
                return $this->completarRendicionAnitaPendiente($jornadaId, $payloadSnap);
            }

            throw new InvalidArgumentException(
                'Ya se grabaron los asientos del proceso para esta jornada.',
            );
        }

        $emisionSnap = is_array($payloadSnap['factura_proceso_emision'] ?? null)
            ? $payloadSnap['factura_proceso_emision']
            : [];
        if (isset($emisionSnap['porcentaje'])) {
            $porcentaje = (float) $emisionSnap['porcentaje'];
        }

        $empresaId = (int) $jornada->empresa_id;
        $fechaJornada = $jornada->fecha_jornada?->format('Y-m-d') ?? '';
        // Siempre fecha de la jornada del cierre (alineado con FAC proceso / IVA por fechajornada).
        $fecha = $fechaJornada;
        if (trim((string) ($fechaAsiento ?? '')) !== '' && (string) $fechaAsiento !== $fechaJornada) {
            Log::warning('cierre_jornada_waitry.fecha_asiento_ignorada', [
                'jornada_id' => $jornadaId,
                'fecha_asiento_pedida' => (string) $fechaAsiento,
                'fecha_jornada' => $fechaJornada,
            ]);
        }

        $clasificacion = $this->procesoService->clasificacionActual($jornadaId, $porcentaje);
        $datosAsientos = $this->procesoService->prepararDatosAsientosProceso($jornada, $clasificacion);
        $config = $datosAsientos['config'];
        $contextoCuadro = $datosAsientos['contexto_cuadro'];

        $previewCompleto = CierreJornadaProcesoAsientosPreviewSupport::generarPreviewCompletoProceso(
            $clasificacion['movimientos'],
            $empresaId,
            $config,
            $contextoCuadro,
        );

        $asientosPreview = CierreJornadaProcesoAsientosPreviewSupport::enriquecerAsientosConEtiquetas(
            $previewCompleto['asientos'] ?? [],
            $empresaId,
            $config,
        );

        $payloads = CierreJornadaProcesoAsientosGrabacionSupport::armarPayloadsAsientos(
            $asientosPreview,
            $empresaId,
            $config,
            $fecha,
            $fechaJornada,
        );

        $tipoAsientoId = $this->resolverTipoAsientoId();

        // ctamov Anita se escribe dentro de create() y NO participa de la transacción MySQL:
        // si algo falla después, hay que borrar en Anita lo ya insertado (evita asientos huérfanos).
        $ctamovGrabados = [];

        try {
            $grabados = DB::transaction(function () use ($payloads, $tipoAsientoId, $snapshot, $porcentaje, $fecha, $jornadaId, &$ctamovGrabados) {
                $registros = [];

                foreach ($payloads as $item) {
                    $data = $item['payload'];
                    $data['tipoasiento_id'] = $tipoAsientoId;
                    $data['jornada_gastronomia_id'] = $jornadaId;
                    $data['moneda_ids'] = $data['moneda_ids'] ?? [];
                    $data['centrocosto_ids'] = $data['centrocosto_ids'] ?? [];
                    $data['debes'] = $data['debes'] ?? [];
                    $data['haberes'] = $data['haberes'] ?? [];
                    $data['cotizaciones'] = $data['cotizaciones'] ?? [];
                    $data['observaciones'] = $data['observaciones'] ?? [];

                    $asiento = $this->asientoRepository->create($data);
                    if ($asiento === 'Error' || $asiento === null) {
                        throw new RuntimeException('Error al grabar asiento en Anita (bridge ctamov).');
                    }

                    $ctamovGrabados[] = [
                        'empresa_id' => (int) $data['empresa_id'],
                        'numeroasiento' => (string) ($asiento->numeroasiento ?? ''),
                    ];

                    $this->asientoMovimientoRepository->create($data, $asiento->id);

                    $registros[] = [
                        'codigo' => $item['codigo'],
                        'titulo' => $item['titulo'],
                        'asiento_id' => (int) $asiento->id,
                        'numeroasiento' => (string) ($asiento->numeroasiento ?? ''),
                        'resumen_debe' => $item['resumen_debe'],
                        'resumen_haber' => $item['resumen_haber'],
                    ];
                }

                if ($snapshot !== null) {
                    $payload = is_array($snapshot->payload) ? $snapshot->payload : [];
                    $payload['asientos_proceso_grabacion'] = [
                        'porcentaje' => round($porcentaje, 4),
                        'fecha_asiento' => $fecha,
                        'cantidad_asientos' => count($registros),
                        'asientos' => $registros,
                        'grabado_en' => now()->toIso8601String(),
                    ];
                    $snapshot->payload = $payload;
                    $snapshot->save();
                }

                return $registros;
            });
        } catch (Throwable $e) {
            // MySQL hizo rollback pero Anita ya tiene los ctamov: borrarlos para no dejar huérfanos.
            $this->revertirCtamovAnita($ctamovGrabados);

            if ($e instanceof InvalidArgumentException) {
                throw $e;
            }

            throw new RuntimeException(
                'Error al grabar asientos del proceso: '.$e->getMessage(),
                0,
                $e,
            );
        }

        // Rendgastro cuelga de las ventas (se graba al emitir). Acá solo reintento de seguridad
        // si la emisión no llegó a persistir CIERRE-WAITRY.
        $rendicionAnita = null;
        try {
            $rendicionAnita = app(GastronomiaCierreJornadaProcesoRendicionAnitaService::class)
                ->grabar($jornadaId);
        } catch (Throwable $e) {
            throw new RuntimeException(
                'Asientos grabados, pero falló la rendición Anita (red de seguridad post-emisión): '.$e->getMessage(),
                0,
                $e,
            );
        }

        // Guardrail: Z ↔ contabilizado por medio. Si hay DIF por Z corto (órdenes tardías) y el
        // recomputo del proceso confirma el MP contab, alinea el Informe Z solo — no bloquea el proceso.
        $guardrail = $this->guardrailMediosZ($empresaId, $fechaJornada);
        $alineacionZ = $this->autoAlinearInformeZSiCorresponde($jornadaId, $empresaId, $fechaJornada, $guardrail);
        if (($alineacionZ['persistido'] ?? false) === true) {
            $guardrail = $this->guardrailMediosZ($empresaId, $fechaJornada);
        }

        $msg = 'Se grabaron '.count($grabados).' asiento(s) contable(s) del proceso.';
        if ($rendicionAnita && empty($rendicionAnita['ya_existia'])) {
            $msg = 'Se grabaron '.count($grabados).' asiento(s) contable(s) del proceso'
                .' y se completó la rendición Anita (rendgastro) pendiente.';
        }

        return [
            'ok' => true,
            'mensaje' => $msg,
            'cantidad_asientos' => count($grabados),
            'asientos' => $grabados,
            'rendicion_anita' => $rendicionAnita,
            'guardrail_medios_z' => $guardrail,
            'alineacion_z_proceso' => $alineacionZ,
        ];
    }

    /**
     * Si el guardrail detectó DIF y el recomputo Waitry confirma el MP contabilizado, regenera el Z.
     * Nunca bloquea: si no es auto-alineable, solo deja el aviso del guardrail.
     *
     * @param  array<string, mixed>|null  $guardrail
     * @return array<string, mixed>|null
     */
    private function autoAlinearInformeZSiCorresponde(
        int $jornadaId,
        int $empresaId,
        string $fechaJornada,
        ?array $guardrail,
    ): ?array {
        if (($guardrail['estado'] ?? 'OK') !== 'DIF') {
            return null;
        }

        if (! filter_var(
            config('gastronomia.regenerar_z_desde_proceso.auto_alinear_al_grabar', true),
            FILTER_VALIDATE_BOOLEAN,
        )) {
            return ['decision' => 'omitido_config', 'persistido' => false];
        }

        $tolerancia = max(0.0, (float) config(
            'gastronomia.regenerar_z_desde_proceso.tolerancia',
            config('gastronomia.cierre_totem_informe_z_tolerancia', 0.02),
        ));

        try {
            $resultado = app(GastronomiaCierreTotemInformeZService::class)
                ->regenerarInformeZDesdeProceso($jornadaId, $tolerancia, true);
        } catch (Throwable $e) {
            Log::warning('Cierre jornada gastro: auto-alineación Z desde proceso falló', [
                'empresa_id' => $empresaId,
                'jornada_id' => $jornadaId,
                'fecha_jornada' => $fechaJornada,
                'mensaje' => $e->getMessage(),
            ]);

            return [
                'decision' => 'error',
                'persistido' => false,
                'error' => $e->getMessage(),
            ];
        }

        $decision = (string) ($resultado['decision'] ?? '');
        if ($decision === 'regenerar' && ($resultado['persistido'] ?? false)) {
            Log::info('Cierre jornada gastro: Informe Z auto-alineado desde proceso (sin bloquear)', [
                'empresa_id' => $empresaId,
                'jornada_id' => $jornadaId,
                'fecha_jornada' => $fechaJornada,
                'mp_z_antes' => $resultado['mp_z'] ?? null,
                'venta_waitry' => $resultado['venta_waitry'] ?? null,
                'venta_erp' => $resultado['venta_erp'] ?? null,
                'z_recomputado' => $resultado['z_recomputado'] ?? null,
                'z_nuevo' => $resultado['z_nuevo'] ?? null,
            ]);
        } elseif (in_array($decision, ['revisar_venta', 'revisar_asiento', 'omitido_recomputo_cero'], true)) {
            Log::warning('Cierre jornada gastro: DIF medios no auto-alineable (revisar venta Waitry/ERP)', [
                'empresa_id' => $empresaId,
                'jornada_id' => $jornadaId,
                'fecha_jornada' => $fechaJornada,
                'decision' => $decision,
                'mp_z' => $resultado['mp_z'] ?? null,
                'venta_waitry' => $resultado['venta_waitry'] ?? null,
                'venta_erp' => $resultado['venta_erp'] ?? null,
                'z_recomputado' => $resultado['z_recomputado'] ?? null,
            ]);
        }

        return $resultado;
    }

    /**
     * Guardrail post-grabación: concilia lo contabilizado por medio de cobro contra el Informe Z.
     * Devuelve el resumen y, si hay diferencia (residual asiento MP != Z), registra un aviso.
     *
     * @return array<string, mixed>|null
     */
    private function guardrailMediosZ(int $empresaId, string $fechaJornada): ?array
    {
        if ($fechaJornada === '') {
            return null;
        }

        try {
            $conc = app(GastronomiaConciliacionMedioPagoSupport::class)
                ->conciliarJornada($empresaId, $fechaJornada);
        } catch (Throwable $e) {
            Log::warning('Cierre jornada gastro: guardrail medios/Z no pudo ejecutarse', [
                'empresa_id' => $empresaId,
                'fecha_jornada' => $fechaJornada,
                'mensaje' => $e->getMessage(),
            ]);

            return null;
        }

        if (($conc['estado'] ?? 'OK') === 'DIF') {
            $mediosDif = array_values(array_filter(
                $conc['medios'] ?? [],
                static fn (array $m): bool => ($m['estado'] ?? '') === 'DIF',
            ));
            Log::warning('Cierre jornada gastro: residual asiento vs Z por medio de cobro', [
                'empresa_id' => $empresaId,
                'fecha_jornada' => $fechaJornada,
                'diff_total' => $conc['diff_total'] ?? null,
                'medios_dif' => array_map(static fn (array $m): array => [
                    'cuenta' => $m['cuenta_codigo'] ?? '',
                    'z' => $m['z'] ?? 0,
                    'contabilizado' => $m['contabilizado'] ?? 0,
                    'diff' => $m['diff'] ?? 0,
                ], $mediosDif),
            ]);
        }

        return $conc;
    }

    /**
     * Rollback compensatorio: elimina en Anita los ctamov de asientos que no llegaron a commitearse en ERP.
     *
     * @param  list<array{empresa_id: int, numeroasiento: string}>  $ctamovGrabados
     */
    private function revertirCtamovAnita(array $ctamovGrabados): void
    {
        foreach ($ctamovGrabados as $ctamov) {
            try {
                $this->asientoRepository->eliminarCtamovAnitaPorNumero(
                    $ctamov['empresa_id'],
                    $ctamov['numeroasiento'],
                );
            } catch (Throwable $rollbackError) {
                Log::warning('Cierre jornada gastro: rollback ctamov Anita falló', [
                    'empresa_id' => $ctamov['empresa_id'],
                    'numeroasiento' => $ctamov['numeroasiento'],
                    'mensaje' => $rollbackError->getMessage(),
                ]);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $payloadSnap
     * @return array<string, mixed>
     */
    private function completarRendicionAnitaPendiente(int $jornadaId, array $payloadSnap): array
    {
        $asientos = is_array($payloadSnap['asientos_proceso_grabacion']['asientos'] ?? null)
            ? $payloadSnap['asientos_proceso_grabacion']['asientos']
            : [];

        try {
            $rendicionAnita = app(GastronomiaCierreJornadaProcesoRendicionAnitaService::class)
                ->grabar($jornadaId);
        } catch (Throwable $e) {
            throw new RuntimeException(
                'Asientos ya grabados, pero falló la rendición Anita: '.$e->getMessage(),
                0,
                $e,
            );
        }

        return [
            'ok' => true,
            'mensaje' => 'Se completó la rendición Anita del proceso (asientos ya estaban grabados).',
            'cantidad_asientos' => count($asientos),
            'asientos' => $asientos,
            'rendicion_anita' => $rendicionAnita,
            'rendicion_reintentada' => true,
        ];
    }

    private function resolverTipoAsientoId(): int
    {
        $abrev = trim((string) config('gastronomia.cierre_jornada_tipoasiento_abreviatura', 'VTA'));
        $tipo = $this->tipoasientoRepository->findPorAbreviatura($abrev);
        if ($tipo === null) {
            throw new InvalidArgumentException(
                'No existe tipo de asiento «'.$abrev.'». Configure GASTRONOMIA_CIERRE_JORNADA_TIPOASIENTO_ABREVIATURA.',
            );
        }

        return (int) $tipo->id;
    }
}
