<?php

declare(strict_types=1);

namespace App\Support\Ventas;

use App\Models\Ventas\FacturacionJuegoSala;
use App\Models\Ventas\Tipotransaccion;
use InvalidArgumentException;

/**
 * Piernas del asiento de facturación juego (paridad asiento_contable en p-resultado.c).
 *
 * Ganancia (signo +): H en ventas / D en caja.
 * Pérdida (signo -): D en ventas / H en caja.
 */
final class FacturacionJuegoAsientoSupport
{
    /**
     * @return array{
     *   lineas: list<array{dh:string,concepto:string,cuentacontable_id:int,debe:float,haber:float,centrocosto_id:?int}>,
     *   resumen_debe: float,
     *   resumen_haber: float
     * }
     */
    public static function armarLineas(
        FacturacionJuegoSala $cfg,
        string $rubro,
        Tipotransaccion $tipo,
        float $montoAbs,
        string $concepto,
    ): array {
        $montoAbs = round(abs($montoAbs), 2);
        if ($montoAbs < 0.009) {
            throw new InvalidArgumentException('Monto en cero para asiento de facturación juego.');
        }

        $cuentaVentaId = match ($rubro) {
            FacturacionJuegoTipoSupport::RUBRO_MESA => (int) ($cfg->cuentacontable_mesa_id ?? 0),
            FacturacionJuegoTipoSupport::RUBRO_SLOT => (int) ($cfg->cuentacontable_slot_id ?? 0),
            FacturacionJuegoTipoSupport::RUBRO_RULETA_EL => (int) ($cfg->cuentacontable_ruleta_el_id ?? 0),
            default => 0,
        };
        if ($cuentaVentaId <= 0) {
            throw new InvalidArgumentException('Falta cuenta contable de ventas para '.$rubro.'.');
        }

        $cfg->loadMissing('cuentacaja');
        $cuentaCajaId = (int) ($cfg->cuentacaja?->cuentacontable_id ?? 0);
        if ($cuentaCajaId <= 0) {
            throw new InvalidArgumentException(
                'La cuenta de caja de la sala no tiene cuenta contable vinculada.',
            );
        }

        $ccId = (int) ($cfg->centrocosto_id ?? 0) ?: null;
        $ganancia = FacturacionJuegoTipoSupport::esGanancia($tipo);

        // SUMA → subd_tipo_mov H en c_venta (ventas al Haber)
        $dhVenta = $ganancia ? 'H' : 'D';
        $dhCaja = $ganancia ? 'D' : 'H';

        $lineas = [
            [
                'dh' => $dhVenta,
                'concepto' => $concepto,
                'cuentacontable_id' => $cuentaVentaId,
                'debe' => $dhVenta === 'D' ? $montoAbs : 0.0,
                'haber' => $dhVenta === 'H' ? $montoAbs : 0.0,
                'centrocosto_id' => $ccId,
            ],
            [
                'dh' => $dhCaja,
                'concepto' => $concepto,
                'cuentacontable_id' => $cuentaCajaId,
                'debe' => $dhCaja === 'D' ? $montoAbs : 0.0,
                'haber' => $dhCaja === 'H' ? $montoAbs : 0.0,
                'centrocosto_id' => $ccId,
            ],
        ];

        return [
            'lineas' => $lineas,
            'resumen_debe' => $montoAbs,
            'resumen_haber' => $montoAbs,
        ];
    }

    /**
     * @param  list<array{cuentacontable_id:int,debe:float,haber:float,centrocosto_id:?int,concepto:string}>  $lineas
     * @return array<string, mixed>
     */
    public static function armarPayloadAsiento(
        array $lineas,
        int $empresaId,
        string $fecha,
        string $leyenda,
        int $tipoAsientoId,
    ): array {
        $cuentacontableIds = [];
        $centrocostoIds = [];
        $monedaIds = [];
        $debes = [];
        $haberes = [];
        $cotizaciones = [];
        $observaciones = [];

        foreach ($lineas as $ln) {
            $debe = round((float) ($ln['debe'] ?? 0), 2);
            $haber = round((float) ($ln['haber'] ?? 0), 2);
            if ($debe < 0.0001 && $haber < 0.0001) {
                continue;
            }
            $cuentacontableIds[] = (int) $ln['cuentacontable_id'];
            $centrocostoIds[] = $ln['centrocosto_id'] ?? null;
            $monedaIds[] = 1;
            $debes[] = $debe > 0.0001 ? $debe : '';
            $haberes[] = $haber > 0.0001 ? $haber : '';
            $cotizaciones[] = 1;
            $observaciones[] = (string) ($ln['concepto'] ?? $leyenda);
        }

        return [
            'empresa_id' => $empresaId,
            'fecha' => $fecha,
            'tipoasiento_id' => $tipoAsientoId,
            'observacion' => mb_substr($leyenda, 0, 80),
            'cuentacontable_ids' => $cuentacontableIds,
            'centrocosto_ids' => $centrocostoIds,
            'moneda_ids' => $monedaIds,
            'debes' => $debes,
            'haberes' => $haberes,
            'cotizaciones' => $cotizaciones,
            'observaciones' => $observaciones,
        ];
    }
}
