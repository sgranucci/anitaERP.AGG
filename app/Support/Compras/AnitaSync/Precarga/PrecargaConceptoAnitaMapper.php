<?php

namespace App\Support\Compras\AnitaSync\Precarga;

/**
 * Mapeo ERP → tabla Informix precargaconc (precc_concepto = concepto_ivacompra.codigo / concc_concepto).
 */
final class PrecargaConceptoAnitaMapper
{
    public static function monto(mixed $valor): string
    {
        return number_format((float) $valor, 4, '.', '');
    }

    public static function valoresInsert(int $preccId, int $precargaId, int $codigoConceptoAnita, mixed $monto): string
    {
        return " 
				'".$preccId."', 
                '".$precargaId."', 
                '".$codigoConceptoAnita."', 
                '".self::monto($monto)."' ";
    }

    public static function valoresUpdate(int $precargaId, int $codigoConceptoAnita, mixed $monto): string
    {
        return " 
                        precc_precarga_id 	            = '".$precargaId."',
                        precc_concepto 	               	= '".$codigoConceptoAnita."',
                        precc_monto 	               	= '".self::monto($monto)."' ";
    }
}
