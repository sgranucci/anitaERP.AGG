<?php

declare(strict_types=1);

namespace App\Support\Caja;

use App\Models\Caja\Caja_Movimiento;
use App\Support\Configuracion\CotizacionVigenteSupport;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;

/**
 * Monto y leyendas del index/export IE: cuentas de caja + cheques.
 * Sin cheques el listado quedaba en 0 cuando el pago era solo CHP.
 * Canje banco→banco: no hay cuentacaja; el único cheque del movimiento es el
 * de reemplazo (tiene cheque_reemplaza_id) y hay que mostrar una pata.
 * OPV no mueve caja: el monto es un lado del asiento, pasado a pesos.
 */
final class IngresoEgresoListadoMontoSupport
{
    /**
     * Precarga asientos solo de OPV (el resumen los necesita; sin índice en
     * asiento.caja_movimiento_id un loadMissing por fila era ~300–800 ms c/u).
     *
     * @param  iterable<int, mixed>  $filas
     */
    public static function precargarAsientosOpv(iterable $filas): void
    {
        $opvs = [];
        foreach ($filas as $fila) {
            if (! $fila instanceof Caja_Movimiento && ! $fila instanceof Model) {
                continue;
            }
            if (method_exists($fila, 'relationLoaded') && $fila->relationLoaded('asientos')) {
                continue;
            }
            if (! self::esOpv($fila)) {
                continue;
            }
            $opvs[] = $fila;
        }
        if ($opvs === []) {
            return;
        }

        (new EloquentCollection($opvs))->load([
            'asientos.asiento_movimientos.cuentacontables',
        ]);
    }

    /**
     * @return array{ingreso: float, egreso: float, monto: float, lineas: list<string>}
     */
    public static function resumen(object $movimiento): array
    {
        $ingreso = 0.0;
        $egreso = 0.0;
        $lineas = [];
        $chequesReemplazo = [];
        $fecha = self::fechaMovimiento($movimiento);
        $cotizacionesMovimiento = CotizacionVigenteSupport::cotizacionesDeclaradas(
            $movimiento->caja_movimiento_cuentacajas ?? [],
            $movimiento->cheques ?? [],
        );

        foreach ($movimiento->caja_movimiento_cuentacajas ?? [] as $linea) {
            $coef = self::coeficiente($linea->moneda_id ?? 1, $linea->cotizacion ?? 1, $fecha, $cotizacionesMovimiento);
            $monto = (float) ($linea->monto ?? 0);
            if ($monto > 0) {
                $ingreso += $monto * $coef;
            } elseif ($monto < 0) {
                $egreso += abs($monto) * $coef;
            }
            $nombre = trim((string) ($linea->cuentacajas->nombre ?? ''));
            $lineas[] = trim($nombre.' '.self::fmt($monto));
        }

        foreach ($movimiento->cheques ?? [] as $cheque) {
            if (! empty($cheque->cheque_reemplaza_id)) {
                $chequesReemplazo[] = $cheque;
                continue;
            }
            $origen = strtoupper(trim((string) ($cheque->origen ?? '')));
            $coef = self::coeficiente($cheque->moneda_id ?? 1, $cheque->cotizacion ?? 1, $fecha, $cotizacionesMovimiento);
            $montoAbs = abs((float) ($cheque->monto ?? 0));
            if ($montoAbs < 0.000001) {
                continue;
            }
            $nro = trim((string) ($cheque->numerocheque ?? ''));
            $cuenta = trim((string) ($cheque->cuentacajas->nombre ?? ''));
            if ($origen === 'E') {
                $egreso += $montoAbs * $coef;
                $lineas[] = trim('CHP '.$nro.' '.$cuenta.' '.self::fmt(-$montoAbs));
            } elseif ($origen === 'R') {
                $ingreso += $montoAbs * $coef;
                $lineas[] = trim('CHT '.$nro.' '.$cuenta.' '.self::fmt($montoAbs));
            }
        }

        $cajaYaUsadaPorTransferencia = [];
        foreach ($movimiento->chequeReemplazoTransferencias ?? [] as $transferencia) {
            $anuladoPrevio = $transferencia->chequeAnulado ?? $transferencia->cheque_anulado ?? null;
            $origenPrevio = strtoupper(trim((string) (is_object($anuladoPrevio) ? ($anuladoPrevio->origen ?? 'E') : 'E')));
            $firmado = IngresoEgresoCanjeChequeSupport::montoFirmadoLineaCajaTransferencia(
                $origenPrevio,
                (float) ($transferencia->monto ?? 0)
            );
            $cubiertaPorCaja = false;
            foreach ($movimiento->caja_movimiento_cuentacajas ?? [] as $idxCaja => $lineaCaja) {
                if (isset($cajaYaUsadaPorTransferencia[$idxCaja])) {
                    continue;
                }
                if ((int) ($lineaCaja->cuentacaja_id ?? 0) !== (int) ($transferencia->cuentacaja_id ?? 0)) {
                    continue;
                }
                if (abs(round((float) ($lineaCaja->monto ?? 0), 2) - $firmado) >= 0.02) {
                    continue;
                }
                $cajaYaUsadaPorTransferencia[$idxCaja] = true;
                $cubiertaPorCaja = true;
                break;
            }
            if ($cubiertaPorCaja) {
                continue;
            }
            $coef = self::coeficiente($transferencia->moneda_id ?? 1, $transferencia->cotizacion ?? 1, $fecha, $cotizacionesMovimiento);
            $montoAbs = abs((float) ($transferencia->monto ?? 0));
            if ($montoAbs < 0.000001) {
                continue;
            }
            $anulado = $transferencia->chequeAnulado ?? $transferencia->cheque_anulado ?? null;
            $origenAnulado = strtoupper(trim((string) (is_object($anulado) ? ($anulado->origen ?? 'E') : 'E')));
            if ($origenAnulado === 'R') {
                $ingreso += $montoAbs * $coef;
            } else {
                $egreso += $montoAbs * $coef;
            }
            $nroAnulado = trim((string) (is_object($anulado) ? ($anulado->numerocheque ?? '') : ''));
            $cuentaCaja = $transferencia->cuentacajas ?? null;
            $cuenta = trim((string) (is_object($cuentaCaja) ? ($cuentaCaja->nombre ?? '') : ($transferencia->cuentacaja_nombre ?? '')));
            $etiqueta = 'Canje';
            if ($nroAnulado !== '') {
                $etiqueta .= ' CHP '.$nroAnulado.' →';
            }
            $etiqueta .= ' transferencia';
            $lineas[] = trim($etiqueta.' '.$cuenta.' '.self::fmt($origenAnulado === 'R' ? $montoAbs : -$montoAbs));
        }

        // Canje: sin caja ni cheques “normales”; una pata = monto del cheque nuevo.
        if (abs($ingreso) < 0.000001 && abs($egreso) < 0.000001 && $chequesReemplazo !== []) {
            foreach ($chequesReemplazo as $cheque) {
                $coef = self::coeficiente($cheque->moneda_id ?? 1, $cheque->cotizacion ?? 1, $fecha, $cotizacionesMovimiento);
                $montoAbs = abs((float) ($cheque->monto ?? 0));
                if ($montoAbs < 0.000001) {
                    continue;
                }
                $egreso += $montoAbs * $coef;
                $nroNuevo = trim((string) ($cheque->numerocheque ?? ''));
                $nroAnulado = trim((string) (
                    $cheque->chequeReemplazado->numerocheque
                    ?? $cheque->cheque_reemplazado->numerocheque
                    ?? ''
                ));
                $cuenta = trim((string) ($cheque->cuentacajas->nombre ?? ''));
                $etiqueta = 'Canje';
                if ($nroAnulado !== '') {
                    $etiqueta .= ' CHP '.$nroAnulado.' →';
                }
                if ($nroNuevo !== '') {
                    $etiqueta .= ' CHP '.$nroNuevo;
                }
                $lineas[] = trim($etiqueta.' '.$cuenta.' '.self::fmt($montoAbs));
            }
        }

        $ingreso = round($ingreso, 2);
        $egreso = round($egreso, 2);
        $monto = $ingreso != 0.0 ? $ingreso : $egreso;

        // OPV no tiene caja ni cheques. El importe vive en el asiento.
        // No suma ingresos/egresos de caja: no hubo movimiento de tesorería.
        if (abs($ingreso) < 0.000001 && abs($egreso) < 0.000001 && self::esOpv($movimiento)) {
            $asiento = self::asientoOpvEnPesos($movimiento);
            if ($asiento['monto'] > 0) {
                $monto = $asiento['monto'];
                $lineas = array_merge($lineas, $asiento['lineas']);
            }
        }

        return [
            'ingreso' => $ingreso,
            'egreso' => $egreso,
            'monto' => $monto,
            'lineas' => array_values(array_filter($lineas, static fn ($t) => $t !== '')),
        ];
    }

    private static function esOpv(object $movimiento): bool
    {
        // Solo el atributo ya traído por el listado. No cargar el tipo: si no, cada fila dispara una query.
        if (method_exists($movimiento, 'getAttributes')) {
            $abrev = (string) ($movimiento->getAttributes()['abreviaturatipotransaccion_caja'] ?? '');
        } else {
            $abrev = (string) ($movimiento->abreviaturatipotransaccion_caja ?? '');
        }

        return strtoupper(trim($abrev)) === IngresoEgresoOperacionVariaSupport::ABREV_OPV;
    }

    /**
     * Un lado del asiento, en pesos. La cotización grabada en la línea manda.
     *
     * @return array{monto: float, lineas: list<string>}
     */
    private static function asientoOpvEnPesos(object $movimiento): array
    {
        $asiento = self::asientoDelMovimiento($movimiento);
        if ($asiento === null) {
            return ['monto' => 0.0, 'lineas' => []];
        }
        $movimientos = $asiento->asiento_movimientos ?? [];
        if ($movimientos instanceof \Traversable) {
            $movimientos = iterator_to_array($movimientos);
        }
        if (! is_array($movimientos) || $movimientos === []) {
            return ['monto' => 0.0, 'lineas' => []];
        }

        $fecha = self::fechaMovimiento($movimiento);
        $cotizaciones = CotizacionVigenteSupport::cotizacionesDeclaradas($movimientos);
        $debe = 0.0;
        $haber = 0.0;
        $lineas = [];

        foreach ($movimientos as $linea) {
            if (! is_object($linea)) {
                continue;
            }
            $nominal = (float) ($linea->monto ?? 0);
            if (abs($nominal) < 0.000001) {
                continue;
            }
            $coef = self::coeficiente($linea->moneda_id ?? 1, $linea->cotizacion ?? 1, $fecha, $cotizaciones);
            $pesos = $nominal * $coef;
            if ($pesos > 0) {
                $debe += $pesos;
            } else {
                $haber += abs($pesos);
            }
            $cuenta = $linea->cuentacontables ?? null;
            $codigo = trim((string) (is_object($cuenta) ? ($cuenta->codigo ?? '') : ''));
            $nombre = trim((string) (is_object($cuenta) ? ($cuenta->nombre ?? '') : ''));
            $lineas[] = trim(trim($codigo.' '.$nombre).' '.self::fmt($nominal));
        }

        $debe = round($debe, 2);
        $haber = round($haber, 2);

        return [
            'monto' => $debe >= $haber ? $debe : $haber,
            'lineas' => $lineas,
        ];
    }

    private static function asientoDelMovimiento(object $movimiento): ?object
    {
        if (method_exists($movimiento, 'loadMissing')) {
            $movimiento->loadMissing('asientos');
        }
        $asiento = $movimiento->asientos ?? null;

        return is_object($asiento) ? $asiento : null;
    }

    /**
     * @param  array<int, float>  $cotizacionesMovimiento
     */
    private static function coeficiente(
        mixed $monedaId,
        mixed $cotizacion,
        ?string $fecha,
        array $cotizacionesMovimiento,
    ): float {
        $monedaId = (int) $monedaId;

        return CotizacionVigenteSupport::ventaEfectiva(
            $cotizacion,
            $monedaId > 0 ? $monedaId : 1,
            $fecha,
            $cotizacionesMovimiento[$monedaId] ?? null,
        );
    }

    private static function fechaMovimiento(object $movimiento): ?string
    {
        $fecha = $movimiento->fecha ?? null;
        if ($fecha instanceof \DateTimeInterface) {
            return $fecha->format('Y-m-d');
        }

        $fecha = trim((string) $fecha);

        return $fecha !== '' ? $fecha : null;
    }

    private static function fmt(float $monto): string
    {
        return number_format($monto, 2, ',', '.');
    }
}
