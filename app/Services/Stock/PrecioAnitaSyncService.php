<?php

namespace App\Services\Stock;

use App\ApiAnita;
use App\Models\Stock\Articulo;
use App\Models\Stock\Listaprecio;
use App\Models\Stock\Precio;
use App\Support\Stock\ArticuloSkuMatchSupport;
use App\Support\Stock\PrecioAnitaFechaSupport;
use App\Support\Stock\PrecioConservarVigenteSupport;
use Illuminate\Support\Facades\Log;

class PrecioAnitaSyncService
{
    private const TABLA = 'stkpre';

    public function __construct(
        private readonly ApiAnita $apiAnita,
        private readonly PrecioConservarVigenteSupport $conservarVigente,
    ) {}

    /**
     * @return array{
     *     fecha_desde_anita: int,
     *     filas_anita: int,
     *     filas_unicas_sku_lista: int,
     *     insertados: int,
     *     actualizados: int,
     *     omitidos_sin_articulo: int,
     *     omitidos_sin_lista: int,
     *     omitidos_precio_invalido: int,
     *     obsoletos_eliminados: int,
     *     pares_con_duplicado: int,
     *     errores: list<string>
     * }
     */
    public function sincronizarDesdeAnita(
        ?int $fechaDesdeAnita = null,
        bool $conservarSoloVigente = true,
        ?int $usuarioId = null,
        ?string $codigoListaAnita = null,
    ): array {
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $fechaDesdeAnita = $fechaDesdeAnita ?? PrecioAnitaFechaSupport::fechaDesdeConfig();
        $usuarioId = max(1, (int) ($usuarioId ?? 1));

        $ret = [
            'fecha_desde_anita' => $fechaDesdeAnita,
            'filas_anita' => 0,
            'filas_unicas_sku_lista' => 0,
            'insertados' => 0,
            'actualizados' => 0,
            'omitidos_sin_articulo' => 0,
            'omitidos_sin_lista' => 0,
            'omitidos_precio_invalido' => 0,
            'obsoletos_eliminados' => 0,
            'pares_con_duplicado' => 0,
            'errores' => [],
        ];

        $filasAnita = $this->listarStkpreDesdeAnita($fechaDesdeAnita, $codigoListaAnita);
        $ret['filas_anita'] = count($filasAnita);

        $filasUnicas = $this->agruparPorSkuListaMasReciente($filasAnita);
        $ret['filas_unicas_sku_lista'] = count($filasUnicas);

        $mapArticulos = $this->mapaArticulosPorSku();
        $mapListas = $this->mapaListasPorCodigo();
        $paresTocados = [];

        foreach ($filasUnicas as $fila) {
            $sku = ltrim(trim((string) ($fila['stkp_articulo'] ?? '')), '0');
            if ($sku === '') {
                continue;
            }

            $codigoLista = trim((string) ($fila['stkp_lista'] ?? ''));
            $articuloId = $mapArticulos[$sku] ?? null;
            if ($articuloId === null) {
                $canonico = ArticuloSkuMatchSupport::resolverCanonico($sku);
                $articuloId = $canonico ? (int) $canonico->id : null;
                if ($articuloId !== null) {
                    $mapArticulos[$sku] = $articuloId;
                }
            }

            if ($articuloId === null) {
                $ret['omitidos_sin_articulo']++;

                continue;
            }

            $listaprecioId = $mapListas[$codigoLista]
                ?? $mapListas[ltrim($codigoLista, '0')]
                ?? null;
            if ($listaprecioId === null) {
                $ret['omitidos_sin_lista']++;

                continue;
            }

            $precio = $fila['stkp_precio'] ?? null;
            if ($precio === null || $precio === '' || ! is_numeric($precio)) {
                $ret['omitidos_precio_invalido']++;

                continue;
            }

            $fechavigencia = PrecioAnitaFechaSupport::fechavigenciaDesdeAnita($fila['stkp_fe_ult_act'] ?? null);
            $precioAnterior = $fila['stkp_precio_ant'] ?? null;
            $precioAnterior = ($precioAnterior !== null && $precioAnterior !== '' && is_numeric($precioAnterior))
                ? (float) $precioAnterior
                : null;
            $monedaId = (int) ($fila['stkp_cod_mon'] ?? 1);
            if ($monedaId < 1) {
                $monedaId = 1;
            }

            try {
                $existente = Precio::query()
                    ->where('articulo_id', $articuloId)
                    ->where('listaprecio_id', $listaprecioId)
                    ->whereDate('fechavigencia', $fechavigencia)
                    ->orderByDesc('id')
                    ->first();

                $payload = [
                    'precio' => (float) $precio,
                    'precioanterior' => $precioAnterior,
                    'moneda_id' => $monedaId,
                    'usuarioultcambio_id' => $usuarioId,
                ];

                if ($existente) {
                    $existente->update($payload);
                    $ret['actualizados']++;
                } else {
                    Precio::create(array_merge($payload, [
                        'articulo_id' => $articuloId,
                        'listaprecio_id' => $listaprecioId,
                        'fechavigencia' => $fechavigencia,
                    ]));
                    $ret['insertados']++;
                }

                $paresTocados[] = [
                    'articulo_id' => $articuloId,
                    'listaprecio_id' => $listaprecioId,
                ];
            } catch (\Throwable $e) {
                $msg = "SKU {$sku} lista {$codigoLista}: ".$e->getMessage();
                if (count($ret['errores']) < 50) {
                    $ret['errores'][] = $msg;
                }
                Log::warning('PrecioAnitaSync: '.$msg, ['exception' => $e]);
            }
        }

        if ($conservarSoloVigente) {
            $scopePares = $codigoListaAnita !== null && $codigoListaAnita !== ''
                ? $this->uniquePares($paresTocados)
                : null;
            $limp = $this->conservarVigente->conservarSoloVigente($scopePares);
            $ret['obsoletos_eliminados'] = $limp['eliminados'];
            $ret['pares_con_duplicado'] = $limp['pares_con_duplicado'];
        }

        return $ret;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function listarStkpreDesdeAnita(int $fechaDesdeAnita, ?string $codigoListaAnita = null): array
    {
        $where = " WHERE stkp_fe_ult_act >= {$fechaDesdeAnita} ";
        $codigoListaAnita = trim((string) ($codigoListaAnita ?? ''));
        if ($codigoListaAnita !== '') {
            $where .= " AND stkp_lista = '".addslashes($codigoListaAnita)."' ";
        }

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
            'whereArmado' => $where,
            'orderBy' => 'stkp_fe_ult_act desc',
        ];

        $respuesta = $this->apiAnita->apiCall($payload);
        if ($respuesta === false || $respuesta === '' || str_contains((string) $respuesta, 'Error')) {
            throw new \RuntimeException('Respuesta inválida al listar stkpre desde Anita.');
        }

        $filas = json_decode((string) $respuesta, true);
        if (! is_array($filas)) {
            throw new \RuntimeException('El listado stkpre desde Anita no es JSON válido.');
        }

        $out = [];
        foreach ($filas as $fila) {
            if (! is_array($fila) && ! is_object($fila)) {
                continue;
            }
            $out[] = is_array($fila) ? $fila : get_object_vars($fila);
        }

        return $out;
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     * @return list<array<string, mixed>>
     */
    private function agruparPorSkuListaMasReciente(array $filas): array
    {
        $mejor = [];
        foreach ($filas as $fila) {
            $articulo = trim((string) ($fila['stkp_articulo'] ?? ''));
            $lista = trim((string) ($fila['stkp_lista'] ?? ''));
            if ($articulo === '' || $lista === '') {
                continue;
            }
            $clave = $articulo.'|'.$lista;
            $fecha = (int) ($fila['stkp_fe_ult_act'] ?? 0);
            if (! isset($mejor[$clave]) || $fecha > (int) ($mejor[$clave]['stkp_fe_ult_act'] ?? 0)) {
                $mejor[$clave] = $fila;
            }
        }

        return array_values($mejor);
    }

    /**
     * @return array<string, int>
     */
    private function mapaArticulosPorSku(): array
    {
        $map = [];
        foreach (Articulo::query()->select(['id', 'sku'])->cursor() as $row) {
            $sku = trim((string) $row->sku);
            if ($sku !== '') {
                $map[$sku] = (int) $row->id;
            }
        }

        return $map;
    }

    /**
     * @return array<string, int>
     */
    private function mapaListasPorCodigo(): array
    {
        $map = [];
        foreach (Listaprecio::query()->select(['id', 'codigo'])->cursor() as $row) {
            $codigo = trim((string) $row->codigo);
            if ($codigo !== '') {
                $map[$codigo] = (int) $row->id;
                $sinCeros = ltrim($codigo, '0');
                if ($sinCeros !== '') {
                    $map[$sinCeros] = (int) $row->id;
                }
            }
        }

        return $map;
    }

    /**
     * @param  list<array{articulo_id: int, listaprecio_id: int}>  $pares
     * @return list<array{articulo_id: int, listaprecio_id: int}>
     */
    private function uniquePares(array $pares): array
    {
        $out = [];
        foreach ($pares as $par) {
            $key = (int) $par['articulo_id'].'|'.(int) $par['listaprecio_id'];
            $out[$key] = [
                'articulo_id' => (int) $par['articulo_id'],
                'listaprecio_id' => (int) $par['listaprecio_id'],
            ];
        }

        return array_values($out);
    }
}
