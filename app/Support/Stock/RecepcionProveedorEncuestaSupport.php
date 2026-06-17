<?php

namespace App\Support\Stock;

use App\Models\Stock\Recepcion_Proveedor;

class RecepcionProveedorEncuestaSupport
{
    /** Origen legacy: COM_{sucursal}-{nro} */
    public static function origenDesdeRecepcion(Recepcion_Proveedor $recepcion): string
    {
        $clave = RecepcionProveedorAnitaClaveSupport::resolver($recepcion);

        return 'COM_'.$clave['sucursal'].'-'.$clave['nro'];
    }

    /** Código proveedor sin ceros a la izquierda (URL legacy Anita). */
    public static function codigoProveedorUrl(?string $codigo): string
    {
        $codigo = trim((string) $codigo);
        if ($codigo === '') {
            return '0';
        }

        $sinCeros = ltrim($codigo, '0');

        return $sinCeros !== '' ? $sinCeros : '0';
    }

    /**
     * Hash MD5 hex (32 chars) compatible con genera_hash() de a-stock.c
     *
     * @param  array{tipo: string, letra: string, sucursal: int, nro: int}  $clave
     */
    public static function hashEncuesta(string $codigoProveedorAnita, array $clave): string
    {
        $buffer = sprintf(
            '%6.6s%3.3s%c%ld%ld',
            substr(str_pad($codigoProveedorAnita, 6, ' ', STR_PAD_RIGHT), 0, 6),
            substr($clave['tipo'], 0, 3),
            substr($clave['letra'], 0, 1),
            (int) $clave['sucursal'],
            (int) $clave['nro']
        );

        return md5($buffer);
    }

    public static function linkEncuestaProveedor(Recepcion_Proveedor $recepcion): ?string
    {
        if (! config('recepcion_proveedor.encuesta_habilitada', true)) {
            return null;
        }

        $encuestaId = (int) config('recepcion_proveedor.encuesta_id', 1);
        if ($encuestaId <= 0) {
            return null;
        }

        $recepcion->loadMissing(['proveedores', 'empresas']);
        $proveedor = $recepcion->proveedores;
        if (! $proveedor || trim((string) ($proveedor->codigo ?? '')) === '') {
            return null;
        }

        $clave = RecepcionProveedorAnitaClaveSupport::resolver($recepcion);
        $codigoProveedor = RecepcionProveedorAnitaWhereSupport::codigoProveedorAnita($recepcion);
        $hash = self::hashEncuesta($codigoProveedor, $clave);
        $origen = self::origenDesdeRecepcion($recepcion);

        return url('compras/genera_proveedor_encuesta/'.self::codigoProveedorUrl($proveedor->codigo).'/'.$encuestaId.'/'.$origen.'/'.$hash);
    }

    public static function etiquetaComAnita(Recepcion_Proveedor $recepcion): string
    {
        $clave = RecepcionProveedorAnitaClaveSupport::resolver($recepcion);

        return $clave['sucursal'].'-'.$clave['nro'];
    }
}
