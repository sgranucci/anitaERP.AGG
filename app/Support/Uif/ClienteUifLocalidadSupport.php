<?php

namespace App\Support\Uif;

use Illuminate\Support\Facades\DB;

/**
 * Evita que el ABM UIF borre localidad de nacimiento/residencia
 * cuando el combo cascada llega vacío al POST (carrera AJAX o provincia desfasada).
 */
final class ClienteUifLocalidadSupport
{
    /**
     * @param  array<string, mixed>  $data
     * @param  callable(int): (?int)|null  $provinciaDeLocalidad
     * @return array<string, mixed>
     */
    public static function aplicar(array $data, ?callable $provinciaDeLocalidad = null): array
    {
        $data['localidad_uif_id'] = self::idConFallback(
            $data['localidad_uif_id'] ?? null,
            $data['localidad_uif_id_previa'] ?? null
        );
        $data['localidadnacimiento_id'] = self::idConFallback(
            $data['localidadnacimiento_id'] ?? null,
            $data['localidadnacimiento_id_previa'] ?? null
        );

        $data = self::completarProvinciaSiVacia(
            $data,
            'localidad_uif_id',
            'provincia_uif_id',
            $provinciaDeLocalidad
        );
        $data = self::completarProvinciaSiVacia(
            $data,
            'localidadnacimiento_id',
            'provincianacimiento_id',
            $provinciaDeLocalidad
        );

        return $data;
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
     * @param  array<string, mixed>  $data
     * @param  callable(int): (?int)|null  $provinciaDeLocalidad
     * @return array<string, mixed>
     */
    public static function completarProvinciaSiVacia(
        array $data,
        string $campoLocalidad,
        string $campoProvincia,
        ?callable $provinciaDeLocalidad = null
    ): array {
        $provinciaId = self::idEnteroONull($data[$campoProvincia] ?? null);
        if ($provinciaId !== null) {
            $data[$campoProvincia] = $provinciaId;

            return $data;
        }

        $localidadId = self::idEnteroONull($data[$campoLocalidad] ?? null);
        if ($localidadId === null) {
            $data[$campoProvincia] = null;

            return $data;
        }

        $desdeLocalidad = $provinciaDeLocalidad !== null
            ? self::idEnteroONull($provinciaDeLocalidad($localidadId))
            : self::provinciaIdDeLocalidad($localidadId);

        $data[$campoProvincia] = $desdeLocalidad;

        return $data;
    }

    public static function provinciaIdDeLocalidad(int $localidadId): ?int
    {
        return self::idEnteroONull(
            DB::table('localidad_uif')->where('id', $localidadId)->value('provincia_uif_id')
        );
    }
}
