<?php

namespace App\Support\Ventas;

use App\Models\Caja\Cuentacaja;

/**
 * Cuenta de caja «efectivo» por empresa para efectivización rápida (F5) en el POS gastronomía.
 */
final class GastronomiaCuentacajaEfectivo
{
    /**
     * @return array<int, int> empresa_id => cuentacaja_id
     */
    public static function mapaPorEmpresa(): array
    {
        $map = config('gastronomia.cuentacaja_efectivo_por_empresa', []);
        if (! is_array($map)) {
            $map = [];
        }

        $normalized = [];
        foreach ($map as $empresaId => $cuentacajaId) {
            $ccId = (int) $cuentacajaId;
            if ($ccId > 0) {
                $normalized[(int) $empresaId] = $ccId;
            }
        }

        return $normalized;
    }

    public static function idParaEmpresa(int $empresaId): ?int
    {
        if ($empresaId <= 0) {
            return null;
        }

        $id = self::mapaPorEmpresa()[$empresaId] ?? null;

        return $id > 0 ? $id : null;
    }

    /**
     * Mensaje cuando el id del .env no resuelve a una cuenta de caja de la empresa.
     */
    public static function mensajeErrorResolucion(int $empresaId): ?string
    {
        $cuentacajaId = self::idParaEmpresa($empresaId);
        if (! $cuentacajaId) {
            return 'No hay cuenta de caja de efectivo configurada para la empresa '.$empresaId
                .' (GASTRONOMIA_CUENTACAJA_EFECTIVO_POR_EMPRESA).';
        }

        $existe = Cuentacaja::query()
            ->whereKey($cuentacajaId)
            ->where('empresa_id', $empresaId)
            ->exists();

        if (! $existe) {
            return 'La cuenta de caja id '.$cuentacajaId.' configurada para la empresa '.$empresaId
                .' no existe o no pertenece a esa empresa.';
        }

        return null;
    }

    /**
     * Cuenta de efectivo configurada en env/config (F5). No exige uso «Gastronomía» en usocuentacaja.
     *
     * @return array{id:int,nombre:string,codigo:string,moneda_id:int,moneda_abreviatura:?string}|null
     */
    public static function cuentaParaEmpresa(int $empresaId): ?array
    {
        $cuentacajaId = self::idParaEmpresa($empresaId);
        if (! $cuentacajaId) {
            return null;
        }

        $cuenta = Cuentacaja::query()
            ->whereKey($cuentacajaId)
            ->where('empresa_id', $empresaId)
            ->with('monedas:id,abreviatura,nombre')
            ->first(['id', 'nombre', 'codigo', 'moneda_id']);

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
}
