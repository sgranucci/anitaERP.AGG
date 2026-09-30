<?php

namespace App\Http\Controllers\Ventas\Tiendanube;

use App\Http\Controllers\Controller;
use App\Models\Caja\Cuentacaja;
use App\Models\Stock\Depmae;
use App\Models\Stock\Listaprecio;
use App\Models\Ventas\Marketplace;
use App\Models\Ventas\Puntoventa;
use App\Models\Ventas\TiendanubeConfiguracion;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Support\Stock\ArticuloSkuMatchSupport;
use App\Support\Ventas\Tiendanube\TiendanubeConfiguracionSupport;
use App\Support\Ventas\Tiendanube\TiendanubeTiendasSupport;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class TiendanubeConfiguracionController extends Controller
{
    public function __construct(
        private readonly EmpresaRepositoryInterface $empresaRepository,
    ) {}

    public function editar(Request $request)
    {
        can('editar-configuracion-tiendanube');

        $tiendas = TiendanubeTiendasSupport::paraVista();
        $storeId = trim((string) $request->query('store_id', ''));
        $ids = array_column($tiendas, 'store_id');
        if ($storeId === '' || ! in_array($storeId, $ids, true)) {
            $storeId = $ids[0] ?? TiendanubeConfiguracionSupport::storeIdFerli();
        }
        $tiendaNombre = TiendanubeTiendasSupport::nombre($storeId);

        $propia = TiendanubeConfiguracionSupport::cabeceraPropia($storeId);
        $configPropia = $propia !== null;
        if ($propia) {
            $config = $propia;
            $config->load(['puntoventa', 'deposito']);
        } else {
            $config = new TiendanubeConfiguracion([
                'store_id' => $storeId,
                'usocuentacaja_nombre' => 'TIENDA NUBE',
            ]);
        }

        $lista = null;
        if ((int) $config->listaprecio_id > 0) {
            $lista = Listaprecio::query()->find((int) $config->listaprecio_id);
        }

        $gateways = TiendanubeConfiguracionSupport::gateways($storeId, true);
        if ($gateways->isEmpty() && $storeId === TiendanubeConfiguracionSupport::storeIdFerli()) {
            foreach ((array) config('tiendanube.gateway_cuentacaja', []) as $key => $codigo) {
                $cuenta = Cuentacaja::query()->where('codigo', (string) $codigo)->first()
                    ?? (is_numeric($codigo) ? Cuentacaja::query()->find((int) $codigo) : null);
                if (! $cuenta) {
                    continue;
                }
                $gateways->push((object) [
                    'gateway_key' => (string) $key,
                    'cuentacaja_id' => (int) $cuenta->id,
                    'cuentacaja' => $cuenta,
                ]);
            }
        }

        $pares = TiendanubeConfiguracionSupport::paresPuntoventaDeposito($storeId, true);
        $empresa_query = $this->empresaRepository->allFiltrado();
        $skuEnvio = (string) old('articulo_envio_sku', $config->articulo_envio_sku);
        $skuDescuento = (string) old('articulo_descuento_sku', $config->articulo_descuento_sku);

        return view('ventas.tiendanube_configuracion.editar', [
            'config' => $config,
            'lista' => $lista,
            'gateways' => $gateways,
            'pares' => $pares,
            'empresa_query' => $empresa_query,
            'tiendas' => $tiendas,
            'storeId' => $storeId,
            'tiendaNombre' => $tiendaNombre,
            'configPropia' => $configPropia,
            'skuEnvio' => $skuEnvio,
            'skuDescuento' => $skuDescuento,
            'articuloEnvio' => ArticuloSkuMatchSupport::resolverCanonico($skuEnvio),
            'articuloDescuento' => ArticuloSkuMatchSupport::resolverCanonico($skuDescuento),
            'marketplaceStock' => $this->marketplaceStock($config),
            'listaPrecioWeb' => $this->lista((int) old('listaprecio_precio_id', $config->listaprecio_precio_id)),
            'listaPrecioOferta' => $this->lista((int) old('listaprecio_oferta_id', $config->listaprecio_oferta_id)),
            'stockDepositos' => $this->stockDepositos($storeId),
        ]);
    }

    public function actualizar(Request $request)
    {
        can('actualizar-configuracion-tiendanube');

        $data = $request->validate([
            'store_id' => 'required|string|max:32',
            'empresa_id' => 'nullable|integer|exists:empresa,id',
            'puntoventa_id' => 'nullable|integer|exists:puntoventa,id',
            'deposito_id' => 'nullable|integer|exists:depmae,id',
            'listaprecio_id' => 'nullable|integer|exists:listaprecio,id',
            'articulo_envio_sku' => 'nullable|string|max:40',
            'articulo_descuento_sku' => 'nullable|string|max:40',
            'usocuentacaja_nombre' => 'nullable|string|max:80',
            'sube_stock' => 'nullable|boolean',
            'stock_marketplace_codigo' => 'nullable|integer|min:1',
            'hora_subida' => 'nullable|date_format:H:i',
            'listaprecio_precio_id' => 'nullable|integer|exists:listaprecio,id',
            'listaprecio_oferta_id' => 'nullable|integer|exists:listaprecio,id',
            'stock_deposito_id' => 'nullable|array',
            'stock_deposito_id.*' => 'nullable|integer',
            'gateway_key' => 'nullable|array',
            'gateway_key.*' => 'nullable|string|max:80',
            'gateway_cuentacaja_id' => 'nullable|array',
            'gateway_cuentacaja_id.*' => 'nullable|integer',
            'par_puntoventa_id' => 'nullable|array',
            'par_puntoventa_id.*' => 'nullable|integer',
            'par_deposito_id' => 'nullable|array',
            'par_deposito_id.*' => 'nullable|integer',
            'par_default' => 'nullable|integer|min:0',
        ]);

        $gateways = [];
        $keys = $data['gateway_key'] ?? [];
        $cuentas = $data['gateway_cuentacaja_id'] ?? [];
        foreach ($keys as $i => $key) {
            $key = strtolower(trim((string) $key));
            $cuentaId = (int) ($cuentas[$i] ?? 0);
            if ($key === '' && $cuentaId <= 0) {
                continue;
            }
            if ($key === '' || $cuentaId <= 0) {
                throw ValidationException::withMessages([
                    'gateway_key' => 'Cada fila de gateway debe tener clave y cuenta de caja.',
                ]);
            }
            if (! Cuentacaja::query()->whereKey($cuentaId)->exists()) {
                throw ValidationException::withMessages([
                    'gateway_cuentacaja_id' => "Cuenta de caja inválida en gateway «{$key}».",
                ]);
            }
            $gateways[] = ['gateway_key' => $key, 'cuentacaja_id' => $cuentaId];
        }

        $pares = [];
        $pvs = $data['par_puntoventa_id'] ?? [];
        $deps = $data['par_deposito_id'] ?? [];
        $defaultIdx = (int) ($data['par_default'] ?? -1);
        foreach ($pvs as $i => $pvId) {
            $pvId = (int) $pvId;
            $depId = (int) ($deps[$i] ?? 0);
            if ($pvId <= 0 && $depId <= 0) {
                continue;
            }
            if ($pvId <= 0 || $depId <= 0) {
                throw ValidationException::withMessages([
                    'par_puntoventa_id' => 'Cada par debe tener punto de venta y depósito.',
                ]);
            }
            if (! Puntoventa::query()->whereKey($pvId)->exists()) {
                throw ValidationException::withMessages([
                    'par_puntoventa_id' => 'Punto de venta inválido en un par.',
                ]);
            }
            if (! Depmae::query()->whereKey($depId)->exists()) {
                throw ValidationException::withMessages([
                    'par_deposito_id' => 'Depósito inválido en un par.',
                ]);
            }
            $pares[] = [
                'puntoventa_id' => $pvId,
                'deposito_id' => $depId,
                'es_default' => $i === $defaultIdx,
            ];
        }

        if ($pares === []) {
            throw ValidationException::withMessages([
                'par_puntoventa_id' => 'Debe cargar al menos un par punto de venta / depósito.',
            ]);
        }

        $storeId = trim((string) ($data['store_id'] ?? ''));
        $ids = array_column(TiendanubeTiendasSupport::definidas(), 'store_id');
        if (! in_array($storeId, $ids, true)) {
            throw ValidationException::withMessages([
                'store_id' => 'La tienda no está configurada.',
            ]);
        }

        $subeStock = $request->boolean('sube_stock');
        $marketplaceCodigo = (int) ($data['stock_marketplace_codigo'] ?? 0);
        if ($subeStock) {
            if ($marketplaceCodigo <= 0 || ! Marketplace::query()->where('codigo', $marketplaceCodigo)->where('activo', true)->exists()) {
                throw ValidationException::withMessages([
                    'stock_marketplace_codigo' => 'El marketplace no existe o está inactivo.',
                ]);
            }
            if ((int) ($data['listaprecio_precio_id'] ?? 0) <= 0 || (int) ($data['listaprecio_oferta_id'] ?? 0) <= 0) {
                throw ValidationException::withMessages([
                    'listaprecio_precio_id' => 'Para subir stock hacen falta la lista de precio y la lista de oferta.',
                ]);
            }
        }
        $stockDepositos = [];
        foreach ($data['stock_deposito_id'] ?? [] as $depId) {
            $depId = (int) $depId;
            if ($depId <= 0) {
                continue;
            }
            if (! Depmae::query()->whereKey($depId)->exists()) {
                throw ValidationException::withMessages([
                    'stock_deposito_id' => 'Hay un depósito de stock inválido.',
                ]);
            }
            $stockDepositos[] = $depId;
        }
        if ($subeStock && $stockDepositos === []) {
            throw ValidationException::withMessages([
                'stock_deposito_id' => 'Indicá al menos un depósito de stock.',
            ]);
        }

        TiendanubeConfiguracionSupport::guardar([
            'empresa_id' => $data['empresa_id'] ?? null,
            'puntoventa_id' => $data['puntoventa_id'] ?? null,
            'deposito_id' => $data['deposito_id'] ?? null,
            'listaprecio_id' => $data['listaprecio_id'] ?? null,
            'articulo_envio_sku' => $this->skuArticuloExistente(
                $data['articulo_envio_sku'] ?? null,
                'articulo_envio_sku',
                'envío'
            ),
            'articulo_descuento_sku' => $this->skuArticuloExistente(
                $data['articulo_descuento_sku'] ?? null,
                'articulo_descuento_sku',
                'descuento'
            ),
            'usocuentacaja_nombre' => $data['usocuentacaja_nombre'] ?? 'TIENDA NUBE',
            'sube_stock' => $subeStock,
            'marketplace_codigo' => $marketplaceCodigo > 0 ? $marketplaceCodigo : 2,
            'hora_subida' => $data['hora_subida'] ?? '14:00',
            'listaprecio_precio_id' => $data['listaprecio_precio_id'] ?? null,
            'listaprecio_oferta_id' => $data['listaprecio_oferta_id'] ?? null,
            'stock_deposito_ids' => $stockDepositos,
        ], $gateways, $pares, $storeId);

        return redirect()
            ->route('editar_configuracion_tiendanube', ['store_id' => $storeId])
            ->with('mensaje', 'Configuración de '.TiendanubeTiendasSupport::nombre($storeId).' actualizada.');
    }

    private function skuArticuloExistente(?string $sku, string $campo, string $etiqueta): ?string
    {
        $sku = trim((string) $sku);
        if ($sku === '') {
            return null;
        }

        $articulo = ArticuloSkuMatchSupport::resolverCanonico($sku);
        if ($articulo === null) {
            throw ValidationException::withMessages([
                $campo => "No hay un artículo con el SKU de {$etiqueta}.",
            ]);
        }

        return (string) $articulo->sku;
    }

    private function lista(int $id): ?Listaprecio
    {
        return $id > 0 ? Listaprecio::query()->find($id) : null;
    }

    private function marketplaceStock(TiendanubeConfiguracion $config): ?Marketplace
    {
        $codigo = (int) old('stock_marketplace_codigo', $config->marketplace_codigo ?: 2);

        return $codigo > 0
            ? Marketplace::query()->where('codigo', $codigo)->where('activo', true)->first()
            : null;
    }

    /**
     * @return list<array{id:int, codigo:string, nombre:string}>
     */
    private function stockDepositos(string $storeId): array
    {
        $ids = old('stock_deposito_id');
        if (! is_array($ids)) {
            $ids = TiendanubeConfiguracionSupport::depositoIdsStock($storeId);
        }
        $out = [];
        foreach ($ids as $id) {
            $id = (int) $id;
            if ($id <= 0) {
                continue;
            }
            $dep = Depmae::query()->find($id);
            if (! $dep) {
                continue;
            }
            $out[] = [
                'id' => (int) $dep->id,
                'codigo' => (string) $dep->codigo,
                'nombre' => (string) $dep->nombre,
            ];
        }

        return $out;
    }
}
