<?php

namespace App\Support\Stock;

use App\ApiAnita;
use App\Models\Stock\Recepcion_Proveedor;
use Carbon\Carbon;

/**
 * Verifica si una COM ya grabada en Anita (REF, ERP o terminal vacío) corresponde
 * a la recepción ERP comparando OC, proveedor y fecha en recepmae, recepmov, stkmov y ctamov.
 */
final class RecepcionProveedorAnitaCorrespondenciaSupport
{
    /**
     * @param  object|null  $cabecera  recepmae ya leída (opcional)
     */
    public static function correspondeConRecepcionErp(Recepcion_Proveedor $recepcion, ?object $cabecera = null): bool
    {
        $recepcion->loadMissing(['proveedores', 'ordencompras', 'empresas', 'asientos']);

        $clave = RecepcionProveedorAnitaClaveSupport::resolver($recepcion);
        if ((int) ($clave['nro'] ?? 0) <= 0) {
            return false;
        }

        $codigoProveedor = RecepcionProveedorAnitaWhereSupport::codigoProveedorAnita($recepcion);
        $fechaRecepcion = self::fechaAnitaDesdeRecepcion($recepcion);
        $fechaCtamov = self::fechaCtamovEsperada($recepcion);
        $ocEsperada = self::claveOcEsperada($recepcion);
        $numeroOcEsperado = (int) ($ocEsperada['nro'] ?? 0);

        $cabecera ??= RecepcionProveedorAnitaColisionSupport::leerRecepmaeProveedorClave($codigoProveedor, $clave);

        if ($cabecera !== null && ! self::cabeceraCoincide($cabecera, $codigoProveedor, $fechaRecepcion, $ocEsperada)) {
            return false;
        }

        if (! self::recepmovCoincide($codigoProveedor, $clave, $fechaRecepcion)) {
            return false;
        }

        if (! self::stkmovCoincide($codigoProveedor, $clave, $fechaRecepcion)) {
            return false;
        }

        if (! self::ctamovCoincide($clave, $fechaCtamov, $numeroOcEsperado)) {
            return false;
        }

        return true;
    }

    private static function cabeceraCoincide(
        object $cabecera,
        string $codigoProveedor,
        int $fechaRecepcion,
        array $ocEsperada,
    ): bool {
        $proveedorCab = RecepcionProveedorAnitaReferenciaSupport::proveedorAnita6(
            trim((string) ($cabecera->recm_proveedor ?? ''))
        );
        $proveedorErp = RecepcionProveedorAnitaReferenciaSupport::proveedorAnita6($codigoProveedor);

        if ($proveedorCab !== $proveedorErp) {
            return false;
        }

        if ((int) ($cabecera->recm_fecha ?? 0) !== $fechaRecepcion) {
            return false;
        }

        return self::claveOcCoincide($ocEsperada, [
            'tipo' => trim((string) ($cabecera->recm_tipo_fac ?? '')),
            'letra' => trim((string) ($cabecera->recm_letra_fac ?? '')),
            'sucursal' => (int) ($cabecera->recm_sucursal_fac ?? 0),
            'nro' => (int) ($cabecera->recm_nro_fac ?? 0),
        ]);
    }

    /**
     * @param  array{tipo: string, letra: string, sucursal: int, nro: int}  $clave
     */
    private static function recepmovCoincide(string $codigoProveedor, array $clave, int $fechaRecepcion): bool
    {
        $filas = self::listarRecepmov($codigoProveedor, $clave);
        if ($filas === []) {
            return true;
        }

        $proveedorErp = RecepcionProveedorAnitaReferenciaSupport::proveedorAnita6($codigoProveedor);

        foreach ($filas as $fila) {
            $row = is_array($fila) ? $fila : get_object_vars($fila);
            $proveedor = RecepcionProveedorAnitaReferenciaSupport::proveedorAnita6(
                trim((string) ($row['recv_proveedor'] ?? ''))
            );
            if ($proveedor !== $proveedorErp) {
                return false;
            }
            if ((int) ($row['recv_fecha'] ?? 0) !== $fechaRecepcion) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array{tipo: string, letra: string, sucursal: int, nro: int}  $clave
     */
    private static function stkmovCoincide(string $codigoProveedor, array $clave, int $fechaRecepcion): bool
    {
        $filas = self::listarStkmov($clave);
        if ($filas === []) {
            return true;
        }

        $proveedorErp = RecepcionProveedorAnitaReferenciaSupport::proveedorAnita6($codigoProveedor);

        foreach ($filas as $fila) {
            $row = is_array($fila) ? $fila : get_object_vars($fila);
            $proveedor = RecepcionProveedorAnitaReferenciaSupport::proveedorAnita6(
                trim((string) ($row['stkv_cli_pro'] ?? ''))
            );
            if ($proveedor !== $proveedorErp) {
                return false;
            }
            if ((int) ($row['stkv_fecha'] ?? 0) !== $fechaRecepcion) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array{tipo: string, letra: string, sucursal: int, nro: int}  $clave
     */
    private static function ctamovCoincide(array $clave, int $fechaCtamov, int $numeroOcEsperado): bool
    {
        $filas = self::listarCtamov($clave);
        if ($filas === []) {
            return true;
        }

        foreach ($filas as $fila) {
            $row = is_array($fila) ? $fila : get_object_vars($fila);
            if ((int) ($row['ctav_fecha'] ?? 0) !== $fechaCtamov) {
                return false;
            }
            if ($numeroOcEsperado > 0 && (int) ($row['ctav_o_compra'] ?? 0) !== $numeroOcEsperado) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array{tipo: string, letra: string, sucursal: int, nro: int}  $clave
     * @return list<object|array<string, mixed>>
     */
    private static function listarRecepmov(string $codigoProveedor, array $clave): array
    {
        $api = new ApiAnita;
        $raw = $api->apiCall([
            'acc' => 'list',
            'sistema' => (string) config('recepcion_proveedor.anita.sistema_compras'),
            'tabla' => (string) config('recepcion_proveedor.anita.tablas.recepcion_linea'),
            'campos' => 'recv_proveedor, recv_fecha',
            'whereArmado' => RecepcionProveedorAnitaWhereSupport::recepmovProveedorCabecera($codigoProveedor, $clave),
        ]);

        return ApiAnita::decodificarListaFilas((string) $raw);
    }

    /**
     * @param  array{tipo: string, letra: string, sucursal: int, nro: int}  $clave
     * @return list<object|array<string, mixed>>
     */
    private static function listarStkmov(array $clave): array
    {
        $cfg = config('recepcion_proveedor.anita');
        $api = new ApiAnita;
        $raw = $api->apiCall([
            'acc' => 'list',
            'sistema' => (string) $cfg['sistema_ventas'],
            'tabla' => (string) $cfg['tablas']['stock_movimiento'],
            'campos' => 'stkv_cli_pro, stkv_fecha',
            'whereArmado' => RecepcionProveedorAnitaWhereSupport::stkmovCabecera($clave),
        ]);

        return ApiAnita::decodificarListaFilas((string) $raw);
    }

    /**
     * @param  array{tipo: string, letra: string, sucursal: int, nro: int}  $clave
     * @return list<object|array<string, mixed>>
     */
    private static function listarCtamov(array $clave): array
    {
        $tipo = str_replace("'", "''", trim((string) ($clave['tipo'] ?? '')));
        $letra = str_replace("'", "''", trim((string) ($clave['letra'] ?? '')));
        $sucursal = (int) ($clave['sucursal'] ?? 0);
        $nro = (int) ($clave['nro'] ?? 0);

        if ($tipo === '' || $nro <= 0) {
            return [];
        }

        $api = new ApiAnita;
        $raw = $api->apiCall([
            'acc' => 'list',
            'sistema' => 'contab',
            'tabla' => 'ctamov',
            'campos' => 'ctav_fecha, ctav_o_compra',
            'whereArmado' => " WHERE ctav_tipo='{$tipo}'"
                ." AND ctav_letra='{$letra}'"
                .' AND ctav_sucursal='.$sucursal
                .' AND ctav_nro='.$nro,
        ]);

        return ApiAnita::decodificarListaFilas((string) $raw);
    }

    /**
     * @return array{tipo: string, letra: string, sucursal: int, nro: int}
     */
    private static function claveOcEsperada(Recepcion_Proveedor $recepcion): array
    {
        $cfg = config('recepcion_proveedor.anita');

        return [
            'tipo' => trim((string) $cfg['oc_tipo']),
            'letra' => trim((string) $cfg['oc_letra']),
            'sucursal' => (int) $cfg['oc_sucursal'],
            'nro' => (int) ($recepcion->ordencompras->numeroordencompra ?? 0),
        ];
    }

    /**
     * @param  array{tipo: string, letra: string, sucursal: int, nro: int}  $esperada
     * @param  array{tipo: string, letra: string, sucursal: int, nro: int}  $anita
     */
    private static function claveOcCoincide(array $esperada, array $anita): bool
    {
        if ((int) ($esperada['nro'] ?? 0) <= 0) {
            return true;
        }

        return trim((string) $esperada['tipo']) === trim((string) $anita['tipo'])
            && trim((string) $esperada['letra']) === trim((string) $anita['letra'])
            && (int) ($esperada['sucursal'] ?? 0) === (int) ($anita['sucursal'] ?? 0)
            && (int) ($esperada['nro'] ?? 0) === (int) ($anita['nro'] ?? 0);
    }

    private static function fechaAnitaDesdeRecepcion(Recepcion_Proveedor $recepcion): int
    {
        $fecha = $recepcion->fecha;
        if ($fecha instanceof \DateTimeInterface) {
            return (int) $fecha->format('Ymd');
        }

        return (int) str_replace('-', '', Carbon::parse((string) $fecha)->format('Y-m-d'));
    }

    private static function fechaCtamovEsperada(Recepcion_Proveedor $recepcion): int
    {
        $asiento = $recepcion->asientos;
        if ($asiento && $asiento->fecha) {
            $fecha = $asiento->fecha;
            if ($fecha instanceof \DateTimeInterface) {
                return (int) $fecha->format('Ymd');
            }

            return (int) str_replace('-', '', Carbon::parse((string) $fecha)->format('Y-m-d'));
        }

        return self::fechaAnitaDesdeRecepcion($recepcion);
    }
}
