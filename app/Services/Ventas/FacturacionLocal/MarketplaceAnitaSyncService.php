<?php

namespace App\Services\Ventas\FacturacionLocal;

use App\ApiAnita;
use App\Models\Stock\Articulo;
use App\Models\Stock\Combinacion;
use App\Models\Ventas\ArticuloMarketplace;
use App\Models\Ventas\LocalVenta;
use App\Models\Ventas\Marketplace;
use App\Support\Stock\ArticuloSkuMatchSupport;
use Illuminate\Support\Facades\Log;

/**
 * Carga desde Informix Local (marketplace + stkmplace) hacia anitaERP.
 * No escribe de vuelta en el bridge.
 */
final class MarketplaceAnitaSyncService
{
    /**
     * Códigos de marketplace del bridge que no se importan.
     * 1178682178: fila sin descripción en Informix Local (basura).
     *
     * @var list<int>
     */
    private const CODIGOS_EXCLUIDOS = [
        1178682178,
    ];

    /**
     * @return array<string, mixed>
     */
    public function sincronizar(?LocalVenta $local = null, bool $ejecutar = false): array
    {
        $servidor = $local?->anitaServidor() ?: (string) config('facturacion_local.anita_servidor_default', 'LOCAL_IP');
        $ifx = $local?->anitaIfxServer() ?: (string) config('facturacion_local.anita_ifx_server_default', 'IFX_SERVER_LOCAL');

        $maestros = $this->listar($servidor, $ifx, 'marketplace', 'markp_codigo,markp_desc', 'markp_codigo');
        if (isset($maestros['error'])) {
            return $this->vacio(! $ejecutar, $maestros['error']);
        }
        $asignaciones = $this->listar(
            $servidor,
            $ifx,
            'stkmplace',
            'stkmpl_articulo,stkmpl_orden,stkmpl_marketplace,stkmpl_combinacion',
            'stkmpl_articulo,stkmpl_orden'
        );
        if (isset($asignaciones['error'])) {
            return $this->vacio(! $ejecutar, $asignaciones['error']);
        }

        $maestrosNuevos = 0;
        $maestrosActualizados = 0;
        $asignacionesNuevas = 0;
        $asignacionesActualizadas = 0;
        $sinArticulo = 0;
        $sinCombinacion = 0;
        $omitidos = 0;
        $skusSinArticulo = [];
        $errores = [];

        /** @var array<int, int> codigo Anita => id ERP (0 = se creará al ejecutar) */
        $mapaMarketplace = [];
        foreach (Marketplace::query()->get(['id', 'codigo', 'nombre']) as $row) {
            $mapaMarketplace[(int) $row->codigo] = (int) $row->id;
        }

        foreach ($maestros['filas'] as $fila) {
            $codigo = (int) ($fila->markp_codigo ?? 0);
            $nombre = trim((string) ($fila->markp_desc ?? ''));
            if ($codigo <= 0 || in_array($codigo, self::CODIGOS_EXCLUIDOS, true)) {
                if ($codigo > 0) {
                    $omitidos++;
                }

                continue;
            }
            if ($nombre === '') {
                $nombre = 'Sin descripción';
            }
            if (! isset($mapaMarketplace[$codigo]) || $mapaMarketplace[$codigo] === 0) {
                $maestrosNuevos++;
                if ($ejecutar) {
                    $creado = Marketplace::query()->create([
                        'codigo' => $codigo,
                        'nombre' => $nombre,
                        'activo' => true,
                    ]);
                    $mapaMarketplace[$codigo] = (int) $creado->id;
                } else {
                    $mapaMarketplace[$codigo] = 0;
                }

                continue;
            }

            $existente = Marketplace::query()->find($mapaMarketplace[$codigo]);
            if ($existente && (string) $existente->nombre !== $nombre) {
                $maestrosActualizados++;
                if ($ejecutar) {
                    $existente->nombre = $nombre;
                    $existente->save();
                }
            }
        }

        $cacheArticulo = [];
        $cacheCombinacion = [];

        foreach ($asignaciones['filas'] as $fila) {
            $codigoAnita = trim((string) ($fila->stkmpl_articulo ?? ''));
            $codigoMarketplace = (int) ($fila->stkmpl_marketplace ?? 0);
            $orden = max(0, (int) ($fila->stkmpl_orden ?? 0));
            $codigoCombinacion = mb_substr(trim((string) ($fila->stkmpl_combinacion ?? '')), 0, 6);
            if ($codigoAnita === '' || $codigoMarketplace <= 0) {
                continue;
            }
            if (in_array($codigoMarketplace, self::CODIGOS_EXCLUIDOS, true)) {
                $omitidos++;

                continue;
            }
            if (! array_key_exists($codigoMarketplace, $mapaMarketplace)) {
                if (count($errores) < 20) {
                    $errores[] = $codigoAnita.': marketplace '.$codigoMarketplace.' no está en el maestro.';
                }

                continue;
            }

            $articulo = $this->buscarArticulo($codigoAnita, $cacheArticulo);
            if (! $articulo) {
                $sinArticulo++;
                if (count($skusSinArticulo) < 40 && ! in_array($codigoAnita, $skusSinArticulo, true)) {
                    $skusSinArticulo[] = $codigoAnita;
                }

                continue;
            }

            $combinacionId = $this->buscarCombinacion((int) $articulo->id, $codigoCombinacion, $cacheCombinacion);
            if ($codigoCombinacion !== '' && $combinacionId === null) {
                $sinCombinacion++;
            }

            $marketplaceId = (int) $mapaMarketplace[$codigoMarketplace];
            if ($marketplaceId <= 0) {
                $asignacionesNuevas++;

                continue;
            }

            $filaErp = ArticuloMarketplace::query()
                ->where('articulo_id', $articulo->id)
                ->where('marketplace_id', $marketplaceId)
                ->where('codigo_combinacion', $codigoCombinacion)
                ->where('orden', $orden)
                ->first();
            if ($filaErp) {
                if ((int) ($filaErp->combinacion_id ?? 0) !== (int) ($combinacionId ?? 0)) {
                    $asignacionesActualizadas++;
                    if ($ejecutar) {
                        $filaErp->combinacion_id = $combinacionId;
                        $filaErp->save();
                    }
                }

                continue;
            }

            $asignacionesNuevas++;
            if ($ejecutar) {
                ArticuloMarketplace::query()->create([
                    'articulo_id' => (int) $articulo->id,
                    'marketplace_id' => $marketplaceId,
                    'combinacion_id' => $combinacionId,
                    'codigo_combinacion' => $codigoCombinacion,
                    'orden' => $orden,
                ]);
            }
        }

        return [
            'dry_run' => ! $ejecutar,
            'maestros_bridge' => count($maestros['filas']),
            'maestros_nuevos' => $maestrosNuevos,
            'maestros_actualizados' => $maestrosActualizados,
            'asignaciones_bridge' => count($asignaciones['filas']),
            'asignaciones_nuevas' => $asignacionesNuevas,
            'asignaciones_actualizadas' => $asignacionesActualizadas,
            'sin_articulo' => $sinArticulo,
            'sin_combinacion' => $sinCombinacion,
            'omitidos' => $omitidos,
            'skus_sin_articulo' => $skusSinArticulo,
            'errores' => $errores,
        ];
    }

    /**
     * @param  array<string, Articulo|null>  $cache
     */
    private function buscarArticulo(string $codigoAnita, array &$cache): ?Articulo
    {
        if (array_key_exists($codigoAnita, $cache)) {
            return $cache[$codigoAnita];
        }

        $sku = ltrim($codigoAnita, '0');
        if ($sku === '') {
            $sku = $codigoAnita;
        }
        $candidatos = array_values(array_unique(array_filter([
            $sku,
            $codigoAnita,
            str_pad($sku, 13, '0', STR_PAD_LEFT),
        ])));
        $articulo = null;
        foreach ($candidatos as $cand) {
            $articulo = ArticuloSkuMatchSupport::resolverCanonico($cand);
            if ($articulo) {
                break;
            }
        }
        $cache[$codigoAnita] = $articulo;

        return $articulo;
    }

    /**
     * @param  array<string, int|null>  $cache
     */
    private function buscarCombinacion(int $articuloId, string $codigo, array &$cache): ?int
    {
        $codigo = trim($codigo);
        if ($codigo === '' || $articuloId <= 0) {
            return null;
        }
        $clave = $articuloId.'|'.strtoupper($codigo);
        if (array_key_exists($clave, $cache)) {
            return $cache[$clave];
        }

        $norm = strtoupper($codigo);
        $sinCeros = ltrim($norm, '0');
        $comb = Combinacion::query()
            ->where('articulo_id', $articuloId)
            ->where(function ($q) use ($norm, $sinCeros) {
                $q->whereRaw('UPPER(TRIM(codigo)) = ?', [$norm]);
                if ($sinCeros !== '' && $sinCeros !== $norm) {
                    $q->orWhereRaw('UPPER(TRIM(codigo)) = ?', [$sinCeros]);
                }
            })
            ->orderBy('id')
            ->first(['id']);

        $cache[$clave] = $comb ? (int) $comb->id : null;

        return $cache[$clave];
    }

    /**
     * @return array{filas: list<object>}|array{error: string}
     */
    private function listar(string $servidor, string $ifx, string $tabla, string $campos, string $orderBy): array
    {
        try {
            $api = new ApiAnita;
            $raw = $api->apiCall([
                'acc' => 'list',
                'tabla' => $tabla,
                'campos' => $campos,
                'orderBy' => $orderBy,
                'servidor' => $servidor,
                'ifx_server' => $ifx,
                'curl_timeout' => 120,
            ]);
            $rawStr = is_string($raw) ? $raw : json_encode($raw);
            $decoded = json_decode((string) $rawStr, true);
            if (is_array($decoded) && isset($decoded['Error']) && is_string($decoded['Error']) && $decoded['Error'] !== '') {
                return ['error' => $decoded['Error'].' ('.$tabla.', servidor='.$servidor.', ifx='.$ifx.')'];
            }

            $filas = [];
            foreach (ApiAnita::decodificarListaFilas((string) $rawStr) as $fila) {
                $filas[] = is_object($fila) ? $fila : (object) $fila;
            }

            return ['filas' => $filas];
        } catch (\Throwable $e) {
            Log::warning('facturacion_local.sync_marketplace.anita_error', [
                'tabla' => $tabla,
                'msg' => $e->getMessage(),
            ]);

            return ['error' => 'Error leyendo '.$tabla.' del local: '.$e->getMessage()];
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function vacio(bool $dryRun, string $error): array
    {
        return [
            'dry_run' => $dryRun,
            'error' => $error,
            'maestros_bridge' => 0,
            'maestros_nuevos' => 0,
            'maestros_actualizados' => 0,
            'asignaciones_bridge' => 0,
            'asignaciones_nuevas' => 0,
            'asignaciones_actualizadas' => 0,
            'sin_articulo' => 0,
            'sin_combinacion' => 0,
            'omitidos' => 0,
            'skus_sin_articulo' => [],
            'errores' => [],
        ];
    }
}
