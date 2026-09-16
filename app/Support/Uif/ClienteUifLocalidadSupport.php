<?php

namespace App\Support\Uif;

use Illuminate\Support\Facades\DB;

/**
 * Evita que el ABM UIF borre localidad de nacimiento/residencia
 * cuando el combo cascada llega vacío al POST (carrera AJAX o provincia desfasada),
 * y alinea la provincia con la de la localidad para que el combo no la “pierda” al reabrir.
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

        $data = self::alinearProvinciaConLocalidad(
            $data,
            'localidad_uif_id',
            'provincia_uif_id',
            $provinciaDeLocalidad
        );
        $data = self::alinearProvinciaConLocalidad(
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
     * En sync Anita→ERP: si el cliente ya tiene geo en el ERP, no la pisa.
     * Anita solo completa cuando el campo ERP está vacío.
     */
    public static function preferirErpSiCargado($valorErp, $valorAnita): ?int
    {
        $erp = self::idEnteroONull($valorErp);
        if ($erp !== null) {
            return $erp;
        }

        return self::idEnteroONull($valorAnita);
    }

    /**
     * Si la localidad tiene provincia en el maestro, usa esa (completa vacíos y corrige desfasajes).
     * Si la localidad no tiene provincia, o es “NO RESIDENTE” y el cliente ya tiene provincia real, conserva la enviada.
     *
     * @param  array<string, mixed>  $data
     * @param  callable(int): (?int)|null  $provinciaDeLocalidad
     * @return array<string, mixed>
     */
    public static function alinearProvinciaConLocalidad(
        array $data,
        string $campoLocalidad,
        string $campoProvincia,
        ?callable $provinciaDeLocalidad = null
    ): array {
        $localidadId = self::idEnteroONull($data[$campoLocalidad] ?? null);
        $provinciaEnviada = self::idEnteroONull($data[$campoProvincia] ?? null);

        if ($localidadId === null) {
            $data[$campoProvincia] = $provinciaEnviada;

            return $data;
        }

        $desdeLocalidad = $provinciaDeLocalidad !== null
            ? self::idEnteroONull($provinciaDeLocalidad($localidadId))
            : self::provinciaIdDeLocalidad($localidadId);

        if ($desdeLocalidad === null) {
            $data[$campoProvincia] = $provinciaEnviada;

            return $data;
        }

        // Localidad exterior / no catalogada bajo NO RESIDENTE: no pisar provincia real ya cargada.
        if (
            $provinciaEnviada !== null
            && $desdeLocalidad === 26
            && $provinciaEnviada !== 26
        ) {
            $data[$campoProvincia] = $provinciaEnviada;

            return $data;
        }

        $data[$campoProvincia] = $desdeLocalidad;

        return $data;
    }

    /**
     * @deprecated Usar alinearProvinciaConLocalidad
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
        return self::alinearProvinciaConLocalidad(
            $data,
            $campoLocalidad,
            $campoProvincia,
            $provinciaDeLocalidad
        );
    }

    public static function provinciaIdDeLocalidad(int $localidadId): ?int
    {
        return self::idEnteroONull(
            DB::table('localidad_uif')->where('id', $localidadId)->value('provincia_uif_id')
        );
    }
}
