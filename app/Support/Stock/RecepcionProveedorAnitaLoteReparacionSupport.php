<?php

namespace App\Support\Stock;

use App\ApiAnita;
use Illuminate\Support\Facades\DB;

/**
 * Completa recv_nro_interno nulo en recepmov (etiquetas Zebra).
 * No inventa lote ni vencimiento: si estaban vacíos quedan en blanco / 0.
 * El número sale del mismo numerador que Anita (a-stock.c referencia 2).
 */
final class RecepcionProveedorAnitaLoteReparacionSupport
{
    /**
     * @return list<object>
     */
    public static function listarSinNroInterno(): array
    {
        $api = new ApiAnita;
        $raw = $api->apiCall([
            'acc' => 'list',
            'sistema' => (string) config('recepcion_proveedor.anita.sistema_compras', 'compras'),
            'tabla' => 'recepmov',
            'campos' => 'recv_proveedor, recv_tipo, recv_letra, recv_sucursal, recv_nro, recv_orden, recv_articulo, recv_fecha, recv_fecha_vto, recv_nro_interno, recv_lote_proveed',
            'whereArmado' => "WHERE recv_tipo = 'COM' AND recv_nro_interno IS NULL",
            'orderBy' => 'recv_fecha, recv_sucursal, recv_nro, recv_orden',
            'curl_timeout' => 180,
        ]);

        $parsed = ApiAnita::parsearRespuestaLista($raw);
        if ($parsed['error_lectura'] !== null) {
            throw new \RuntimeException('No se pudo listar recepmov sin número de lote: '.$parsed['error_lectura']);
        }

        return $parsed['filas'];
    }

    /**
     * @return array{
     *   cantidad: int,
     *   desde: int,
     *   hasta: int,
     *   aplicadas: int,
     *   vinculadas_erp: int,
     *   sin_linea_erp: int,
     *   muestra: list<string>
     * }
     */
    public static function reparar(bool $aplicar): array
    {
        $filas = self::listarSinNroInterno();
        $cantidad = count($filas);
        $base = RecepcionProveedorAnitaLoteNumeracionSupport::baseActual();
        $desde = $cantidad > 0 ? $base + 1 : 0;
        $hasta = $cantidad > 0 ? $base + $cantidad : 0;

        $muestra = [];
        foreach (array_slice($filas, 0, 8) as $fila) {
            $muestra[] = self::etiqueta($fila);
        }

        if (! $aplicar || $cantidad === 0) {
            return [
                'cantidad' => $cantidad,
                'desde' => $desde,
                'hasta' => $hasta,
                'aplicadas' => 0,
                'vinculadas_erp' => 0,
                'sin_linea_erp' => 0,
                'muestra' => $muestra,
            ];
        }

        $primero = RecepcionProveedorAnitaLoteNumeracionSupport::reservarBloque($cantidad);
        $indice = self::indiceLineasErp();
        $nro = $primero;
        $aplicadas = 0;
        $vinculadas = 0;
        $sinLinea = 0;

        foreach ($filas as $fila) {
            self::actualizarRecepmov($fila, $nro);
            $lineaId = self::lineaErp($indice, $fila);
            if ($lineaId !== null) {
                DB::table('recepcion_proveedor_articulo')
                    ->where('id', $lineaId)
                    ->whereNull('anita_nro_lote')
                    ->update([
                        'anita_nro_lote' => $nro,
                        'updated_at' => now(),
                    ]);
                $vinculadas++;
            } else {
                $sinLinea++;
            }
            $nro++;
            $aplicadas++;
        }

        return [
            'cantidad' => $cantidad,
            'desde' => $primero,
            'hasta' => $primero + $cantidad - 1,
            'aplicadas' => $aplicadas,
            'vinculadas_erp' => $vinculadas,
            'sin_linea_erp' => $sinLinea,
            'muestra' => $muestra,
        ];
    }

    private static function etiqueta(object $fila): string
    {
        return trim((string) ($fila->recv_tipo ?? '')).trim((string) ($fila->recv_letra ?? ''))
            .' '.trim((string) ($fila->recv_sucursal ?? '')).'-'.trim((string) ($fila->recv_nro ?? ''))
            .' ord '.trim((string) ($fila->recv_orden ?? ''))
            .' '.trim((string) ($fila->recv_articulo ?? ''))
            .' lote['.trim((string) ($fila->recv_lote_proveed ?? '')).']'
            .' vto['.trim((string) ($fila->recv_fecha_vto ?? '')).']';
    }

    private static function actualizarRecepmov(object $fila, int $nroInterno): void
    {
        $lote = trim((string) ($fila->recv_lote_proveed ?? ''));
        $fecha = trim((string) ($fila->recv_fecha_vto ?? ''));
        $valores = 'recv_nro_interno = '.(int) $nroInterno;
        if ($lote === '') {
            $valores .= ", recv_lote_proveed = ' '";
        }
        if ($fecha === '' || (int) $fecha === 0) {
            $valores .= ', recv_fecha_vto = 0';
        }

        $api = new ApiAnita;
        $raw = $api->apiCallEscritura([
            'acc' => 'update',
            'sistema' => (string) config('recepcion_proveedor.anita.sistema_compras', 'compras'),
            'tabla' => 'recepmov',
            'valores' => $valores,
            'whereArmado' => self::whereLinea($fila).' AND recv_nro_interno IS NULL',
        ], 'recepmov lote nro interno '.$nroInterno);

        $err = ApiAnita::extraerMensajeError($raw);
        if ($err !== null) {
            throw new \RuntimeException(
                'No se pudo corregir '.self::etiqueta($fila).': '.$err
            );
        }
    }

    private static function whereLinea(object $fila): string
    {
        $tipo = self::literal((string) ($fila->recv_tipo ?? ''));
        $letra = self::literal((string) ($fila->recv_letra ?? ''));
        $proveedor = self::literal((string) ($fila->recv_proveedor ?? ''));
        $articulo = self::literal((string) ($fila->recv_articulo ?? ''));

        return " WHERE recv_tipo = '".$tipo."'"
            ." AND recv_letra = '".$letra."'"
            .' AND recv_sucursal = '.(int) ($fila->recv_sucursal ?? 0)
            .' AND recv_nro = '.(int) ($fila->recv_nro ?? 0)
            .' AND recv_orden = '.(int) ($fila->recv_orden ?? 0)
            ." AND recv_proveedor = '".$proveedor."'"
            ." AND recv_articulo = '".$articulo."'";
    }

    /**
     * @return array<string, int>
     */
    private static function indiceLineasErp(): array
    {
        $filas = DB::table('recepcion_proveedor_articulo as rpa')
            ->join('recepcion_proveedor as rp', 'rp.id', '=', 'rpa.recepcion_proveedor_id')
            ->join('articulo as a', 'a.id', '=', 'rpa.articulo_id')
            ->whereNotNull('rp.anita_nro')
            ->where('rp.anita_nro', '>', 0)
            ->get([
                'rpa.id',
                'rp.anita_tipo',
                'rp.anita_letra',
                'rp.anita_sucursal',
                'rp.anita_nro',
                'rpa.orden',
                'a.sku',
            ]);

        $indice = [];
        foreach ($filas as $fila) {
            $clave = self::claveErp(
                (string) ($fila->anita_tipo ?? ''),
                (string) ($fila->anita_letra ?? ''),
                (int) ($fila->anita_sucursal ?? 0),
                (int) ($fila->anita_nro ?? 0),
                (int) ($fila->orden ?? 0),
                (string) ($fila->sku ?? '')
            );
            if (isset($indice[$clave])) {
                $indice[$clave] = 0;
                continue;
            }
            $indice[$clave] = (int) $fila->id;
        }

        return $indice;
    }

    private static function lineaErp(array $indice, object $fila): ?int
    {
        $clave = self::claveErp(
            (string) ($fila->recv_tipo ?? ''),
            (string) ($fila->recv_letra ?? ''),
            (int) ($fila->recv_sucursal ?? 0),
            (int) ($fila->recv_nro ?? 0),
            (int) ($fila->recv_orden ?? 0),
            (string) ($fila->recv_articulo ?? '')
        );
        $id = $indice[$clave] ?? null;
        if ($id === null || $id <= 0) {
            return null;
        }

        return $id;
    }

    private static function claveErp(
        string $tipo,
        string $letra,
        int $sucursal,
        int $nro,
        int $orden,
        string $sku
    ): string {
        return strtoupper(trim($tipo)).'|'
            .strtoupper(trim($letra)).'|'
            .$sucursal.'|'
            .$nro.'|'
            .$orden.'|'
            .RecepcionProveedorAnitaEscrituraSupport::skuAnita13($sku);
    }

    private static function literal(string $valor): string
    {
        return str_replace("'", "''", trim($valor));
    }
}
