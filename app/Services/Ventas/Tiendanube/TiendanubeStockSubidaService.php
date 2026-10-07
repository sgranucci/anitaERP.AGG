<?php

namespace App\Services\Ventas\Tiendanube;

use App\Models\Ventas\TiendanubeStockSubida;
use App\Models\Ventas\TiendanubeStockSubidaLinea;
use App\Support\Ventas\Tiendanube\TiendanubeStockCatalogoSupport;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Sube stock y precios a Tiendanube de los artículos marcados en el marketplace configurado.
 * El stock es el del informe de stock del local, sumado en cada depósito configurado.
 * El precio es la lista de precio; si la lista de oferta difiere, va como precio promocional.
 */
final class TiendanubeStockSubidaService
{
    /**
     * @return array{ok:bool, mensaje:string, subidas:list<int>}
     */
    public function ejecutar(string $origen, ?int $usuarioId = null, ?string $storeId = null, bool $soloHoraActual = false): array
    {
        $this->cerrarCorridasColgadas();

        if ($origen === TiendanubeStockSubida::ORIGEN_CRON
            && ! TiendanubeStockCatalogoSupport::esDiaHabilSubidaAutomatica()) {
            return [
                'ok' => true,
                'mensaje' => 'Sábado, domingo o feriado: no se sube stock automáticamente.',
                'subidas' => [],
            ];
        }

        $tiendas = TiendanubeStockCatalogoSupport::tiendasParaSubir($storeId, $soloHoraActual);
        if ($tiendas === []) {
            return [
                'ok' => true,
                'mensaje' => 'No hay tiendas con la subida de stock activa para este horario.',
                'subidas' => [],
            ];
        }

        $lock = Cache::lock('tiendanube-stock-subida', 7200);
        if (! $lock->get()) {
            return [
                'ok' => false,
                'mensaje' => 'Ya hay una subida de stock en curso.',
                'subidas' => [],
            ];
        }

        $ids = [];
        try {
            foreach ($tiendas as $tienda) {
                if ($origen === TiendanubeStockSubida::ORIGEN_CRON && $this->cronYaCorrioHoy($tienda)) {
                    continue;
                }
                $ids[] = $this->subirTienda($tienda, $origen, $usuarioId);
            }
        } finally {
            $lock->release();
        }

        return [
            'ok' => true,
            'mensaje' => $ids === []
                ? 'La subida de hoy ya se había lanzado.'
                : 'Subida finalizada.',
            'subidas' => $ids,
        ];
    }

    /**
     * @param  array{
     *   store_id:string,
     *   nombre:string,
     *   marketplace_codigo:int,
     *   hora_subida:string,
     *   deposito_ids:list<int>,
     *   listaprecio_precio_id:int,
     *   listaprecio_oferta_id:int
     * }  $tienda
     */
    private function subirTienda(array $tienda, string $origen, ?int $usuarioId): int
    {
        $subida = TiendanubeStockSubida::query()->create([
            'store_id' => $tienda['store_id'],
            'origen' => $origen,
            'usuario_id' => $usuarioId && $usuarioId > 0 ? $usuarioId : null,
            'hora_programada' => $tienda['hora_subida'],
            'marketplace_codigo' => $tienda['marketplace_codigo'],
            'inicio_at' => Carbon::now(),
            'estado' => TiendanubeStockSubida::ESTADO_PROCESO,
        ]);

        try {
            if ($tienda['deposito_ids'] === []) {
                $this->cerrar($subida, TiendanubeStockSubida::ESTADO_ERROR, 'No hay depósitos configurados para el stock.');

                return (int) $subida->id;
            }
            if ($tienda['listaprecio_precio_id'] <= 0) {
                $this->cerrar($subida, TiendanubeStockSubida::ESTADO_ERROR, 'Falta la lista de precio.');

                return (int) $subida->id;
            }

            $articulos = TiendanubeStockCatalogoSupport::articulosMarketplace($tienda['marketplace_codigo']);
            if ($articulos === []) {
                $this->cerrar($subida, TiendanubeStockSubida::ESTADO_OK, 'No hay artículos con ese marketplace.');

                return (int) $subida->id;
            }

            $ids = array_keys($articulos);
            $stock = TiendanubeStockCatalogoSupport::stockPorVariante($tienda['deposito_ids'], $ids);
            $precios = TiendanubeStockCatalogoSupport::precios(
                $ids,
                $tienda['listaprecio_precio_id'],
                $tienda['listaprecio_oferta_id']
            );
            if ($origen === TiendanubeStockSubida::ORIGEN_SIMULACION) {
                $this->previsualizarDesdeErp($subida, $articulos, $stock, $precios);
                $this->cerrarConConteos($subida);

                return (int) $subida->id;
            }

            $conocidos = TiendanubeStockCatalogoSupport::skusConocidosTienda($ids);
            $api = TiendanubeApiClient::paraEscrituraProductos($tienda['store_id'])->sinRegistrarSalud();

            foreach ($articulos as $articuloId => $articulo) {
                $this->subirArticulo(
                    $subida,
                    $api,
                    (int) $articuloId,
                    $articulo,
                    $stock,
                    $precios[(int) $articuloId] ?? ['precio' => null, 'oferta' => null],
                    $conocidos[(int) $articuloId] ?? []
                );
            }

            $this->cerrarConConteos($subida);
        } catch (Throwable $e) {
            $this->cerrar($subida, TiendanubeStockSubida::ESTADO_ERROR, mb_substr($e->getMessage(), 0, 500));
        }

        return (int) $subida->id;
    }

    /**
     * Arma el listado con stock y precios de anitaERP, sin llamar a Tiendanube.
     *
     * @param  array<int, array{sku:string, combinaciones:list<string>}>  $articulos
     * @param  array<string, int>  $stock
     * @param  array<int, array{precio:?float, oferta:?float}>  $precios
     */
    private function previsualizarDesdeErp(
        TiendanubeStockSubida $subida,
        array $articulos,
        array $stock,
        array $precios,
    ): void {
        $porArticulo = [];
        foreach ($stock as $clave => $unidades) {
            $articuloId = (int) strtok((string) $clave, '|');
            $porArticulo[$articuloId][$clave] = (int) $unidades;
        }

        $batch = [];
        foreach ($articulos as $articuloId => $articulo) {
            $articuloId = (int) $articuloId;
            $sku = trim($articulo['sku']);
            $combinaciones = $articulo['combinaciones'];
            if ($sku === '' || $combinaciones === []) {
                $batch[] = $this->filaPrevisualizacion($subida, [
                    'articulo_id' => $articuloId,
                    'sku' => $sku,
                    'estado' => TiendanubeStockSubidaLinea::ESTADO_OMITIDA,
                    'mensaje' => 'Sin código de combinación para armar el SKU.',
                ]);
                continue;
            }

            $asignadas = [];
            foreach ($combinaciones as $codigo) {
                $asignadas[TiendanubeStockCatalogoSupport::clave($codigo)] = $codigo;
            }
            $precio = $precios[$articuloId] ?? ['precio' => null, 'oferta' => null];
            $precioTxt = $precio['precio'] !== null
                ? number_format((float) $precio['precio'], 2, '.', '')
                : null;
            $ofertaTxt = $precioTxt !== null
                ? number_format((float) ($precio['oferta'] ?? 0), 2, '.', '')
                : null;
            $avisoPrecio = $precioTxt === null ? 'Sin precio en la lista.' : null;

            $alguna = false;
            foreach ($porArticulo[$articuloId] ?? [] as $clave => $unidades) {
                $partes = explode('|', (string) $clave);
                $combClave = $partes[1] ?? '';
                $talle = $partes[2] ?? '';
                if ($talle === '' || ! isset($asignadas[$combClave])) {
                    continue;
                }
                $alguna = true;
                $codigoVisible = $asignadas[$combClave];
                [$stockEnviar, $avisoStock] = self::stockNoNegativo((int) $unidades);
                $avisoLinea = trim(implode(' ', array_filter([$avisoPrecio, $avisoStock])));
                $batch[] = $this->filaPrevisualizacion($subida, [
                    'articulo_id' => $articuloId,
                    'sku' => $sku,
                    'variante_sku' => $sku.'-'.$codigoVisible.'-'.$talle,
                    'combinacion_codigo' => $codigoVisible,
                    'talle' => $talle,
                    'stock' => $stockEnviar,
                    'precio' => $precioTxt,
                    'precio_promocional' => $ofertaTxt,
                    'estado' => TiendanubeStockSubidaLinea::ESTADO_PREVISTA,
                    'mensaje' => $avisoLinea !== '' ? $avisoLinea : null,
                ]);
                if (count($batch) >= 400) {
                    DB::table('tiendanube_stock_subida_linea')->insert($batch);
                    $batch = [];
                }
            }

            if (! $alguna) {
                $batch[] = $this->filaPrevisualizacion($subida, [
                    'articulo_id' => $articuloId,
                    'sku' => $sku,
                    'estado' => TiendanubeStockSubidaLinea::ESTADO_OMITIDA,
                    'mensaje' => 'Sin stock por talle en los depósitos configurados.',
                ]);
            }
        }

        if ($batch !== []) {
            DB::table('tiendanube_stock_subida_linea')->insert($batch);
        }
    }

    /**
     * @param  array<string, mixed>  $datos
     * @return array<string, mixed>
     */
    private function filaPrevisualizacion(TiendanubeStockSubida $subida, array $datos): array
    {
        $ahora = Carbon::now();

        return [
            'subida_id' => $subida->id,
            'articulo_id' => $datos['articulo_id'] ?? null,
            'sku' => $datos['sku'] ?? null,
            'variante_sku' => $datos['variante_sku'] ?? null,
            'combinacion_codigo' => $datos['combinacion_codigo'] ?? null,
            'talle' => $datos['talle'] ?? null,
            'stock' => $datos['stock'] ?? null,
            'precio' => $datos['precio'] ?? null,
            'precio_promocional' => $datos['precio_promocional'] ?? null,
            'estado' => (string) ($datos['estado'] ?? TiendanubeStockSubidaLinea::ESTADO_PREVISTA),
            'mensaje' => isset($datos['mensaje']) ? mb_substr((string) $datos['mensaje'], 0, 500) : null,
            'created_at' => $ahora,
            'updated_at' => $ahora,
        ];
    }

    /**
     * @param  array{sku:string, combinaciones:list<string>}  $articulo
     * @param  array<string, int>  $stock
     * @param  array{precio:?float, oferta:?float}  $precio
     * @param  list<string>  $skusConocidos
     */
    private function subirArticulo(
        TiendanubeStockSubida $subida,
        TiendanubeApiClient $api,
        int $articuloId,
        array $articulo,
        array $stock,
        array $precio,
        array $skusConocidos,
        bool $simular = false,
    ): void {
        $sku = trim($articulo['sku']);
        $combinaciones = $articulo['combinaciones'];
        if ($sku === '' || $combinaciones === []) {
            $this->linea($subida, [
                'articulo_id' => $articuloId,
                'sku' => $sku,
                'estado' => TiendanubeStockSubidaLinea::ESTADO_OMITIDA,
                'mensaje' => 'Sin código de combinación para armar el SKU de Tiendanube.',
            ]);

            return;
        }

        $asignadas = [];
        foreach ($combinaciones as $codigo) {
            $asignadas[TiendanubeStockCatalogoSupport::clave($codigo)] = $codigo;
        }

        $producto = $this->buscarProducto($api, $sku, $combinaciones, $stock, $articuloId, $skusConocidos);
        if ($producto === null) {
            $this->linea($subida, [
                'articulo_id' => $articuloId,
                'sku' => $sku,
                'estado' => TiendanubeStockSubidaLinea::ESTADO_ERROR,
                'mensaje' => 'Producto no encontrado en Tiendanube.',
            ]);

            return;
        }

        $cubiertas = [];
        $payload = [];
        $previstas = [];
        foreach ($producto['variants'] as $variante) {
            $skuVariante = trim((string) ($variante['sku'] ?? ''));
            $variantId = (int) ($variante['id'] ?? 0);
            $partes = TiendanubeStockCatalogoSupport::partesVariante($skuVariante, $sku);
            if ($variantId <= 0 || $partes === null) {
                continue;
            }
            $claveComb = TiendanubeStockCatalogoSupport::clave($partes['combinacion']);
            if (! isset($asignadas[$claveComb])) {
                continue;
            }
            $claveStock = TiendanubeStockCatalogoSupport::claveStock($articuloId, $partes['combinacion'], $partes['talle']);
            [$unidades, $avisoStock] = self::stockNoNegativo((int) ($stock[$claveStock] ?? 0));
            $cubiertas[$claveStock] = true;
            $item = [
                'id' => $variantId,
                'inventory_levels' => [['stock' => $unidades]],
            ];
            $precioTxt = null;
            $ofertaTxt = null;
            $aviso = null;
            if ($precio['precio'] !== null) {
                $precioTxt = number_format((float) $precio['precio'], 2, '.', '');
                $ofertaTxt = number_format((float) ($precio['oferta'] ?? 0), 2, '.', '');
                $item['price'] = $precioTxt;
                $item['compare_at_price'] = $precioTxt;
                $item['promotional_price'] = $ofertaTxt;
            } else {
                $aviso = $simular
                    ? 'Sin precio en la lista; se enviaría solo el stock.'
                    : 'Sin precio en la lista; se actualizó solo el stock.';
            }
            if ($simular) {
                $hoy = self::textoHoyEnTienda($variante);
                $aviso = trim($hoy.($aviso !== null ? ' '.$aviso : '').' No se envió.');
            }
            if ($avisoStock !== null) {
                $aviso = trim(($aviso !== null ? $aviso.' ' : '').$avisoStock);
            }
            $payload[] = $item;
            $previstas[] = [
                'articulo_id' => $articuloId,
                'sku' => $sku,
                'variante_sku' => $skuVariante,
                'combinacion_codigo' => $partes['combinacion'],
                'talle' => $partes['talle'],
                'stock' => $unidades,
                'precio' => $precioTxt,
                'precio_promocional' => $ofertaTxt,
                'mensaje' => $aviso,
            ];
        }

        foreach ($stock as $clave => $unidades) {
            if ($unidades <= 0 || isset($cubiertas[$clave])) {
                continue;
            }
            if (! str_starts_with($clave, $articuloId.'|')) {
                continue;
            }
            $partesClave = explode('|', $clave);
            $combClave = $partesClave[1] ?? '';
            $talleClave = $partesClave[2] ?? '';
            if (! isset($asignadas[$combClave])) {
                continue;
            }
            $codigoVisible = $asignadas[$combClave];
            $this->linea($subida, [
                'articulo_id' => $articuloId,
                'sku' => $sku,
                'variante_sku' => $sku.'-'.$codigoVisible.'-'.$talleClave,
                'combinacion_codigo' => $codigoVisible,
                'talle' => $talleClave,
                'stock' => $unidades,
                'estado' => TiendanubeStockSubidaLinea::ESTADO_ERROR,
                'mensaje' => 'La variante no está en Tiendanube.',
            ]);
        }

        if ($payload === []) {
            $this->linea($subida, [
                'articulo_id' => $articuloId,
                'sku' => $sku,
                'estado' => TiendanubeStockSubidaLinea::ESTADO_OMITIDA,
                'mensaje' => 'El producto no tiene variantes de las combinaciones marcadas.',
            ]);

            return;
        }

        if ($simular) {
            foreach ($previstas as $prevista) {
                $prevista['estado'] = TiendanubeStockSubidaLinea::ESTADO_PREVISTA;
                $this->linea($subida, $prevista);
            }

            return;
        }

        $respuesta = $this->patchVariantes($api, (int) $producto['id'], $payload);
        $estado = ($respuesta['ok'] ?? false)
            ? TiendanubeStockSubidaLinea::ESTADO_OK
            : TiendanubeStockSubidaLinea::ESTADO_ERROR;
        $error = $estado === TiendanubeStockSubidaLinea::ESTADO_OK
            ? null
            : mb_substr((string) ($respuesta['error'] ?? 'Error al actualizar variantes'), 0, 500);
        if ($error !== null && str_contains($error, 'Missing required scope')) {
            throw new \RuntimeException($error);
        }

        foreach ($previstas as $prevista) {
            if ($error !== null) {
                $prevista['mensaje'] = $error;
            }
            $prevista['estado'] = $estado;
            $this->linea($subida, $prevista);
        }
    }

    /**
     * @param  list<string>  $combinaciones
     * @param  array<string, int>  $stock
     * @param  list<string>  $skusConocidos
     * @return array{id:int, variants:list<array<string, mixed>>}|null
     */
    private function buscarProducto(
        TiendanubeApiClient $api,
        string $sku,
        array $combinaciones,
        array $stock,
        int $articuloId,
        array $skusConocidos,
    ): ?array {
        $candidatos = [];
        foreach ($skusConocidos as $conocido) {
            $candidatos[$conocido] = $conocido;
        }
        foreach ($combinaciones as $codigo) {
            foreach ($stock as $clave => $unidades) {
                if (! str_starts_with($clave, $articuloId.'|'.TiendanubeStockCatalogoSupport::clave($codigo).'|')) {
                    continue;
                }
                $talle = explode('|', $clave)[2] ?? '';
                if ($talle === '') {
                    continue;
                }
                $armado = $sku.'-'.$codigo.'-'.$talle;
                $candidatos[$armado] = $armado;
                if (count($candidatos) >= 6) {
                    break 2;
                }
            }
        }
        if ($candidatos === [] && $combinaciones !== []) {
            $candidatos[$sku.'-'.$combinaciones[0]] = $sku.'-'.$combinaciones[0];
        }

        $intentos = 0;
        foreach ($candidatos as $candidato) {
            if ($intentos >= 4) {
                break;
            }
            $intentos++;
            $resp = $this->llamar(fn () => $api->get('products/sku/'.rawurlencode($candidato)));
            if (! ($resp['ok'] ?? false) || ! is_array($resp['data'] ?? null)) {
                continue;
            }
            $id = (int) ($resp['data']['id'] ?? 0);
            $variants = $resp['data']['variants'] ?? [];
            if ($id <= 0 || ! is_array($variants)) {
                continue;
            }

            return [
                'id' => $id,
                'variants' => array_values(array_filter($variants, 'is_array')),
            ];
        }

        return null;
    }

    /**
     * @param  list<array<string, mixed>>  $payload
     * @return array{ok:bool, error?:string}
     */
    private function patchVariantes(TiendanubeApiClient $api, int $productId, array $payload): array
    {
        $ultimoError = 'Error al actualizar variantes';
        foreach (array_chunk($payload, 40) as $lote) {
            $resp = $this->llamar(fn () => $api->patch('products/'.$productId.'/variants', $lote));
            if (! ($resp['ok'] ?? false)) {
                return [
                    'ok' => false,
                    'error' => (string) ($resp['error'] ?? $ultimoError),
                ];
            }
        }

        return ['ok' => true];
    }

    /**
     * @param  callable(): array{ok:bool,status:int,data?:mixed,error?:string}  $llamada
     * @return array{ok:bool,status:int,data?:mixed,error?:string}
     */
    private function llamar(callable $llamada): array
    {
        $resp = ['ok' => false, 'status' => 0, 'error' => 'Sin respuesta'];
        for ($intento = 0; $intento < 3; $intento++) {
            $resp = $llamada();
            usleep(450000);
            if (($resp['status'] ?? 0) !== 429) {
                return $resp;
            }
            sleep(2);
        }

        return $resp;
    }

    /**
     * Tiendanube rechaza el lote del producto si un talle va con stock menor a cero.
     *
     * @return array{0:int, 1:?string}
     */
    private static function stockNoNegativo(int $unidades): array
    {
        if ($unidades >= 0) {
            return [$unidades, null];
        }

        return [0, 'Stock negativo; se sube 0.'];
    }

    /**
     * @param  array<string, mixed>  $variante
     */
    private static function textoHoyEnTienda(array $variante): string
    {
        $stock = $variante['stock'] ?? null;
        if ($stock === null && isset($variante['inventory_levels'][0]['stock'])) {
            $stock = $variante['inventory_levels'][0]['stock'];
        }
        $precio = $variante['price'] ?? null;
        $promo = $variante['promotional_price'] ?? null;
        $partes = ['Hoy en la tienda:'];
        $partes[] = 'stock '.(is_numeric($stock) ? (string) (int) $stock : 's/d');
        $partes[] = 'precio '.(is_numeric($precio) ? number_format((float) $precio, 2, ',', '.') : 's/d');
        if (is_numeric($promo) && (float) $promo > 0) {
            $partes[] = 'promo '.number_format((float) $promo, 2, ',', '.');
        }

        return implode(' ', $partes).'.';
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private function linea(TiendanubeStockSubida $subida, array $datos): void
    {
        $ahora = Carbon::now();
        DB::table('tiendanube_stock_subida_linea')->insert([
            'subida_id' => $subida->id,
            'articulo_id' => $datos['articulo_id'] ?? null,
            'sku' => $datos['sku'] ?? null,
            'variante_sku' => $datos['variante_sku'] ?? null,
            'combinacion_codigo' => $datos['combinacion_codigo'] ?? null,
            'talle' => $datos['talle'] ?? null,
            'stock' => $datos['stock'] ?? null,
            'precio' => $datos['precio'] ?? null,
            'precio_promocional' => $datos['precio_promocional'] ?? null,
            'estado' => (string) ($datos['estado'] ?? TiendanubeStockSubidaLinea::ESTADO_ERROR),
            'mensaje' => isset($datos['mensaje']) ? mb_substr((string) $datos['mensaje'], 0, 500) : null,
            'created_at' => $ahora,
            'updated_at' => $ahora,
        ]);
    }

    private function cerrarConConteos(TiendanubeStockSubida $subida): void
    {
        $conteos = DB::table('tiendanube_stock_subida_linea')
            ->where('subida_id', $subida->id)
            ->selectRaw('estado, COUNT(*) as c')
            ->groupBy('estado')
            ->pluck('c', 'estado');

        $ok = (int) ($conteos[TiendanubeStockSubidaLinea::ESTADO_OK] ?? 0)
            + (int) ($conteos[TiendanubeStockSubidaLinea::ESTADO_PREVISTA] ?? 0);
        $error = (int) ($conteos[TiendanubeStockSubidaLinea::ESTADO_ERROR] ?? 0);
        $omitidas = (int) ($conteos[TiendanubeStockSubidaLinea::ESTADO_OMITIDA] ?? 0);
        $simular = $subida->origen === TiendanubeStockSubida::ORIGEN_SIMULACION;

        $estado = TiendanubeStockSubida::ESTADO_OK;
        $mensaje = $simular ? 'Simulación. No se escribió en Tiendanube.' : null;
        if ($ok === 0 && $error > 0) {
            $estado = TiendanubeStockSubida::ESTADO_ERROR;
            $mensaje = $simular
                ? 'Simulación. No se escribiría ninguna variante. No se escribió en Tiendanube.'
                : 'No se actualizó ninguna variante.';
        } elseif ($error > 0) {
            $estado = TiendanubeStockSubida::ESTADO_PARCIAL;
            if ($simular) {
                $mensaje = 'Simulación con variantes que no están en la tienda. No se escribió en Tiendanube.';
            }
        } elseif ($ok === 0 && $omitidas > 0) {
            $estado = TiendanubeStockSubida::ESTADO_ERROR;
            $mensaje = $simular
                ? 'Simulación. No se escribiría ninguna variante. No se escribió en Tiendanube.'
                : 'No se actualizó ninguna variante.';
        }

        $subida->variantes_ok = $ok;
        $subida->variantes_error = $error;
        $subida->variantes_omitidas = $omitidas;
        $subida->estado = $estado;
        $subida->mensaje = $mensaje;
        $subida->fin_at = Carbon::now();
        $subida->save();
    }

    private function cerrar(TiendanubeStockSubida $subida, string $estado, ?string $mensaje): void
    {
        $subida->estado = $estado;
        $subida->mensaje = $mensaje !== null ? mb_substr($mensaje, 0, 500) : null;
        $subida->fin_at = Carbon::now();
        $subida->save();
    }

    /**
     * @param  array{store_id:string, hora_subida:string}  $tienda
     */
    private function cronYaCorrioHoy(array $tienda): bool
    {
        return TiendanubeStockCatalogoSupport::cronYaCorrioHoy(
            $tienda['store_id'],
            $tienda['hora_subida']
        );
    }

    private function cerrarCorridasColgadas(): void
    {
        foreach (TiendanubeStockSubida::query()
            ->where('estado', TiendanubeStockSubida::ESTADO_PROCESO)
            ->where('inicio_at', '<', Carbon::now()->subHours(3))
            ->get() as $corrida) {
            $corrida->estado = TiendanubeStockSubida::ESTADO_ERROR;
            $corrida->mensaje = 'La subida quedó interrumpida.';
            $corrida->fin_at = Carbon::now();
            $corrida->save();
        }
    }
}
