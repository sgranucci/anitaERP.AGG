<?php

declare(strict_types=1);

namespace App\Services\Caja\Remesa;

use App\Models\Caja\Cuentacaja;
use App\Models\Caja\Remesa;
use App\Models\Caja\RemesaLinea;
use App\Models\Contable\Asiento;
use App\Models\Contable\Asiento_Archivo;
use App\Repositories\Contable\AsientoRepositoryInterface;
use App\Repositories\Contable\Asiento_MovimientoRepositoryInterface;
use App\Repositories\Contable\TipoasientoRepositoryInterface;
use App\Support\Caja\CotizacionTesoreriaConsultaSupport;
use App\Support\Caja\Remesa\RemesaSupport;
use App\Support\Contable\CuentacajaCuentacontableResolverSupport;
use App\Support\Contable\PeriodoContableCierreSupport;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

final class RemesaAsientoService
{
    private const TOLERANCIA_CUADRE = 0.02;

    private const MONEDA_DEFAULT = 1;

    public function __construct(
        private readonly AsientoRepositoryInterface $asientoRepository,
        private readonly Asiento_MovimientoRepositoryInterface $asientoMovimientoRepository,
        private readonly TipoasientoRepositoryInterface $tipoasientoRepository,
    ) {}

    /**
     * Genera un asiento REM por moneda (patrón p-vtamaquina: Debe banco / Haber caja efectivo).
     * Devuelve el id representativo (preferencia pesos) para remesa.asiento_id.
     */
    public function generar(Remesa $remesa): int
    {
        if (! $remesa->esExterna()) {
            throw new InvalidArgumentException('Solo las remesas externas generan asiento contable.');
        }

        $remesa->loadMissing(['lineas.cuentacaja.cuentacontables', 'lineas.cuentacaja.monedas']);

        $grupos = $this->armarLineasAsientoPorMoneda($remesa);
        if ($grupos === []) {
            throw new InvalidArgumentException('No hay imputaciones contables para la remesa.');
        }

        foreach ($grupos as $monedaId => $pack) {
            $this->assertCuadre(
                (float) $pack['total_debe'],
                (float) $pack['total_haber'],
                'moneda #'.$monedaId
            );
        }

        $tipoasiento = $this->tipoasientoRepository->findPorAbreviatura(RemesaSupport::ABREV_TIPOASIENTO);
        if ($tipoasiento === null) {
            throw new RuntimeException(
                'No existe el tipo de asiento '.RemesaSupport::ABREV_TIPOASIENTO.' (remesa).'
            );
        }

        $cajaMovimientoId = (int) ($remesa->caja_movimiento_id ?? 0);
        if ($cajaMovimientoId <= 0) {
            throw new InvalidArgumentException('La remesa externa requiere movimiento de caja antes del asiento.');
        }

        $fecha = $remesa->fecha?->format('Y-m-d') ?? date('Y-m-d');
        $creados = [];
        $idPreferido = 0;

        try {
            foreach ($grupos as $monedaId => $pack) {
                $monedaId = (int) $monedaId;
                $cotizacion = $monedaId === self::MONEDA_DEFAULT
                    ? 1.0
                    : CotizacionTesoreriaConsultaSupport::calculaVenta($fecha, $monedaId, (int) $remesa->empresa_id);

                $abrev = (string) ($pack['moneda_abrev'] ?? '');
                $detalle = $this->detalleAsiento($remesa, $abrev);

                $payload = [
                    'empresa_id' => (int) $remesa->empresa_id,
                    'tipoasiento_id' => (int) $tipoasiento->id,
                    'fecha' => $fecha,
                    'observacion' => $detalle,
                    'usuario_id' => (int) ($remesa->usuario_id ?? auth()->id()),
                    'caja_movimiento_id' => $cajaMovimientoId,
                    'remesa_id' => (int) $remesa->id,
                    'omitir_anita' => true,
                    'alcance_cierre_contable' => PeriodoContableCierreSupport::ALCANCE_CAJA,
                    'cuentacontable_ids' => [],
                    'moneda_ids' => [],
                    'centrocosto_ids' => [],
                    'debes' => [],
                    'haberes' => [],
                    'cotizaciones' => [],
                    'observaciones' => [],
                ];

                foreach ($pack['lineas'] as $linea) {
                    $payload['cuentacontable_ids'][] = $linea['cuentacontable_id'];
                    $payload['moneda_ids'][] = $monedaId;
                    $payload['centrocosto_ids'][] = 0;
                    $payload['debes'][] = $linea['debe'] > 0 ? $linea['debe'] : '';
                    $payload['haberes'][] = $linea['haber'] > 0 ? $linea['haber'] : '';
                    $payload['cotizaciones'][] = $cotizacion;
                    $payload['observaciones'][] = $linea['observacion'];
                }

                $asiento = $this->asientoRepository->create($payload);
                if ($asiento === 'Error' || ! $asiento) {
                    throw new RuntimeException('Error al grabar el asiento contable de la remesa ('.$abrev.').');
                }

                $asientoId = (int) $asiento->id;
                $this->asientoMovimientoRepository->create($payload, $asientoId);
                $this->sincronizarCtamov($asientoId);
                $creados[] = $asientoId;

                if ($idPreferido === 0 || $monedaId === self::MONEDA_DEFAULT) {
                    $idPreferido = $asientoId;
                }
            }
        } catch (Throwable $e) {
            foreach ($creados as $id) {
                try {
                    $this->anular($id);
                } catch (Throwable) {
                    // best effort
                }
            }

            if ($e instanceof InvalidArgumentException) {
                throw $e;
            }

            throw new RuntimeException(
                'Error al grabar asientos de remesa: '.$e->getMessage(),
                0,
                $e
            );
        }

        return $idPreferido;
    }

    /**
     * Contraasientos de todos los asientos originales de la remesa (uno por moneda).
     *
     * @return list<int>
     */
    public function generarReversos(Remesa $remesa, string $fecha, int $cajaMovimientoReversoId): array
    {
        if (! $remesa->esExterna()) {
            throw new InvalidArgumentException('Solo las remesas externas tienen asiento para revertir.');
        }

        if ($cajaMovimientoReversoId <= 0) {
            throw new InvalidArgumentException('El contraasiento requiere el movimiento de caja de reversión.');
        }

        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
            throw new InvalidArgumentException('Fecha de reversión inválida.');
        }

        $ids = $this->idsAsientosOriginalesDeRemesa($remesa);
        if ($ids === []) {
            throw new InvalidArgumentException('La remesa no tiene asiento contable para revertir.');
        }

        $creados = [];
        try {
            foreach ($ids as $asientoId) {
                $asientoOriginal = Asiento::query()
                    ->with('asiento_movimientos')
                    ->find($asientoId);
                if ($asientoOriginal === null || $asientoOriginal->asiento_movimientos->isEmpty()) {
                    throw new InvalidArgumentException('Asiento #'.$asientoId.' inválido para revertir.');
                }
                $creados[] = $this->generarReversoDeAsiento(
                    $asientoOriginal,
                    $remesa,
                    $fecha,
                    $cajaMovimientoReversoId
                );
            }
        } catch (Throwable $e) {
            foreach ($creados as $id) {
                try {
                    $this->anular($id);
                } catch (Throwable) {
                }
            }
            throw $e;
        }

        return $creados;
    }

    public function generarReversoDeAsiento(
        Asiento $asientoOriginal,
        Remesa $remesa,
        string $fecha,
        int $cajaMovimientoReversoId
    ): int {
        $numeroOrig = (string) ($asientoOriginal->numeroasiento ?? $asientoOriginal->id);
        $payload = [
            'empresa_id' => (int) $asientoOriginal->empresa_id,
            'tipoasiento_id' => (int) $asientoOriginal->tipoasiento_id,
            'fecha' => $fecha,
            'observacion' => trim('Revierte asiento '.$numeroOrig.' '.$this->detalleAsiento($remesa)),
            'usuario_id' => (int) (auth()->id() ?? $remesa->usuario_id),
            'caja_movimiento_id' => $cajaMovimientoReversoId,
            'remesa_id' => (int) $remesa->id,
            'omitir_anita' => true,
            'alcance_cierre_contable' => PeriodoContableCierreSupport::ALCANCE_CAJA,
            'cuentacontable_ids' => [],
            'moneda_ids' => [],
            'centrocosto_ids' => [],
            'debes' => [],
            'haberes' => [],
            'cotizaciones' => [],
            'observaciones' => [],
        ];

        foreach ($asientoOriginal->asiento_movimientos as $movimiento) {
            $monto = (float) ($movimiento->monto ?? 0);
            $payload['cuentacontable_ids'][] = (int) $movimiento->cuentacontable_id;
            $payload['moneda_ids'][] = (int) ($movimiento->moneda_id ?: self::MONEDA_DEFAULT);
            $payload['centrocosto_ids'][] = (int) ($movimiento->centrocosto_id ?: 0);
            $payload['cotizaciones'][] = (float) ($movimiento->cotizacion ?: 1);
            $payload['observaciones'][] = (string) ($movimiento->observacion ?? '');

            if ($monto >= 0) {
                $payload['debes'][] = '';
                $payload['haberes'][] = $monto;
            } else {
                $payload['debes'][] = abs($monto);
                $payload['haberes'][] = '';
            }
        }

        $asiento = $this->asientoRepository->create($payload);
        if ($asiento === 'Error' || ! $asiento) {
            throw new RuntimeException('Error al grabar el asiento contable de reversión de la remesa.');
        }

        $asientoId = (int) $asiento->id;
        $this->asientoMovimientoRepository->create($payload, $asientoId);

        try {
            $this->sincronizarCtamov($asientoId);
        } catch (Throwable $e) {
            $this->anular($asientoId);
            throw new RuntimeException(
                'Asiento de reversión ERP grabado pero falló ctamov Anita: '.$e->getMessage(),
                0,
                $e
            );
        }

        return $asientoId;
    }

    public function anular(?int $asientoId): void
    {
        if ($asientoId === null || $asientoId <= 0) {
            return;
        }

        Asiento_Archivo::query()->where('asiento_id', $asientoId)->delete();
        $this->asientoRepository->delete($asientoId);
    }

    /**
     * @return list<int>
     */
    public function idsAsientosDeRemesa(Remesa $remesa): array
    {
        return Asiento::query()
            ->where(function ($q) use ($remesa) {
                $q->where('remesa_id', (int) $remesa->id);
                if ($remesa->asiento_id) {
                    $q->orWhere('id', (int) $remesa->asiento_id);
                }
            })
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Asientos originales (mismo caja_movimiento que la remesa), no los de reversión.
     *
     * @return list<int>
     */
    public function idsAsientosOriginalesDeRemesa(Remesa $remesa): array
    {
        $cajaId = (int) ($remesa->caja_movimiento_id ?? 0);
        $q = Asiento::query()->where('remesa_id', (int) $remesa->id);
        if ($cajaId > 0) {
            $q->where('caja_movimiento_id', $cajaId);
        } elseif ($remesa->asiento_id) {
            $q->where('id', (int) $remesa->asiento_id);
        }

        return $q->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    public function anularTodosDeRemesa(Remesa $remesa): void
    {
        foreach ($this->idsAsientosDeRemesa($remesa) as $id) {
            $this->anular($id);
        }
    }

    private function sincronizarCtamov(int $asientoId): void
    {
        $asiento = Asiento::query()->with('asiento_movimientos.monedas')->find($asientoId);
        if ($asiento === null) {
            throw new RuntimeException('No se encontró el asiento '.$asientoId.' para sincronizar ctamov.');
        }

        $dataAnita = $this->asientoRepository->armarPayloadAnitaDesdeModelo($asiento);
        $this->asientoRepository->sincronizarCtamovAnita($dataAnita);
    }

    private function detalleAsiento(Remesa $remesa, string $monedaAbrev = ''): string
    {
        $tipoLabel = $remesa->esInterna() ? 'Interna' : 'Externa';
        $numero = (int) ($remesa->numero ?? 0);
        $sufijo = $monedaAbrev !== '' ? ' '.$monedaAbrev : '';

        return 'Remesa '.$numero.' '.$tipoLabel.$sufijo;
    }

    /**
     * @return array<int, array{lineas: list<array{cuentacontable_id: int, debe: float, haber: float, observacion: string}>, total_debe: float, total_haber: float, moneda_abrev: string}>
     */
    private function armarLineasAsientoPorMoneda(Remesa $remesa): array
    {
        /** @var array<int, array{cuentacontable_id: int, debe: float, haber: float, observacion: string, moneda_abrev: string}> $agrupadas */
        $agrupadas = [];
        $empresaId = (int) $remesa->empresa_id;

        foreach ($remesa->lineas as $linea) {
            $monto = round((float) $linea->monto, 2);
            if (abs($monto) < 0.00001) {
                continue;
            }

            $cuentacaja = $linea->cuentacaja;
            if (! $cuentacaja instanceof Cuentacaja) {
                $cuentacaja = Cuentacaja::query()->with('monedas')->find((int) $linea->cuentacaja_id);
            }
            if ($cuentacaja === null) {
                continue;
            }

            $monedaId = (int) ($cuentacaja->moneda_id ?: self::MONEDA_DEFAULT);
            $monedaAbrev = (string) ($cuentacaja->monedas->abreviatura ?? $cuentacaja->monedas->nombre ?? $monedaId);

            $cuentacontableId = $this->resolverCuentacontableId($linea, $empresaId, $cuentacaja);
            if ($cuentacontableId <= 0) {
                $codigo = (string) ($cuentacaja->codigo ?? $linea->cuentacaja_id);
                throw new InvalidArgumentException('No se pudo resolver cuenta contable para la cuenta de caja '.$codigo.'.');
            }

            $debe = $linea->lado === RemesaSupport::LADO_DESTINO ? $monto : 0.0;
            $haber = $linea->lado === RemesaSupport::LADO_ORIGEN ? $monto : 0.0;
            $key = $monedaId.'|'.$cuentacontableId;

            if (! isset($agrupadas[$key])) {
                $agrupadas[$key] = [
                    'moneda_id' => $monedaId,
                    'moneda_abrev' => $monedaAbrev,
                    'cuentacontable_id' => $cuentacontableId,
                    'debe' => 0.0,
                    'haber' => 0.0,
                    'observacion' => $this->detalleAsiento($remesa, $monedaAbrev),
                ];
            }

            $agrupadas[$key]['debe'] += $debe;
            $agrupadas[$key]['haber'] += $haber;
        }

        $out = [];
        foreach ($agrupadas as $row) {
            $monedaId = (int) $row['moneda_id'];
            if (! isset($out[$monedaId])) {
                $out[$monedaId] = [
                    'lineas' => [],
                    'total_debe' => 0.0,
                    'total_haber' => 0.0,
                    'moneda_abrev' => (string) $row['moneda_abrev'],
                ];
            }
            $out[$monedaId]['lineas'][] = [
                'cuentacontable_id' => (int) $row['cuentacontable_id'],
                'debe' => round((float) $row['debe'], 2),
                'haber' => round((float) $row['haber'], 2),
                'observacion' => (string) $row['observacion'],
            ];
            $out[$monedaId]['total_debe'] = round($out[$monedaId]['total_debe'] + (float) $row['debe'], 2);
            $out[$monedaId]['total_haber'] = round($out[$monedaId]['total_haber'] + (float) $row['haber'], 2);
        }

        ksort($out);

        return $out;
    }

    private function resolverCuentacontableId(RemesaLinea $linea, int $empresaId, ?Cuentacaja $cuentacaja = null): int
    {
        $cuentacaja ??= $linea->cuentacaja;
        if (! $cuentacaja instanceof Cuentacaja) {
            $cuentacaja = Cuentacaja::query()->find((int) $linea->cuentacaja_id);
        }
        if ($cuentacaja === null) {
            return 0;
        }

        return (int) (CuentacajaCuentacontableResolverSupport::resolverIdParaEmpresa($cuentacaja, $empresaId) ?? 0);
    }

    private function assertCuadre(float $sumaDestino, float $sumaOrigen, string $contexto = ''): void
    {
        if (abs($sumaDestino - $sumaOrigen) > self::TOLERANCIA_CUADRE) {
            $ctx = $contexto !== '' ? ' ('.$contexto.')' : '';
            throw new InvalidArgumentException(
                'Los totales destino ('.number_format($sumaDestino, 2, ',', '.')
                .') y origen ('.number_format($sumaOrigen, 2, ',', '.').') no cuadran'.$ctx.'.'
            );
        }
    }
}
