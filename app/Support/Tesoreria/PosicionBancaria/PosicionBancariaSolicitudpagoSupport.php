<?php

declare(strict_types=1);

namespace App\Support\Tesoreria\PosicionBancaria;

use App\Models\Caja\Caja_Movimiento;
use App\Models\Caja\Cuentacaja;
use App\Models\Solicitudpago\Solicitudpago;
use App\Support\Configuracion\CotizacionVigenteSupport;
use App\Support\Finanzas\FinanzaPosicionHojaSupport;
use App\Support\Solicitudpago\SolicitudpagoEstados;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;

/**
 * Solicitudes de pago por fecha de vencimiento, no suspendidas,
 * ubicadas en el banco de la cuenta de caja.
 * Sin pagar: la cuenta contable del asiento de la SP.
 * Pagada: la cuenta de caja del ingreso/egreso.
 */
final class PosicionBancariaSolicitudpagoSupport
{
    /**
     * @return array<string, array<string, list<array{
     *   detalle: string, B: ?float, C: ?float, D: ?float, estado: string, tono: string, rubro: string
     * }>>>
     */
    public function lineasPorFechaYHoja(Carbon $desde, Carbon $hasta): array
    {
        if (! Schema::hasTable('solicitudpago')) {
            return [];
        }

        $cuentasPorContable = Cuentacaja::query()
            ->with('bancos')
            ->whereNotNull('cuentacontable_id')
            ->where('cuentacontable_id', '>', 0)
            ->get()
            ->keyBy(fn (Cuentacaja $cuenta) => (int) $cuenta->cuentacontable_id);

        $solicitudes = Solicitudpago::query()
            ->with([
                'conceptos:id,nombre',
                'cuentas',
                'cajaMovimientosPago.caja_movimiento_cuentacajas.cuentacajas.bancos',
            ])
            ->whereDoesntHave('hijas')
            ->whereNotIn('estado', [SolicitudpagoEstados::SUSPENDIDA, SolicitudpagoEstados::RECHAZADA])
            ->whereDate('fecha_vencimiento', '>=', $desde->toDateString())
            ->whereDate('fecha_vencimiento', '<=', $hasta->toDateString())
            ->orderBy('fecha_vencimiento')
            ->orderBy('id')
            ->get();

        $out = [];
        foreach ($solicitudes as $solicitud) {
            $fecha = $solicitud->fecha_vencimiento?->toDateString() ?? '';
            if ($fecha === '') {
                continue;
            }
            $pago = $this->pagoVigente($solicitud);
            $piernas = $pago !== null
                ? $this->piernasDesdeIngresoEgreso($pago)
                : $this->piernasDesdeAsiento($solicitud, $cuentasPorContable);
            if ($piernas === []) {
                continue;
            }
            $nombre = $this->nombre($solicitud);
            $estado = $this->etiquetaEstado((string) $solicitud->estado);
            foreach ($piernas as $pierna) {
                $col = FinanzaPosicionHojaSupport::COLUMNA_POR_EMPRESA[(int) $pierna['empresa_id']] ?? null;
                if ($col === null) {
                    continue;
                }
                $fila = [
                    'detalle' => $nombre,
                    'B' => null,
                    'C' => null,
                    'D' => null,
                    'estado' => $estado,
                    'tono' => 'sp',
                    'rubro' => '',
                ];
                $fila[$col] = (float) $pierna['importe'];
                $out[$fecha][$pierna['hoja']][] = $fila;
            }
        }

        return $out;
    }

    private function pagoVigente(Solicitudpago $solicitud): ?Caja_Movimiento
    {
        foreach ($solicitud->cajaMovimientosPago as $movimiento) {
            if ((int) ($movimiento->caja_movimiento_revertido_por_id ?? 0) > 0) {
                continue;
            }

            return $movimiento;
        }

        return null;
    }

    /**
     * @return list<array{hoja: string, empresa_id: int, importe: float}>
     */
    private function piernasDesdeIngresoEgreso(Caja_Movimiento $movimiento): array
    {
        $out = [];
        foreach ($movimiento->caja_movimiento_cuentacajas as $linea) {
            $cuenta = $linea->cuentacajas;
            $hoja = FinanzaPosicionHojaSupport::hojaDesdeCuenta($cuenta);
            $empresaId = FinanzaPosicionHojaSupport::empresaColumna($cuenta, (int) $movimiento->empresa_id);
            if ($hoja === null || $empresaId === null) {
                continue;
            }
            $importe = (float) $linea->monto;
            if ((int) ($linea->moneda_id ?? 1) > 1) {
                $cot = CotizacionVigenteSupport::ventaValor(
                    $movimiento->fecha ? (string) $movimiento->fecha : date('Y-m-d'),
                    (int) $linea->moneda_id
                );
                $importe = round($importe * ($cot > 0 ? $cot : 0), 2);
            }
            if (abs($importe) < 0.00001) {
                continue;
            }
            $out[] = [
                'hoja' => $hoja,
                'empresa_id' => $empresaId,
                'importe' => round($importe, 2),
            ];
        }

        return $out;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Cuentacaja>  $cuentasPorContable
     * @return list<array{hoja: string, empresa_id: int, importe: float}>
     */
    private function piernasDesdeAsiento(Solicitudpago $solicitud, $cuentasPorContable): array
    {
        $out = [];
        $fecha = $solicitud->fecha_vencimiento?->format('Y-m-d') ?? date('Y-m-d');
        foreach ($solicitud->cuentas as $linea) {
            $cuenta = $cuentasPorContable->get((int) $linea->cuentacontable_id);
            $hoja = FinanzaPosicionHojaSupport::hojaDesdeCuenta($cuenta);
            $empresaId = FinanzaPosicionHojaSupport::empresaColumna($cuenta, (int) ($linea->empresa_id ?: $solicitud->empresa_id));
            if ($hoja === null || $empresaId === null) {
                continue;
            }
            $importe = abs((float) $linea->monto);
            if ($importe <= 0) {
                $importe = abs((float) $solicitud->monto);
            }
            if ((int) $solicitud->moneda_id > 1) {
                $cot = CotizacionVigenteSupport::ventaValor($fecha, (int) $solicitud->moneda_id);
                $importe = round($importe * ($cot > 0 ? $cot : 0), 2);
            }
            $dh = strtoupper(trim((string) $linea->debe_haber));
            $firmado = in_array($dh, ['D', 'DEBE'], true) ? $importe : -1 * $importe;
            if (abs($firmado) < 0.00001) {
                continue;
            }
            $out[] = [
                'hoja' => $hoja,
                'empresa_id' => $empresaId,
                'importe' => round($firmado, 2),
            ];
        }

        return $out;
    }

    private function nombre(Solicitudpago $solicitud): string
    {
        $detalle = trim((string) $solicitud->detalle);
        if ($detalle !== '') {
            return $detalle;
        }
        $beneficiario = trim((string) $solicitud->beneficiario);
        if ($beneficiario !== '') {
            return $beneficiario;
        }
        $concepto = trim((string) ($solicitud->conceptos->nombre ?? ''));
        if ($concepto !== '') {
            return $concepto;
        }

        return 'SP '.(string) $solicitud->codigo;
    }

    private function etiquetaEstado(string $estado): string
    {
        foreach (SolicitudpagoEstados::opciones() as $opcion) {
            if ($opcion['valor'] === strtoupper(trim($estado))) {
                return $opcion['nombre'];
            }
        }

        return $estado !== '' ? $estado : 'SP';
    }
}
