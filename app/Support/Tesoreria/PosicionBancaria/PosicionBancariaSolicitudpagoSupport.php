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
 * Solicitudes de pago en el banco de la cuenta de caja.
 * Con fecha de pago marcada: ese día, esté autorizada o ya pagada.
 * Pagada sin fecha marcada: el día del ingreso/egreso.
 * Sin pagar y sin fecha marcada: el día de la solicitud.
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
        if (! Schema::hasTable('solicitudpago') || ! Schema::hasColumn('solicitudpago', 'fecha_pago')) {
            return [];
        }

        $desdeDia = $desde->toDateString();
        $hastaDia = $hasta->toDateString();
        $cuentasPorContable = Cuentacaja::query()
            ->with('bancos')
            ->whereNotNull('cuentacontable_id')
            ->where('cuentacontable_id', '>', 0)
            ->orderBy('id')
            ->get()
            ->groupBy(fn (Cuentacaja $cuenta) => (int) $cuenta->cuentacontable_id);

        $solicitudes = Solicitudpago::query()
            ->with([
                'conceptos:id,nombre',
                'cuentas',
                'cajaMovimientosPago.caja_movimiento_cuentacajas.cuentacajas.bancos',
            ])
            ->whereDoesntHave('hijas')
            ->whereNotIn('estado', [SolicitudpagoEstados::SUSPENDIDA, SolicitudpagoEstados::RECHAZADA])
            ->where(function ($q) use ($desdeDia, $hastaDia) {
                $q->where(function ($w) use ($desdeDia, $hastaDia) {
                    $w->whereNotNull('fecha_pago')
                        ->whereDate('fecha_pago', '>=', $desdeDia)
                        ->whereDate('fecha_pago', '<=', $hastaDia);
                })->orWhere(function ($w) use ($desdeDia, $hastaDia) {
                    $w->whereNull('fecha_pago')
                        ->whereDate('fecha', '>=', $desdeDia)
                        ->whereDate('fecha', '<=', $hastaDia);
                })->orWhereHas('cajaMovimientosPago', function ($m) use ($desdeDia, $hastaDia) {
                    $m->whereDate('fecha', '>=', $desdeDia)
                        ->whereDate('fecha', '<=', $hastaDia);
                });
            })
            ->orderBy('id')
            ->get();

        $out = [];
        foreach ($solicitudes as $solicitud) {
            $pago = $this->pagoVigente($solicitud);
            $fechaMarcada = $this->dia($solicitud->fecha_pago);
            $fechaMovimiento = $pago !== null ? $this->dia($pago->fecha) : '';
            $fechaSolicitud = $this->dia($solicitud->fecha);
            if ($fechaMarcada !== '' && $fechaMarcada >= $desdeDia && $fechaMarcada <= $hastaDia) {
                $fecha = $fechaMarcada;
            } elseif ($fechaMovimiento !== '' && $fechaMovimiento >= $desdeDia && $fechaMovimiento <= $hastaDia) {
                $fecha = $fechaMovimiento;
            } elseif ($fechaMarcada === '' && $fechaSolicitud >= $desdeDia && $fechaSolicitud <= $hastaDia) {
                $fecha = $fechaSolicitud;
            } else {
                continue;
            }
            $piernas = $pago !== null
                ? $this->piernasDesdeIngresoEgreso($pago)
                : $this->piernasDesdeAsiento($solicitud, $cuentasPorContable);
            if ($piernas === [] && $pago !== null) {
                $piernas = $this->piernasDesdeAsiento($solicitud, $cuentasPorContable);
            }
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

    private function dia(mixed $valor): string
    {
        if ($valor instanceof \DateTimeInterface) {
            return $valor->format('Y-m-d');
        }
        $texto = substr(trim((string) $valor), 0, 10);

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $texto) ? $texto : '';
    }

    /**
     * @param  \Illuminate\Support\Collection<int, \Illuminate\Support\Collection<int, Cuentacaja>>  $cuentasPorContable
     */
    private function cuentaDeAsiento($cuentasPorContable, int $contableId, int $empresaId): ?Cuentacaja
    {
        $candidatas = $cuentasPorContable->get($contableId);
        if ($candidatas === null) {
            return null;
        }
        $mismaEmpresa = null;
        $sinEmpresa = null;
        foreach ($candidatas as $cuenta) {
            if (FinanzaPosicionHojaSupport::hojaDesdeCuenta($cuenta) === null) {
                continue;
            }
            if ((int) $cuenta->empresa_id === $empresaId) {
                $mismaEmpresa ??= $cuenta;
            } elseif ($cuenta->empresa_id === null) {
                $sinEmpresa ??= $cuenta;
            }
        }

        return $mismaEmpresa ?? $sinEmpresa;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, \Illuminate\Support\Collection<int, Cuentacaja>>  $cuentasPorContable
     * @return list<array{hoja: string, empresa_id: int, importe: float}>
     */
    private function piernasDesdeAsiento(Solicitudpago $solicitud, $cuentasPorContable): array
    {
        $out = [];
        $fecha = $this->dia($solicitud->fecha_pago) ?: $this->dia($solicitud->fecha) ?: date('Y-m-d');
        foreach ($solicitud->cuentas as $linea) {
            $empresaLinea = (int) ($linea->empresa_id ?: $solicitud->empresa_id);
            $cuenta = $this->cuentaDeAsiento($cuentasPorContable, (int) $linea->cuentacontable_id, $empresaLinea);
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
