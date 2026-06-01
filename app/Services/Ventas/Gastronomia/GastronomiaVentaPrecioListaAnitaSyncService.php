<?php

namespace App\Services\Ventas\Gastronomia;

use App\ApiAnita;
use App\Models\Stock\Articulo;
use App\Models\Stock\Listaprecio;
use App\Models\Stock\Precio;
use App\Support\Stock\PrecioListaVigenteSupport;
use App\Support\Ventas\GastronomiaSkuCatalogoSupport;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Importa precios de lista desde Anita (stkpre) para artículos de venta gastronomía (SKU catálogo, ej. Vxxxx)
 * que no tienen precio vigente en el ERP.
 */
class GastronomiaVentaPrecioListaAnitaSyncService
{
    private const TABLA = 'stkpre';

    private const LONGITUD_CODIGO = 13;

    private const CHUNK_SIZE = 80;

    public function __construct(
        private ApiAnita $apiAnita,
    ) {}

    /**
     * @return array{
     *     listaprecio_id: int,
     *     listaprecio_codigo: string,
     *     candidatos_sin_precio_vigente: int,
     *     filas_anita: int,
     *     importados: int,
     *     omitidos_ya_vigente: int,
     *     omitidos_sin_fila_anita: int,
     *     omitidos_precio_invalido: int,
     *     omitidos_duplicado: int,
     *     errores: list<string>
     * }
     */
    public function sincronizarDesdeAnita(
        ?int $listaprecioId = null,
        ?string $skuFiltro = null,
        bool $dryRun = false,
        ?int $usuarioId = null,
    ): array {
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $lista = $this->resolverListaPrecio($listaprecioId);
        $ret = [
            'listaprecio_id' => $lista['id'],
            'listaprecio_codigo' => $lista['codigo'],
            'candidatos_sin_precio_vigente' => 0,
            'filas_anita' => 0,
            'importados' => 0,
            'omitidos_ya_vigente' => 0,
            'omitidos_sin_fila_anita' => 0,
            'omitidos_precio_invalido' => 0,
            'omitidos_duplicado' => 0,
            'errores' => [],
        ];

        $candidatos = $this->articulosSinPrecioVigente($lista['id'], $skuFiltro);
        $ret['candidatos_sin_precio_vigente'] = count($candidatos);

        if ($candidatos === []) {
            return $ret;
        }

        if ($dryRun) {
            return $ret;
        }

        $usuarioId = $usuarioId ?? (int) (Auth::id() ?? 0);
        if ($usuarioId < 1) {
            $usuarioId = 1;
        }

        $skuPorCodigoAnita = [];
        foreach ($candidatos as $row) {
            $skuPorCodigoAnita[$this->codigoAnitaDesdeSku($row['sku'])] = $row;
        }

        $codigos = array_keys($skuPorCodigoAnita);
        foreach (array_chunk($codigos, self::CHUNK_SIZE) as $chunk) {
            $filas = $this->consultarStkprePorCodigos($chunk, $lista['codigo']);
            $ret['filas_anita'] += count($filas);

            foreach ($chunk as $codigoAnita) {
                $articulo = $skuPorCodigoAnita[$codigoAnita] ?? null;
                if ($articulo === null) {
                    continue;
                }

                $fila = $filas[$codigoAnita] ?? null;
                if ($fila === null) {
                    $ret['omitidos_sin_fila_anita']++;

                    continue;
                }

                try {
                    $estado = $this->importarFilaAnita($articulo, $lista['id'], $fila, $usuarioId);
                    if ($estado === 'importado') {
                        $ret['importados']++;
                    } elseif ($estado === 'duplicado') {
                        $ret['omitidos_duplicado']++;
                    } else {
                        $ret['omitidos_precio_invalido']++;
                    }
                } catch (\Throwable $e) {
                    $msg = "SKU {$articulo['sku']}: ".$e->getMessage();
                    $ret['errores'][] = $msg;
                    Log::warning('GastronomiaVentaPrecioListaAnitaSync: '.$msg, ['exception' => $e]);
                }
            }
        }

        return $ret;
    }

    /**
     * @return array{id: int, codigo: string}
     */
    private function resolverListaPrecio(?int $listaprecioId): array
    {
        $id = $listaprecioId !== null && $listaprecioId > 0
            ? $listaprecioId
            : (int) config('gastronomia.precio_lista_sync.listaprecio_id', 0);

        if ($id < 1) {
            $id = (int) config('precio.listaprecio_default_id', 1);
        }

        $lista = Listaprecio::query()->whereKey($id)->first(['id', 'codigo']);
        if ($lista === null) {
            throw new \RuntimeException("No existe listaprecio id {$id}.");
        }

        $codigo = trim((string) $lista->codigo);
        if ($codigo === '') {
            throw new \RuntimeException("La lista de precios id {$id} no tiene código Anita (prem_lista).");
        }

        return ['id' => (int) $lista->id, 'codigo' => $codigo];
    }

    /**
     * @return list<array{id: int, sku: string}>
     */
    private function articulosSinPrecioVigente(int $listaprecioId, ?string $skuFiltro): array
    {
        $query = Articulo::query()->select(['id', 'sku']);
        GastronomiaSkuCatalogoSupport::aplicarScopeFormatoCatalogo($query);

        $skuFiltro = trim((string) $skuFiltro);
        if ($skuFiltro !== '') {
            $query->whereRaw('UPPER(sku) = ?', [mb_strtoupper($skuFiltro, 'UTF-8')]);
        }

        $articulos = $query->orderBy('sku')->get();
        if ($articulos->isEmpty()) {
            return [];
        }

        $ids = $articulos->pluck('id')->map(fn ($id) => (int) $id)->all();
        $vigentes = PrecioListaVigenteSupport::vigentesPorArticulos($ids, $listaprecioId);

        $out = [];
        foreach ($articulos as $articulo) {
            $id = (int) $articulo->id;
            if (isset($vigentes[$id])) {
                continue;
            }
            $sku = trim((string) $articulo->sku);
            if ($sku === '') {
                continue;
            }
            $out[] = ['id' => $id, 'sku' => $sku];
        }

        return $out;
    }

    public function codigoAnitaDesdeSku(string $sku): string
    {
        return str_pad(trim($sku), self::LONGITUD_CODIGO, '0', STR_PAD_LEFT);
    }

    /**
     * @param  list<string>  $codigosAnita
     * @return array<string, array<string, mixed>>
     */
    private function consultarStkprePorCodigos(array $codigosAnita, string $codigoLista): array
    {
        if ($codigosAnita === []) {
            return [];
        }

        $listaSql = implode(',', array_map(
            fn (string $c) => "'".str_replace("'", "''", $c)."'",
            $codigosAnita
        ));
        $listaCodigo = str_replace("'", "''", $codigoLista);

        $payload = [
            'acc' => 'list',
            'tabla' => self::TABLA,
            'campos' => implode(',', [
                'stkp_articulo',
                'stkp_lista',
                'stkp_precio',
                'stkp_precio_ant',
                'stkp_cod_mon',
                'stkp_fe_ult_act',
            ]),
            'whereArmado' => " WHERE stkp_lista = '{$listaCodigo}' AND stkp_articulo IN ({$listaSql}) ",
        ];

        try {
            $respuesta = $this->apiAnita->apiCall($payload);
        } catch (\Throwable $e) {
            Log::warning('GastronomiaVentaPrecioListaAnitaSync: error ApiAnita', ['exception' => $e]);

            return [];
        }

        if ($respuesta === false || $respuesta === '' || str_contains((string) $respuesta, 'Error')) {
            Log::warning('GastronomiaVentaPrecioListaAnitaSync: respuesta inválida', [
                'respuesta' => substr((string) $respuesta, 0, 200),
            ]);

            return [];
        }

        $filas = json_decode((string) $respuesta, true);
        if (! is_array($filas)) {
            return [];
        }

        $out = [];
        foreach ($filas as $fila) {
            if (! is_array($fila) && ! is_object($fila)) {
                continue;
            }
            $row = is_array($fila) ? $fila : get_object_vars($fila);
            $codigo = trim((string) ($row['stkp_articulo'] ?? ''));
            if ($codigo === '') {
                continue;
            }
            $out[$codigo] = $row;
        }

        return $out;
    }

    /**
     * @param  array{id: int, sku: string}  $articulo
     * @param  array<string, mixed>  $fila
     * @return 'importado'|'duplicado'|'invalido'
     */
    private function importarFilaAnita(array $articulo, int $listaprecioId, array $fila, int $usuarioId): string
    {
        $precio = $fila['stkp_precio'] ?? null;
        if ($precio === null || $precio === '' || ! is_numeric($precio)) {
            return 'invalido';
        }

        $precioFloat = (float) $precio;
        $fechavigencia = $this->fechavigenciaDesdeAnita($fila['stkp_fe_ult_act'] ?? null);
        $precioAnterior = $fila['stkp_precio_ant'] ?? null;
        $precioAnterior = ($precioAnterior !== null && $precioAnterior !== '' && is_numeric($precioAnterior))
            ? (float) $precioAnterior
            : null;

        $monedaId = (int) ($fila['stkp_cod_mon'] ?? 1);
        if ($monedaId < 1) {
            $monedaId = 1;
        }

        $existe = Precio::query()
            ->where('articulo_id', $articulo['id'])
            ->where('listaprecio_id', $listaprecioId)
            ->whereDate('fechavigencia', $fechavigencia)
            ->where('precio', $precioFloat)
            ->exists();

        if ($existe) {
            return 'duplicado';
        }

        Precio::create([
            'articulo_id' => $articulo['id'],
            'listaprecio_id' => $listaprecioId,
            'fechavigencia' => $fechavigencia,
            'moneda_id' => $monedaId,
            'precio' => $precioFloat,
            'precioanterior' => $precioAnterior,
            'usuarioultcambio_id' => $usuarioId,
        ]);

        return 'importado';
    }

    private function fechavigenciaDesdeAnita(mixed $fechaRaw): string
    {
        $n = (int) $fechaRaw;
        if ($n < 19000000) {
            $n = 20100101;
        }
        $s = (string) $n;
        if (strlen($s) === 8) {
            return substr($s, 0, 4).'-'.substr($s, 4, 2).'-'.substr($s, 6, 2);
        }

        return Carbon::today()->toDateString();
    }
}
