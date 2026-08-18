<?php

declare(strict_types=1);

namespace App\Support\Ventas\Gastronomia;

use App\Models\Contable\Asiento;
use App\Models\Ventas\GastronomiaCierreJornadaProcesoSnapshot;
use App\Models\Ventas\JornadaGastronomia;
use App\Repositories\Contable\Asiento_MovimientoRepository;
use App\Repositories\Contable\AsientoRepository;
use App\Services\Ventas\Gastronomia\GastronomiaCierreJornadaProcesoService;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * Recalcula y reemplaza el asiento «ventas_medio_real» del cierre de jornada Waitry ya grabado.
 *
 * Usado tras recuperar una factura ARCA: la venta + cobranza entran en datosAsientoVentasJornadaExclTotem
 * y se regeneran todas las líneas del asiento consolidado (ERP + Anita ctamov).
 */
final class RecalcularAsientoVentasMedioRealCierreJornadaSupport
{
    private const CODIGO_ASIENTO = 'ventas_medio_real';

    private const TOLERANCIA_CUADRE = 0.02;

    public function __construct(
        private readonly AsientoRepository $asientoRepository,
        private readonly Asiento_MovimientoRepository $asientoMovimientoRepository,
        private readonly GastronomiaCierreJornadaProcesoService $procesoService,
    ) {
    }

    /**
     * Si el cierre de jornada ya tiene asiento «ventas_medio_real» grabado, lo recalcula con todas
     * las emisiones/cobranzas actuales de la fecha (incluye la factura recuperada).
     *
     * @return array<string, mixed>|null null si no hay asiento grabado para esa jornada
     */
    public function actualizarSiExiste(
        int $empresaId,
        string $fechaJornada,
        bool $sincronizarAnita = true,
    ): ?array
    {
        if ($empresaId <= 0 || $fechaJornada === '') {
            return null;
        }

        $asientoId = $this->resolverAsientoVentasMedioRealId($empresaId, $fechaJornada);
        if ($asientoId === null) {
            return null;
        }

        $config = CierreJornadaProcesoConfigSupport::paraEmpresa($empresaId);
        $datos = $this->datosAsientoConCompensacionSiAplica($empresaId, $fechaJornada);
        $lineas = CierreJornadaProcesoAsientosPreviewSupport::construirLineasVentasMedioReal($config, $datos);

        if ($lineas === []) {
            throw new RuntimeException(
                'No se generaron líneas para el asiento ventas_medio_real (empresa '.$empresaId.', jornada '.$fechaJornada.').',
            );
        }

        /** @var array<int, int> $cacheCuentas */
        $cacheCuentas = [];
        $payloadLineas = CierreJornadaProcesoAsientosGrabacionSupport::payloadDesdeLineasPreview(
            $lineas,
            $empresaId,
            $config,
            $cacheCuentas,
            CierreJornadaProcesoAsientosGrabacionSupport::DESCRIPCION_ASIENTO,
        );

        $debe = round(array_sum(array_filter($payloadLineas['debes'], 'is_numeric')), 2);
        $haber = round(array_sum(array_filter($payloadLineas['haberes'], 'is_numeric')), 2);
        if (abs($debe - $haber) > self::TOLERANCIA_CUADRE) {
            throw new RuntimeException(
                'Asiento ventas_medio_real recalculado no cuadra (debe '.$debe.' vs haber '.$haber.').',
            );
        }

        $asiento = Asiento::query()->findOrFail($asientoId);
        $payload = array_merge($payloadLineas, [
            'empresa_id' => $empresaId,
            'fecha' => $asiento->fecha,
            'observacion' => CierreJornadaProcesoAsientosGrabacionSupport::DESCRIPCION_ASIENTO,
            'numeroasiento' => $asiento->numeroasiento,
            'tipoasiento_id' => $asiento->tipoasiento_id,
        ]);

        $this->asientoMovimientoRepository->update($payload, $asientoId);

        $asiento->refresh()->load(['asiento_movimientos.monedas']);
        if ($sincronizarAnita) {
            $anitaPayload = $this->asientoRepository->armarPayloadAnitaDesdeModelo($asiento);
            $this->asientoRepository->sincronizarCtamovAnita($anitaPayload);
        }

        Log::info('gastronomia.recalcular_asiento_ventas_medio_real.ok', [
            'empresa_id' => $empresaId,
            'fecha_jornada' => $fechaJornada,
            'asiento_id' => $asientoId,
            'numeroasiento' => $asiento->numeroasiento,
            'debe' => $debe,
            'haber' => $haber,
            'lineas' => count($payloadLineas['cuentacontable_ids']),
            'anita_sincronizado' => $sincronizarAnita,
        ]);

        return [
            'asiento_id' => $asientoId,
            'numeroasiento' => (string) $asiento->numeroasiento,
            'resumen_debe' => $debe,
            'resumen_haber' => $haber,
            'cantidad_lineas' => count($payloadLineas['cuentacontable_ids']),
        ];
    }

    public function resolverAsientoVentasMedioRealId(int $empresaId, string $fechaJornada): ?int
    {
        $mapa = CierreJornadaProcesoAsientosGrabacionSupport::mapaAsientosGrabadosPorEmpresaJornada(
            $empresaId,
            $fechaJornada,
        );

        foreach ($mapa as $asientoId => $meta) {
            if (($meta['codigo'] ?? '') === self::CODIGO_ASIENTO) {
                return (int) $asientoId;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function datosAsientoConCompensacionSiAplica(int $empresaId, string $fechaJornada): array
    {
        $datos = CierreJornadaFacturadoAnitaSupport::datosAsientoVentasJornadaExclTotem($empresaId, $fechaJornada);

        $jornada = JornadaGastronomia::query()
            ->where('empresa_id', $empresaId)
            ->whereDate('fecha_jornada', $fechaJornada)
            ->first();

        if ($jornada === null) {
            return $datos;
        }

        $snapshot = GastronomiaCierreJornadaProcesoSnapshot::query()
            ->where('jornada_gastronomia_id', $jornada->id)
            ->first();

        if ($snapshot === null) {
            return $datos;
        }

        $payload = is_array($snapshot->payload) ? $snapshot->payload : [];
        $porcentaje = (float) (
            $payload['asientos_proceso_grabacion']['porcentaje']
            ?? $snapshot->porcentaje
            ?? 0
        );

        if ($porcentaje <= 0.0001) {
            return $datos;
        }

        try {
            $clasificacion = $this->procesoService->clasificacionActual((int) $jornada->id, $porcentaje);

            return CierreJornadaAnitaCompensacionOverlaySupport::aplicarDatosAsiento(
                $datos,
                $clasificacion['movimientos'] ?? [],
                $empresaId,
            );
        } catch (InvalidArgumentException|RuntimeException $e) {
            Log::warning('gastronomia.recalcular_asiento_ventas_medio_real.compensacion_omitida', [
                'empresa_id' => $empresaId,
                'fecha_jornada' => $fechaJornada,
                'error' => $e->getMessage(),
            ]);

            return $datos;
        } catch (Throwable $e) {
            Log::warning('gastronomia.recalcular_asiento_ventas_medio_real.compensacion_omitida', [
                'empresa_id' => $empresaId,
                'fecha_jornada' => $fechaJornada,
                'error' => $e->getMessage(),
            ]);

            return $datos;
        }
    }
}
