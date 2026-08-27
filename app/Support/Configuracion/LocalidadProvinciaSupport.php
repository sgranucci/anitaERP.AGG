<?php

namespace App\Support\Configuracion;

use App\Models\Configuracion\Localidad;

/**
 * Recupera localidad si el combo cascada llega vacío y alinea provincia
 * con la localidad (misma lógica que el ABM de clientes).
 */
final class LocalidadProvinciaSupport
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function aplicar(array $data): array
    {
        $data['localidad_id'] = self::idConFallback(
            $data['localidad_id'] ?? null,
            $data['localidad_id_previa'] ?? null
        );

        return self::alinearProvinciaConLocalidad($data);
    }

    public static function idEnteroONull($valor): ?int
    {
        if ($valor === null || $valor === false) {
            return null;
        }

        if (is_string($valor) && trim($valor) === '') {
            return null;
        }

        $id = (int) $valor;

        return $id > 0 ? $id : null;
    }

    public static function idConFallback($enviado, $previa): ?int
    {
        $id = self::idEnteroONull($enviado);
        if ($id !== null) {
            return $id;
        }

        return self::idEnteroONull($previa);
    }

    /**
     * @param  array<int|string|null>  $enviados
     * @param  array<int|string|null>  $previas
     * @return list<int|null>
     */
    public static function recuperarIdsEnLista($enviados, $previas): array
    {
        $enviados = array_values((array) $enviados);
        $previas = array_values((array) $previas);
        $max = max(count($enviados), count($previas));
        $out = [];
        for ($i = 0; $i < $max; $i++) {
            $out[] = self::idConFallback($enviados[$i] ?? null, $previas[$i] ?? null);
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function alinearProvinciaConLocalidad(array $data): array
    {
        $localidadId = self::idEnteroONull($data['localidad_id'] ?? null);
        if ($localidadId === null) {
            $data['localidad_id'] = null;

            return $data;
        }

        $data['localidad_id'] = $localidadId;

        $localidad = Localidad::query()
            ->with('provincias:id,nombre')
            ->find($localidadId);
        if ($localidad === null || (int) $localidad->provincia_id <= 0) {
            return $data;
        }

        $provinciaLocalidad = (int) $localidad->provincia_id;
        if ((int) ($data['provincia_id'] ?? 0) === $provinciaLocalidad) {
            return $data;
        }

        $data['provincia_id'] = $provinciaLocalidad;
        $nombreProvincia = trim((string) ($localidad->provincias?->nombre ?? ''));
        if ($nombreProvincia !== '') {
            $data['desc_provincia'] = $nombreProvincia;
        }

        return $data;
    }
}
