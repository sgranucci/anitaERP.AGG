<?php

namespace App\Services\Stock;

use App\ApiAnita;
use App\Models\Stock\Depmae;
use App\Models\Stock\MovimientoStock;
use App\Support\Stock\MovimientoStockStkmovAnitaSupport;
use App\Support\Stock\AnitaStkmovClaveErpSupport;
use App\Support\Stock\RecepcionProveedorAnitaEscrituraSupport;
use App\Support\Stock\StockAnitaBridgeSupport;
use App\Support\Stock\DepmaeAnitaCodigoSupport;
use Auth;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Replica movimientos de stock ERP en Informix stkmov (sin numerador Anita).
 */
class MovimientoStockStkmovAnitaService
{
    public function __construct(
        private ApiAnita $apiAnita,
    ) {}

    /**
     * Inserta líneas stkmov para un movimientostock ERP. Idempotente por cabecera (tipo+sucursal+nro).
     */
    public function sincronizar(int $movimientoStockId, ?string $tipoStkmov = null): int
    {
        $movimiento = MovimientoStock::query()
            ->with([
                'tipotransaccion_stock',
                'articulos_movimiento.articulos.categorias',
            ])
            ->findOrFail($movimientoStockId);

        if (! MovimientoStockStkmovAnitaSupport::debeSincronizar($movimiento, $tipoStkmov)) {
            return 0;
        }

        $tipo = MovimientoStockStkmovAnitaSupport::resolverTipoStkmov($movimiento, $tipoStkmov);
        if ($tipo === null || $tipo === '') {
            return 0;
        }

        $empresaCodigo = $this->resolverEmpresaCodigoAnita($movimiento);
        $empresaId = $this->resolverEmpresaId($movimiento);
        $clave = MovimientoStockStkmovAnitaSupport::claveDesdeMovimiento($movimiento, $tipo, $empresaCodigo);
        if ($this->cabeceraExisteEnAnita($clave, $empresaId)) {
            return 0;
        }

        $lineas = $movimiento->articulos_movimiento
            ->filter(static fn ($linea) => abs((float) ($linea->cantidad ?? 0)) > 0.000001)
            ->values();

        if ($lineas->isEmpty()) {
            return 0;
        }

        $fechaAnita = (int) Carbon::parse($movimiento->fecha ?? now())->format('Ymd');
        $usuario = substr((string) (Auth::user()->usuario ?? Auth::user()->name ?? 'ERP'), 0, 8);
        $sistema = MovimientoStockStkmovAnitaSupport::sistemaVentas();
        $cfg = config('recepcion_proveedor.anita');
        $tabla = (string) ($cfg['tablas']['stock_movimiento'] ?? 'stkmov');
        $insertadas = 0;
        $orden = 0;

        foreach ($lineas as $linea) {
            $articulo = $linea->articulos;
            if ($articulo === null) {
                continue;
            }

            $sku = trim((string) ($articulo->sku ?? ''));
            if ($sku === '') {
                continue;
            }

            $depositoId = (int) ($linea->deposito_id ?? 0);
            $depositoAnita = DepmaeAnitaCodigoSupport::codigoDeposito($depositoId);
            if ($depositoAnita <= 0) {
                throw new \RuntimeException(
                    'Depósito '.$depositoId.' sin código Anita para stkmov (movimientostock '.$movimientoStockId.').'
                );
            }

            $codigoAgrupacion = (string) optional($articulo->categorias)->codigo;
            if ($codigoAgrupacion === '') {
                $codigoAgrupacion = '0';
            }

            $orden++;
            $insert = RecepcionProveedorAnitaEscrituraSupport::stkmovInsert(
                $clave,
                $fechaAnita,
                RecepcionProveedorAnitaEscrituraSupport::skuAnita13($sku),
                $codigoAgrupacion,
                $orden,
                '000000',
                $depositoAnita,
                AnitaStkmovClaveErpSupport::cantidadStkmov((float) $linea->cantidad),
                (float) ($linea->precio ?? 0),
                '1',
                $this->empresaCodigoAnita($depositoId),
                $usuario,
                $empresaId,
            );

            $this->apiAnita->apiCallEscritura(
                StockAnitaBridgeSupport::mergePayload([
                    'acc' => 'insert',
                    'sistema' => $sistema,
                    'tabla' => $tabla,
                    'campos' => $insert['campos'],
                    'valores' => $insert['valores'],
                ], $empresaId),
                'erp stkmov '.$tipo.' mov '.$movimientoStockId.' orden '.$orden
            );

            $insertadas++;
        }

        if ($insertadas > 0) {
            Log::info('MovimientoStockStkmovAnita: sincronizado', [
                'movimientostock_id' => $movimientoStockId,
                'stkv_tipo' => $tipo,
                'stkv_sucursal' => $clave['sucursal'],
                'stkv_nro' => $clave['nro'],
                'lineas' => $insertadas,
            ]);
        }

        return $insertadas;
    }

    /**
     * Borra cabecera previa (p. ej. sucursal legacy) y vuelve a insertar.
     */
    public function resincronizar(int $movimientoStockId, ?string $tipoStkmov = null, ?int $empresaCodigoAnita = null): int
    {
        $movimiento = MovimientoStock::query()
            ->with(['tipotransaccion_stock', 'articulos_movimiento.articulos.categorias'])
            ->findOrFail($movimientoStockId);

        $tipo = MovimientoStockStkmovAnitaSupport::resolverTipoStkmov($movimiento, $tipoStkmov);
        if ($tipo === null || $tipo === '') {
            return 0;
        }

        $empresaCodigo = $empresaCodigoAnita ?? $this->resolverEmpresaCodigoAnita($movimiento);
        $empresaId = $this->resolverEmpresaId($movimiento);
        $claveNueva = MovimientoStockStkmovAnitaSupport::claveDesdeMovimiento($movimiento, $tipo, $empresaCodigo);

        foreach ($this->clavesLegacyPosibles($movimiento, $tipo, $empresaCodigo) as $claveLegacy) {
            if ($this->cabeceraExisteEnAnita($claveLegacy, $empresaId)) {
                $this->eliminarCabecera($claveLegacy, $empresaId);
            }
        }

        if ($this->cabeceraExisteEnAnita($claveNueva, $empresaId)) {
            $this->eliminarCabecera($claveNueva, $empresaId);
        }

        return $this->sincronizar($movimientoStockId, $tipoStkmov);
    }

    /** @return list<array{tipo: string, letra: string, sucursal: int, nro: int}> */
    private function clavesLegacyPosibles(MovimientoStock $movimiento, string $tipo, ?int $empresaCodigo): array
    {
        $claves = [
            MovimientoStockStkmovAnitaSupport::claveDesdeMovimiento($movimiento, $tipo, null),
        ];
        // Sucursal errónea por suma aritmética (99+1=100) en lugar de concat (991).
        if ($empresaCodigo !== null && $empresaCodigo > 0) {
            $base = MovimientoStockStkmovAnitaSupport::sucursalErp();
            $claves[] = [
                'tipo' => strtoupper(substr($tipo, 0, 3)),
                'letra' => MovimientoStockStkmovAnitaSupport::letraErp(),
                'sucursal' => $base + $empresaCodigo,
                'nro' => (int) $movimiento->id,
            ];
        }

        return $claves;
    }

    private function resolverEmpresaCodigoAnita(MovimientoStock $movimiento): ?int
    {
        $linea = $movimiento->articulos_movimiento->first();
        if ($linea === null) {
            return null;
        }

        $codigo = $this->empresaCodigoAnita((int) ($linea->deposito_id ?? 0));

        return $codigo > 0 ? $codigo : null;
    }

    private function resolverEmpresaId(MovimientoStock $movimiento): int
    {
        $linea = $movimiento->articulos_movimiento->first();
        if ($linea === null) {
            return 1;
        }

        $deposito = Depmae::query()->find((int) ($linea->deposito_id ?? 0));

        return max(1, (int) ($deposito?->empresa_id ?? 1));
    }

    /** @param array{tipo: string, letra: string, sucursal: int, nro: int} $clave */
    public function eliminarCabecera(array $clave, int $empresaId = 1): void
    {
        $sistema = MovimientoStockStkmovAnitaSupport::sistemaVentas();
        $cfg = config('recepcion_proveedor.anita');
        $tabla = (string) ($cfg['tablas']['stock_movimiento'] ?? 'stkmov');

        $this->apiAnita->apiCallEscritura(
            StockAnitaBridgeSupport::mergePayload([
                'acc' => 'delete',
                'sistema' => $sistema,
                'tabla' => $tabla,
                'whereArmado' => MovimientoStockStkmovAnitaSupport::whereCabecera($clave),
            ], $empresaId),
            'erp stkmov delete '.$clave['tipo'].' '.$clave['nro']
        );
    }

    /** @param array{tipo: string, letra: string, sucursal: int, nro: int} $clave */
    private function cabeceraExisteEnAnita(array $clave, int $empresaId = 1): bool
    {
        try {
            $raw = $this->apiAnita->apiCall(StockAnitaBridgeSupport::mergePayload([
                'acc' => 'list',
                'sistema' => MovimientoStockStkmovAnitaSupport::sistemaVentas(),
                'tabla' => (string) config('recepcion_proveedor.anita.tablas.stock_movimiento', 'stkmov'),
                'campos' => 'stkv_nro_orden',
                'whereArmado' => MovimientoStockStkmovAnitaSupport::whereCabecera($clave),
            ], $empresaId));
        } catch (\Throwable $e) {
            Log::warning('MovimientoStockStkmovAnita: error consultando cabecera', [
                'clave' => $clave,
                'error' => $e->getMessage(),
            ]);

            return false;
        }

        return count(ApiAnita::decodificarListaFilas($raw)) > 0;
    }

    private function empresaCodigoAnita(int $depositoId): int
    {
        if ($depositoId <= 0) {
            return 0;
        }

        $deposito = Depmae::query()->with('empresas')->find($depositoId);
        $codigo = trim((string) ($deposito?->empresas?->codigo ?? ''));

        return $codigo !== '' ? (int) $codigo : 0;
    }
}
