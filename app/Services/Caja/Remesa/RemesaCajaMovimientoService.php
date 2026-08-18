<?php

declare(strict_types=1);

namespace App\Services\Caja\Remesa;

use App\Models\Caja\Caja_Movimiento;
use App\Models\Caja\Caja_Movimiento_Cuentacaja;
use App\Models\Caja\Caja_Movimiento_Estado;
use App\Models\Caja\Cheque;
use App\Models\Caja\Remesa;
use App\Models\Caja\RemesaLinea;
use App\Models\Caja\Tipotransaccion_Caja;
use App\Models\Contable\Asiento;
use App\Repositories\Caja\Caja_MovimientoRepositoryInterface;
use App\Support\Caja\CajaMovimientoEloquentDeleteSupport;
use App\Support\Caja\Remesa\RemesaSupport;
use Carbon\Carbon;
use InvalidArgumentException;
use RuntimeException;

/**
 * Genera caja_movimiento + líneas firmadas (destino +, origen −) alineadas al asiento.
 */
final class RemesaCajaMovimientoService
{
    private const MONEDA_DEFAULT = 1;

    public function __construct(
        private readonly Caja_MovimientoRepositoryInterface $cajaMovimientoRepository,
    ) {}

    public function generar(Remesa $remesa): int
    {
        $remesa->loadMissing(['lineas.cuentacaja']);

        $abrev = $remesa->esInterna()
            ? RemesaSupport::ABREV_RMI
            : RemesaSupport::ABREV_REM;

        $tipo = Tipotransaccion_Caja::query()
            ->where('abreviatura', $abrev)
            ->whereNull('deleted_at')
            ->first();

        if ($tipo === null) {
            throw new RuntimeException('No existe el tipo de transacción de caja '.$abrev.'.');
        }

        $lineasConMonto = $remesa->lineas
            ->filter(fn (RemesaLinea $l) => abs((float) $l->monto) >= 0.00001);

        if ($lineasConMonto->isEmpty()) {
            throw new InvalidArgumentException('No hay líneas con monto para el movimiento de caja.');
        }

        $detalle = $this->detalleMovimiento($remesa);
        $fecha = $remesa->fecha?->format('Y-m-d') ?? date('Y-m-d');

        $cajaMovimiento = $this->cajaMovimientoRepository->create([
            'empresa_id' => (int) $remesa->empresa_id,
            'tipotransaccion_caja_id' => (int) $tipo->id,
            'fecha' => $fecha,
            'detalle' => $detalle,
            'usuario_id' => (int) ($remesa->usuario_id ?? auth()->id()),
        ]);

        if ($cajaMovimiento === 'Error' || ! $cajaMovimiento) {
            throw new RuntimeException('Error al grabar el movimiento de caja de la remesa.');
        }

        $cajaMovimientoId = (int) $cajaMovimiento->id;

        foreach ($lineasConMonto as $linea) {
            $montoAbs = round(abs((float) $linea->monto), 2);
            $montoFirmado = $linea->lado === RemesaSupport::LADO_DESTINO
                ? $montoAbs
                : -$montoAbs;

            $monedaId = (int) ($linea->cuentacaja?->moneda_id ?? self::MONEDA_DEFAULT);
            if ($monedaId <= 0) {
                $monedaId = self::MONEDA_DEFAULT;
            }

            Caja_Movimiento_Cuentacaja::query()->create([
                'caja_movimiento_id' => $cajaMovimientoId,
                'cuentacaja_id' => (int) $linea->cuentacaja_id,
                'fecha' => $fecha,
                'monto' => $montoFirmado,
                'moneda_id' => $monedaId,
                'cotizacion' => 1,
                'observacion' => $detalle,
            ]);
        }

        Caja_Movimiento_Estado::query()->create([
            'caja_movimiento_id' => $cajaMovimientoId,
            'fecha' => Carbon::now(),
            'estado' => Caja_Movimiento_Estado::$enumEstado[0]['valor'],
            'observacion' => 'Alta de remesa',
        ]);

        return $cajaMovimientoId;
    }

    /**
     * Contramovimiento de caja con fecha de operación (típicamente hoy).
     * Invierte signos de las líneas del movimiento original; no lo borra.
     */
    public function generarReverso(Remesa $remesa, string $fecha): int
    {
        $cajaOriginalId = (int) ($remesa->caja_movimiento_id ?? 0);
        if ($cajaOriginalId <= 0) {
            throw new InvalidArgumentException('La remesa no tiene movimiento de caja para revertir.');
        }

        $original = Caja_Movimiento::query()->find($cajaOriginalId);
        if ($original === null) {
            throw new InvalidArgumentException('No se encontró el movimiento de caja #'.$cajaOriginalId.'.');
        }

        $lineas = Caja_Movimiento_Cuentacaja::query()
            ->where('caja_movimiento_id', $cajaOriginalId)
            ->orderBy('id')
            ->get();

        if ($lineas->isEmpty()) {
            throw new InvalidArgumentException('El movimiento de caja #'.$cajaOriginalId.' no tiene líneas para revertir.');
        }

        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
            throw new InvalidArgumentException('Fecha de reversión inválida.');
        }

        $detalle = 'Revierte '.$this->detalleMovimiento($remesa);

        $cajaMovimiento = $this->cajaMovimientoRepository->create([
            'empresa_id' => (int) $original->empresa_id,
            'tipotransaccion_caja_id' => (int) $original->tipotransaccion_caja_id,
            'fecha' => $fecha,
            'detalle' => $detalle,
            'usuario_id' => (int) (auth()->id() ?? $remesa->usuario_id),
        ]);

        if ($cajaMovimiento === 'Error' || ! $cajaMovimiento) {
            throw new RuntimeException('Error al grabar el movimiento de caja de reversión.');
        }

        $cajaMovimientoId = (int) $cajaMovimiento->id;

        foreach ($lineas as $linea) {
            $monto = round(-1 * (float) $linea->monto, 2);
            if (abs($monto) < 0.00001) {
                continue;
            }

            Caja_Movimiento_Cuentacaja::query()->create([
                'caja_movimiento_id' => $cajaMovimientoId,
                'cuentacaja_id' => (int) $linea->cuentacaja_id,
                'fecha' => $fecha,
                'monto' => $monto,
                'moneda_id' => (int) ($linea->moneda_id ?: self::MONEDA_DEFAULT),
                'cotizacion' => (float) ($linea->cotizacion ?: 1),
                'observacion' => $detalle,
            ]);
        }

        Caja_Movimiento_Estado::query()->create([
            'caja_movimiento_id' => $cajaMovimientoId,
            'fecha' => Carbon::now(),
            'estado' => Caja_Movimiento_Estado::$enumEstado[0]['valor'],
            'observacion' => 'Reversión de remesa',
        ]);

        return $cajaMovimientoId;
    }

    public function anular(?int $cajaMovimientoId): void
    {
        if ($cajaMovimientoId === null || $cajaMovimientoId <= 0) {
            return;
        }

        // Si quedó algún asiento apuntando al movimiento (no debería tras anular asiento), desenganchar.
        Asiento::query()
            ->where('caja_movimiento_id', $cajaMovimientoId)
            ->update(['caja_movimiento_id' => null]);

        Cheque::query()
            ->where('caja_movimiento_id', $cajaMovimientoId)
            ->update(['caja_movimiento_id' => null]);

        // No usar repository->delete: eliminarAnita legacy recibe firma distinta y puede fallar.
        CajaMovimientoEloquentDeleteSupport::eliminarPorId($cajaMovimientoId);
    }

    private function detalleMovimiento(Remesa $remesa): string
    {
        $tipoLabel = $remesa->esInterna() ? 'Interna' : 'Externa';
        $numero = (int) ($remesa->numero ?? 0);

        return 'Remesa '.$numero.' '.$tipoLabel;
    }
}
