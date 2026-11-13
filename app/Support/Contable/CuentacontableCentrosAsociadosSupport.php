<?php

declare(strict_types=1);

namespace App\Support\Contable;

use App\Models\Contable\Cuentacontable;

/**
 * Texto de centros de costo asociados a una cuenta (matriz cuentacontable_centrocosto).
 */
final class CuentacontableCentrosAsociadosSupport
{
    public static function desdeCuenta(Cuentacontable $cuenta): string
    {
        $vinculos = $cuenta->relationLoaded('cuentacontable_centrocostos')
            ? $cuenta->cuentacontable_centrocostos
            : $cuenta->cuentacontable_centrocostos()->with('centrocostos')->get();

        return self::textoAsociados($cuenta->manejaccosto, $vinculos);
    }

    /**
     * @param  iterable<int, mixed>  $vinculos  Filas pivot con relación centrocostos, o centros con codigo/nombre.
     */
    public static function textoAsociados(mixed $manejaccosto, iterable $vinculos): string
    {
        if (! AsientoCentrocostoObligatorioSupport::maneja($manejaccosto)) {
            return '—';
        }

        $partes = self::listaPartes($vinculos);
        if ($partes === []) {
            return 'Cualquiera';
        }

        return implode(', ', $partes);
    }

    /**
     * @param  iterable<int, mixed>  $vinculos
     * @return list<string>
     */
    public static function listaPartes(iterable $vinculos): array
    {
        $out = [];
        foreach ($vinculos as $vinculo) {
            $centro = self::resolverCentro($vinculo);
            if ($centro === null) {
                continue;
            }

            $codigo = trim((string) ($centro->codigo ?? ''));
            $nombre = trim((string) ($centro->nombre ?? ''));
            $label = trim($codigo.($codigo !== '' && $nombre !== '' ? ' - ' : '').$nombre);
            if ($label === '' || in_array($label, $out, true)) {
                continue;
            }
            $out[] = $label;
        }

        natcasesort($out);

        return array_values($out);
    }

    private static function resolverCentro(mixed $vinculo): ?object
    {
        if (! is_object($vinculo)) {
            return null;
        }

        if (isset($vinculo->centrocostos) && is_object($vinculo->centrocostos)) {
            return $vinculo->centrocostos;
        }

        if (isset($vinculo->codigo) || isset($vinculo->nombre)) {
            return $vinculo;
        }

        return null;
    }
}
