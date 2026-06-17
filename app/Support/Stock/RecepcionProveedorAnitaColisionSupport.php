<?php

namespace App\Support\Stock;

use App\ApiAnita;
use App\Models\Stock\Recepcion_Proveedor;

/**
 * Evita confirmar una recepción COM cuyo número ya existe en Anita con datos ajenos
 * (p. ej. numeración ERP con max() desfasada del legado Anita).
 */
final class RecepcionProveedorAnitaColisionSupport
{
    /**
     * @param  array{tipo: string, letra: string, sucursal: int, nro: int}  $clave
     */
    public static function numeroOcupadoEnAnita(array $clave): bool
    {
        if ((int) $clave['nro'] <= 0) {
            return false;
        }

        return self::existeRecepmaePorClave($clave)
            || self::existeRecepmov($clave)
            || self::existeStkmov($clave);
    }

    /**
     * Antes de borrar recepmov/stkmov en Anita, valida que el número COM no pertenezca
     * a otra recepción. Si hay datos y no es un reintento coherente, detiene con error.
     */
    public static function assertConfirmacionSegura(Recepcion_Proveedor $recepcion): void
    {
        $recepcion->loadMissing(['proveedores', 'ordencompras', 'empresas']);

        $clave = RecepcionProveedorAnitaClaveSupport::resolver($recepcion);
        if (! self::numeroOcupadoEnAnita($clave)) {
            return;
        }

        $cabeceraClave = self::leerRecepmaePorClave($clave);
        if ($cabeceraClave !== null && self::esReintentoMismaRecepcion($recepcion, $cabeceraClave)) {
            return;
        }

        $codigoProveedor = RecepcionProveedorAnitaWhereSupport::codigoProveedorAnita($recepcion);
        if ($cabeceraClave !== null) {
            $proveedorAnita = RecepcionProveedorAnitaReferenciaSupport::proveedorAnita6($codigoProveedor);
            $proveedorCabecera = RecepcionProveedorAnitaReferenciaSupport::proveedorAnita6(
                trim((string) ($cabeceraClave->recm_proveedor ?? ''))
            );

            if ($proveedorCabecera !== $proveedorAnita) {
                throw new \RuntimeException(self::mensajeColision(
                    $clave,
                    'ya está registrada en Anita para el proveedor '.$proveedorCabecera.'.'
                ));
            }
        }

        throw new \RuntimeException(self::mensajeColision(
            $clave,
            'ya tiene datos en Anita'.($cabeceraClave === null ? ' (movimientos sin cabecera de esta recepción)' : '').'.'
        ));
    }

    /**
     * Máximo recm_nro COM en Anita para la sucursal (empresa).
     */
    public static function maxNumeroRecepmaeSucursal(int $sucursal): int
    {
        if ($sucursal <= 0) {
            return 0;
        }

        $cfg = config('recepcion_proveedor.anita');
        $tipo = addslashes((string) $cfg['recepcion_tipo']);
        $letra = addslashes((string) $cfg['recepcion_letra']);

        $api = new ApiAnita;
        $raw = $api->apiCall([
            'acc' => 'list',
            'sistema' => (string) $cfg['sistema_compras'],
            'tabla' => (string) $cfg['tablas']['recepcion_cabecera'],
            'campos' => 'max(recm_nro) as max_nro',
            'whereArmado' => " WHERE recm_tipo = '{$tipo}' AND recm_letra = '{$letra}'"
                .' AND recm_sucursal = '.(int) $sucursal,
        ]);

        $fila = ApiAnita::primeraFilaLista($raw);

        return max(0, (int) ($fila->max_nro ?? 0));
    }

    /**
     * @param  array{tipo: string, letra: string, sucursal: int, nro: int}  $clave
     */
    private static function existeRecepmaePorClave(array $clave): bool
    {
        return self::leerRecepmaePorClave($clave) !== null;
    }

    /**
     * @param  array{tipo: string, letra: string, sucursal: int, nro: int}  $clave
     */
    private static function existeRecepmov(array $clave): bool
    {
        return RecepcionProveedorAnitaImportSupport::listarRecepmov(
            (string) $clave['tipo'],
            (string) $clave['letra'],
            (int) $clave['sucursal'],
            (int) $clave['nro']
        ) !== [];
    }

    /**
     * @param  array{tipo: string, letra: string, sucursal: int, nro: int}  $clave
     */
    private static function existeStkmov(array $clave): bool
    {
        $cfg = config('recepcion_proveedor.anita');
        $api = new ApiAnita;
        $raw = $api->apiCall([
            'acc' => 'list',
            'sistema' => (string) $cfg['sistema_ventas'],
            'tabla' => (string) $cfg['tablas']['stock_movimiento'],
            'campos' => 'stkv_nro',
            'whereArmado' => RecepcionProveedorAnitaWhereSupport::stkmovCabecera($clave),
            'limit' => 'FIRST 1',
        ]);

        return ApiAnita::primeraFilaLista($raw) !== null;
    }

    /**
     * @param  array{tipo: string, letra: string, sucursal: int, nro: int}  $clave
     */
    private static function leerRecepmaePorClave(array $clave): ?object
    {
        $cfg = config('recepcion_proveedor.anita');
        $api = new ApiAnita;
        $raw = $api->apiCall([
            'acc' => 'list',
            'sistema' => (string) $cfg['sistema_compras'],
            'tabla' => (string) $cfg['tablas']['recepcion_cabecera'],
            'campos' => 'recm_proveedor, recm_tipo_fac, recm_letra_fac, recm_sucursal_fac, recm_nro_fac, recm_estado, recm_documentoid',
            'whereArmado' => RecepcionProveedorAnitaWhereSupport::recepmaePorClave($clave),
            'limit' => 'FIRST 1',
        ]);

        return ApiAnita::primeraFilaLista($raw);
    }

    private static function esReintentoMismaRecepcion(Recepcion_Proveedor $recepcion, object $cabecera): bool
    {
        return (int) ($cabecera->recm_documentoid ?? 0) === (int) $recepcion->id;
    }

    /**
     * @param  array{tipo: string, letra: string, sucursal: int, nro: int}  $clave
     */
    private static function mensajeColision(array $clave, string $detalle): string
    {
        return 'El número de recepción COM '.(int) $clave['nro']
            .' (sucursal '.(int) $clave['sucursal'].') '.$detalle
            .' No se puede confirmar para no sobrescribir movimientos existentes.'
            .' Revise la numeración o importe la recepción desde Anita.';
    }
}
