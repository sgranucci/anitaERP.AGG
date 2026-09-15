<?php

namespace App\Services\Ventas\FacturacionLocal;

use App\ApiAnita;
use App\Models\Stock\Articulo;
use App\Models\Stock\Combinacion;
use App\Models\Stock\Listaprecio;
use App\Models\Stock\Numeracion;
use App\Models\Stock\Precio;
use App\Models\Ventas\LocalVenta;
use App\Services\Stock\PrecioServiceFerli;
use App\Support\Ventas\FacturacionLocal\ArticuloCanalSupport;
use App\Support\Ventas\FacturacionLocal\FacturacionLocalVarianteArticuloSupport;
use App\Support\Ventas\FacturacionLocal\PrecioListaLocalMapeoSupport;
use App\Support\Ventas\FacturacionLocal\StockLocalInformeListadoFiltros;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Consulta stock/precios de locales (puerto de c-stocklocal.c / c-articulo.c).
 *
 * Origen (igual que el informe stock locales):
 * - anita (default mientras se prueba el bridge): stkdep / stkvmed vía Anita Local
 * - erp: articulo_movimiento del depósito ERP del local (sin Anita)
 */
final class StockLocalConsultaService
{
    private const LONGITUD_SKU_ANITA = 13;

    /** @var array<string, int>|null */
    private ?array $mapaSignoTcomp = null;

    public function __construct(
        private readonly ApiAnita $apiAnita,
    ) {
    }

    /**
     * @return array{
     *   ok:bool,
     *   error?:string,
     *   articulo?:array{id:int,sku:string,descripcion:string,sku_anita:string},
     *   precio?:array{valor:float,listaprecio_id:int|null,lista:string|null,origen:string},
     *   medidas?:list<int|string>,
     *   filas?:list<array{deposito:int|string,color:int|string,color_desc:string,cantidades:array<string,float>,total:float}>,
     *   saldo_total?:float,
     *   origen_stock?:string,
     *   origen?:string
     * }
     */
    public function consultarStockLocal(LocalVenta $local, string $busqueda, string $origen = StockLocalInformeListadoFiltros::ORIGEN_ANITA): array
    {
        $origen = $this->normalizarOrigen($origen);
        $articulo = $this->resolverArticulo($busqueda);
        if ($articulo === null) {
            return ['ok' => false, 'error' => 'Artículo inexistente o sin canal LOCAL.'];
        }
        if ((bool) ($articulo->nofactura ?? false)) {
            return ['ok' => false, 'error' => 'Artículo inactivo (no factura).'];
        }

        $skuAnita = $this->codigoAnitaDesdeSku((string) $articulo->sku);
        $precio = $this->resolverPrecio($local, $articulo);
        $medidas = $this->medidasDesdeArticulo($articulo);

        if ($origen === StockLocalInformeListadoFiltros::ORIGEN_ERP) {
            $agg = $this->agregarDesdeErp($local, $articulo, $medidas, matriz: true);
            if (($agg['error'] ?? null) !== null) {
                return [
                    'ok' => false,
                    'error' => $agg['error'],
                    'articulo' => $this->payloadArticulo($articulo, $skuAnita),
                    'precio' => $precio,
                    'origen' => $origen,
                ];
            }
            $medidasFinales = $agg['medidas'] !== [] ? $agg['medidas'] : $medidas;
            $filas = $this->enriquecerColorDesc($articulo, $agg['filas']);

            return [
                'ok' => true,
                'articulo' => $this->payloadArticulo($articulo, $skuAnita),
                'precio' => $precio,
                'medidas' => $medidasFinales,
                'filas' => $filas,
                'saldo_total' => $agg['saldo_total'],
                'origen_stock' => 'erp_articulo_movimiento',
                'origen' => $origen,
            ];
        }

        $agg = $this->agregarDesdeStkvmed($local, $skuAnita, $medidas);
        if (($agg['error'] ?? null) !== null && ($agg['filas'] ?? []) === []) {
            $aggDep = $this->agregarDesdeStkdep($local, $skuAnita, $medidas, matriz: true);
            if (($aggDep['error'] ?? null) !== null && ($aggDep['filas'] ?? []) === []) {
                return [
                    'ok' => false,
                    'error' => $agg['error'] ?? $aggDep['error'] ?? 'No se pudo consultar stock en Anita Local.',
                    'articulo' => $this->payloadArticulo($articulo, $skuAnita),
                    'precio' => $precio,
                    'origen' => $origen,
                ];
            }
            $agg = $aggDep;
            $agg['origen_stock'] = 'stkdep';
        } else {
            $agg['origen_stock'] = 'stkvmed';
        }

        $medidasFinales = $agg['medidas'] !== [] ? $agg['medidas'] : $medidas;
        $filas = $this->enriquecerColorDesc($articulo, $agg['filas']);

        return [
            'ok' => true,
            'articulo' => $this->payloadArticulo($articulo, $skuAnita),
            'precio' => $precio,
            'medidas' => $medidasFinales,
            'filas' => $filas,
            'saldo_total' => $agg['saldo_total'],
            'origen_stock' => $agg['origen_stock'],
            'origen' => $origen,
        ];
    }

    /**
     * @return array{
     *   ok:bool,
     *   error?:string,
     *   articulo?:array{id:int,sku:string,descripcion:string,sku_anita:string},
     *   precio?:array{valor:float,listaprecio_id:int|null,lista:string|null,origen:string},
     *   precios_listas?:list<array{listaprecio_id:int,lista:string,precio:float,fechavigencia:string|null}>,
     *   filas?:list<array{deposito:int|string,color:int|string,color_desc:string,medida:int|string,cantidad:float}>,
     *   saldo_total?:float,
     *   origen_stock?:string,
     *   origen?:string
     * }
     */
    public function consultarPreciosYStock(LocalVenta $local, string $busqueda, string $origen = StockLocalInformeListadoFiltros::ORIGEN_ANITA): array
    {
        $origen = $this->normalizarOrigen($origen);
        $articulo = $this->resolverArticulo($busqueda);
        if ($articulo === null) {
            return ['ok' => false, 'error' => 'Artículo inexistente o sin canal LOCAL.'];
        }
        if ((bool) ($articulo->nofactura ?? false)) {
            return ['ok' => false, 'error' => 'Artículo inactivo (no factura).'];
        }

        $skuAnita = $this->codigoAnitaDesdeSku((string) $articulo->sku);
        $precio = $this->resolverPrecio($local, $articulo);
        $preciosListas = $this->preciosPorListasErp($articulo);
        $modo = FacturacionLocalVarianteArticuloSupport::modo($articulo);
        $combinaciones = FacturacionLocalVarianteArticuloSupport::queryCombinacionesActivas((int) $articulo->id)
            ->get(['id', 'codigo', 'nombre', 'estado'])
            ->map(static fn ($c) => [
                'id' => (int) $c->id,
                'codigo' => (string) $c->codigo,
                'nombre' => (string) $c->nombre,
                'estado' => (string) $c->estado,
            ])
            ->values()
            ->all();

        if ($origen === StockLocalInformeListadoFiltros::ORIGEN_ERP) {
            $agg = $this->agregarDesdeErp($local, $articulo, [], matriz: false);
            if (($agg['error'] ?? null) !== null) {
                return [
                    'ok' => false,
                    'error' => $agg['error'],
                    'articulo' => $this->payloadArticulo($articulo, $skuAnita),
                    'precio' => $precio,
                    'precios_listas' => $preciosListas,
                    'modo_variante' => $modo,
                    'combinaciones' => $combinaciones,
                    'origen' => $origen,
                ];
            }
            $filas = [];
            foreach ($agg['filas'] as $fila) {
                $filas[] = [
                    'deposito' => $fila['deposito'] ?? '',
                    'color' => $fila['color'] ?? 0,
                    'color_desc' => (string) ($fila['color_desc'] ?? ''),
                    'medida' => $fila['medida'] ?? '',
                    'cantidad' => (float) ($fila['cantidad'] ?? 0),
                ];
            }

            return [
                'ok' => true,
                'articulo' => $this->payloadArticulo($articulo, $skuAnita),
                'precio' => $precio,
                'precios_listas' => $preciosListas,
                'filas' => $filas,
                'saldo_total' => (float) ($agg['saldo_total'] ?? 0),
                'origen_stock' => 'erp_articulo_movimiento',
                'origen' => $origen,
                'modo_variante' => $modo,
                'combinaciones' => $combinaciones,
            ];
        }

        $agg = $this->agregarDesdeStkdep($local, $skuAnita, [], matriz: false);
        if (($agg['error'] ?? null) !== null && ($agg['filas'] ?? []) === []) {
            $aggMed = $this->agregarDesdeStkvmed($local, $skuAnita, []);
            if (($aggMed['error'] ?? null) !== null && ($aggMed['filas'] ?? []) === []) {
                return [
                    'ok' => false,
                    'error' => $agg['error'] ?? $aggMed['error'] ?? 'No se pudo consultar stock en Anita Local.',
                    'articulo' => $this->payloadArticulo($articulo, $skuAnita),
                    'precio' => $precio,
                    'precios_listas' => $preciosListas,
                    'modo_variante' => $modo,
                    'combinaciones' => $combinaciones,
                    'origen' => $origen,
                ];
            }
            $filasPlanas = $this->matrizAFilasPlanas($aggMed['filas'], $aggMed['medidas']);
            $agg = [
                'filas' => $filasPlanas,
                'saldo_total' => $aggMed['saldo_total'],
                'origen_stock' => 'stkvmed',
                'error' => null,
            ];
        } else {
            $agg['origen_stock'] = 'stkdep';
        }

        $filas = [];
        foreach ($agg['filas'] as $fila) {
            $filas[] = [
                'deposito' => (int) ($fila['deposito'] ?? 0),
                'color' => (int) ($fila['color'] ?? 0),
                'color_desc' => '',
                'medida' => $fila['medida'] ?? '',
                'cantidad' => (float) ($fila['cantidad'] ?? 0),
            ];
        }
        $filas = $this->enriquecerColorDescPlanas($articulo, $filas);

        return [
            'ok' => true,
            'articulo' => $this->payloadArticulo($articulo, $skuAnita),
            'precio' => $precio,
            'precios_listas' => $preciosListas,
            'filas' => $filas,
            'saldo_total' => (float) ($agg['saldo_total'] ?? 0),
            'origen_stock' => (string) ($agg['origen_stock'] ?? 'stkdep'),
            'origen' => $origen,
            'modo_variante' => $modo,
            'combinaciones' => $combinaciones,
        ];
    }

    private function normalizarOrigen(string $origen): string
    {
        $origen = strtolower(trim($origen));

        return $origen === StockLocalInformeListadoFiltros::ORIGEN_ERP
            ? StockLocalInformeListadoFiltros::ORIGEN_ERP
            : StockLocalInformeListadoFiltros::ORIGEN_ANITA;
    }

    /**
     * Stock ERP: articulo_movimiento firmado del depósito del local.
     *
     * @param  list<int>  $medidasBase
     * @return array{
     *   filas:list<array{deposito:int|string,color:int|string,color_desc?:string,cantidades?:array<string,float>,total?:float,medida?:int|string,cantidad?:float}>,
     *   medidas:list<int|string>,
     *   saldo_total:float,
     *   error:?string
     * }
     */
    private function agregarDesdeErp(LocalVenta $local, Articulo $articulo, array $medidasBase, bool $matriz): array
    {
        $depositoId = (int) ($local->deposito_id ?: 0);
        if ($depositoId <= 0) {
            return [
                'filas' => [],
                'medidas' => $medidasBase,
                'saldo_total' => 0.0,
                'error' => 'El local no tiene depósito ERP configurado (deposito_id).',
            ];
        }

        $depCodigo = (string) ($local->deposito?->codigo ?? $depositoId);

        $rows = DB::table('articulo_movimiento as am')
            ->leftJoin('combinacion as c', 'c.id', '=', 'am.combinacion_id')
            ->leftJoin('color as col', 'col.id', '=', 'am.color_id')
            ->leftJoin('talle as t', 't.id', '=', 'am.talle_id')
            ->where('am.deposito_id', $depositoId)
            ->where('am.articulo_id', (int) $articulo->id)
            ->select([
                'am.cantidad',
                'c.codigo as combinacion_codigo',
                'c.nombre as combinacion_nombre',
                'col.codigo as color_codigo_m',
                'col.nombre as color_nombre',
                't.codigo as medida',
                't.nombre as medida_nombre',
            ])
            ->get();

        if (! $matriz) {
            /** @var array<string, array{deposito:string,color:string,color_desc:string,medida:int|string,cantidad:float}> $porClave */
            $porClave = [];
            $saldo = 0.0;
            foreach ($rows as $row) {
                $cant = (float) ($row->cantidad ?? 0);
                if (abs($cant) < 0.000001) {
                    continue;
                }
                [$colorCodigo, $colorDesc] = $this->colorDesdeMovimientoErp($row);
                $medida = $this->medidaDesdeMovimientoErp($row);
                $clave = $colorCodigo.'|'.$medida;
                if (! isset($porClave[$clave])) {
                    $porClave[$clave] = [
                        'deposito' => $depCodigo,
                        'color' => $colorCodigo,
                        'color_desc' => $colorDesc,
                        'medida' => $medida,
                        'cantidad' => 0.0,
                    ];
                }
                $porClave[$clave]['cantidad'] += $cant;
                $saldo += $cant;
            }
            $filas = array_values(array_filter(
                $porClave,
                static fn (array $f) => abs($f['cantidad']) > 0.000001
            ));
            usort($filas, static fn ($a, $b) => [$a['color'], $a['medida']] <=> [$b['color'], $b['medida']]);

            return ['filas' => $filas, 'medidas' => $medidasBase, 'saldo_total' => $saldo, 'error' => null];
        }

        /** @var array<string, array{deposito:string,color:string,color_desc:string,cantidades:array<string,float>,total:float}> $porClave */
        $porClave = [];
        $medidasVistas = [];
        $saldoTotal = 0.0;
        foreach ($rows as $row) {
            $cant = (float) ($row->cantidad ?? 0);
            if (abs($cant) < 0.000001) {
                continue;
            }
            [$colorCodigo, $colorDesc] = $this->colorDesdeMovimientoErp($row);
            $medida = $this->medidaDesdeMovimientoErp($row);
            $medidasVistas[(int) $medida] = true;
            $clave = $colorCodigo;
            if (! isset($porClave[$clave])) {
                $porClave[$clave] = [
                    'deposito' => $depCodigo,
                    'color' => $colorCodigo,
                    'color_desc' => $colorDesc,
                    'cantidades' => [],
                    'total' => 0.0,
                ];
            }
            $kMed = (string) $medida;
            $porClave[$clave]['cantidades'][$kMed] = ($porClave[$clave]['cantidades'][$kMed] ?? 0.0) + $cant;
            $porClave[$clave]['total'] += $cant;
            $saldoTotal += $cant;
        }

        $medidas = $medidasBase !== []
            ? $medidasBase
            : array_values(array_map('intval', array_keys($medidasVistas)));
        sort($medidas, SORT_NUMERIC);

        $filas = array_values(array_filter(
            $porClave,
            static fn (array $f) => abs($f['total']) > 0.000001
        ));
        usort($filas, static fn ($a, $b) => [(string) $a['color'], (string) $a['deposito']] <=> [(string) $b['color'], (string) $b['deposito']]);

        return [
            'filas' => $filas,
            'medidas' => $medidas,
            'saldo_total' => $saldoTotal,
            'error' => null,
        ];
    }

    /**
     * @param  object{combinacion_codigo?:mixed,combinacion_nombre?:mixed,color_codigo_m?:mixed,color_nombre?:mixed}  $row
     * @return array{0:string,1:string}
     */
    private function colorDesdeMovimientoErp(object $row): array
    {
        $colorCodigo = trim((string) ($row->combinacion_codigo ?? ''));
        $colorDesc = trim((string) ($row->combinacion_nombre ?? ''));
        if ($colorCodigo === '') {
            $colorCodigo = trim((string) ($row->color_codigo_m ?? ''));
            $colorDesc = trim((string) ($row->color_nombre ?? ''));
        }
        if ($colorCodigo === '') {
            $colorCodigo = '0';
        }

        return [$colorCodigo, $colorDesc];
    }

    /**
     * @param  object{medida?:mixed,medida_nombre?:mixed}  $row
     */
    private function medidaDesdeMovimientoErp(object $row): int|string
    {
        $medida = trim((string) ($row->medida ?? ''));
        if ($medida !== '' && ctype_digit($medida)) {
            return (int) $medida;
        }
        if ($medida !== '') {
            return $medida;
        }
        $nombre = trim((string) ($row->medida_nombre ?? ''));
        if ($nombre !== '' && ctype_digit($nombre)) {
            return (int) $nombre;
        }

        return 0;
    }

    private function resolverArticulo(string $busqueda): ?Articulo
    {
        $q = trim($busqueda);
        if ($q === '') {
            return null;
        }

        $query = Articulo::query()->with(['lineas.numeraciones', 'lineas']);
        ArticuloCanalSupport::scopeArticulosCanalLocal($query);

        if (ctype_digit($q)) {
            $porId = (clone $query)->where('id', (int) $q)->first();
            if ($porId) {
                return $porId;
            }
        }

        $skuAnita = $this->codigoAnitaDesdeSku($q);
        $skuLimpio = ltrim($q, '0');

        $porSku = (clone $query)
            ->where(function ($w) use ($q, $skuAnita, $skuLimpio) {
                $w->where('sku', $q)
                    ->orWhere('sku', $skuAnita)
                    ->orWhere('sku', $skuLimpio)
                    ->orWhere('sku', 'like', $q.'%');
            })
            ->orderByRaw('CASE WHEN sku = ? THEN 0 WHEN sku = ? THEN 1 ELSE 2 END', [$q, $skuAnita])
            ->first();
        if ($porSku) {
            return $porSku;
        }

        // Nombre / descripción (ej. MELISA): preferir facturables (nofactura=0).
        $porDesc = (clone $query)
            ->where('descripcion', 'like', '%'.$q.'%')
            ->orderByRaw('CASE WHEN nofactura = 0 OR nofactura IS NULL THEN 0 ELSE 1 END')
            ->orderByRaw('CASE WHEN UPPER(descripcion) = ? THEN 0 WHEN descripcion LIKE ? THEN 1 ELSE 2 END', [
                mb_strtoupper($q),
                $q.'%',
            ])
            ->orderBy('sku')
            ->first();

        return $porDesc;
    }

    /**
     * @return array{valor:float,listaprecio_id:int|null,lista:string|null,origen:string}
     */
    private function resolverPrecio(LocalVenta $local, Articulo $articulo): array
    {
        $listaId = (int) ($local->listaprecio_id ?: 0);
        if ($listaId <= 0) {
            $listaId = (int) ($articulo->lineas?->listaprecio_id ?: 0);
        }

        $fecha = now()->format('Y-m-d');
        if ($listaId > 0) {
            $valor = (float) PrecioServiceFerli::asignaPrecioPorLista((int) $articulo->id, $listaId, $fecha);
            $lista = Listaprecio::query()->find($listaId);

            return [
                'valor' => $valor,
                'listaprecio_id' => $listaId,
                'lista' => $lista ? trim((string) ($lista->nombre ?? $lista->codigo ?? '')) : null,
                'origen' => 'erp',
            ];
        }

        $anita = $this->precioDesdeAnita($local, $this->codigoAnitaDesdeSku((string) $articulo->sku));
        if ($anita !== null) {
            return $anita;
        }

        return [
            'valor' => 0.0,
            'listaprecio_id' => null,
            'lista' => null,
            'origen' => 'sin_precio',
        ];
    }

    /**
     * @return array{valor:float,listaprecio_id:int|null,lista:string|null,origen:string}|null
     */
    private function precioDesdeAnita(LocalVenta $local, string $skuAnita): ?array
    {
        $skuEsc = str_replace("'", "''", $skuAnita);
        $payload = [
            'acc' => 'list',
            'tabla' => 'stkpre',
            'campos' => 'stkp_articulo,stkp_lista,stkp_precio',
            'whereArmado' => " WHERE stkp_articulo = '{$skuEsc}' ",
            'orderBy' => 'stkp_lista',
            'servidor' => $local->anitaServidor(),
            'ifx_server' => $local->anitaIfxServer(),
        ];

        $filas = $this->listarAnita($payload);
        if ($filas === null || $filas === []) {
            return null;
        }

        $elegida = $this->elegirFilaPrecioAnita($filas, $local);
        if ($elegida === null) {
            return null;
        }

        $listaCodigoAnita = PrecioListaLocalMapeoSupport::normalizarCodigo($elegida['stkp_lista'] ?? '');
        $listaCodigoErp = PrecioListaLocalMapeoSupport::codigoErpDesdeAnita($listaCodigoAnita)
            ?? $listaCodigoAnita;
        $listaErp = null;
        if ($listaCodigoErp !== '') {
            $listaErp = Listaprecio::query()
                ->where(function ($q) use ($listaCodigoErp) {
                    $q->where('codigo', $listaCodigoErp)
                        ->orWhere('codigo', (int) $listaCodigoErp);
                })
                ->first();
        }

        $etiquetaAnita = $listaCodigoAnita !== '' ? $listaCodigoAnita : null;
        $etiqueta = $listaErp
            ? trim((string) ($listaErp->nombre ?? $listaErp->codigo ?? ''))
            : $etiquetaAnita;
        if ($listaCodigoAnita !== '' && $listaCodigoErp !== '' && $listaCodigoAnita !== $listaCodigoErp) {
            $etiqueta = trim(($etiqueta ?: $listaCodigoErp).' (Anita '.$listaCodigoAnita.')');
        }

        return [
            'valor' => (float) ($elegida['stkp_precio'] ?? 0),
            'listaprecio_id' => $listaErp ? (int) $listaErp->id : null,
            'lista' => $etiqueta,
            'origen' => 'anita_stkpre',
        ];
    }

    /**
     * Prefiere la lista del local (si está mapeada) o la primera fila con mapeo conocido.
     *
     * @param  list<array<string, mixed>>  $filas
     * @return array<string, mixed>|null
     */
    private function elegirFilaPrecioAnita(array $filas, LocalVenta $local): ?array
    {
        $codigoLocalErp = null;
        $listaId = (int) ($local->listaprecio_id ?: 0);
        if ($listaId > 0) {
            $codigoLocalErp = PrecioListaLocalMapeoSupport::normalizarCodigo(
                Listaprecio::query()->whereKey($listaId)->value('codigo')
            );
        }

        $porCodigoAnita = [];
        foreach ($filas as $fila) {
            $codigoAnita = PrecioListaLocalMapeoSupport::normalizarCodigo($fila['stkp_lista'] ?? '');
            if ($codigoAnita === '') {
                continue;
            }
            $porCodigoAnita[$codigoAnita] = $fila;
        }
        if ($porCodigoAnita === []) {
            return $filas[0] ?? null;
        }

        if ($codigoLocalErp !== null && $codigoLocalErp !== '') {
            foreach ($porCodigoAnita as $codigoAnita => $fila) {
                $codigoErp = PrecioListaLocalMapeoSupport::codigoErpDesdeAnita($codigoAnita) ?? $codigoAnita;
                if ($codigoErp === $codigoLocalErp) {
                    return $fila;
                }
            }
        }

        foreach (PrecioListaLocalMapeoSupport::codigosAnitaLocal() as $preferida) {
            if (isset($porCodigoAnita[$preferida])) {
                return $porCodigoAnita[$preferida];
            }
        }

        return array_values($porCodigoAnita)[0];
    }

    /**
     * @return list<array{listaprecio_id:int,lista:string,precio:float,fechavigencia:string|null}>
     */
    private function preciosPorListasErp(Articulo $articulo): array
    {
        $fecha = now()->format('Y-m-d');
        $rows = Precio::query()
            ->with('listaprecios:id,codigo,nombre')
            ->where('articulo_id', (int) $articulo->id)
            ->whereNull('combinacion_id')
            ->where('fechavigencia', '<=', $fecha)
            ->orderBy('listaprecio_id')
            ->orderByDesc('fechavigencia')
            ->get();

        $porLista = [];
        foreach ($rows as $row) {
            $listaId = (int) $row->listaprecio_id;
            if (isset($porLista[$listaId])) {
                continue;
            }
            $lista = $row->listaprecios;
            $porLista[$listaId] = [
                'listaprecio_id' => $listaId,
                'lista' => $lista
                    ? trim((string) (($lista->codigo ?? '').' '.($lista->nombre ?? '')))
                    : (string) $listaId,
                'precio' => (float) $row->precio,
                'fechavigencia' => $row->fechavigencia
                    ? (string) \Illuminate\Support\Carbon::parse($row->fechavigencia)->format('Y-m-d')
                    : null,
            ];
        }

        return array_values($porLista);
    }

    /**
     * @return list<int>
     */
    private function medidasDesdeArticulo(Articulo $articulo): array
    {
        $numeracionId = (int) ($articulo->lineas?->numeracion_id ?: 0);
        if ($numeracionId <= 0) {
            return [];
        }
        $numer = Numeracion::query()->find($numeracionId);
        if (! $numer) {
            return [];
        }
        $desde = (int) ($numer->desde_nro ?? 0);
        $hasta = (int) ($numer->hasta_nro ?? 0);
        if ($desde <= 0 || $hasta < $desde || ($hasta - $desde) > 80) {
            return [];
        }
        $out = [];
        for ($m = $desde; $m <= $hasta; $m++) {
            $out[] = $m;
        }

        return $out;
    }

    /**
     * @param  list<int>  $medidasBase
     * @return array{filas:list<array{deposito:int,color:int,cantidades:array<string,float>,total:float}>,medidas:list<int|string>,saldo_total:float,error:?string}
     */
    private function agregarDesdeStkvmed(LocalVenta $local, string $skuAnita, array $medidasBase): array
    {
        $skuEsc = str_replace("'", "''", $skuAnita);
        $payload = [
            'acc' => 'list',
            'tabla' => 'stkvmed',
            'campos' => 'stkvm_articulo,stkvm_tipo,stkvm_deposito,stkvm_medida,stkvm_cantidad,stkvm_color',
            'whereArmado' => " WHERE stkvm_articulo = '{$skuEsc}' ",
            'orderBy' => 'stkvm_deposito,stkvm_color,stkvm_medida',
            'servidor' => $local->anitaServidor(),
            'ifx_server' => $local->anitaIfxServer(),
        ];

        $filasRaw = $this->listarAnita($payload);
        if ($filasRaw === null) {
            return ['filas' => [], 'medidas' => $medidasBase, 'saldo_total' => 0.0, 'error' => 'Error al leer stkvmed en Anita Local.'];
        }

        $signos = $this->mapaSignoTcomp($local);
        /** @var array<string, array{deposito:int,color:int,cantidades:array<string,float>,total:float}> $porClave */
        $porClave = [];
        $medidasVistas = [];
        $saldoTotal = 0.0;

        foreach ($filasRaw as $fila) {
            $cantidad = (float) ($fila['stkvm_cantidad'] ?? 0);
            if (abs($cantidad) < 0.000001) {
                continue;
            }
            $tipo = strtoupper(trim((string) ($fila['stkvm_tipo'] ?? '')));
            $signo = $signos[$tipo] ?? null;
            if ($signo === null) {
                // Sin t_comp: ventas (FAC/NCD/etc.) restan; remitos/ingresos suman.
                $signo = $this->signoHeuristico($tipo);
            }
            if ($signo === 0) {
                continue;
            }
            $cantidadFirmada = $cantidad * $signo;
            $deposito = (int) ($fila['stkvm_deposito'] ?? 0);
            $color = (int) ($fila['stkvm_color'] ?? 0);
            $medida = (int) ($fila['stkvm_medida'] ?? 0);
            $medidasVistas[$medida] = true;
            $clave = sprintf('%06d-%06d', $color, $deposito);
            if (! isset($porClave[$clave])) {
                $porClave[$clave] = [
                    'deposito' => $deposito,
                    'color' => $color,
                    'cantidades' => [],
                    'total' => 0.0,
                ];
            }
            $kMed = (string) $medida;
            $porClave[$clave]['cantidades'][$kMed] = ($porClave[$clave]['cantidades'][$kMed] ?? 0.0) + $cantidadFirmada;
            $porClave[$clave]['total'] += $cantidadFirmada;
            $saldoTotal += $cantidadFirmada;
        }

        $medidas = $medidasBase !== []
            ? $medidasBase
            : array_values(array_map('intval', array_keys($medidasVistas)));
        sort($medidas, SORT_NUMERIC);

        $filas = array_values($porClave);
        usort($filas, static function (array $a, array $b): int {
            return [$a['color'], $a['deposito']] <=> [$b['color'], $b['deposito']];
        });

        // Omitir filas con total 0
        $filas = array_values(array_filter($filas, static fn (array $f) => abs($f['total']) > 0.000001));

        return [
            'filas' => $filas,
            'medidas' => $medidas,
            'saldo_total' => $saldoTotal,
            'error' => null,
        ];
    }

    /**
     * @param  list<int>  $medidasBase
     * @return array{
     *   filas:list<array{deposito:int,color:int,cantidades?:array<string,float>,total?:float,medida?:int,cantidad?:float}>,
     *   medidas:list<int|string>,
     *   saldo_total:float,
     *   error:?string
     * }
     */
    private function agregarDesdeStkdep(LocalVenta $local, string $skuAnita, array $medidasBase, bool $matriz): array
    {
        $skuEsc = str_replace("'", "''", $skuAnita);
        $campos = 'stkd_articulo,stkd_deposito,stkd_cantidad,stkd_color,stkd_medida';
        $where = " WHERE stkd_articulo = '{$skuEsc}' AND stkd_cantidad <> 0 ";

        $filasRaw = [];
        foreach (['stkdep', 'stkdpal'] as $tabla) {
            $payload = [
                'acc' => 'list',
                'tabla' => $tabla,
                'campos' => $campos,
                'whereArmado' => $where,
                'orderBy' => 'stkd_deposito,stkd_color,stkd_medida',
                'servidor' => $local->anitaServidor(),
                'ifx_server' => $local->anitaIfxServer(),
            ];
            $chunk = $this->listarAnita($payload);
            if ($chunk === null) {
                if ($tabla === 'stkdep') {
                    return ['filas' => [], 'medidas' => $medidasBase, 'saldo_total' => 0.0, 'error' => 'Error al leer stkdep en Anita Local.'];
                }
                // stkdpal puede no existir en todos los locales
                continue;
            }
            foreach ($chunk as $fila) {
                $filasRaw[] = $fila;
            }
        }

        if (! $matriz) {
            $filas = [];
            $saldo = 0.0;
            foreach ($filasRaw as $fila) {
                $cant = (float) ($fila['stkd_cantidad'] ?? 0);
                if (abs($cant) < 0.000001) {
                    continue;
                }
                $filas[] = [
                    'deposito' => (int) ($fila['stkd_deposito'] ?? 0),
                    'color' => (int) ($fila['stkd_color'] ?? 0),
                    'medida' => (int) ($fila['stkd_medida'] ?? 0),
                    'cantidad' => $cant,
                ];
                $saldo += $cant;
            }
            usort($filas, static fn ($a, $b) => [$a['deposito'], $a['color'], $a['medida']] <=> [$b['deposito'], $b['color'], $b['medida']]);

            return ['filas' => $filas, 'medidas' => $medidasBase, 'saldo_total' => $saldo, 'error' => null];
        }

        /** @var array<string, array{deposito:int,color:int,cantidades:array<string,float>,total:float}> $porClave */
        $porClave = [];
        $medidasVistas = [];
        $saldoTotal = 0.0;
        foreach ($filasRaw as $fila) {
            $cant = (float) ($fila['stkd_cantidad'] ?? 0);
            if (abs($cant) < 0.000001) {
                continue;
            }
            $deposito = (int) ($fila['stkd_deposito'] ?? 0);
            $color = (int) ($fila['stkd_color'] ?? 0);
            $medida = (int) ($fila['stkd_medida'] ?? 0);
            $medidasVistas[$medida] = true;
            $clave = sprintf('%06d-%06d', $color, $deposito);
            if (! isset($porClave[$clave])) {
                $porClave[$clave] = [
                    'deposito' => $deposito,
                    'color' => $color,
                    'cantidades' => [],
                    'total' => 0.0,
                ];
            }
            $kMed = (string) $medida;
            $porClave[$clave]['cantidades'][$kMed] = ($porClave[$clave]['cantidades'][$kMed] ?? 0.0) + $cant;
            $porClave[$clave]['total'] += $cant;
            $saldoTotal += $cant;
        }

        $medidas = $medidasBase !== []
            ? $medidasBase
            : array_values(array_map('intval', array_keys($medidasVistas)));
        sort($medidas, SORT_NUMERIC);

        $filas = array_values($porClave);
        usort($filas, static fn ($a, $b) => [$a['color'], $a['deposito']] <=> [$b['color'], $b['deposito']]);

        return [
            'filas' => $filas,
            'medidas' => $medidas,
            'saldo_total' => $saldoTotal,
            'error' => null,
        ];
    }

    /**
     * @return array<string, int> tipo => +1 entrada / -1 salida / 0 no opera stock
     */
    private function mapaSignoTcomp(LocalVenta $local): array
    {
        if ($this->mapaSignoTcomp !== null) {
            return $this->mapaSignoTcomp;
        }

        $payload = [
            'acc' => 'list',
            'tabla' => 't_comp',
            'campos' => 'tcomp_clave,tcomp_oper_stk',
            'servidor' => $local->anitaServidor(),
            'ifx_server' => $local->anitaIfxServer(),
        ];
        $filas = $this->listarAnita($payload);
        $mapa = [];
        if ($filas !== null) {
            foreach ($filas as $fila) {
                $tipo = strtoupper(trim((string) ($fila['tcomp_clave'] ?? '')));
                if ($tipo === '') {
                    continue;
                }
                $oper = trim((string) ($fila['tcomp_oper_stk'] ?? ''));
                if ($oper === '1') {
                    $mapa[$tipo] = 0;
                } elseif (in_array($oper, ['2', '5', '7'], true)) {
                    $mapa[$tipo] = 1;
                } else {
                    $mapa[$tipo] = -1;
                }
            }
        }
        $this->mapaSignoTcomp = $mapa;

        return $mapa;
    }

    private function signoHeuristico(string $tipo): int
    {
        $tipo = strtoupper(trim($tipo));
        if ($tipo === '') {
            return 0;
        }
        // Entradas típicas Ferli
        if (in_array($tipo, ['REM', 'ING', 'AJ+', 'TRA', 'REC', 'COM'], true)) {
            return 1;
        }
        // Salidas típicas
        if (in_array($tipo, ['FAC', 'NCD', 'TKT', 'EGR', 'AJ-', 'NCR'], true)) {
            return -1;
        }

        // Prefijo FAC / TKT / NCD → salida
        if (str_starts_with($tipo, 'FAC') || str_starts_with($tipo, 'TKT') || str_starts_with($tipo, 'NCD')) {
            return -1;
        }

        return -1;
    }

    /**
     * @param  array{acc:string,tabla:string,campos:string,whereArmado?:string,orderBy?:string,servidor:string,ifx_server:string}  $payload
     * @return list<array<string, mixed>>|null null = error bridge
     */
    private function listarAnita(array $payload): ?array
    {
        try {
            $raw = $this->apiAnita->apiCall($payload);
            $rawStr = is_string($raw) ? $raw : json_encode($raw);
            $error = ApiAnita::extraerMensajeError($rawStr);
            if ($error !== null) {
                Log::warning('facturacion_local.stock_consulta.anita', [
                    'tabla' => $payload['tabla'] ?? '',
                    'error' => $error,
                    'servidor' => $payload['servidor'] ?? '',
                ]);

                return null;
            }
            $filas = ApiAnita::decodificarListaFilas($rawStr);
            $out = [];
            foreach ($filas as $fila) {
                $out[] = is_object($fila) ? get_object_vars($fila) : (array) $fila;
            }

            return $out;
        } catch (\Throwable $e) {
            Log::warning('facturacion_local.stock_consulta.ex', [
                'tabla' => $payload['tabla'] ?? '',
                'msg' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * @param  list<array{deposito:int,color:int,cantidades:array<string,float>,total:float}>  $filas
     * @return list<array{deposito:int,color:int,color_desc:string,cantidades:array<string,float>,total:float}>
     */
    private function enriquecerColorDesc(Articulo $articulo, array $filas): array
    {
        $mapa = $this->mapaCombinacion((int) $articulo->id);
        foreach ($filas as &$fila) {
            $codigo = (string) ($fila['color'] ?? '');
            $desc = $mapa[$codigo] ?? $mapa[ltrim($codigo, '0')] ?? '';
            if ($desc !== '') {
                $fila['color_desc'] = $desc;
            } else {
                $fila['color_desc'] = (string) ($fila['color_desc'] ?? '');
            }
        }
        unset($fila);

        return $filas;
    }

    /**
     * @param  list<array{deposito:int|string,color:int|string,color_desc:string,medida:int|string,cantidad:float}>  $filas
     * @return list<array{deposito:int|string,color:int|string,color_desc:string,medida:int|string,cantidad:float}>
     */
    private function enriquecerColorDescPlanas(Articulo $articulo, array $filas): array
    {
        $mapa = $this->mapaCombinacion((int) $articulo->id);
        foreach ($filas as &$fila) {
            $codigo = (string) ($fila['color'] ?? '');
            $desc = $mapa[$codigo] ?? $mapa[ltrim($codigo, '0')] ?? '';
            if ($desc !== '') {
                $fila['color_desc'] = $desc;
            } else {
                $fila['color_desc'] = (string) ($fila['color_desc'] ?? '');
            }
        }
        unset($fila);

        return $filas;
    }

    /**
     * @return array<string, string>
     */
    private function mapaCombinacion(int $articuloId): array
    {
        $mapa = [];
        $rows = Combinacion::query()
            ->where('articulo_id', $articuloId)
            ->get(['codigo', 'nombre']);
        foreach ($rows as $row) {
            $codigo = trim((string) $row->codigo);
            $mapa[$codigo] = trim((string) $row->nombre);
            $mapa[ltrim($codigo, '0')] = $mapa[$codigo];
            if (ctype_digit($codigo)) {
                $mapa[(string) ((int) $codigo)] = $mapa[$codigo];
            }
        }

        return $mapa;
    }

    /**
     * @param  list<array{deposito:int,color:int,cantidades:array<string,float>,total:float}>  $filas
     * @param  list<int|string>  $medidas
     * @return list<array{deposito:int,color:int,medida:int|string,cantidad:float}>
     */
    private function matrizAFilasPlanas(array $filas, array $medidas): array
    {
        $out = [];
        foreach ($filas as $fila) {
            foreach ($medidas as $medida) {
                $cant = (float) ($fila['cantidades'][(string) $medida] ?? 0);
                if (abs($cant) < 0.000001) {
                    continue;
                }
                $out[] = [
                    'deposito' => (int) $fila['deposito'],
                    'color' => (int) $fila['color'],
                    'medida' => $medida,
                    'cantidad' => $cant,
                ];
            }
        }

        return $out;
    }

    /**
     * @return array{id:int,sku:string,descripcion:string,sku_anita:string}
     */
    private function payloadArticulo(Articulo $articulo, string $skuAnita): array
    {
        return [
            'id' => (int) $articulo->id,
            'sku' => (string) $articulo->sku,
            'descripcion' => (string) $articulo->descripcion,
            'sku_anita' => $skuAnita,
        ];
    }

    public function codigoAnitaDesdeSku(string $sku): string
    {
        return str_pad(trim($sku), self::LONGITUD_SKU_ANITA, '0', STR_PAD_LEFT);
    }
}
