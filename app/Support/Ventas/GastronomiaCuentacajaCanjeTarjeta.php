<?php

namespace App\Support\Ventas;

use App\Models\Caja\Cuentacaja;
use App\Models\Caja\Usocuentacaja;

/**
 * Cuenta de caja «Canje ticket gastronomía» (código CTG) para el POS gastronomía.
 */
final class GastronomiaCuentacajaCanjeTarjeta
{
    public static function codigo(): string
    {
        return strtoupper(trim((string) config('gastronomia.ticket_tarjeta_cuentacaja_codigo', 'CTG')));
    }

    public static function usoCuentacajaId(): ?int
    {
        $configured = config('gastronomia.usocuentacaja_id');
        if ($configured !== null && $configured !== '') {
            return (int) $configured;
        }

        $id = Usocuentacaja::query()->where('nombre', 'Gastronomia')->value('id');

        return $id ? (int) $id : null;
    }

    public static function mensajeErrorResolucion(int $empresaId): ?string
    {
        if ($empresaId <= 0) {
            return 'Empresa no válida para resolver cuenta CTG.';
        }

        $codigo = self::codigo();
        if ($codigo === '') {
            return 'No está configurado el código de cuenta de caja para canje de ticket (GASTRONOMIA_TICKET_TARJETA_CUENTACAJA_CODIGO).';
        }

        $usoId = self::usoCuentacajaId();
        if (! $usoId) {
            return 'No está configurado el uso de cuenta de caja Gastronomía (GASTRONOMIA_USO_CUENTACAJA_ID).';
        }

        $cuenta = self::queryCuenta($empresaId, $codigo, $usoId);
        if (! $cuenta) {
            return 'No existe la cuenta de caja «'.$codigo.'» con uso Gastronomía para la empresa '
                .$empresaId.' ni como cuenta multiempresa.';
        }

        return null;
    }

    /**
     * @return array{id:int,nombre:string,codigo:string,moneda_id:int,moneda_abreviatura:?string}|null
     */
    public static function cuentaParaEmpresa(int $empresaId): ?array
    {
        if ($empresaId <= 0) {
            return null;
        }

        $codigo = self::codigo();
        $usoId = self::usoCuentacajaId();
        if ($codigo === '' || ! $usoId) {
            return null;
        }

        $cuenta = self::queryCuenta($empresaId, $codigo, $usoId);
        if (! $cuenta) {
            return null;
        }

        return [
            'id' => (int) $cuenta->id,
            'nombre' => (string) $cuenta->nombre,
            'codigo' => (string) $cuenta->codigo,
            'moneda_id' => (int) $cuenta->moneda_id,
            'moneda_abreviatura' => $cuenta->monedas->abreviatura ?? null,
        ];
    }

    private static function queryCuenta(int $empresaId, string $codigo, int $usoId): ?Cuentacaja
    {
        $variantes = array_values(array_unique(array_filter([
            $codigo,
            ltrim($codigo, '0') !== '' ? ltrim($codigo, '0') : null,
        ])));

        $query = Cuentacaja::query()
            ->whereIn('codigo', $variantes)
            ->whereHas('usocuentacajas', fn ($r) => $r->whereKey($usoId))
            ->with('monedas:id,abreviatura,nombre');

        if ($empresaId > 0) {
            $query->paraEmpresa($empresaId);
        }

        $cuentas = $query->get(['id', 'nombre', 'codigo', 'moneda_id', 'empresa_id']);

        return $cuentas->first(fn (Cuentacaja $c) => (int) $c->empresa_id === $empresaId)
            ?? $cuentas->first();
    }
}
