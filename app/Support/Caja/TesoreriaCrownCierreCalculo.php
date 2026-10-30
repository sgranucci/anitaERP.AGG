<?php

namespace App\Support\Caja;

use App\Models\Caja\TesoreriaCrownAsignacion;
use App\Models\Caja\TesoreriaCrownComprobante;
use App\Models\Caja\TesoreriaCrownCotizacion;

/**
 * Cierre de caja con la cuenta de Anita (rescajero.fc, procesa_un_cajero).
 *
 * El efectivo de cada comprobante es pesos + moneda extranjera por la cotización
 * de la jornada + tarjeta de crédito − recargo + débito + QR. Las fichas van aparte.
 * En venta, kiosco y cashless la diferencia suma lo rendido (efectivo, fichas,
 * cashless, vouchers, online y apuestas) y resta la habilitación. Anita todavía
 * no tiene movimientos de token en este módulo, así que ese término queda en cero.
 * En gastronomía y hotel usa el X, las notas de crédito y el fallo de mozo, y suma
 * la transferencia y la cuenta corriente. En empleados la diferencia queda en cero.
 */
final class TesoreriaCrownCierreCalculo
{
    /**
     * @return array{
     *     habilitado: float,
     *     rendido: float,
     *     resultado: float,
     *     diferencia: float,
     *     signo: string,
     *     tolerancia: float,
     *     supera: bool,
     *     puede: bool,
     *     motivo: string,
     *     formula: string,
     *     componentes: array{formula: string, filas: list<array{concepto: string, importe: float, entra: bool}>}
     * }
     */
    public static function de(TesoreriaCrownAsignacion $asignacion): array
    {
        $tolerancia = TesoreriaCrownCierreSupport::tolerancia();
        $docs = $asignacion->comprobantes()->with('lineas.valor.tipoValor')->get();
        $pendiente = $docs->first(fn (TesoreriaCrownComprobante $doc) => in_array($doc->estado, [
            TesoreriaCrownComprobanteSupport::BORRADOR,
            TesoreriaCrownComprobanteSupport::PENDIENTE,
        ], true));
        $conforme = $docs->filter(fn (TesoreriaCrownComprobante $doc) => $doc->estado === TesoreriaCrownComprobanteSupport::CONFORME);
        $hayRendicion = $conforme->contains(fn (TesoreriaCrownComprobante $doc) => $doc->tipo === TesoreriaCrownComprobanteSupport::RENDICION);

        $tarea = (string) $asignacion->tipo_tarea;
        $formula = self::formula($tarea);
        $fecha = $asignacion->jornada->fecha->format('Y-m-d');
        $salaId = (int) $asignacion->sala_id;
        $sinCotizacion = [];
        $hab = 0.0;
        $rendido = 0.0;
        $fichasHab = 0.0;
        $fichasRend = 0.0;
        $cashless = 0.0;
        $online = 0.0;
        $vouchers = 0.0;
        $apuestas = 0.0;
        $aRendir = 0.0;
        $hotel = 0.0;
        $ventaZ = 0.0;

        foreach ($conforme as $doc) {
            $partes = self::partes($doc, $salaId, $fecha, $sinCotizacion);
            $esRendicion = $doc->tipo === TesoreriaCrownComprobanteSupport::RENDICION;
            if ($esRendicion) {
                $rendido += $partes['efectivo'];
                $fichasRend += $partes['fichas'];
                $cashless += (float) $doc->venta_cash;
                $online += (float) $doc->venta_online;
                $vouchers += (float) $doc->pago_voucher - (float) $doc->venta_voucher;
                $apuestas += (float) $doc->vta_apuesta;
                $aRendir += (float) $doc->total_x - (float) $doc->total_nc - (float) $doc->fallo_mozo;
                $hotel += (float) $doc->transferencia_hotel + (float) $doc->ctacte_hotel;
                $ventaZ += (float) $doc->total_z;
            } else {
                $hab += $partes['efectivo'];
                $fichasHab += $partes['fichas'];
            }
        }

        $hab = round($hab, 2);
        $rendido = round($rendido, 2);
        $fichas = round($fichasRend - $fichasHab, 2);
        $cashless = round($cashless, 2);
        $online = round($online, 2);
        $vouchers = round($vouchers, 2);
        $apuestas = round($apuestas, 2);
        $aRendir = round($aRendir, 2);
        $hotel = round($hotel, 2);
        $ventaZ = round($ventaZ, 2);

        $resultado = match ($formula) {
            'gastro' => $aRendir,
            default => 0.0,
        };
        $diferencia = match ($formula) {
            'empleados' => 0.0,
            'gastro' => round($rendido + $hotel - ($hab + $aRendir - $fichas), 2),
            default => round($rendido + $fichas + $cashless + $vouchers + $online + $apuestas - $hab, 2),
        };

        $filas = self::filas($formula, $hab, $rendido, $fichas, $cashless, $online, $vouchers, $apuestas, $aRendir, $hotel, $ventaZ);
        $motivo = '';
        $puede = true;
        if ($pendiente !== null) {
            $puede = false;
            $motivo = 'Hay comprobantes en borrador o pendientes de firma.';
        } elseif (! $hayRendicion) {
            $puede = false;
            $motivo = 'El cajero no tiene una rendición conforme.';
        } elseif ($sinCotizacion !== []) {
            $puede = false;
            $motivo = 'Falta cotización de '.implode(', ', array_unique($sinCotizacion)).' para expresar el cierre en pesos.';
        }

        return [
            'habilitado' => $hab,
            'rendido' => $rendido,
            'resultado' => $resultado,
            'diferencia' => $diferencia,
            'signo' => TesoreriaCrownCierreSupport::signo($diferencia),
            'tolerancia' => $tolerancia,
            'supera' => abs($diferencia) > $tolerancia + 0.001,
            'puede' => $puede,
            'motivo' => $motivo,
            'formula' => $formula,
            'componentes' => [
                'formula' => $formula,
                'filas' => $filas,
            ],
        ];
    }

    private static function formula(string $tarea): string
    {
        if (TesoreriaCrownRendicionCampos::esCajaGastroHotel($tarea)) {
            return 'gastro';
        }
        if ($tarea === 'E') {
            return 'empleados';
        }

        return 'sala';
    }

    /**
     * @param  array<string, string>  $sinCotizacion
     * @return array{efectivo: float, fichas: float}
     */
    private static function partes(TesoreriaCrownComprobante $doc, int $salaId, string $fecha, array &$sinCotizacion): array
    {
        $pesos = 0.0;
        $extranjera = 0.0;
        $fichas = 0.0;
        foreach ($doc->lineas as $linea) {
            $valor = $linea->valor;
            $tipo = $valor?->tipoValor;
            if ($valor === null || $tipo === null) {
                continue;
            }
            $importe = (float) $linea->cantidad * (float) $valor->denominacion;
            $codigo = (string) $tipo->codigo;
            if (in_array($codigo, ['FVAL', 'FCOL'], true)) {
                $fichas += $importe;

                continue;
            }
            if ($tipo->es_moneda_referencia || $tipo->cotizacion_fija) {
                $pesos += $importe;

                continue;
            }
            $tasa = self::tasa($salaId, $fecha, (int) $tipo->id);
            if ($tasa <= 0) {
                $sinCotizacion[$codigo] = $codigo;

                continue;
            }
            $extranjera += $importe * $tasa;
        }

        $efectivo = $pesos + $extranjera
            + (float) $doc->tarjeta_credito - (float) $doc->recargo_fin
            + (float) $doc->tarjeta_debito + (float) $doc->total_qr;

        return [
            'efectivo' => $efectivo,
            'fichas' => $fichas,
        ];
    }

    /**
     * @return list<array{concepto: string, importe: float, entra: bool}>
     */
    private static function filas(
        string $formula,
        float $hab,
        float $rendido,
        float $fichas,
        float $cashless,
        float $online,
        float $vouchers,
        float $apuestas,
        float $aRendir,
        float $hotel,
        float $ventaZ,
    ): array {
        $sala = $formula !== 'gastro';
        $filas = [
            ['concepto' => 'Efectivo habilitado', 'importe' => $hab, 'entra' => true],
            ['concepto' => 'Efectivo rendido', 'importe' => $rendido, 'entra' => true],
            ['concepto' => 'Fichas', 'importe' => $fichas, 'entra' => true],
            ['concepto' => 'Cashless', 'importe' => $cashless, 'entra' => $sala && $formula !== 'empleados'],
            ['concepto' => 'Vouchers', 'importe' => $vouchers, 'entra' => $sala && $formula !== 'empleados'],
            ['concepto' => 'Online', 'importe' => $online, 'entra' => $sala && $formula !== 'empleados'],
            ['concepto' => 'Apuestas deportivas', 'importe' => $apuestas, 'entra' => $sala && $formula !== 'empleados'],
        ];
        if ($formula === 'gastro') {
            $filas[] = ['concepto' => 'A rendir (X − notas de crédito − fallo de mozo)', 'importe' => $aRendir, 'entra' => true];
            $filas[] = ['concepto' => 'Transferencia y cuenta corriente', 'importe' => $hotel, 'entra' => true];
            $filas[] = ['concepto' => 'Total Z', 'importe' => $ventaZ, 'entra' => false];
        }
        if ($formula === 'empleados') {
            $filas[] = ['concepto' => 'En caja de empleados la diferencia queda en cero', 'importe' => 0.0, 'entra' => false];
        }

        return $filas;
    }

    private static function tasa(int $salaId, string $fecha, int $tipoValorId): float
    {
        $venta = TesoreriaCrownCotizacion::query()
            ->where('sala_id', $salaId)
            ->where('tipo_valor_id', $tipoValorId)
            ->whereDate('jornada', '<=', $fecha)
            ->where('venta', '>', 0)
            ->orderByDesc('jornada')
            ->value('venta');

        return (float) $venta;
    }
}
