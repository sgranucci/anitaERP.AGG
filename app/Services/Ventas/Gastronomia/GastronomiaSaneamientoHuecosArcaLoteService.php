<?php

declare(strict_types=1);

namespace App\Services\Ventas\Gastronomia;

use App\Models\Ventas\GastronomiaHuecoArcaPendiente;
use App\Models\Ventas\TurnoOperativoGastronomia;
use App\Models\Ventas\Venta;
use App\Models\Ventas\VentaGastronomiaEmision;
use App\Services\Arca\ArcaWsfeFacturaElectronicaService;
use App\Support\Ventas\Gastronomia\GastronomiaTurnoHuecosArcaSupport;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Diagnóstico ARCA + saneamiento en lote: N FAC recuperadas + 1 NC consolidada (PeriodoAsoc jornada).
 */
final class GastronomiaSaneamientoHuecosArcaLoteService
{
    private const CBTE_TIPO_FAC_B = 6;

    public function __construct(
        private readonly ArcaWsfeFacturaElectronicaService $arcaWsfeService,
        private readonly GastronomiaRecuperarComprobanteArcaService $recuperarService,
        private readonly GastronomiaNotaCreditoService $notaCreditoService,
        private readonly GastronomiaAjusteFiscalZService $ajusteZService,
    ) {
    }

    /**
     * @return array{
     *   ok:bool,
     *   arca_indisponible:bool,
     *   cantidad_huecos:int,
     *   recuperables:list<array<string,mixed>>,
     *   inexistentes:list<array<string,mixed>>,
     *   errores:list<array<string,mixed>>,
     *   preview_nc:?array<string,mixed>,
     *   mensaje:?string
     * }
     */
    public function diagnosticar(TurnoOperativoGastronomia $turno): array
    {
        $det = GastronomiaTurnoHuecosArcaSupport::detectarParaTurno($turno);
        $faltantes = $det['numeros_faltantes'];
        $pvCodigo = (string) ($det['puntoventa_codigo'] ?? '');
        $pvId = (int) ($det['puntoventa_id'] ?? 0);
        $fechaJornada = (string) ($det['fecha_jornada'] ?? '');
        $empresaId = (int) $turno->empresa_id;

        if ($faltantes === [] || $pvId <= 0 || $pvCodigo === '') {
            return [
                'ok' => true,
                'arca_indisponible' => false,
                'cantidad_huecos' => 0,
                'recuperables' => [],
                'inexistentes' => [],
                'errores' => [],
                'preview_nc' => null,
                'mensaje' => 'No hay huecos de numeración en el turno.',
            ];
        }

        $ptoVta = (int) $pvCodigo;
        $recuperables = [];
        $inexistentes = [];
        $errores = [];
        $arcaIndisponible = false;
        $suma = 0.0;

        foreach ($faltantes as $numero) {
            $filaBase = [
                'numero' => $numero,
                'puntoventa_id' => $pvId,
                'puntoventa_codigo' => $pvCodigo,
                'fecha_jornada' => $fechaJornada,
            ];

            try {
                $res = $this->arcaWsfeService->feCompConsultar(
                    $empresaId,
                    $ptoVta,
                    self::CBTE_TIPO_FAC_B,
                    $numero,
                );
                $rg = $res->ResultGet ?? null;
                if ($rg === null) {
                    $inexistentes[] = array_merge($filaBase, [
                        'estado' => 'inexistente_arca',
                        'detalle' => 'ARCA no devolvió ResultGet.',
                    ]);
                    $this->persistirPendiente(
                        $empresaId,
                        $fechaJornada,
                        $pvId,
                        $numero,
                        $turno,
                        GastronomiaHuecoArcaPendiente::ESTADO_INEXISTENTE_ARCA,
                        'Sin ResultGet',
                    );
                    continue;
                }

                $resultado = strtoupper(trim((string) ($rg->Resultado ?? '')));
                if (! in_array($resultado, ['A', 'P'], true)) {
                    $inexistentes[] = array_merge($filaBase, [
                        'estado' => 'inexistente_arca',
                        'detalle' => 'Resultado ARCA='.$resultado,
                    ]);
                    $this->persistirPendiente(
                        $empresaId,
                        $fechaJornada,
                        $pvId,
                        $numero,
                        $turno,
                        GastronomiaHuecoArcaPendiente::ESTADO_INEXISTENTE_ARCA,
                        'Resultado '.$resultado,
                    );
                    continue;
                }

                $impTotal = round((float) ($rg->ImpTotal ?? 0), 2);
                $cuentaId = $this->resolverCuentaReferencia(
                    $turno,
                    $impTotal,
                    $fechaJornada,
                );
                $recuperables[] = array_merge($filaBase, [
                    'estado' => 'recuperable',
                    'imp_total' => $impTotal,
                    'cae' => trim((string) ($rg->CodAutorizacion ?? '')),
                    'cuenta_referencia_id' => $cuentaId,
                    'sin_cuenta_referencia' => $cuentaId === null,
                ]);
                $suma += $impTotal;
                $this->persistirPendiente(
                    $empresaId,
                    $fechaJornada,
                    $pvId,
                    $numero,
                    $turno,
                    GastronomiaHuecoArcaPendiente::ESTADO_RECUPERABLE,
                    null,
                );
            } catch (Throwable $e) {
                $arcaIndisponible = true;
                $errores[] = array_merge($filaBase, [
                    'estado' => 'arca_indisponible',
                    'detalle' => $e->getMessage(),
                ]);
                $this->persistirPendiente(
                    $empresaId,
                    $fechaJornada,
                    $pvId,
                    $numero,
                    $turno,
                    GastronomiaHuecoArcaPendiente::ESTADO_ARCA_INDISPONIBLE,
                    mb_substr($e->getMessage(), 0, 500),
                );
            }
        }

        $ymd = preg_replace('/\D+/', '', $fechaJornada) ?: null;

        return [
            'ok' => true,
            'arca_indisponible' => $arcaIndisponible,
            'cantidad_huecos' => count($faltantes),
            'recuperables' => $recuperables,
            'inexistentes' => $inexistentes,
            'errores' => $errores,
            'preview_nc' => $recuperables === [] ? null : [
                'cantidad_fac' => count($recuperables),
                'importe_total' => round($suma, 2),
                'periodo_asoc_desde' => $ymd,
                'periodo_asoc_hasta' => $ymd,
                'efectivo_neto' => 0.0,
                'omitir_stock' => true,
                'omitir_anita_ventas' => true,
                'leyenda' => $this->leyendaLote($pvCodigo, array_column($recuperables, 'numero')),
            ],
            'mensaje' => $arcaIndisponible
                ? 'ARCA no respondió para uno o más huecos. Puede cerrar el turno; quedan pendientes para auditoría.'
                : null,
        ];
    }

    /**
     * @param  list<int>|null  $numeros  Si null, usa todos los recuperables del diagnóstico.
     * @return array<string, mixed>
     */
    public function ejecutar(
        TurnoOperativoGastronomia $turno,
        ?array $numeros = null,
        bool $dryRun = false,
        ?string $fechaNc = null,
    ): array {
        $diag = $this->diagnosticar($turno);
        if (! empty($diag['arca_indisponible'])) {
            return [
                'ok' => false,
                'error' => 'ARCA indisponible: no se puede ejecutar el lote. Cierre el turno y sanee luego con artisan.',
                'diagnostico' => $diag,
            ];
        }

        $recuperables = $diag['recuperables'] ?? [];
        if ($numeros !== null && $numeros !== []) {
            $set = array_fill_keys(array_map('intval', $numeros), true);
            $recuperables = array_values(array_filter(
                $recuperables,
                static fn (array $r): bool => isset($set[(int) $r['numero']]),
            ));
        }

        if ($recuperables === []) {
            return [
                'ok' => false,
                'error' => 'No hay comprobantes recuperables en ARCA para este lote.',
                'diagnostico' => $diag,
            ];
        }

        foreach ($recuperables as $r) {
            if (! empty($r['sin_cuenta_referencia'])) {
                return [
                    'ok' => false,
                    'error' => 'El hueco FAC '.$r['numero'].' no tiene cuenta de referencia en el turno (importe $'
                        .number_format((float) $r['imp_total'], 2, ',', '.').').',
                    'diagnostico' => $diag,
                ];
            }
        }

        if ($dryRun) {
            return [
                'ok' => true,
                'dry_run' => true,
                'diagnostico' => $diag,
                'a_recuperar' => $recuperables,
                'preview_nc' => $diag['preview_nc'],
            ];
        }

        $turno->loadMissing(['jornada', 'configuracionPuntoventa']);
        $empresaId = (int) $turno->empresa_id;
        $fechaJornada = $turno->jornada?->fecha_jornada?->format('Y-m-d')
            ?? Carbon::today()->format('Y-m-d');
        $fechaNc = $fechaNc && preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaNc)
            ? $fechaNc
            : now()->format('Y-m-d');
        $pvCodigo = (string) ($recuperables[0]['puntoventa_codigo'] ?? '');
        $tipoFacId = (int) ($turno->configuracionPuntoventa?->tipotransaccion_id
            ?? config('gastronomia.tipotransaccion_factura_id', 1));

        $ventaFacturaIds = [];
        $detalleFac = [];

        return DB::transaction(function () use (
            $recuperables,
            $empresaId,
            $pvCodigo,
            $tipoFacId,
            $fechaJornada,
            $fechaNc,
            $turno,
            &$ventaFacturaIds,
            &$detalleFac,
        ): array {
            foreach ($recuperables as $r) {
                $numero = (int) $r['numero'];
                $cuentaId = (int) $r['cuenta_referencia_id'];
                $existente = Venta::query()
                    ->where('puntoventa_id', (int) $r['puntoventa_id'])
                    ->where('numerocomprobante', $numero)
                    ->whereNull('deleted_at')
                    ->first();

                if ($existente !== null) {
                    $ventaFacturaIds[] = (int) $existente->id;
                    $detalleFac[] = [
                        'numero' => $numero,
                        'venta_id' => (int) $existente->id,
                        'codigo' => (string) $existente->codigo,
                        'ya_existia' => true,
                    ];
                    continue;
                }

                $rec = $this->recuperarService->recuperar(
                    $empresaId,
                    $pvCodigo,
                    $numero,
                    $tipoFacId > 0 ? $tipoFacId : 1,
                    $cuentaId,
                    null,
                    false,
                    true,
                    (string) $turno->identificador_pc,
                    $fechaJornada,
                );
                $ventaId = (int) ($rec['venta_id'] ?? 0);
                if ($ventaId <= 0) {
                    throw new RuntimeException('No se pudo recuperar FAC '.$numero.'.');
                }
                $ventaFacturaIds[] = $ventaId;
                $detalleFac[] = [
                    'numero' => $numero,
                    'venta_id' => $ventaId,
                    'codigo' => (string) ($rec['codigo'] ?? ''),
                    'ya_existia' => false,
                ];
            }

            $ventaFacturaIds = array_values(array_unique($ventaFacturaIds));
            $yaNc = null;
            foreach ($ventaFacturaIds as $facId) {
                $existenteNc = GastronomiaNotaCreditoService::notaCreditoExistenteParaFactura($facId);
                if ($existenteNc !== null) {
                    $yaNc = $existenteNc;
                    break;
                }
            }

            if ($yaNc !== null) {
                $ventaNcId = $yaNc;
                $ncCodigo = (string) (Venta::query()->find($ventaNcId)?->codigo ?? '');
            } else {
                $leyenda = $this->leyendaLote(
                    $pvCodigo,
                    array_map(static fn (array $d): int => (int) $d['numero'], $detalleFac),
                );
                $resultadoNc = $this->notaCreditoService->generarLoteAjusteFiscal(
                    $ventaFacturaIds,
                    $leyenda,
                    [
                        'fecha_factura' => $fechaNc,
                        'fecha_jornada' => $fechaJornada,
                        'identificador_pc' => (string) $turno->identificador_pc,
                        'omitir_stock' => true,
                        'omitir_impresion' => true,
                    ],
                );
                if (empty($resultadoNc['ok']) || empty($resultadoNc['venta_id'])) {
                    throw new RuntimeException((string) ($resultadoNc['error'] ?? 'No se pudo emitir la NC consolidada.'));
                }
                $ventaNcId = (int) $resultadoNc['venta_id'];
                $ncCodigo = (string) ($resultadoNc['factura'] ?? '');
            }

            $z = null;
            if ($turno->estado === TurnoOperativoGastronomia::ESTADO_CERRADO) {
                $z = $this->ajusteZService->actualizarLote($ventaFacturaIds, $ventaNcId, (int) $turno->id);
            }

            $this->marcarPendientesResueltos(
                $empresaId,
                $fechaJornada,
                (int) $recuperables[0]['puntoventa_id'],
                array_map(static fn (array $d): int => (int) $d['numero'], $detalleFac),
                $ventaFacturaIds,
                $ventaNcId,
            );

            Log::info('gastronomia.saneamiento_huecos_arca_lote.ok', [
                'turno_id' => $turno->id,
                'venta_factura_ids' => $ventaFacturaIds,
                'venta_nc_id' => $ventaNcId,
            ]);

            return [
                'ok' => true,
                'facturas' => $detalleFac,
                'venta_nc_id' => $ventaNcId,
                'factura_nc' => $ncCodigo,
                'z' => $z,
                'mensaje' => 'Lote saneado: '.count($detalleFac).' FAC recuperada(s) y NC '.$ncCodigo.'.',
            ];
        });
    }

    /**
     * Sanea huecos de una jornada (artisan / día siguiente) re-detectando o leyendo pendientes.
     *
     * @return array<string, mixed>
     */
    public function ejecutarParaJornada(
        int $empresaId,
        string $fechaJornada,
        ?string $pvCodigo = null,
        bool $dryRun = false,
        bool $ejecutar = false,
    ): array {
        $query = TurnoOperativoGastronomia::query()
            ->with(['jornada', 'configuracionPuntoventa.puntoventaCae'])
            ->where('empresa_id', $empresaId)
            ->whereHas('jornada', fn ($j) => $j->whereDate('fecha_jornada', $fechaJornada))
            ->orderBy('id');

        $resultados = [];
        foreach ($query->get() as $turno) {
            $det = GastronomiaTurnoHuecosArcaSupport::detectarParaTurno(
                $turno,
                $turno->cierre_en ? Carbon::parse($turno->cierre_en) : null,
            );
            if ($pvCodigo !== null && $pvCodigo !== '') {
                $pvNorm = str_pad(trim($pvCodigo), 5, '0', STR_PAD_LEFT);
                if ((string) ($det['puntoventa_codigo'] ?? '') !== $pvNorm) {
                    continue;
                }
            }
            if ((int) $det['cantidad'] <= 0) {
                continue;
            }

            if ($dryRun || ! $ejecutar) {
                $diag = $this->diagnosticar($turno);
                $resultados[] = [
                    'turno_id' => (int) $turno->id,
                    'dry_run' => true,
                    'diagnostico' => $diag,
                ];
                continue;
            }

            $resultados[] = array_merge(
                ['turno_id' => (int) $turno->id],
                $this->ejecutar($turno, null, false, now()->format('Y-m-d')),
            );
        }

        return [
            'ok' => true,
            'empresa_id' => $empresaId,
            'fecha_jornada' => $fechaJornada,
            'turnos' => $resultados,
        ];
    }

    /**
     * @param  list<int>  $numeros
     */
    private function leyendaLote(string $pvCodigo, array $numeros): string
    {
        $lista = implode(', ', array_map(
            static fn (int $n): string => str_pad((string) $n, 8, '0', STR_PAD_LEFT),
            $numeros,
        ));
        $txt = 'Saneamiento fiscal ARCA lote PV '.$pvCodigo.' FAC '.$lista;
        if (mb_strlen($txt) > 255) {
            $txt = mb_substr($txt, 0, 255);
        }

        return $txt;
    }

    private function resolverCuentaReferencia(
        TurnoOperativoGastronomia $turno,
        float $impTotal,
        string $fechaJornada,
    ): ?int {
        if ($impTotal <= 0) {
            return null;
        }

        $turno->loadMissing('configuracionPuntoventa');
        $cfg = $turno->configuracionPuntoventa;
        $pvCaeaId = (int) ($cfg->puntoventa_caea_id ?? 0);
        $desde = Carbon::parse($turno->habilitacion_en);
        $hasta = $turno->cierre_en ? Carbon::parse($turno->cierre_en) : now();
        $pc = (string) $turno->identificador_pc;
        $tol = 0.05;

        $candidatos = VentaGastronomiaEmision::query()
            ->with('venta')
            ->where('identificador_pc', $pc)
            ->whereNull('venta_factura_origen_id')
            ->whereNotNull('cuenta_gastronomia_id')
            ->whereHas('venta', function ($v) use ($fechaJornada, $desde, $hasta, $impTotal, $tol) {
                $v->whereNull('deleted_at')
                    ->where(function ($fecha) use ($fechaJornada) {
                        $fecha->whereDate('fechajornada', $fechaJornada)
                            ->orWhere(function ($legacy) use ($fechaJornada) {
                                $legacy->whereNull('fechajornada')->whereDate('fecha', $fechaJornada);
                            });
                    })
                    ->whereBetween('created_at', [$desde, $hasta])
                    ->whereRaw('ABS(ABS(total) - ?) <= ?', [$impTotal, $tol]);
            })
            ->get();

        if ($pvCaeaId > 0) {
            $preferidos = $candidatos->filter(
                static fn ($em) => (int) ($em->venta?->puntoventa_id ?? 0) === $pvCaeaId,
            );
            if ($preferidos->isNotEmpty()) {
                $candidatos = $preferidos;
            }
        }

        foreach ($candidatos as $em) {
            $cuentaId = (int) ($em->cuenta_gastronomia_id ?? 0);
            if ($cuentaId > 0) {
                return $cuentaId;
            }
        }

        return null;
    }

    private function persistirPendiente(
        int $empresaId,
        string $fechaJornada,
        int $puntoventaId,
        int $numero,
        TurnoOperativoGastronomia $turno,
        string $estado,
        ?string $error,
    ): void {
        GastronomiaHuecoArcaPendiente::query()->updateOrCreate(
            [
                'empresa_id' => $empresaId,
                'puntoventa_id' => $puntoventaId,
                'numero_comprobante' => $numero,
                'fecha_jornada' => $fechaJornada,
            ],
            [
                'turno_operativo_gastronomia_id' => $turno->id,
                'identificador_pc' => (string) $turno->identificador_pc,
                'estado' => $estado,
                'ultimo_error' => $error,
                'diagnosticado_en' => now(),
            ],
        );
    }

    /**
     * @param  list<int>  $numeros
     * @param  list<int>  $ventaFacturaIds
     */
    private function marcarPendientesResueltos(
        int $empresaId,
        string $fechaJornada,
        int $puntoventaId,
        array $numeros,
        array $ventaFacturaIds,
        int $ventaNcId,
    ): void {
        $mapFac = [];
        foreach ($ventaFacturaIds as $facId) {
            $v = Venta::query()->find($facId);
            if ($v) {
                $mapFac[(int) $v->numerocomprobante] = (int) $v->id;
            }
        }

        foreach ($numeros as $numero) {
            GastronomiaHuecoArcaPendiente::query()
                ->where('empresa_id', $empresaId)
                ->where('puntoventa_id', $puntoventaId)
                ->where('numero_comprobante', $numero)
                ->whereDate('fecha_jornada', $fechaJornada)
                ->update([
                    'estado' => GastronomiaHuecoArcaPendiente::ESTADO_RESUELTO,
                    'venta_factura_id' => $mapFac[$numero] ?? null,
                    'venta_nc_id' => $ventaNcId,
                    'resuelto_en' => now(),
                    'ultimo_error' => null,
                ]);
        }
    }
}
