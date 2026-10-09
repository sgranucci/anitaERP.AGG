<?php

namespace App\Support\Ventas;

use App\Models\Ventas\Cliente;
use App\Support\Database\SqlDialectSupport;
use RuntimeException;

/**
 * AGG: dos numeraciones de cliente.
 * Administración sigue ERP### (Anita + el mayor ya cargado en el ERP).
 * Gastronomía sigue la serie numérica 7000–7999 (clientes internos).
 */
final class ClienteCodigoSecuenciaSupport
{
    public const ADMINISTRACION = 'administracion';

    public const GASTRONOMIA = 'gastronomia';

    public static function habilitada(): bool
    {
        return config('app.empresa') === 'AGG'
            && self::desde() > 0
            && self::hasta() >= self::desde();
    }

    public static function esPedidoGastronomia(?string $secuencia): bool
    {
        return self::habilitada()
            && trim((string) $secuencia) === self::GASTRONOMIA;
    }

    public static function esGastronomia(?string $codigo): bool
    {
        if (! self::habilitada() || ! ctype_digit(trim((string) $codigo))) {
            return false;
        }

        $numero = (int) $codigo;

        return $numero >= self::desde() && $numero <= self::hasta();
    }

    public static function esAdministracionErp(?string $codigo): bool
    {
        return (bool) preg_match('/^ERP\d+$/i', trim((string) $codigo));
    }

    /**
     * El selector se muestra al crear y al editar un código ERP o de la serie gastronomía.
     */
    public static function muestraSelector(?string $codigo, bool $esAlta): bool
    {
        if (! self::habilitada()) {
            return false;
        }

        if ($esAlta) {
            return true;
        }

        return self::esGastronomia($codigo) || self::esAdministracionErp($codigo);
    }

    /**
     * Solo un código ERP puede pasar a la serie de gastronomía.
     * Un 70xx ya asignado no se renumera.
     */
    public static function debeReasignarAGastronomia(?string $codigoActual, ?string $secuencia): bool
    {
        return self::esPedidoGastronomia($secuencia)
            && self::esAdministracionErp($codigoActual)
            && ! self::esGastronomia($codigoActual);
    }

    public static function proximoGastronomia(bool $bloquear = false): string
    {
        if (! self::habilitada()) {
            throw new RuntimeException('La secuencia de clientes de gastronomía no está habilitada.');
        }

        $desde = self::desde();
        $hasta = self::hasta();
        $cast = SqlDialectSupport::castEntero('codigo');

        $consulta = Cliente::withTrashed()
            ->whereRaw(SqlDialectSupport::coincideRegex('codigo'), ['^[0-9]+$'])
            ->whereRaw($cast.' BETWEEN ? AND ?', [$desde, $hasta]);
        if ($bloquear) {
            $consulta->lockForUpdate();
        }
        $ocupados = $consulta->pluck('codigo');

        $maximo = $desde - 1;
        foreach ($ocupados as $codigo) {
            $numero = (int) $codigo;
            if ($numero > $maximo) {
                $maximo = $numero;
            }
        }

        $siguiente = $maximo + 1;
        while ($siguiente <= $hasta && self::codigoOcupado((string) $siguiente)) {
            $siguiente++;
        }

        if ($siguiente > $hasta) {
            throw new RuntimeException(
                'No hay códigos libres en la secuencia de gastronomía ('.$desde.'–'.$hasta.').'
            );
        }

        return (string) $siguiente;
    }

    /**
     * Siguiente ERP###. Toma el mayor entre Anita y los códigos ya grabados en el ERP
     * para no repetir un código que Anita todavía no devolvió.
     */
    public static function proximoCodigoAdministracion(int $ultimoNumeroAnita): string
    {
        $numero = max(0, $ultimoNumeroAnita, self::maxNumeroErpLocal()) + 1;

        do {
            $codigo = 'ERP'.str_pad((string) $numero, 3, '0', STR_PAD_LEFT);
            $numero++;
        } while (self::codigoOcupado($codigo));

        return $codigo;
    }

    public static function maxNumeroErpLocal(): int
    {
        $maximo = 0;
        $codigos = Cliente::withTrashed()
            ->where('codigo', 'like', 'ERP%')
            ->pluck('codigo');

        foreach ($codigos as $codigo) {
            if (preg_match('/^ERP(\d+)$/i', (string) $codigo, $coincidencias)) {
                $maximo = max($maximo, (int) $coincidencias[1]);
            }
        }

        return $maximo;
    }

    private static function codigoOcupado(string $codigo): bool
    {
        return Cliente::withTrashed()->where('codigo', $codigo)->exists();
    }

    private static function desde(): int
    {
        return (int) config('cliente.SECUENCIA_GASTRONOMIA_DESDE', 0);
    }

    private static function hasta(): int
    {
        return (int) config('cliente.SECUENCIA_GASTRONOMIA_HASTA', 0);
    }
}
