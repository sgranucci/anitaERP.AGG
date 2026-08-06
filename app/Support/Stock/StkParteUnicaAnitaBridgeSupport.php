<?php

namespace App\Support\Stock;

use App\ApiAnita;
use App\Models\Stock\Articulo;
use App\Models\Stock\Articulo_ParteUnica;
use Illuminate\Support\Facades\Log;

/**
 * Bridge Anita base_admin — tabla stk_parte_unica (stkpu_articulo, stkpu_id).
 */
class StkParteUnicaAnitaBridgeSupport
{
    public static function sistema(): string
    {
        return (string) config('recepcion_proveedor.anita.sistema_stk_parte_unica', 'base_admin');
    }

    public static function tabla(): string
    {
        return (string) config('recepcion_proveedor.anita.tablas.articulo_parte_unica', 'stk_parte_unica');
    }

    public static function maxNumeroparteAnita(): int
    {
        $api = new ApiAnita;
        $raw = $api->apiCall([
            'acc' => 'list',
            'sistema' => self::sistema(),
            'tabla' => self::tabla(),
            'campos' => 'max(stkpu_id) as max_id',
        ]);

        $fila = ApiAnita::primeraFilaLista($raw);

        return $fila ? (int) ($fila->max_id ?? $fila->MAX_ID ?? 0) : 0;
    }

    public static function maxNumeroparteGlobal(): int
    {
        $maxLocal = (int) Articulo_ParteUnica::query()->max('numeroparte');

        return max($maxLocal, self::maxNumeroparteAnita());
    }

    public static function existeEnAnita(int $numeroparte): bool
    {
        $api = new ApiAnita;
        $raw = $api->apiCall([
            'acc' => 'list',
            'sistema' => self::sistema(),
            'tabla' => self::tabla(),
            'campos' => 'stkpu_id',
            'whereArmado' => ' WHERE stkpu_id = '.(int) $numeroparte,
            'limit' => 'FIRST 1',
        ]);

        return ApiAnita::primeraFilaLista($raw) !== null;
    }

    /**
     * SKU (13) con el que el NPU está registrado en Anita; null si no existe.
     */
    public static function skuAnitaDeNumeroparte(int $numeroparte): ?string
    {
        if ($numeroparte <= 0) {
            return null;
        }

        $filas = self::listarDesdeAnita(' WHERE stkpu_id = '.$numeroparte, 'FIRST 1');
        $fila = $filas[0] ?? null;

        return $fila !== null ? trim((string) ($fila->stkpu_articulo ?? '')) : null;
    }

    /**
     * Anita guarda el SKU en 13 posiciones con relleno; comparar sin ceros ni espacios.
     */
    public static function mismoSku(?string $skuAnita, ?string $skuErp): bool
    {
        $clave = self::claveSku($skuAnita);

        return $clave !== '' && $clave === self::claveSku($skuErp);
    }

    public static function insertar(Articulo_ParteUnica $parte): bool
    {
        $parte->loadMissing('articulos');
        $numeroparte = (int) $parte->numeroparte;
        if ($numeroparte <= 0) {
            return false;
        }

        if (self::existeEnAnita($numeroparte)) {
            return true;
        }

        $api = new ApiAnita;
        $insert = RecepcionProveedorAnitaEscrituraSupport::stkParteUnicaInsert(
            self::skuAnita13($parte->articulos),
            $numeroparte,
        );
        $payload = [
            'acc' => 'insert',
            'sistema' => self::sistema(),
            'tabla' => self::tabla(),
            'campos' => $insert['campos'],
            'valores' => $insert['valores'],
        ];

        $raw = (string) $api->apiCallEscritura($payload);
        if (stripos($raw, 'error') !== false) {
            Log::warning('StkParteUnicaAnitaBridge: insert', ['numeroparte' => $numeroparte, 'respuesta' => $raw]);

            return false;
        }

        return true;
    }

    public static function actualizar(Articulo_ParteUnica $parte, int $numeroparteAnterior): bool
    {
        if ($numeroparteAnterior === (int) $parte->numeroparte) {
            return true;
        }

        self::eliminarPorClave($parte->articulos, $numeroparteAnterior);

        return self::insertar($parte);
    }

    public static function eliminar(Articulo_ParteUnica $parte): bool
    {
        $parte->loadMissing('articulos');

        return self::eliminarPorClave($parte->articulos, (int) $parte->numeroparte);
    }

    public static function eliminarPorClave(?Articulo $articulo, int $numeroparte): bool
    {
        if ($numeroparte <= 0) {
            return false;
        }

        $api = new ApiAnita;
        $sku = self::skuAnita13($articulo);
        $where = " WHERE stkpu_articulo = '".addslashes($sku)."' AND stkpu_id = ".(int) $numeroparte;

        $raw = (string) $api->apiCallEscritura([
            'acc' => 'delete',
            'sistema' => self::sistema(),
            'tabla' => self::tabla(),
            'whereArmado' => $where,
        ]);

        if (stripos($raw, 'error') !== false) {
            Log::warning('StkParteUnicaAnitaBridge: delete', ['numeroparte' => $numeroparte, 'respuesta' => $raw]);

            return false;
        }

        return true;
    }

    /**
     * @return list<object>
     */
    public static function listarDesdeAnita(?string $whereArmado = null, ?string $limit = null): array
    {
        $api = new ApiAnita;
        $data = [
            'acc' => 'list',
            'sistema' => self::sistema(),
            'tabla' => self::tabla(),
            'campos' => 'stkpu_id,stkpu_articulo',
            'orderBy' => 'stkpu_id',
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
     * Importación por rangos de stkpu_id (tabla grande en base_admin).
     *
     * @param  callable(list<object>, int, int): void  $procesarLote
     */
    public static function importarEnLotes(callable $procesarLote, int $tamanoLote = 2000): int
    {
        $maxId = self::maxNumeroparteAnita();
        $totalFilas = 0;

        for ($desde = 0; $desde <= $maxId; $desde += $tamanoLote) {
            $hasta = $desde + $tamanoLote;
            $filas = self::listarDesdeAnita(" WHERE stkpu_id >= {$desde} AND stkpu_id < {$hasta}");
            if ($filas === []) {
                continue;
            }
            $totalFilas += count($filas);
            $procesarLote($filas, $desde, $hasta);
        }

        return $totalFilas;
    }

    public static function skuAnita13(?Articulo $articulo): string
    {
        return str_pad(substr((string) ($articulo->sku ?? ''), 0, 13), 13, ' ', STR_PAD_RIGHT);
    }

    private static function claveSku(?string $sku): string
    {
        return ltrim(strtoupper(trim((string) $sku)), '0');
    }
}
