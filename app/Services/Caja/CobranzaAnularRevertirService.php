<?php

namespace App\Services\Caja;

use App\Models\Caja\Caja_Movimiento;
use App\Models\Caja\Caja_Movimiento_Cuentacaja;
use App\Models\Caja\Caja_Movimiento_Estado;
use App\Models\Caja\Cheque;
use App\Models\Caja\Cobranza;
use App\Models\Caja\Cobranza_Archivo;
use App\Models\Caja\Cobranza_Comprobante;
use App\Models\Caja\Cobranza_Descuento;
use App\Models\Caja\Cobranza_Estado;
use App\Models\Caja\Cobranza_Retencion;
use App\Models\Contable\Asiento;
use App\Models\Ventas\Cliente_Cuentacorriente;
use App\Models\Ventas\Cliente_Cuentacorriente_Aplicacion;
use App\Repositories\Caja\CobranzaRepositoryInterface;
use App\Repositories\Contable\AsientoRepositoryInterface;
use App\Services\Ventas\ClienteCuentacorrienteAplicacionAnitaSyncService;
use App\Support\Caja\CajaMovimientoEloquentDeleteSupport;
use App\Support\Caja\ChequeOperacionActivaSupport;
use App\Support\Caja\CobranzaNumeracionTransaccion;
use App\Support\Caja\IngresoEgresoAnitaTesmovSupport;
use App\Support\Contable\AsientoReversoSupport;
use App\Support\Contable\PeriodoContableCierreSupport;
use App\Support\Contable\Sicore\SicoreEmpresaAnitaSupport;
use App\Support\Database\EloquentAuditDeleteSupport;
use DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Anulación física de cobranza: revierte CC/aplicaciones, asiento, caja, cheques y Anita.
 *
 * El delete histórico de CobranzaRepository solo borraba la cabecera y dejaba huérfanos
 * (incidente 22/sep/2026, cobranza 106601 / COB 77744).
 */
class CobranzaAnularRevertirService
{
    public function __construct(
        private CobranzaRepositoryInterface $cobranzaRepository,
        private AsientoRepositoryInterface $asientoRepository,
        private ClienteCuentacorrienteAplicacionAnitaSyncService $aplicacionAnitaSyncService,
        private CobranzaService $cobranzaService,
        private CobranzaDescuentoNotaDebitoService $notaDebitoDescuentoService,
        private AsientoReversoSupport $asientoReversoSupport,
    ) {}

    /**
     * @return array{mensaje: string, cobranza_id: int, aviso?: string, ndp?: list<string>}
     */
    public function anularFisicamente(int $id): array
    {
        $cobranza = $this->cobranzaRepository->findOrFail($id);
        $this->assertNoEsReversion((int) $cobranza->id, (int) $cobranza->tipotransaccion_caja_id, (string) $cobranza->estado);

        PeriodoContableCierreSupport::assertOperacionPermitida(
            (int) $cobranza->empresa_id,
            $this->fechaYmd($cobranza->fecha),
            PeriodoContableCierreSupport::ALCANCE_COBRANZA
        );

        $ventaNcIds = $this->idsNotasCreditoEmitidas((int) $cobranza->id);

        $fechaBaja = $this->fechaYmd($cobranza->fecha);

        $resultado = DB::transaction(function () use ($cobranza, $fechaBaja) {
            $actual = Cobranza::query()->whereKey((int) $cobranza->id)->lockForUpdate()->first();
            if ($actual === null) {
                throw new RuntimeException('La cobranza ya no existe.');
            }
            $this->assertNoEsReversion((int) $actual->id, (int) $actual->tipotransaccion_caja_id, (string) $actual->estado);

            $anitaChequesFallos = $this->limpiarCascada(
                cobranzaId: (int) $actual->id,
                empresaId: (int) $actual->empresa_id,
                numeroTransaccion: (string) ($actual->numerotransaccion ?? ''),
                borrarCabecera: $actual,
                fechaBajaChequesYmd: $fechaBaja,
            );

            $salida = [
                'mensaje' => 'ok',
                'cobranza_id' => (int) $actual->id,
            ];
            if ($anitaChequesFallos !== []) {
                $salida['aviso'] = 'Los cheques se anularon en el ERP pero Anita no dio de baja: '
                    .implode('; ', $anitaChequesFallos).'.';
            }

            return $salida;
        });

        return $this->conNotaDebitoDescuento($resultado, $ventaNcIds, date('Y-m-d'));
    }

    /**
     * Reversión compensatoria. El botón Revertir ya postea a caja/copiar_cobranza.
     *
     * @return array{
     *     mensaje: string,
     *     cobranza_id: int,
     *     cobranza_reverso_id: int,
     *     caja_movimiento_id: int,
     *     numerotransaccion: string,
     *     ndp: list<string>,
     *     aviso?: string
     * }
     */
    public function revertir(int $id, ?string $fecha = null): array
    {
        $cobranza = $this->cobranzaRepository->findOrFail($id);
        $fechaOp = $this->fechaOperacion($fecha);

        if ($this->esCompensatoria($cobranza)) {
            throw new RuntimeException('Esta cobranza es la reversión. No se vuelve a revertir.');
        }

        $abreviatura = strtoupper(trim((string) ($cobranza->tipotransaccioncajas->abreviatura ?? '')));
        if ($abreviatura === 'COA') {
            throw new RuntimeException('Revertí la cobranza que originó el anticipo, no la COA.');
        }

        $ventaNcIds = $this->idsNotasCreditoEmitidas((int) $cobranza->id);
        if ($this->yaRevertida((int) $cobranza->id, (int) $cobranza->tipotransaccion_caja_id, (string) $cobranza->estado)) {
            return $this->completarNotasDebitoDeReversionYaGrabada($cobranza, $ventaNcIds, $fechaOp);
        }

        if ((string) $cobranza->estado === 'PRE CARGA') {
            throw new RuntimeException('La cobranza está en pre carga y no emitió nota de crédito. Anulala, no la reviertas.');
        }
        if ((string) $cobranza->estado !== 'CONFIRMADA') {
            throw new RuntimeException('Solo se puede revertir una cobranza confirmada.');
        }

        PeriodoContableCierreSupport::assertOperacionPermitida(
            (int) $cobranza->empresa_id,
            $fechaOp,
            PeriodoContableCierreSupport::ALCANCE_COBRANZA
        );

        $local = CobranzaNumeracionTransaccion::conExclusividad(
            (int) $cobranza->empresa_id,
            (int) $cobranza->tipotransaccion_caja_id,
            function () use ($cobranza, $fechaOp) {
                return DB::transaction(function () use ($cobranza, $fechaOp) {
                    $actual = Cobranza::query()->whereKey((int) $cobranza->id)->lockForUpdate()->first();
                    if ($actual === null) {
                        throw new RuntimeException('La cobranza ya no existe.');
                    }
                    if ($this->yaRevertida((int) $actual->id, (int) $actual->tipotransaccion_caja_id, (string) $actual->estado)) {
                        throw new RuntimeException('La cobranza ya fue revertida.');
                    }
                    if ((string) $actual->estado !== 'CONFIRMADA') {
                        throw new RuntimeException('Solo se puede revertir una cobranza confirmada.');
                    }

                    $anticiposAnita = $this->cobranzaService->anticiposAnitaDeCobranza((int) $actual->id);
                    $this->cobranzaService->assertAnticiposAnitaSinCobrar($anticiposAnita);
                    app(CobranzaPagoDeMasCoaService::class)->eliminarCoaHijas((int) $actual->id);

                    $snapshots = $this->snapshotsAplicacionesAnita((int) $actual->id);
                    $numero = CobranzaNumeracionTransaccion::calcularSiguienteNumeroSecuencialBd(
                        (int) $actual->empresa_id,
                        (int) $actual->tipotransaccion_caja_id,
                    );
                    $nroOrig = (string) ($actual->numerotransaccion ?? '');
                    $leyenda = 'Revierte cobranza Nro '.$nroOrig;

                    $reverso = Cobranza::query()->create([
                        'empresa_id' => (int) $actual->empresa_id,
                        'tipotransaccion_caja_id' => (int) $actual->tipotransaccion_caja_id,
                        'numerotransaccion' => $numero,
                        'fecha' => $fechaOp,
                        'caja_id' => $actual->caja_id,
                        'cliente_id' => (int) $actual->cliente_id,
                        'venta_id' => $actual->venta_id,
                        'detalle' => $this->texto($leyenda.'. '.$actual->detalle),
                        'estado' => 'REVERTIDA',
                        'monto' => -1 * (float) $actual->monto,
                        'cotizacion' => (float) ($actual->cotizacion ?: 1),
                        'moneda_id' => (int) ($actual->moneda_id ?: 1),
                        'usuario_id' => Auth::id(),
                        'cobranza_origen_id' => (int) $actual->id,
                    ]);

                    $this->registrarEstado($reverso, 'REVERTIDA', $leyenda);
                    $this->compensarCuentaCorriente($actual, $reverso, $fechaOp, $numero);
                    $cajaReverso = $this->crearCajaCompensatoria($actual, $reverso, $fechaOp, $leyenda);
                    $chequesAnula = ChequeOperacionActivaSupport::anularPorCobranza(
                        (int) $actual->id,
                        $fechaOp
                    );
                    $this->revertirAsientos($actual, $reverso, $fechaOp, $leyenda, $cajaReverso);
                    $this->copiarRetencionesNegadas($actual, $reverso);

                    $detalleOrig = trim((string) ($actual->detalle ?? ''));
                    if ($detalleOrig === '' || preg_match('/^REVERTIDA\b/iu', $detalleOrig) !== 1) {
                        $detalleOrig = $this->texto('REVERTIDA: '.$detalleOrig);
                    }
                    $actual->estado = 'REVERTIDA';
                    $actual->detalle = $detalleOrig;
                    $actual->save();
                    $this->registrarEstado($actual, 'REVERTIDA', $leyenda.' (compensatoria '.$reverso->id.')');

                    return [
                        'reverso' => $reverso,
                        'caja' => $cajaReverso,
                        'snapshots' => $snapshots,
                        'anticipos' => $anticiposAnita,
                        'cliente_id' => (int) $actual->cliente_id,
                        'cotizacion' => (float) ($actual->cotizacion ?: 1),
                        'empresa_id' => (int) $actual->empresa_id,
                        'detalle' => $leyenda,
                        'anita_cheques_fallos' => $chequesAnula['anita_fallos'],
                    ];
                });
            }
        );

        $reverso = $local['reverso'];
        $salida = [
            'mensaje' => 'ok',
            'cobranza_id' => (int) $cobranza->id,
            'cobranza_reverso_id' => (int) $reverso->id,
            'caja_movimiento_id' => (int) ($local['caja']->id ?? 0),
            'numerotransaccion' => (string) $reverso->numerotransaccion,
            'ndp' => [],
        ];

        $avisos = [];
        $anitaChequesFallos = $local['anita_cheques_fallos'] ?? [];
        if ($anitaChequesFallos !== []) {
            $avisos[] = 'Los cheques se anularon en el ERP pero Anita no dio de baja: '
                .implode('; ', $anitaChequesFallos).'.';
        }
        try {
            foreach ($local['snapshots'] as $snapshot) {
                $this->aplicacionAnitaSyncService->revertir($snapshot);
            }
            $this->replicarReversoEnAnita($local, $reverso, $fechaOp);
            $this->cobranzaService->borrarClimovAnticiposAnita($local['anticipos']);
        } catch (Throwable $e) {
            Log::error('cobranza.reversion.anita', [
                'cobranza_id' => (int) $cobranza->id,
                'reverso_id' => (int) $reverso->id,
                'error' => $e->getMessage(),
            ]);
            $avisos[] = 'La reversión quedó grabada en el ERP (cobranza '.$reverso->numerotransaccion
                .') pero falló la réplica en Anita: '.$e->getMessage()
                .'. No reintente la reversión.';
        }

        $ndp = $this->notaDebitoDescuentoService->emitirYAplicar($ventaNcIds, $fechaOp);
        $salida['ndp'] = $ndp['codigos'];
        $avisos = array_merge($avisos, $ndp['avisos']);
        if ($avisos !== []) {
            $salida['aviso'] = implode(' ', $avisos);
        }

        return $salida;
    }

    /**
     * Repara huérfanos cuando la cabecera ya se borró (bug histórico) y quedan CC/caja/asiento.
     *
     * @return array{mensaje: string, cobranza_id: int, limpio: bool}
     */
    public function limpiarHuerfanosSinCabecera(
        int $cobranzaId,
        int $empresaId,
        string $numeroTransaccion,
        string $fechaYmd,
    ): array {
        if (Cobranza::query()->whereKey($cobranzaId)->exists()) {
            throw new RuntimeException(
                'La cobranza '.$cobranzaId.' aún existe: use anularFisicamente().'
            );
        }

        PeriodoContableCierreSupport::assertOperacionPermitida(
            $empresaId,
            $fechaYmd,
            PeriodoContableCierreSupport::ALCANCE_COBRANZA
        );

        $ventaNcIds = $this->idsNotasCreditoEmitidas($cobranzaId);

        $resultado = DB::transaction(function () use ($cobranzaId, $empresaId, $numeroTransaccion, $fechaYmd) {
            $anitaChequesFallos = $this->limpiarCascada(
                cobranzaId: $cobranzaId,
                empresaId: $empresaId,
                numeroTransaccion: $numeroTransaccion,
                borrarCabecera: null,
                fechaBajaChequesYmd: $fechaYmd,
            );

            $salida = [
                'mensaje' => 'ok',
                'cobranza_id' => $cobranzaId,
                'limpio' => true,
            ];
            if ($anitaChequesFallos !== []) {
                $salida['aviso'] = 'Los cheques se anularon en el ERP pero Anita no dio de baja: '
                    .implode('; ', $anitaChequesFallos).'.';
            }

            return $salida;
        });

        return $this->conNotaDebitoDescuento($resultado, $ventaNcIds, date('Y-m-d'));
    }

    /**
     * @return list<string> Fallos al dar de baja CHT en Anita
     */
    private function limpiarCascada(
        int $cobranzaId,
        int $empresaId,
        string $numeroTransaccion,
        ?Cobranza $borrarCabecera,
        ?string $fechaBajaChequesYmd = null,
    ): array {
        $anticiposAnita = $this->cobranzaService->anticiposAnitaDeCobranza($cobranzaId);
        $this->cobranzaService->assertAnticiposAnitaSinCobrar($anticiposAnita);

        app(CobranzaPagoDeMasCoaService::class)->eliminarCoaHijas($cobranzaId);

        $snapshots = [];
        $aplicacionesDeuda = Cliente_Cuentacorriente_Aplicacion::query()
            ->where('cobranza_id', $cobranzaId)
            ->where('total', '<', 0)
            ->orderBy('id')
            ->get();
        foreach ($aplicacionesDeuda as $apl) {
            try {
                $snapshots[] = $this->aplicacionAnitaSyncService->snapshotDesdeAplicacion($apl);
            } catch (Throwable $e) {
                Log::warning('cobranza.anular.anita_cc_snapshot', [
                    'cobranza_id' => $cobranzaId,
                    'aplicacion_id' => $apl->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        foreach ($snapshots as $snapshot) {
            try {
                $this->aplicacionAnitaSyncService->revertir($snapshot);
            } catch (Throwable $e) {
                Log::warning('cobranza.anular.anita_cc_revertir', [
                    'cobranza_id' => $cobranzaId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        EloquentAuditDeleteSupport::each(
            Cliente_Cuentacorriente_Aplicacion::query()->where('cobranza_id', $cobranzaId)
        );

        EloquentAuditDeleteSupport::each(
            Cliente_Cuentacorriente::query()->where('cobranza_id', $cobranzaId)
        );

        EloquentAuditDeleteSupport::each(
            Cobranza_Comprobante::query()->where('cobranza_id', $cobranzaId)
        );

        $asientos = Asiento::query()->where('cobranza_id', $cobranzaId)->get();
        foreach ($asientos as $asiento) {
            $this->asientoRepository->delete((int) $asiento->id);
        }

        $cajaMovimientos = Caja_Movimiento::query()
            ->where('cobranza_id', $cobranzaId)
            ->get();

        $tesoreriaBorrada = false;
        foreach ($cajaMovimientos as $mov) {
            IngresoEgresoAnitaTesmovSupport::eliminarDesdeMovimiento($mov);
            $tesoreriaBorrada = true;
        }

        if (! $tesoreriaBorrada && $numeroTransaccion !== '') {
            $empresaAnita = SicoreEmpresaAnitaSupport::codigoEmpresaAnita($empresaId);
            if ($empresaAnita <= 0) {
                $empresaAnita = $empresaId;
            }
            IngresoEgresoAnitaTesmovSupport::eliminarTesoreriaPorClave(
                'COB',
                (int) $numeroTransaccion,
                $empresaAnita,
                'cobranza anular '.$cobranzaId
            );
        }

        try {
            $this->cobranzaService->borrarClimovAnticiposAnita($anticiposAnita);
        } catch (Throwable $e) {
            Log::warning('cobranza.anular.borra_anita_coa', [
                'cobranza_id' => $cobranzaId,
                'error' => $e->getMessage(),
            ]);
        }

        try {
            $this->cobranzaService->borraAnita('COB', 'X', 0, $numeroTransaccion, $empresaId);
        } catch (Throwable $e) {
            Log::warning('cobranza.anular.borra_anita_pago', [
                'cobranza_id' => $cobranzaId,
                'error' => $e->getMessage(),
            ]);
        }

        $chequesAnula = ChequeOperacionActivaSupport::anularPorCobranza(
            $cobranzaId,
            $fechaBajaChequesYmd
        );
        EloquentAuditDeleteSupport::each(
            Cheque::query()->where('cobranza_id', $cobranzaId)
        );

        CajaMovimientoEloquentDeleteSupport::eliminarPorQuery(
            Caja_Movimiento::query()->where('cobranza_id', $cobranzaId)
        );

        EloquentAuditDeleteSupport::each(
            Cobranza_Descuento::query()->where('cobranza_id', $cobranzaId)
        );
        EloquentAuditDeleteSupport::each(
            Cobranza_Retencion::query()->where('cobranza_id', $cobranzaId)
        );
        EloquentAuditDeleteSupport::each(
            Cobranza_Archivo::query()->where('cobranza_id', $cobranzaId)
        );
        EloquentAuditDeleteSupport::each(
            Cobranza_Estado::query()->where('cobranza_id', $cobranzaId)
        );

        $borrarCabecera?->delete();

        return $chequesAnula['anita_fallos'];
    }

    private function fechaYmd(mixed $fecha): string
    {
        if ($fecha instanceof \DateTimeInterface) {
            return $fecha->format('Y-m-d');
        }

        $raw = trim((string) $fecha);
        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $raw) === 1) {
            return substr($raw, 0, 10);
        }

        return date('Y-m-d');
    }

    /**
     * @param  array<string, mixed>  $resultado
     * @param  list<int>  $ventaNcIds
     * @return array<string, mixed>
     */
    private function conNotaDebitoDescuento(array $resultado, array $ventaNcIds, string $fecha): array
    {
        $ndp = $this->notaDebitoDescuentoService->emitirYAplicar($ventaNcIds, $fecha);
        $resultado['ndp'] = $ndp['codigos'];
        $avisos = [];
        if (! empty($resultado['aviso'])) {
            $avisos[] = trim((string) $resultado['aviso']);
        }
        if ($ndp['avisos'] !== []) {
            $avisos = array_merge($avisos, $ndp['avisos']);
        }
        if ($avisos !== []) {
            $resultado['aviso'] = implode(' ', $avisos);
        }

        return $resultado;
    }

    /**
     * @param  list<int>  $ventaNcIds
     * @return array<string, mixed>
     */
    private function completarNotasDebitoDeReversionYaGrabada(Cobranza $cobranza, array $ventaNcIds, string $fecha): array
    {
        $ndp = $this->notaDebitoDescuentoService->emitirYAplicar($ventaNcIds, $fecha);
        if (! $ndp['trabajo'] && $ndp['avisos'] === []) {
            throw new RuntimeException('La cobranza ya fue revertida.');
        }

        $reverso = Cobranza::query()
            ->where('cobranza_origen_id', (int) $cobranza->id)
            ->where('tipotransaccion_caja_id', (int) $cobranza->tipotransaccion_caja_id)
            ->where('estado', 'REVERTIDA')
            ->orderByDesc('id')
            ->first();
        $cajaId = 0;
        if ($reverso !== null) {
            $cajaId = (int) (Caja_Movimiento::query()
                ->where('cobranza_id', (int) $reverso->id)
                ->orderBy('id')
                ->value('id') ?? 0);
        }

        $salida = [
            'mensaje' => 'ok',
            'cobranza_id' => (int) $cobranza->id,
            'cobranza_reverso_id' => (int) ($reverso->id ?? 0),
            'caja_movimiento_id' => $cajaId,
            'numerotransaccion' => (string) ($reverso->numerotransaccion ?? $cobranza->numerotransaccion ?? ''),
            'ndp' => $ndp['codigos'],
            'aviso' => 'La cobranza ya estaba revertida.',
        ];
        if ($ndp['avisos'] !== []) {
            $salida['aviso'] = trim($salida['aviso'].' '.implode(' ', $ndp['avisos']));
        }

        return $salida;
    }

    private function assertNoEsReversion(int $cobranzaId, int $tipoCajaId, string $estado): void
    {
        if ($this->yaRevertida($cobranzaId, $tipoCajaId, $estado)) {
            throw new RuntimeException(
                'La cobranza está revertida. No se anula: la reversión ya compensó la cuenta corriente.'
            );
        }
    }

    private function yaRevertida(int $cobranzaId, int $tipoCajaId, string $estado): bool
    {
        if ($estado === 'REVERTIDA') {
            return true;
        }

        return Cobranza::query()
            ->where('cobranza_origen_id', $cobranzaId)
            ->where('tipotransaccion_caja_id', $tipoCajaId)
            ->where('estado', 'REVERTIDA')
            ->exists();
    }

    private function esCompensatoria(Cobranza $cobranza): bool
    {
        return (int) ($cobranza->cobranza_origen_id ?? 0) > 0
            && (string) $cobranza->estado === 'REVERTIDA';
    }

    /**
     * @return list<int>
     */
    private function idsNotasCreditoEmitidas(int $cobranzaId): array
    {
        return Cobranza_Descuento::query()
            ->where('cobranza_id', $cobranzaId)
            ->where('estado', Cobranza_Descuento::ESTADO_EMITIDA)
            ->where('venta_nc_id', '>', 0)
            ->pluck('venta_nc_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    private function fechaOperacion(?string $fecha): string
    {
        $fecha = trim((string) $fecha);
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha) === 1) {
            return $fecha;
        }

        return date('Y-m-d');
    }

    private function registrarEstado(Cobranza $cobranza, string $estado, string $observacion): void
    {
        Cobranza_Estado::query()->create([
            'cobranza_id' => (int) $cobranza->id,
            'fecha' => $this->fechaYmd($cobranza->fecha),
            'estado' => $estado,
            'usuario_id' => Auth::id(),
            'observacion' => $this->texto($observacion),
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function snapshotsAplicacionesAnita(int $cobranzaId): array
    {
        $snapshots = [];
        $aplicaciones = Cliente_Cuentacorriente_Aplicacion::query()
            ->where('cobranza_id', $cobranzaId)
            ->where('total', '<', 0)
            ->orderBy('id')
            ->get();
        foreach ($aplicaciones as $apl) {
            try {
                $snapshots[] = $this->aplicacionAnitaSyncService->snapshotDesdeAplicacion($apl);
            } catch (Throwable $e) {
                Log::warning('cobranza.reversion.anita_cc_snapshot', [
                    'cobranza_id' => $cobranzaId,
                    'aplicacion_id' => $apl->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $snapshots;
    }

    private function compensarCuentaCorriente(Cobranza $original, Cobranza $reverso, string $fecha, string $numero): void
    {
        $mapaCc = [];
        $ccOriginales = Cliente_Cuentacorriente::query()
            ->where('cobranza_id', (int) $original->id)
            ->orderBy('id')
            ->get();
        foreach ($ccOriginales as $ccOrig) {
            $ccNueva = Cliente_Cuentacorriente::query()->create([
                'fecha' => $fecha,
                'fechavencimiento' => $fecha,
                'cliente_id' => $ccOrig->cliente_id,
                'total' => -1 * (float) $ccOrig->total,
                'moneda_id' => $ccOrig->moneda_id,
                'cotizacion' => $ccOrig->cotizacion,
                'venta_id' => $ccOrig->venta_id,
                'cobranza_id' => (int) $reverso->id,
                'empresa_id' => $ccOrig->empresa_id,
                'leyenda' => $this->texto('Revierte cobranza '.$numero),
            ]);
            $mapaCc[(int) $ccOrig->id] = (int) $ccNueva->id;
        }

        $aplicaciones = Cliente_Cuentacorriente_Aplicacion::query()
            ->where('cobranza_id', (int) $original->id)
            ->orderBy('id')
            ->get();
        foreach ($aplicaciones as $apl) {
            $ccId = (int) $apl->cliente_cuentacorriente_id;
            $aplicadoId = (int) $apl->cliente_cuentacorriente_aplicado_id;
            $dc = $apl->diferencia_cambio;
            Cliente_Cuentacorriente_Aplicacion::query()->create([
                'fecha' => $fecha,
                'cliente_cuentacorriente_id' => $mapaCc[$ccId] ?? $ccId,
                'total' => -1 * (float) $apl->total,
                'moneda_id' => $apl->moneda_id,
                'cotizacion' => $apl->cotizacion,
                'cotizacion_liquidacion' => $apl->cotizacion_liquidacion,
                'diferencia_cambio' => $dc === null ? null : -1 * (float) $dc,
                'ventaaplicado_id' => $apl->ventaaplicado_id,
                'cobranza_id' => (int) $reverso->id,
                'comprobanteaplicado' => isset($mapaCc[$aplicadoId])
                    ? $numero
                    : $apl->comprobanteaplicado,
                'empresa_id' => $apl->empresa_id ?: $reverso->empresa_id,
                'cliente_cuentacorriente_aplicado_id' => $mapaCc[$aplicadoId] ?? $aplicadoId,
            ]);
        }

        $comprobantes = Cobranza_Comprobante::query()
            ->where('cobranza_id', (int) $original->id)
            ->orderBy('id')
            ->get();
        foreach ($comprobantes as $comprobante) {
            Cobranza_Comprobante::query()->create([
                'cobranza_id' => (int) $reverso->id,
                'cliente_cuentacorriente_id' => $comprobante->cliente_cuentacorriente_id,
                'montoaplicado' => -1 * (float) $comprobante->montoaplicado,
                'moneda_id' => $comprobante->moneda_id,
                'cotizacion' => $comprobante->cotizacion,
            ]);
        }
    }

    private function crearCajaCompensatoria(Cobranza $original, Cobranza $reverso, string $fecha, string $leyenda): ?Caja_Movimiento
    {
        $movimientos = Caja_Movimiento::query()
            ->with('caja_movimiento_cuentacajas')
            ->where('cobranza_id', (int) $original->id)
            ->orderBy('id')
            ->get();
        $primero = null;

        foreach ($movimientos as $movimiento) {
            $nuevo = Caja_Movimiento::query()->create([
                'empresa_id' => (int) $movimiento->empresa_id,
                'tipotransaccion_caja_id' => (int) $movimiento->tipotransaccion_caja_id,
                'numerotransaccion' => (string) $reverso->numerotransaccion,
                'fecha' => $fecha,
                'caja_id' => $movimiento->caja_id,
                'proveedor_id' => $movimiento->proveedor_id,
                'cliente_id' => $movimiento->cliente_id,
                'conceptogasto_id' => $movimiento->conceptogasto_id,
                'cobranza_id' => (int) $reverso->id,
                'venta_id' => $movimiento->venta_id,
                'detalle' => $this->texto($leyenda),
                'usuario_id' => Auth::id(),
                'caja_movimiento_origen_id' => (int) $movimiento->id,
            ]);
            Caja_Movimiento_Estado::query()->create([
                'caja_movimiento_id' => (int) $nuevo->id,
                'fecha' => $fecha,
                'estado' => 'A',
                'observacion' => $this->texto($leyenda),
            ]);
            foreach ($movimiento->caja_movimiento_cuentacajas as $linea) {
                Caja_Movimiento_Cuentacaja::query()->create([
                    'caja_movimiento_id' => (int) $nuevo->id,
                    'cuentacaja_id' => $linea->cuentacaja_id,
                    'fecha' => $fecha,
                    'monto' => -1 * (float) $linea->monto,
                    'moneda_id' => $linea->moneda_id,
                    'cotizacion' => $linea->cotizacion,
                    'observacion' => $linea->observacion,
                ]);
            }
            $movimiento->caja_movimiento_revertido_por_id = (int) $nuevo->id;
            $movimiento->save();
            Caja_Movimiento_Estado::query()->create([
                'caja_movimiento_id' => (int) $movimiento->id,
                'fecha' => $fecha,
                'estado' => 'R',
                'observacion' => $this->texto($leyenda),
            ]);
            $primero ??= $nuevo;
        }

        return $primero;
    }

    private function revertirAsientos(
        Cobranza $original,
        Cobranza $reverso,
        string $fecha,
        string $leyenda,
        ?Caja_Movimiento $cajaReverso,
    ): void {
        $asientos = Asiento::query()
            ->with('asiento_movimientos')
            ->where('cobranza_id', (int) $original->id)
            ->orderBy('id')
            ->get();
        foreach ($asientos as $asiento) {
            $res = $this->asientoReversoSupport->generarDesdeAsiento(
                $asiento,
                $fecha,
                null,
                $leyenda,
                false,
                $cajaReverso !== null ? (int) $cajaReverso->id : null,
                null,
                PeriodoContableCierreSupport::ALCANCE_COBRANZA,
            );
            if (empty($res['asiento_id'])) {
                continue;
            }
            Asiento::query()->where('id', (int) $res['asiento_id'])->update([
                'cobranza_id' => (int) $reverso->id,
            ]);
        }
    }

    private function copiarRetencionesNegadas(Cobranza $original, Cobranza $reverso): void
    {
        $retenciones = Cobranza_Retencion::query()
            ->where('cobranza_id', (int) $original->id)
            ->orderBy('id')
            ->get();
        foreach ($retenciones as $retencion) {
            Cobranza_Retencion::query()->create([
                'cobranza_id' => (int) $reverso->id,
                'retencion_cobranza_id' => $retencion->retencion_cobranza_id,
                'monto' => -1 * (float) $retencion->monto,
                'moneda_id' => $retencion->moneda_id,
                'cotizacion' => $retencion->cotizacion,
                'tasa' => $retencion->tasa,
                'comprobante' => $retencion->comprobante,
            ]);
        }
    }

    /**
     * @param  array{
     *     caja: ?Caja_Movimiento,
     *     cliente_id: int,
     *     cotizacion: float,
     *     empresa_id: int,
     *     detalle: string
     * }  $local
     */
    private function replicarReversoEnAnita(array $local, Cobranza $reverso, string $fecha): void
    {
        $caja = $local['caja'];
        $cuentacajaIds = [];
        $monedaIds = [];
        $montos = [];
        $cotizaciones = [];
        $observaciones = [];
        if ($caja !== null) {
            $caja->load('caja_movimiento_cuentacajas');
            foreach ($caja->caja_movimiento_cuentacajas as $linea) {
                $cuentacajaIds[] = $linea->cuentacaja_id;
                $monedaIds[] = $linea->moneda_id;
                $montos[] = $linea->monto;
                $cotizaciones[] = $linea->cotizacion;
                $observaciones[] = (string) ($linea->observacion ?? '');
            }
        }

        $anita = $this->cobranzaService->grabaAnita(
            $fecha,
            'COB',
            'X',
            0,
            (string) $reverso->numerotransaccion,
            (float) $reverso->monto,
            (float) $local['cotizacion'],
            (string) $local['detalle'],
            (int) $local['empresa_id'],
            [
                'cliente_id' => (int) $local['cliente_id'],
                'fecha' => $fecha,
                'detalle' => (string) $local['detalle'],
                'empresa_id' => (int) $local['empresa_id'],
                'cuentacaja_ids' => $cuentacajaIds,
                'moneda_ids' => $monedaIds,
                'montos' => $montos,
                'cotizaciones' => $cotizaciones,
                'observaciones' => $observaciones,
            ],
        );
        if (is_array($anita) && isset($anita['error'])) {
            throw new RuntimeException((string) ($anita['mensaje'] ?? 'Error al grabar la reversión en Anita.'));
        }
    }

    private function texto(?string $valor): string
    {
        $valor = trim((string) $valor);
        if (mb_strlen($valor) > 250) {
            return mb_substr($valor, 0, 250);
        }

        return $valor;
    }
}
