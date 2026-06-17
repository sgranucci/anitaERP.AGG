<?php

namespace App\Support\Stock;

use App\ApiAnita;
use App\Models\Stock\Articulo_ParteUnica;
use App\Models\Stock\Recepcion_Proveedor_ParteUnica;
use App\Support\Stock\RecepcionProveedorAnitaWhereSupport;
use App\Support\Stock\RecepcionProveedorParteUnicaSupport;
use Illuminate\Support\Facades\Log;

/**
 * Bridge Anita compras — tabla recpunica (por recepción/línea).
 */
class RecpunicaAnitaBridgeSupport
{
    public static function insertarDesdeParte(Recepcion_Proveedor_ParteUnica $parte): bool
    {
        $parte->loadMissing([
            'recepcion_proveedores.empresas',
            'recepcion_proveedor_articulos.articulos',
        ]);

        $recepcion = $parte->recepcion_proveedores;
        $linea = $parte->recepcion_proveedor_articulos;
        if (! $recepcion || ! $linea) {
            return false;
        }

        $numeroparte = (int) $parte->numeroparte;
        if ($numeroparte <= 0) {
            return false;
        }

        if (self::existeEnAnita($numeroparte)) {
            return true;
        }

        $clave = RecepcionProveedorAnitaClaveSupport::resolver($recepcion);
        $lineaAnita = (int) ($linea->orden ?? 0);
        if ($lineaAnita <= 0) {
            $lineaAnita = (int) ($linea->penvp_orden ?? 1);
        }
        $skuAnita = RecepcionProveedorParteUnicaSupport::skuAnita13($linea->articulos);

        $api = new ApiAnita;
        $insert = RecepcionProveedorAnitaEscrituraSupport::recpunicaInsert(
            $clave,
            $lineaAnita,
            $skuAnita,
            $numeroparte,
        );
        $payload = [
            'acc' => 'insert',
            'sistema' => config('recepcion_proveedor.anita.sistema_compras'),
            'tabla' => config('recepcion_proveedor.anita.tablas.recepcion_parte_unica', 'recpunica'),
            'campos' => $insert['campos'],
            'valores' => $insert['valores'],
        ];

        $raw = (string) $api->apiCallEscritura($payload);
        if (stripos($raw, 'error') !== false) {
            Log::warning('RecpunicaAnitaBridge: insert', ['numeroparte' => $numeroparte, 'respuesta' => $raw]);

            return false;
        }

        return true;
    }

    public static function existeEnAnita(int $numeroparte): bool
    {
        $api = new ApiAnita;
        $raw = $api->apiCall([
            'acc' => 'list',
            'tabla' => config('recepcion_proveedor.anita.tablas.recepcion_parte_unica', 'recpunica'),
            'campos' => 'recpu_id',
            'whereArmado' => ' WHERE recpu_id = '.(int) $numeroparte,
            'limit' => ' FIRST 1 ',
        ]);

        return ApiAnita::primeraFilaLista($raw) !== null;
    }

    /**
     * @return list<object>
     */
    public static function listarDesdeAnita(?string $whereArmado = null, ?string $limit = null): array
    {
        $api = new ApiAnita;
        $data = [
            'acc' => 'list',
            'sistema' => config('recepcion_proveedor.anita.sistema_compras'),
            'tabla' => config('recepcion_proveedor.anita.tablas.recepcion_parte_unica', 'recpunica'),
            'campos' => 'recpu_tipo, recpu_letra, recpu_sucursal, recpu_nro, recpu_linea, recpu_articulo, recpu_id',
            'orderBy' => 'recpu_sucursal, recpu_nro, recpu_linea',
        ];
        if ($whereArmado) {
            $data['whereArmado'] = $whereArmado;
        }
        if ($limit) {
            $data['limit'] = $limit;
        }

        return ApiAnita::decodificarListaFilas($api->apiCall($data));
    }

    /**
     * @param  array{tipo: string, letra: string, sucursal: int, nro: int}  $clave
     */
    public static function eliminarDesdeParte(Recepcion_Proveedor_ParteUnica $parte, array $clave): bool
    {
        $parte->loadMissing('recepcion_proveedor_articulos.articulos');
        $linea = $parte->recepcion_proveedor_articulos;
        $numeroparte = (int) $parte->numeroparte;
        if ($numeroparte <= 0 || ! $linea) {
            return false;
        }

        $lineaAnita = (int) ($linea->orden ?? 0);
        if ($lineaAnita <= 0) {
            $lineaAnita = (int) ($linea->penvp_orden ?? 1);
        }
        $skuAnita = RecepcionProveedorParteUnicaSupport::skuAnita13($linea->articulos);

        $where = RecepcionProveedorAnitaWhereSupport::recpunicaCabecera($clave)
            .' AND recpu_linea = '.$lineaAnita
            ." AND recpu_articulo = '".addslashes($skuAnita)."'"
            .' AND recpu_id = '.$numeroparte;

        $api = new ApiAnita;
        $raw = (string) $api->apiCallEscritura([
            'acc' => 'delete',
            'sistema' => config('recepcion_proveedor.anita.sistema_compras'),
            'tabla' => config('recepcion_proveedor.anita.tablas.recepcion_parte_unica', 'recpunica'),
            'whereArmado' => $where,
        ]);

        if (stripos($raw, 'error') !== false) {
            Log::warning('RecpunicaAnitaBridge: delete', ['numeroparte' => $numeroparte, 'respuesta' => $raw]);

            return false;
        }

        return true;
    }
}
