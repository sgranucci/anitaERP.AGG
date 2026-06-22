<?php

namespace App\Services\Stock;

use App\Models\Compras\Ordencompra;
use App\Models\Compras\Proveedor;
use App\Models\Configuracion\Empresa;
use App\Models\Configuracion\Moneda;
use App\Models\Contable\Centrocosto;
use App\Models\Seguridad\Usuario;
use App\Models\Stock\Articulo;
use App\Models\Stock\Recepcion_Proveedor;
use App\Models\Stock\Recepcion_Proveedor_Articulo;
use App\Support\Stock\RecepcionProveedorAnitaImportSupport;
use App\Support\Stock\RecepcionProveedorEstados;
use App\Support\Stock\RecepcionProveedorVisibilidadSupport;
use Illuminate\Support\Facades\DB;

class RecepcionProveedorImportarDesdeAnitaService
{
    /** @var array<string, int|null> */
    private array $cacheEmpresa = [];

    /** @var array<string, int|null> */
    private array $cacheProveedor = [];

    /** @var array<string, int|null> */
    private array $cacheOc = [];

    /** @var array<string, int|null> */
    private array $cacheArticulo = [];

    /** @var array<int, int|null> */
    private array $cacheCentrocosto = [];

    /**
     * @return array{importadas: int, omitidas: int, sin_oc: int, sin_proveedor: int, sin_empresa: int, lineas: int}
     */
    public function importarRecepmae(int $fechaDesdeAnita, ?int $fechaHastaAnita = null, bool $dryRun = false): array
    {
        $stats = [
            'importadas' => 0,
            'omitidas' => 0,
            'sin_oc' => 0,
            'sin_proveedor' => 0,
            'sin_empresa' => 0,
            'lineas' => 0,
        ];

        $usuarioId = (int) (Usuario::query()->orderBy('id')->value('id') ?? 1);
        $monedaDefault = (int) (Moneda::query()->orderBy('id')->value('id') ?? 1);

        $lineasPorRecepcion = $this->cargarLineasAgrupadas($fechaDesdeAnita, $fechaHastaAnita);
        $cabeceras = RecepcionProveedorAnitaImportSupport::listarRecepmae($fechaDesdeAnita, $fechaHastaAnita);

        foreach ($cabeceras as $cab) {
            $sucursal = (int) ($cab->recm_sucursal ?? 0);
            $nro = (int) ($cab->recm_nro ?? 0);
            $numeroOc = (int) ($cab->recm_com_nro ?? 0);
            $codProveedor = trim((string) ($cab->recm_proveedor ?? ''));

            if ($nro <= 0 || $sucursal <= 0) {
                $stats['omitidas']++;

                continue;
            }

            $empresaId = $this->resolverEmpresaId($sucursal);
            if (! $empresaId) {
                $stats['sin_empresa']++;

                continue;
            }

            if (Recepcion_Proveedor::query()
                ->where('empresa_id', $empresaId)
                ->where('numerorecepcion', $nro)
                ->exists()) {
                $stats['omitidas']++;

                continue;
            }

            $proveedorId = $this->resolverProveedorId($codProveedor);
            if (! $proveedorId) {
                $stats['sin_proveedor']++;

                continue;
            }

            $ordencompraId = null;
            $ocCentrocostoId = null;
            if ($numeroOc > 0) {
                $oc = $this->resolverOrdencompra($numeroOc, $empresaId);
                if ($oc) {
                    $ordencompraId = $oc->id;
                    $ocCentrocostoId = (int) ($oc->centrocosto_id ?? 0) ?: null;
                } else {
                    $stats['sin_oc']++;
                }
            }

            $lineasAnita = $lineasPorRecepcion[$sucursal.'-'.$nro] ?? [];
            if ($lineasAnita === []) {
                $stats['omitidas']++;

                continue;
            }

            if ($dryRun) {
                $stats['importadas']++;
                $stats['lineas'] += count($lineasAnita);

                continue;
            }

            try {
                DB::transaction(function () use (
                    $cab, $empresaId, $nro, $sucursal, $ordencompraId, $ocCentrocostoId, $proveedorId,
                    $usuarioId, $monedaDefault, $lineasAnita, &$stats
                ) {
                    $fecha = RecepcionProveedorAnitaImportSupport::fechaDesdeAnita((int) ($cab->recm_fecha ?? 0));

                    $recepcion = Recepcion_Proveedor::create([
                        'ordencompra_id' => $ordencompraId,
                        'tipo' => Recepcion_Proveedor::TIPO_RECEPCION,
                        'empresa_id' => $empresaId,
                        'proveedor_id' => $proveedorId,
                        'fecha' => $fecha,
                        'numerorecepcion' => $nro,
                        'numerofactura' => '',
                        'moneda_id' => $monedaDefault,
                        'cotizacion' => 1,
                        'estado' => RecepcionProveedorEstados::CONFIRMADA,
                        'observacion' => trim((string) ($cab->recm_observacion ?? '')) ?: null,
                        'anita_tipo' => 'COM',
                        'anita_letra' => 'X',
                        'anita_sucursal' => $sucursal,
                        'anita_nro' => $nro,
                        'origen_carga' => 'ANITA_IMPORT',
                        'creousuario_id' => $usuarioId,
                        'centrocosto_id' => RecepcionProveedorVisibilidadSupport::resolverCentrocostoCarga($usuarioId),
                    ]);

                    $orden = 1;
                    $lineasGrabadas = 0;
                    foreach ($lineasAnita as $lin) {
                        $sku = trim((string) ($lin->recv_articulo ?? ''));
                        $articuloId = $this->resolverArticuloId($sku);
                        if (! $articuloId) {
                            continue;
                        }

                        $cantidad = abs((float) ($lin->recv_cantidad ?? 0));
                        if ($cantidad <= 0) {
                            continue;
                        }

                        $ccId = $this->resolverCentrocostoId((int) ($lin->recv_ccosto ?? 0));
                        if (! $ccId && $ocCentrocostoId) {
                            $ccId = $ocCentrocostoId;
                        }
                        if (! $ccId) {
                            $ccId = 1;
                        }

                        Recepcion_Proveedor_Articulo::create([
                            'recepcion_proveedor_id' => $recepcion->id,
                            'orden' => $orden,
                            'penvp_orden' => (int) ($lin->recv_orden ?? $orden),
                            'tipo_linea' => 'OC',
                            'articulo_id' => $articuloId,
                            'cantidad' => $cantidad,
                            'cantidad_stock' => $cantidad,
                            'precio' => (float) ($lin->recv_precio ?? 0),
                            'precio_ordencompra' => (float) ($lin->recv_precio ?? 0),
                            'moneda_id' => $monedaDefault,
                            'cotizacion' => (float) ($lin->recv_cotizacion ?? 1) ?: 1,
                            'descuento' => (float) ($lin->recv_dto_art ?? 0),
                            'deposito_id' => max(1, (int) ($lin->recv_deposito ?? 1)),
                            'estado' => 'ACTIVO',
                            'incluyeimpuesto' => 'N',
                            'impuesto_id' => null,
                            'centrocosto_id' => $ccId,
                        ]);

                        $orden++;
                        $lineasGrabadas++;
                        $stats['lineas']++;
                    }

                    if ($lineasGrabadas === 0) {
                        throw new \RuntimeException('Recepción sin líneas válidas');
                    }

                    $stats['importadas']++;
                });
            } catch (\Throwable) {
                $stats['omitidas']++;
            }
        }

        return $stats;
    }

    /**
     * @return array<string, list<object>>
     */
    private function cargarLineasAgrupadas(int $fechaDesdeAnita, ?int $fechaHastaAnita): array
    {
        $grupos = [];
        $desde = $fechaDesdeAnita;
        $hasta = $fechaHastaAnita ?? (int) date('Ymd');

        while ($desde <= $hasta) {
            $anio = (int) substr((string) $desde, 0, 4);
            $mes = (int) substr((string) $desde, 4, 2);
            $ultimoDia = (int) date('t', mktime(0, 0, 0, $mes, 1, $anio));
            $finMes = min($hasta, (int) sprintf('%04d%02d%02d', $anio, $mes, $ultimoDia));

            $lineas = RecepcionProveedorAnitaImportSupport::listarRecepmovPorRangoFecha($desde, $finMes);
            foreach (RecepcionProveedorAnitaImportSupport::agruparRecepmovPorRecepcion($lineas) as $key => $items) {
                if (! isset($grupos[$key])) {
                    $grupos[$key] = [];
                }
                array_push($grupos[$key], ...$items);
            }

            if ($mes === 12) {
                $desde = (int) sprintf('%04d0101', $anio + 1);
            } else {
                $desde = (int) sprintf('%04d%02d01', $anio, $mes + 1);
            }
        }

        return $grupos;
    }

    private function resolverEmpresaId(int $codigoSucursal): ?int
    {
        $key = (string) $codigoSucursal;
        if (! array_key_exists($key, $this->cacheEmpresa)) {
            $this->cacheEmpresa[$key] = (int) (Empresa::query()->where('codigo', (string) $codigoSucursal)->value('id') ?: 0) ?: null;
        }

        return $this->cacheEmpresa[$key];
    }

    private function resolverProveedorId(string $codigo): ?int
    {
        $codigoNorm = ltrim(trim($codigo), '0');
        if ($codigoNorm === '') {
            return null;
        }
        if (! array_key_exists($codigoNorm, $this->cacheProveedor)) {
            $this->cacheProveedor[$codigoNorm] = (int) (Proveedor::query()
                ->where('codigo', $codigoNorm)
                ->orWhere('codigo', str_pad($codigoNorm, 6, '0', STR_PAD_LEFT))
                ->value('id') ?: 0) ?: null;
        }

        return $this->cacheProveedor[$codigoNorm];
    }

    private function resolverOrdencompra(int $numeroOc, int $empresaId): ?Ordencompra
    {
        $key = $empresaId.'-'.$numeroOc;
        if (! array_key_exists($key, $this->cacheOc)) {
            $id = Ordencompra::query()
                ->where('numeroordencompra', $numeroOc)
                ->where('empresa_id', $empresaId)
                ->value('id');
            $this->cacheOc[$key] = $id ? (int) $id : null;
        }

        return $this->cacheOc[$key] ? Ordencompra::find($this->cacheOc[$key]) : null;
    }

    private function resolverArticuloId(string $skuAnita): ?int
    {
        $sku = ltrim(trim($skuAnita), '0');
        if ($sku === '') {
            return null;
        }
        if (! array_key_exists($sku, $this->cacheArticulo)) {
            $this->cacheArticulo[$sku] = (int) (Articulo::query()->where('sku', $sku)->value('id') ?: 0) ?: null;
        }

        return $this->cacheArticulo[$sku];
    }

    private function resolverCentrocostoId(int $codigo): ?int
    {
        if ($codigo <= 0) {
            return null;
        }
        if (! array_key_exists($codigo, $this->cacheCentrocosto)) {
            $this->cacheCentrocosto[$codigo] = (int) (Centrocosto::query()->where('codigo', (string) $codigo)->value('id') ?: 0) ?: null;
        }

        return $this->cacheCentrocosto[$codigo];
    }
}
