<?php

declare(strict_types=1);

namespace App\Support\Ventas\GastronomiaAnitaImport;

use App\Support\Ventas\GastronomiaCuentacajaCanjeTarjeta;
use App\Support\Ventas\GastronomiaCuentacajaEfectivo;
use InvalidArgumentException;
use stdClass;

/**
 * Convierte montos de resvta (Informix) en líneas de cobranza del POS gastronomía.
 */
final class GastronomiaAnitaImportMediosPagoSupport
{
    private const TOLERANCIA_MONTO = 0.02;

    /**
     * @return list<array{cuentacaja_id:int,moneda_id:int,monto:float,cotizacion:float,observacion:string}>
     */
    public static function lineasDesdeResvta(stdClass $resvta, int $empresaId): array
    {
        $mapa = self::mapaCuentas($empresaId);
        $lineas = [];

        $pares = [
            ['campo' => 'resv_tot_efectivo', 'clave' => 'efectivo'],
            ['campo' => 'resv_tot_fiserv', 'clave' => 'fiserv'],
            ['campo' => 'resv_tot_qr', 'clave' => 'totalcoin'],
            ['campo' => 'resv_tot_tarjeta', 'clave' => 'totalcoin'],
            ['campo' => 'resv_tot_ctacte', 'clave' => 'ctg'],
        ];

        foreach ($pares as $par) {
            $monto = round((float) ($resvta->{$par['campo']} ?? 0), 2);
            if ($monto <= self::TOLERANCIA_MONTO) {
                continue;
            }

            $cuenta = $mapa[$par['clave']] ?? null;
            if ($cuenta === null) {
                throw new InvalidArgumentException(
                    'Sin cuenta de caja para medio «'.$par['clave'].'» (empresa '.$empresaId.').',
                );
            }

            $lineas[] = [
                'cuentacaja_id' => (int) $cuenta['id'],
                'moneda_id' => (int) $cuenta['moneda_id'],
                'monto' => $monto,
                'cotizacion' => 1.,
                'observacion' => 'Import Anita resvta',
            ];
        }

        return $lineas;
    }

    public static function esCortesiaSinCobranza(float $totalVenta, array $lineas): bool
    {
        if (abs($totalVenta - 0.01) <= self::TOLERANCIA_MONTO) {
            return true;
        }

        return $lineas === [];
    }

    /**
     * @return array<string, array{id:int,moneda_id:int}>
     */
    private static function mapaCuentas(int $empresaId): array
    {
        $efectivo = GastronomiaCuentacajaEfectivo::cuentaParaEmpresa($empresaId);
        $ctg = GastronomiaCuentacajaCanjeTarjeta::cuentaParaEmpresa($empresaId);
        $totalcoinId = (int) (config('waitry.tipo_pago_cuentacaja.totalcoin') ?? 0);
        $fiservId = (int) (config('gastronomia_anita_import.cuentacaja_fiserv_id', 233));

        if (! $efectivo || ! $ctg || $totalcoinId <= 0 || $fiservId <= 0) {
            throw new InvalidArgumentException('Faltan cuentas de caja para importación (efectivo, CTG, FISERV o Totalcoin).');
        }

        $fiserv = \App\Models\Caja\Cuentacaja::query()
            ->whereKey($fiservId)
            ->paraEmpresa($empresaId)
            ->first(['id', 'moneda_id']);
        $totalcoin = \App\Models\Caja\Cuentacaja::query()
            ->whereKey($totalcoinId)
            ->paraEmpresa($empresaId)
            ->first(['id', 'moneda_id']);

        if (! $fiserv || ! $totalcoin) {
            throw new InvalidArgumentException('Cuenta FISERV o Totalcoin (226) no válida para empresa '.$empresaId.'.');
        }

        return [
            'efectivo' => ['id' => (int) $efectivo['id'], 'moneda_id' => (int) $efectivo['moneda_id']],
            'ctg' => ['id' => (int) $ctg['id'], 'moneda_id' => (int) $ctg['moneda_id']],
            'fiserv' => ['id' => (int) $fiserv->id, 'moneda_id' => (int) $fiserv->moneda_id],
            'totalcoin' => ['id' => (int) $totalcoin->id, 'moneda_id' => (int) $totalcoin->moneda_id],
        ];
    }
}
