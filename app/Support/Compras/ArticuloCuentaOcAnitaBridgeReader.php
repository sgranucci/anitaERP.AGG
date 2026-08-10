<?php

namespace App\Support\Compras;

use App\ApiAnita;
use Illuminate\Support\Facades\Log;

/**
 * Lectura de líneas de OC Anita (pendmovp + stkmae) para el informe de cuentas vs artículos.
 */
final class ArticuloCuentaOcAnitaBridgeReader
{
    public function __construct(
        private ApiAnita $api,
    ) {}

    /**
     * @return list<object>
     */
    public function listarLineasOc(
        int $empresaAnita,
        int $fechaDesdeYmd,
        int $fechaHastaYmd,
    ): array {
        if ($empresaAnita <= 0 || $fechaDesdeYmd <= 0) {
            return [];
        }

        $where = ' WHERE penvp_empresa='.$empresaAnita
            .' AND penvp_fecha>='.$fechaDesdeYmd;

        if ($fechaHastaYmd > 0) {
            $where .= ' AND penvp_fecha<='.$fechaHastaYmd;
        }

        $where .= ' AND penvp_articulo=stkm_articulo';

        // penvp_desc al final: si Informix escapa mal el pipe, solo se trunca la descripción.
        // stkm_cta_contablec = cuenta de compra del maestro Anita.
        $campos = 'penvp_proveedor,penvp_tipo,penvp_letra,penvp_sucursal,penvp_nro,'
            .'penvp_fecha,penvp_articulo,penvp_empresa,penvp_cantidad,'
            .'stkm_cta_contablec,stkm_desc,penvp_desc';

        try {
            $raw = (string) $this->api->apiCall([
                'acc' => 'list',
                'sistema' => 'compras',
                'tabla' => 'pendmovp,stkmae',
                'campos' => $campos,
                'whereArmado' => $where,
            ]);
            $parsed = ApiAnita::parsearRespuestaLista($raw);
            if ($parsed['error_lectura'] !== null) {
                Log::warning('ArticuloCuentaOcAnitaBridgeReader: '.$parsed['error_lectura'], [
                    'empresa_anita' => $empresaAnita,
                    'desde' => $fechaDesdeYmd,
                    'hasta' => $fechaHastaYmd,
                ]);

                return [];
            }

            /** @var list<object> $filas */
            $filas = $parsed['filas'];

            return $filas;
        } catch (\Throwable $e) {
            Log::warning('ArticuloCuentaOcAnitaBridgeReader: '.$e->getMessage(), [
                'empresa_anita' => $empresaAnita,
            ]);

            return [];
        }
    }
}
