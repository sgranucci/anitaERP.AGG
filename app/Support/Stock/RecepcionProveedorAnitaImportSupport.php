<?php

namespace App\Support\Stock;

use App\ApiAnita;
use Illuminate\Support\Facades\DB;

class RecepcionProveedorAnitaImportSupport
{
    /** @var array<string, int> */
    private static array $monedaIdPorCodigoAnita = [];

    private static bool $monedaIdPorCodigoAnitaCargado = false;
    public static function sistemaCompras(): string
    {
        return (string) config('recepcion_proveedor.anita.sistema_compras', 'compras');
    }

    public static function fechaAnitaDesde(string $fechaIso): int
    {
        return (int) str_replace('-', '', $fechaIso);
    }

    public static function fechaDesdeAnita(int $fechaAnita): string
    {
        $s = str_pad((string) $fechaAnita, 8, '0', STR_PAD_LEFT);

        return substr($s, 0, 4).'-'.substr($s, 4, 2).'-'.substr($s, 6, 2);
    }

    /**
     * @return list<object>
     */
    public static function listarRecepmae(?int $fechaDesde = null, ?int $fechaHasta = null, ?int $sucursal = null, ?int $first = null): array
    {
        $where = " WHERE recm_tipo = 'COM' AND recm_letra = 'X'";
        if ($fechaDesde !== null) {
            $where .= ' AND recm_fecha >= '.(int) $fechaDesde;
        }
        if ($fechaHasta !== null) {
            $where .= ' AND recm_fecha <= '.(int) $fechaHasta;
        }
        if ($sucursal !== null) {
            $where .= ' AND recm_sucursal = '.(int) $sucursal;
        }

        $api = new ApiAnita;
        $data = [
            'acc' => 'list',
            'sistema' => self::sistemaCompras(),
            'tabla' => config('recepcion_proveedor.anita.tablas.recepcion_cabecera', 'recepmae'),
            'campos' => 'recm_proveedor,recm_tipo,recm_letra,recm_sucursal,recm_nro,recm_fecha,recm_estado,recm_observacion,recm_empresa,recm_com_tipo,recm_com_letra,recm_com_sucursal,recm_com_nro,recm_tipo_fac,recm_letra_fac,recm_sucursal_fac,recm_nro_fac',
            'orderBy' => 'recm_fecha, recm_sucursal, recm_nro',
            'whereArmado' => $where,
        ];
        if ($first !== null && $first > 0) {
            $data['limit'] = 'FIRST '.(int) $first;
        }

        return ApiAnita::decodificarListaFilas($api->apiCall($data));
    }

    /**
     * @return list<object>
     */
    public static function listarRecepmov(string $tipo, string $letra, int $sucursal, int $nro): array
    {
        $api = new ApiAnita;
        $where = " WHERE recv_tipo = '".addslashes($tipo)."'"
            ." AND recv_letra = '".addslashes($letra)."'"
            .' AND recv_sucursal = '.(int) $sucursal
            .' AND recv_nro = '.(int) $nro;

        return ApiAnita::decodificarListaFilas($api->apiCall([
            'acc' => 'list',
            'sistema' => self::sistemaCompras(),
            'tabla' => config('recepcion_proveedor.anita.tablas.recepcion_linea', 'recepmov'),
            'campos' => 'recv_orden,recv_articulo,recv_cantidad,recv_precio,recv_dto_art,recv_deposito,recv_fecha,recv_cod_mon,recv_ccosto,recv_empresa,recv_cotizacion',
            'orderBy' => 'recv_orden',
            'whereArmado' => $where,
        ]));
    }

    public static function listarRecepmaePorClave(string $tipo, string $letra, int $sucursal, int $nro): ?object
    {
        $where = " WHERE recm_tipo = '".addslashes($tipo)."'"
            ." AND recm_letra = '".addslashes($letra)."'"
            .' AND recm_sucursal = '.(int) $sucursal
            .' AND recm_nro = '.(int) $nro;

        $api = new ApiAnita;

        return ApiAnita::primeraFilaLista($api->apiCall([
            'acc' => 'list',
            'sistema' => self::sistemaCompras(),
            'tabla' => config('recepcion_proveedor.anita.tablas.recepcion_cabecera', 'recepmae'),
            'campos' => 'recm_proveedor,recm_tipo,recm_letra,recm_sucursal,recm_nro,recm_fecha,recm_estado,recm_observacion,recm_com_tipo,recm_com_letra,recm_com_sucursal,recm_com_nro,recm_tipo_fac,recm_letra_fac,recm_sucursal_fac,recm_nro_fac',
            'whereArmado' => $where,
            'limit' => 'FIRST 1',
        ]));
    }

    /**
     * Número OC Anita desde recepmae: recm_com_nro o recm_nro_fac (PEP/FIB en recm_tipo_fac).
     */
    public static function numeroOrdencompraDesdeCabecera(object $cabecera): int
    {
        $nroCom = (int) ($cabecera->recm_com_nro ?? 0);
        if ($nroCom > 0) {
            return $nroCom;
        }

        $tipoFac = strtoupper(trim((string) ($cabecera->recm_tipo_fac ?? '')));
        if (in_array($tipoFac, ['PEP', 'OC', 'ORD'], true)) {
            return (int) ($cabecera->recm_nro_fac ?? 0);
        }

        return 0;
    }

    /**
     * @return list<object>
     */
    public static function listarRecpunicaPorRecepcion(string $tipo, string $letra, int $sucursal, int $nro): array
    {
        $api = new ApiAnita;
        $where = " WHERE recpu_tipo = '".addslashes($tipo)."'"
            ." AND recpu_letra = '".addslashes($letra)."'"
            .' AND recpu_sucursal = '.(int) $sucursal
            .' AND recpu_nro = '.(int) $nro;

        return ApiAnita::decodificarListaFilas($api->apiCall([
            'acc' => 'list',
            'sistema' => self::sistemaCompras(),
            'tabla' => config('recepcion_proveedor.anita.tablas.recepcion_parte_unica', 'recpunica'),
            'campos' => 'recpu_tipo,recpu_letra,recpu_sucursal,recpu_nro,recpu_linea,recpu_articulo,recpu_id',
            'orderBy' => 'recpu_id',
            'whereArmado' => $where,
        ]));
    }

    public static function contarRecepmae(int $fechaDesde, ?int $fechaHasta = null): int
    {
        $where = " WHERE recm_tipo = 'COM' AND recm_letra = 'X' AND recm_fecha >= ".(int) $fechaDesde;
        if ($fechaHasta !== null) {
            $where .= ' AND recm_fecha <= '.(int) $fechaHasta;
        }

        $api = new ApiAnita;
        $fila = ApiAnita::primeraFilaLista($api->apiCall([
            'acc' => 'list',
            'sistema' => self::sistemaCompras(),
            'tabla' => config('recepcion_proveedor.anita.tablas.recepcion_cabecera', 'recepmae'),
            'campos' => 'count(*) as total',
            'whereArmado' => $where,
        ]));

        return $fila ? (int) ($fila->total ?? 0) : 0;
    }

    /**
     * @return list<object>
     */
    public static function listarRecepmovPorRangoFecha(int $fechaDesde, int $fechaHasta): array
    {
        $api = new ApiAnita;
        $where = " WHERE recv_tipo = 'COM' AND recv_letra = 'X'"
            .' AND recv_fecha >= '.(int) $fechaDesde
            .' AND recv_fecha <= '.(int) $fechaHasta;

        return ApiAnita::decodificarListaFilas($api->apiCall([
            'acc' => 'list',
            'sistema' => self::sistemaCompras(),
            'tabla' => config('recepcion_proveedor.anita.tablas.recepcion_linea', 'recepmov'),
            'campos' => 'recv_tipo,recv_letra,recv_sucursal,recv_nro,recv_orden,recv_articulo,recv_cantidad,recv_precio,recv_dto_art,recv_deposito,recv_fecha,recv_cod_mon,recv_ccosto,recv_empresa,recv_cotizacion',
            'orderBy' => 'recv_sucursal, recv_nro, recv_orden',
            'whereArmado' => $where,
        ]));
    }

    /**
     * @param  list<object>  $lineas
     * @return array<string, list<object>>
     */
    public static function agruparRecepmovPorRecepcion(array $lineas): array
    {
        $grupos = [];
        foreach ($lineas as $lin) {
            $suc = (int) ($lin->recv_sucursal ?? 0);
            $nro = (int) ($lin->recv_nro ?? 0);
            if ($nro <= 0 || $suc <= 0) {
                continue;
            }
            $key = $suc.'-'.$nro;
            $grupos[$key][] = $lin;
        }

        return $grupos;
    }

    public static function monedaIdDesdeCodigoAnita(mixed $codMon): int
    {
        self::cargarMapaMonedaIdPorCodigoAnita();

        $cod = trim((string) $codMon);
        if ($cod === '') {
            return 1;
        }

        return self::$monedaIdPorCodigoAnita[$cod] ?? 1;
    }

    private static function cargarMapaMonedaIdPorCodigoAnita(): void
    {
        if (self::$monedaIdPorCodigoAnitaCargado) {
            return;
        }

        self::$monedaIdPorCodigoAnitaCargado = true;
        foreach (DB::table('moneda')->get(['id', 'codigo']) as $moneda) {
            $codigo = trim((string) $moneda->codigo);
            if ($codigo === '') {
                continue;
            }
            self::$monedaIdPorCodigoAnita[$codigo] = (int) $moneda->id;
        }
    }
}
