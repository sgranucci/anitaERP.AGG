<?php

namespace App\Services\Stock;

use App\Models\Compras\Ordencompra;
use App\Models\Compras\Ordencompra_Articulo;
use App\Models\Compras\Proveedor;
use App\Services\Compras\OrdencompraAnitaSyncService;
use App\Models\Configuracion\Empresa;
use App\Models\Configuracion\Moneda;
use App\Models\Contable\Centrocosto;
use App\Models\Seguridad\Usuario;
use App\Models\Stock\Articulo;
use App\Models\Stock\Recepcion_Proveedor;
use App\Models\Stock\Recepcion_Proveedor_Articulo;
use App\Support\Stock\RecepcionProveedorAnitaImportSupport;
use App\Support\Stock\RecepcionProveedorDepositoAnitaSupport;
use App\Support\Stock\RecepcionProveedorEstados;
use App\Support\Stock\RecepcionProveedorVisibilidadSupport;
use Illuminate\Support\Facades\DB;

class RecepcionProveedorImportarDesdeAnitaService
{
    public function __construct(
        private readonly RecepcionProveedorService $recepcionProveedorService,
        private readonly OrdencompraAnitaSyncService $ordencompraAnitaSyncService,
    ) {
    }

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

    /** @var array<string, int|null> */
    private array $cacheDeposito = [];

    /**
     * Importa una COM/X puntual desde Anita.
     *
     * @return array{estado: string, recepcion_id: int|null, movimientostock_id: int|null, asiento_id: int|null, lineas: int, mensaje: string|null}
     */
    public function importarCom(
        int $sucursal,
        int $nro,
        bool $conImpacto = true,
        bool $dryRun = false,
        ?int $usuarioId = null,
    ): array {
        $resultado = [
            'estado' => 'error',
            'recepcion_id' => null,
            'movimientostock_id' => null,
            'asiento_id' => null,
            'lineas' => 0,
            'mensaje' => null,
        ];

        if ($nro <= 0 || $sucursal <= 0) {
            $resultado['mensaje'] = 'Clave COM inválida.';

            return $resultado;
        }

        $cab = RecepcionProveedorAnitaImportSupport::listarRecepmaePorClave('COM', 'X', $sucursal, $nro);
        if ($cab === null) {
            $resultado['mensaje'] = "COM {$nro} sucursal {$sucursal} no encontrada en Anita.";

            return $resultado;
        }

        $empresaId = $this->resolverEmpresaId($sucursal);
        if (! $empresaId) {
            $resultado['estado'] = 'sin_empresa';
            $resultado['mensaje'] = "Empresa ERP inexistente para sucursal Anita {$sucursal}.";

            return $resultado;
        }

        if (Recepcion_Proveedor::query()
            ->where('empresa_id', $empresaId)
            ->where('numerorecepcion', $nro)
            ->exists()) {
            $resultado['estado'] = 'omitida';
            $resultado['mensaje'] = 'La COM ya existe en anitaERP.';

            return $resultado;
        }

        $proveedorId = $this->resolverProveedorId(trim((string) ($cab->recm_proveedor ?? '')));
        if (! $proveedorId) {
            $resultado['estado'] = 'sin_proveedor';
            $resultado['mensaje'] = 'Proveedor Anita no mapeado en ERP.';

            return $resultado;
        }

        $lineasAnita = RecepcionProveedorAnitaImportSupport::listarRecepmov('COM', 'X', $sucursal, $nro);
        if ($lineasAnita === []) {
            $resultado['estado'] = 'sin_lineas';
            $resultado['mensaje'] = 'recepmov vacío en Anita.';

            return $resultado;
        }

        $numeroOc = RecepcionProveedorAnitaImportSupport::numeroOrdencompraDesdeCabecera($cab);
        $ordencompraId = null;
        $ocCentrocostoId = null;
        if ($numeroOc > 0) {
            $oc = $this->resolverOrdencompra($numeroOc, $empresaId);
            if ($oc === null && ! $dryRun) {
                $this->ordencompraAnitaSyncService->traerRegistroDeAnita($numeroOc);
                $oc = $this->resolverOrdencompra($numeroOc, $empresaId);
            }
            if ($oc) {
                $ordencompraId = $oc->id;
                $ocCentrocostoId = (int) ($oc->centrocosto_id ?? 0) ?: null;
            }
        }

        $resultado['lineas'] = count($lineasAnita);

        if ($dryRun) {
            $resultado['estado'] = 'dry_run';

            return $resultado;
        }

        $usuarioId = $usuarioId ?? (int) (Usuario::query()->orderBy('id')->value('id') ?? 1);
        $monedaDefault = (int) (Moneda::query()->orderBy('id')->value('id') ?? 1);

        try {
            $recepcionId = DB::transaction(function () use (
                $cab, $empresaId, $nro, $sucursal, $ordencompraId, $ocCentrocostoId, $proveedorId,
                $usuarioId, $monedaDefault, $lineasAnita, $conImpacto
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
                    'estado' => $conImpacto
                        ? RecepcionProveedorEstados::BORRADOR
                        : RecepcionProveedorEstados::CONFIRMADA,
                    'observacion' => trim((string) ($cab->recm_observacion ?? '')) ?: null,
                    'anita_tipo' => 'COM',
                    'anita_letra' => 'X',
                    'anita_sucursal' => $sucursal,
                    'anita_nro' => $nro,
                    'origen_carga' => 'ANITA_IMPORT',
                    'creousuario_id' => $usuarioId,
                    'centrocosto_id' => RecepcionProveedorVisibilidadSupport::resolverCentrocostoCarga($usuarioId),
                ]);

                $this->grabarLineasDesdeAnita(
                    $recepcion,
                    $lineasAnita,
                    $ordencompraId,
                    $ocCentrocostoId,
                    $empresaId,
                    $monedaDefault
                );

                return (int) $recepcion->id;
            });

            $resultado['recepcion_id'] = $recepcionId;

            if ($conImpacto) {
                $confirmada = $this->recepcionProveedorService->confirmar($recepcionId);
                $resultado['estado'] = 'importada_con_impacto';
                $resultado['movimientostock_id'] = (int) ($confirmada->movimientostock_id ?? 0) ?: null;
                $resultado['asiento_id'] = (int) ($confirmada->asiento_id ?? 0) ?: null;
            } else {
                $resultado['estado'] = 'importada';
            }
        } catch (\Throwable $e) {
            $resultado['estado'] = 'error';
            $resultado['mensaje'] = $e->getMessage();
        }

        return $resultado;
    }

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
            $numeroOc = RecepcionProveedorAnitaImportSupport::numeroOrdencompraDesdeCabecera($cab);
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

                    $lineasGrabadas = $this->grabarLineasDesdeAnita(
                        $recepcion,
                        $lineasAnita,
                        $ordencompraId,
                        $ocCentrocostoId,
                        $empresaId,
                        $monedaDefault
                    );
                    $stats['lineas'] += $lineasGrabadas;

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

    /**
     * @param  list<object>  $lineasAnita
     */
    private function grabarLineasDesdeAnita(
        Recepcion_Proveedor $recepcion,
        array $lineasAnita,
        ?int $ordencompraId,
        ?int $ocCentrocostoId,
        int $empresaId,
        int $monedaDefault,
    ): int {
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

            $ocArtId = null;
            if ($ordencompraId) {
                $ocArtId = (int) (Ordencompra_Articulo::query()
                    ->where('ordencompra_id', $ordencompraId)
                    ->where('articulo_id', $articuloId)
                    ->value('id') ?: 0) ?: null;
            }

            Recepcion_Proveedor_Articulo::create([
                'recepcion_proveedor_id' => $recepcion->id,
                'orden' => $orden,
                'penvp_orden' => (int) ($lin->recv_orden ?? $orden),
                'tipo_linea' => 'OC',
                'articulo_id' => $articuloId,
                'ordencompra_articulo_id' => $ocArtId,
                'cantidad' => $cantidad,
                'cantidad_stock' => $cantidad,
                'precio' => (float) ($lin->recv_precio ?? 0),
                'precio_ordencompra' => (float) ($lin->recv_precio ?? 0),
                'moneda_id' => RecepcionProveedorAnitaImportSupport::monedaIdDesdeCodigoAnita($lin->recv_cod_mon ?? 1),
                'cotizacion' => (float) ($lin->recv_cotizacion ?? 1) ?: 1,
                'descuento' => (float) ($lin->recv_dto_art ?? 0),
                'deposito_id' => $this->resolverDepositoId(
                    (int) ($lin->recv_deposito ?? 0),
                    $empresaId
                ),
                'estado' => 'ACTIVO',
                'incluyeimpuesto' => 'N',
                'impuesto_id' => null,
                'centrocosto_id' => $ccId,
            ]);

            $orden++;
            $lineasGrabadas++;
        }

        if ($lineasGrabadas === 0) {
            throw new \RuntimeException('Recepción sin líneas válidas');
        }

        return $lineasGrabadas;
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

    private function resolverDepositoId(int $codigoDepositoAnita, int $empresaId): int
    {
        $cacheKey = $empresaId.'-'.$codigoDepositoAnita;
        if (! array_key_exists($cacheKey, $this->cacheDeposito)) {
            $this->cacheDeposito[$cacheKey] = RecepcionProveedorDepositoAnitaSupport::resolverIdDesdeCodigoAnita(
                $codigoDepositoAnita,
                $empresaId
            );
        }

        $depositoId = (int) ($this->cacheDeposito[$cacheKey] ?? 0);

        return $depositoId > 0 ? $depositoId : 1;
    }
}
