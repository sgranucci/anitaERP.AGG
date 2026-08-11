<?php

namespace App\Services\Stock\Surmar;

use App\ApiAnita;
use App\Models\Compras\Proveedor;
use App\Models\Configuracion\Empresa;
use App\Models\Configuracion\Moneda;
use App\Models\Contable\Centrocosto;
use App\Models\Seguridad\Usuario;
use App\Models\Stock\Articulo;
use App\Models\Stock\Recepcion_Proveedor;
use App\Models\Stock\RecepcionProveedorArticuloSurmar;
use App\Support\Stock\RecepcionProveedorAnitaImportSupport;
use App\Support\Stock\RecepcionProveedorDepositoAnitaSupport;
use App\Support\Stock\RecepcionProveedorEstados;
use App\Support\Stock\Surmar\RecepcionProveedorSurmarAnitaBridgeSupport;
use App\Support\Stock\SurmarSupport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Importador histórico recepmae/recepmov Anita Surmar (COM/D).
 * No modifica el import AGG ni confirma stock/asiento/write-back.
 */
class RecepcionProveedorSurmarAnitaSyncService
{
    /** @var array<string, int|null> */
    private array $cacheProveedor = [];

    /** @var array<string, int|null> */
    private array $cacheArticulo = [];

    /** @var array<string, int|null> */
    private array $cacheDeposito = [];

    /**
     * @return array{
     *   en_anita:int,
     *   importadas:int,
     *   omitidas:int,
     *   sin_proveedor:int,
     *   sin_lineas:int,
     *   lineas:int,
     *   errores:list<string>
     * }
     */
    public function sincronizar(?int $usuarioId = null, bool $dryRun = false): array
    {
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $this->assertEntornoSurmar();
        RecepcionProveedorDepositoAnitaSupport::reiniciarCache();

        $fechaDesde = (int) config('recepcion_anita_surmar.fecha_desde', 20260101);
        $tipo = RecepcionProveedorSurmarAnitaBridgeSupport::tipo();
        $letra = RecepcionProveedorSurmarAnitaBridgeSupport::letra();

        $stats = [
            'en_anita' => 0,
            'importadas' => 0,
            'omitidas' => 0,
            'sin_proveedor' => 0,
            'sin_lineas' => 0,
            'lineas' => 0,
            'errores' => [],
        ];

        $cabeceras = $this->listarCabeceras($fechaDesde, $tipo, $letra);
        $stats['en_anita'] = count($cabeceras);
        if ($cabeceras === []) {
            return $stats;
        }

        $lineasPorClave = $this->cargarLineasAgrupadas($fechaDesde, $tipo, $letra);
        $usuarioId = $usuarioId ?? (int) (Usuario::query()->orderBy('id')->value('id') ?? 1);
        $monedaDefault = (int) (Moneda::query()->orderBy('id')->value('id') ?? 1);
        $empresaId = (int) config('recepcion_anita_surmar.empresa_id', SurmarSupport::EMPRESA_ID);
        $centrocostoId = (int) config('recepcion_anita_surmar.centrocosto_id', 1);
        $origen = (string) config('recepcion_anita_surmar.origen_carga', 'ANITA_IMPORT');

        foreach ($cabeceras as $cab) {
            $sucursal = (int) ($cab->recm_sucursal ?? 0);
            $nro = (int) ($cab->recm_nro ?? 0);
            if ($nro <= 0 || $sucursal <= 0) {
                $stats['omitidas']++;

                continue;
            }

            if (Recepcion_Proveedor::query()
                ->where('empresa_id', $empresaId)
                ->where('anita_tipo', $tipo)
                ->where('anita_letra', $letra)
                ->where('anita_sucursal', $sucursal)
                ->where('anita_nro', $nro)
                ->exists()) {
                $stats['omitidas']++;

                continue;
            }

            if (Recepcion_Proveedor::query()
                ->where('empresa_id', $empresaId)
                ->where('numerorecepcion', $nro)
                ->exists()) {
                $stats['omitidas']++;

                continue;
            }

            $proveedorId = $this->resolverProveedorId(trim((string) ($cab->recm_proveedor ?? '')), $empresaId);
            if (! $proveedorId) {
                $stats['sin_proveedor']++;

                continue;
            }

            $clave = $sucursal.'-'.$nro;
            $lineasAnita = $lineasPorClave[$clave] ?? [];
            if ($lineasAnita === []) {
                $stats['sin_lineas']++;

                continue;
            }

            if ($dryRun) {
                $stats['importadas']++;
                $stats['lineas'] += count($lineasAnita);

                continue;
            }

            try {
                $grabadas = DB::transaction(function () use (
                    $cab, $empresaId, $nro, $sucursal, $tipo, $letra, $proveedorId,
                    $usuarioId, $monedaDefault, $lineasAnita, $centrocostoId, $origen
                ) {
                    $fecha = RecepcionProveedorAnitaImportSupport::fechaDesdeAnita((int) ($cab->recm_fecha ?? 0));
                    $depositoCabecera = $this->depositoDesdeLineas($lineasAnita, $empresaId);
                    $cotizacion = $this->cotizacionDesdeLineas($lineasAnita);
                    $monedaId = $this->monedaDesdeLineas($lineasAnita, $monedaDefault);

                    $recepcion = Recepcion_Proveedor::create([
                        'ordencompra_id' => null,
                        'tipo' => Recepcion_Proveedor::TIPO_RECEPCION,
                        'empresa_id' => $empresaId,
                        'proveedor_id' => $proveedorId,
                        'deposito_id' => $depositoCabecera,
                        'fecha' => $fecha,
                        'numerorecepcion' => $nro,
                        'numerofactura' => $this->numerofacturaDesdeCabecera($cab),
                        'moneda_id' => $monedaId,
                        'cotizacion' => $cotizacion,
                        'estado' => RecepcionProveedorEstados::CONFIRMADA,
                        'observacion' => trim((string) ($cab->recm_observacion ?? '')) ?: null,
                        'anita_tipo' => $tipo,
                        'anita_letra' => $letra,
                        'anita_sucursal' => $sucursal,
                        'anita_nro' => $nro,
                        'origen_carga' => $origen,
                        'creousuario_id' => $usuarioId,
                        'centrocosto_id' => $centrocostoId > 0 ? $centrocostoId : null,
                    ]);

                    return $this->grabarLineas($recepcion, $lineasAnita, $empresaId, $monedaId, $centrocostoId);
                });

                $stats['importadas']++;
                $stats['lineas'] += $grabadas;
            } catch (\Throwable $e) {
                $msg = "COM {$letra} {$nro} suc {$sucursal}: ".$e->getMessage();
                $stats['errores'][] = $msg;
                Log::warning('RecepcionSurmarAnitaSync: '.$msg, ['exception' => $e]);
            }
        }

        return $stats;
    }

    /**
     * @return array{estado:string, recepcion_id:int|null, lineas:int, mensaje:?string}
     */
    public function importarUna(int $sucursal, int $nro, ?int $usuarioId = null, bool $dryRun = false): array
    {
        $this->assertEntornoSurmar();
        RecepcionProveedorDepositoAnitaSupport::reiniciarCache();

        $tipo = RecepcionProveedorSurmarAnitaBridgeSupport::tipo();
        $letra = RecepcionProveedorSurmarAnitaBridgeSupport::letra();
        $empresaId = (int) config('recepcion_anita_surmar.empresa_id', SurmarSupport::EMPRESA_ID);

        $ret = [
            'estado' => 'error',
            'recepcion_id' => null,
            'lineas' => 0,
            'mensaje' => null,
        ];

        if ($nro <= 0 || $sucursal <= 0) {
            $ret['mensaje'] = 'Clave COM inválida.';

            return $ret;
        }

        $cab = $this->leerCabecera($tipo, $letra, $sucursal, $nro);
        if ($cab === null) {
            $ret['mensaje'] = "COM/{$letra} {$nro} sucursal {$sucursal} no encontrada en Anita Surmar.";

            return $ret;
        }

        if (Recepcion_Proveedor::query()
            ->where('empresa_id', $empresaId)
            ->where(function ($q) use ($tipo, $letra, $sucursal, $nro) {
                $q->where(function ($q2) use ($tipo, $letra, $sucursal, $nro) {
                    $q2->where('anita_tipo', $tipo)
                        ->where('anita_letra', $letra)
                        ->where('anita_sucursal', $sucursal)
                        ->where('anita_nro', $nro);
                })->orWhere('numerorecepcion', $nro);
            })
            ->exists()) {
            $ret['estado'] = 'omitida';
            $ret['mensaje'] = 'La recepción ya existe en anitaERP.';

            return $ret;
        }

        $proveedorId = $this->resolverProveedorId(trim((string) ($cab->recm_proveedor ?? '')), $empresaId);
        if (! $proveedorId) {
            $ret['estado'] = 'sin_proveedor';
            $ret['mensaje'] = 'Proveedor Anita no mapeado en ERP (empresa Surmar).';

            return $ret;
        }

        $lineasAnita = $this->leerLineas($tipo, $letra, $sucursal, $nro);
        $ret['lineas'] = count($lineasAnita);
        if ($lineasAnita === []) {
            $ret['estado'] = 'sin_lineas';
            $ret['mensaje'] = 'recepmov vacío en Anita Surmar.';

            return $ret;
        }

        if ($dryRun) {
            $ret['estado'] = 'dry_run';

            return $ret;
        }

        $usuarioId = $usuarioId ?? (int) (Usuario::query()->orderBy('id')->value('id') ?? 1);
        $monedaDefault = (int) (Moneda::query()->orderBy('id')->value('id') ?? 1);
        $centrocostoId = (int) config('recepcion_anita_surmar.centrocosto_id', 1);
        $origen = (string) config('recepcion_anita_surmar.origen_carga', 'ANITA_IMPORT');

        try {
            $recepcionId = DB::transaction(function () use (
                $cab, $empresaId, $nro, $sucursal, $tipo, $letra, $proveedorId,
                $usuarioId, $monedaDefault, $lineasAnita, $centrocostoId, $origen
            ) {
                $fecha = RecepcionProveedorAnitaImportSupport::fechaDesdeAnita((int) ($cab->recm_fecha ?? 0));
                $depositoCabecera = $this->depositoDesdeLineas($lineasAnita, $empresaId);
                $cotizacion = $this->cotizacionDesdeLineas($lineasAnita);
                $monedaId = $this->monedaDesdeLineas($lineasAnita, $monedaDefault);

                $recepcion = Recepcion_Proveedor::create([
                    'ordencompra_id' => null,
                    'tipo' => Recepcion_Proveedor::TIPO_RECEPCION,
                    'empresa_id' => $empresaId,
                    'proveedor_id' => $proveedorId,
                    'deposito_id' => $depositoCabecera,
                    'fecha' => $fecha,
                    'numerorecepcion' => $nro,
                    'numerofactura' => $this->numerofacturaDesdeCabecera($cab),
                    'moneda_id' => $monedaId,
                    'cotizacion' => $cotizacion,
                    'estado' => RecepcionProveedorEstados::CONFIRMADA,
                    'observacion' => trim((string) ($cab->recm_observacion ?? '')) ?: null,
                    'anita_tipo' => $tipo,
                    'anita_letra' => $letra,
                    'anita_sucursal' => $sucursal,
                    'anita_nro' => $nro,
                    'origen_carga' => $origen,
                    'creousuario_id' => $usuarioId,
                    'centrocosto_id' => $centrocostoId > 0 ? $centrocostoId : null,
                ]);

                $this->grabarLineas($recepcion, $lineasAnita, $empresaId, $monedaId, $centrocostoId);

                return (int) $recepcion->id;
            });

            $ret['estado'] = 'importada';
            $ret['recepcion_id'] = $recepcionId;
        } catch (\Throwable $e) {
            $ret['estado'] = 'error';
            $ret['mensaje'] = $e->getMessage();
        }

        return $ret;
    }

    private function assertEntornoSurmar(): void
    {
        $empresaId = (int) config('recepcion_anita_surmar.empresa_id', SurmarSupport::EMPRESA_ID);
        if (! SurmarSupport::esEmpresaSurmar($empresaId)) {
            throw new \RuntimeException(
                "Import recepción Surmar: empresa_id={$empresaId} no es Surmar en este ERP (evitar uso en AGG)."
            );
        }
        if (! Empresa::query()->whereKey($empresaId)->exists()) {
            throw new \RuntimeException("Import recepción Surmar: empresa_id {$empresaId} inexistente.");
        }
        $ccId = (int) config('recepcion_anita_surmar.centrocosto_id', 1);
        if ($ccId > 0 && ! Centrocosto::query()->whereKey($ccId)->exists()) {
            throw new \RuntimeException("Import recepción Surmar: centrocosto_id {$ccId} inexistente.");
        }
    }

    /**
     * @return list<object>
     */
    private function listarCabeceras(int $fechaDesde, string $tipo, string $letra): array
    {
        $api = new ApiAnita;
        $where = " WHERE recm_tipo = '".addslashes($tipo)."'"
            ." AND recm_letra = '".addslashes($letra)."'"
            .' AND recm_fecha >= '.(int) $fechaDesde;

        return ApiAnita::decodificarListaFilas($api->apiCall(
            RecepcionProveedorSurmarAnitaBridgeSupport::mergePayload([
                'acc' => 'list',
                'tabla' => config('recepcion_anita_surmar.tablas.cabecera', 'recepmae'),
                'campos' => RecepcionProveedorSurmarAnitaBridgeSupport::camposCabecera(),
                'orderBy' => 'recm_fecha, recm_sucursal, recm_nro',
                'whereArmado' => $where,
            ])
        ));
    }

    private function leerCabecera(string $tipo, string $letra, int $sucursal, int $nro): ?object
    {
        $api = new ApiAnita;
        $where = " WHERE recm_tipo = '".addslashes($tipo)."'"
            ." AND recm_letra = '".addslashes($letra)."'"
            .' AND recm_sucursal = '.(int) $sucursal
            .' AND recm_nro = '.(int) $nro;

        return ApiAnita::primeraFilaLista($api->apiCall(
            RecepcionProveedorSurmarAnitaBridgeSupport::mergePayload([
                'acc' => 'list',
                'tabla' => config('recepcion_anita_surmar.tablas.cabecera', 'recepmae'),
                'campos' => RecepcionProveedorSurmarAnitaBridgeSupport::camposCabecera(),
                'whereArmado' => $where,
                'limit' => 'FIRST 1',
            ])
        ));
    }

    /**
     * @return list<object>
     */
    private function leerLineas(string $tipo, string $letra, int $sucursal, int $nro): array
    {
        $api = new ApiAnita;
        $where = " WHERE recv_tipo = '".addslashes($tipo)."'"
            ." AND recv_letra = '".addslashes($letra)."'"
            .' AND recv_sucursal = '.(int) $sucursal
            .' AND recv_nro = '.(int) $nro;

        return ApiAnita::decodificarListaFilas($api->apiCall(
            RecepcionProveedorSurmarAnitaBridgeSupport::mergePayload([
                'acc' => 'list',
                'tabla' => config('recepcion_anita_surmar.tablas.linea', 'recepmov'),
                'campos' => RecepcionProveedorSurmarAnitaBridgeSupport::camposLinea(),
                'orderBy' => 'recv_orden, recv_nro_interno',
                'whereArmado' => $where,
            ])
        ));
    }

    /**
     * @return array<string, list<object>>
     */
    private function cargarLineasAgrupadas(int $fechaDesde, string $tipo, string $letra): array
    {
        $grupos = [];
        $desde = $fechaDesde;
        $hasta = (int) date('Ymd');

        while ($desde <= $hasta) {
            $anio = (int) substr((string) $desde, 0, 4);
            $mes = (int) substr((string) $desde, 4, 2);
            $ultimoDia = (int) date('t', mktime(0, 0, 0, $mes, 1, $anio));
            $finMes = min($hasta, (int) sprintf('%04d%02d%02d', $anio, $mes, $ultimoDia));

            $api = new ApiAnita;
            $where = " WHERE recv_tipo = '".addslashes($tipo)."'"
                ." AND recv_letra = '".addslashes($letra)."'"
                .' AND recv_fecha >= '.(int) $desde
                .' AND recv_fecha <= '.(int) $finMes;

            $lineas = ApiAnita::decodificarListaFilas($api->apiCall(
                RecepcionProveedorSurmarAnitaBridgeSupport::mergePayload([
                    'acc' => 'list',
                    'tabla' => config('recepcion_anita_surmar.tablas.linea', 'recepmov'),
                    'campos' => RecepcionProveedorSurmarAnitaBridgeSupport::camposLinea(),
                    'orderBy' => 'recv_sucursal, recv_nro, recv_orden',
                    'whereArmado' => $where,
                ])
            ));

            foreach ($lineas as $lin) {
                $suc = (int) ($lin->recv_sucursal ?? 0);
                $nro = (int) ($lin->recv_nro ?? 0);
                if ($nro <= 0 || $suc <= 0) {
                    continue;
                }
                $grupos[$suc.'-'.$nro][] = $lin;
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
    private function grabarLineas(
        Recepcion_Proveedor $recepcion,
        array $lineasAnita,
        int $empresaId,
        int $monedaDefault,
        int $centrocostoDefault,
    ): int {
        $orden = 1;
        $grabadas = 0;

        foreach ($lineasAnita as $lin) {
            $sku = trim((string) ($lin->recv_articulo ?? ''));
            $articuloId = $this->resolverArticuloId($sku);
            if (! $articuloId) {
                continue;
            }

            $pesoNeto = $this->resolverPesoNeto($lin);
            if ($pesoNeto <= 0) {
                continue;
            }

            $depositoId = $this->resolverDepositoId((int) ($lin->recv_deposito ?? 0), $empresaId);
            $certificado = trim((string) ($lin->recv_certificado ?? ''));
            $lote = trim((string) ($lin->recv_lote_proveed ?? ''));
            if ($lote === '' || $lote === '0') {
                $lote = $certificado;
            }
            $fechaVto = (int) ($lin->recv_fecha_vto ?? 0);
            $fechaVtoIso = $fechaVto > 0
                ? RecepcionProveedorAnitaImportSupport::fechaDesdeAnita($fechaVto)
                : null;
            $pesoUnit = (float) ($lin->recv_peso_unitario ?? 0);
            $cantPieza = abs((float) ($lin->recv_cantidad ?? 0));
            if ($cantPieza <= 0 && $pesoUnit > 0) {
                $cantPieza = round($pesoNeto / $pesoUnit, 4);
            }
            if ($cantPieza <= 0) {
                $cantPieza = 1;
            }

            $ccId = $centrocostoDefault > 0 ? $centrocostoDefault : 1;
            $detalle = trim((string) ($lin->recv_desc ?? ''));
            $umdId = (int) (Articulo::query()->whereKey($articuloId)->value('unidadmedida_id') ?? 0);
            if ($umdId <= 0) {
                $umdId = 1;
            }

            RecepcionProveedorArticuloSurmar::create([
                'recepcion_proveedor_id' => $recepcion->id,
                'orden' => $orden,
                'penvp_orden' => (int) ($lin->recv_orden ?? $orden),
                'penvp_nro_interno' => (int) ($lin->recv_nro_interno ?? 0) ?: null,
                'tipo_linea' => 'EXTRA',
                'articulo_id' => $articuloId,
                'ordencompra_articulo_id' => null,
                'cantidad' => $pesoNeto,
                'cantidad_stock' => $pesoNeto,
                'cantidad_rechazada' => abs((float) ($lin->recv_cantrech ?? 0)),
                'unidadmedida_id' => $umdId,
                'coeficienteconversion' => 1,
                'precio' => (float) ($lin->recv_precio ?? 0),
                'precio_ordencompra' => (float) ($lin->recv_precio ?? 0),
                'moneda_id' => RecepcionProveedorAnitaImportSupport::monedaIdDesdeCodigoAnita($lin->recv_cod_mon ?? 1)
                    ?: $monedaDefault,
                'cotizacion' => (float) ($lin->recv_cotizacion ?? 1) ?: 1,
                'descuento' => (float) ($lin->recv_dto_art ?? 0),
                'deposito_id' => $depositoId,
                'detalle' => $detalle !== '' ? $detalle : null,
                'estado' => 'ACTIVO',
                'incluyeimpuesto' => strtoupper(trim((string) ($lin->recv_incl_impuesto ?? 'N'))) === 'S' ? 'S' : 'N',
                'impuesto_id' => null,
                'centrocosto_id' => $ccId,
                'lote_proveedor' => $lote !== '' ? $lote : null,
                'certificado' => $certificado !== '' ? $certificado : null,
                'fecha_vto' => $fechaVtoIso,
                'peso_bruto' => $pesoNeto,
                'peso_neto' => $pesoNeto,
                'cant_pieza' => $cantPieza,
            ]);

            $orden++;
            $grabadas++;
        }

        if ($grabadas === 0) {
            throw new \RuntimeException('Recepción sin líneas válidas (SKU/peso).');
        }

        return $grabadas;
    }

    private function resolverPesoNeto(object $lin): float
    {
        $totalPeso = abs((float) ($lin->recv_total_peso ?? 0));
        if ($totalPeso > 0) {
            return $totalPeso;
        }

        $cantidad = abs((float) ($lin->recv_cantidad ?? 0));
        $pesoUnit = abs((float) ($lin->recv_peso_unitario ?? 0));
        if ($cantidad > 0 && $pesoUnit > 0 && $cantidad < 50 && $pesoUnit > $cantidad) {
            return round($cantidad * $pesoUnit, 4);
        }

        return $cantidad;
    }

    /**
     * @param  list<object>  $lineas
     */
    private function depositoDesdeLineas(array $lineas, int $empresaId): int
    {
        foreach ($lineas as $lin) {
            $codigo = (int) ($lin->recv_deposito ?? 0);
            if ($codigo <= 0) {
                continue;
            }
            $id = $this->resolverDepositoId($codigo, $empresaId);
            if ($id > 0) {
                return $id;
            }
        }

        return 1;
    }

    /**
     * @param  list<object>  $lineas
     */
    private function cotizacionDesdeLineas(array $lineas): float
    {
        foreach ($lineas as $lin) {
            $cot = (float) ($lin->recv_cotizacion ?? 0);
            if ($cot > 0) {
                return $cot;
            }
        }

        return 1.0;
    }

    /**
     * @param  list<object>  $lineas
     */
    private function monedaDesdeLineas(array $lineas, int $default): int
    {
        foreach ($lineas as $lin) {
            $id = RecepcionProveedorAnitaImportSupport::monedaIdDesdeCodigoAnita($lin->recv_cod_mon ?? null);
            if ($id > 0) {
                return $id;
            }
        }

        return $default;
    }

    private function numerofacturaDesdeCabecera(object $cab): string
    {
        $tipo = trim((string) ($cab->recm_tipo_fac ?? ''));
        $letra = trim((string) ($cab->recm_letra_fac ?? ''));
        $suc = (int) ($cab->recm_sucursal_fac ?? 0);
        $nro = (int) ($cab->recm_nro_fac ?? 0);
        if ($nro <= 0 && $tipo === '') {
            $remito = trim((string) ($cab->recm_cod_remito ?? ''));

            return $remito !== '' && $remito !== '0' ? $remito : '';
        }

        return trim(sprintf('%s %s %04d-%08d', $tipo, $letra, $suc, $nro));
    }

    private function resolverProveedorId(string $codigo, int $empresaId): ?int
    {
        $codigoNorm = ltrim(trim($codigo), '0');
        if ($codigoNorm === '') {
            return null;
        }
        $cacheKey = $empresaId.'-'.$codigoNorm;
        if (! array_key_exists($cacheKey, $this->cacheProveedor)) {
            $padded = str_pad($codigoNorm, 6, '0', STR_PAD_LEFT);
            $q = Proveedor::query()->where(function ($q) use ($codigoNorm, $padded) {
                $q->where('codigo', $codigoNorm)->orWhere('codigo', $padded);
            });
            if (config('proveedor.filtro_empresa')) {
                $q->where(function ($q) use ($empresaId) {
                    $q->where('empresa_id', $empresaId)->orWhereNull('empresa_id');
                });
            }
            $this->cacheProveedor[$cacheKey] = (int) ($q->value('id') ?: 0) ?: null;
        }

        return $this->cacheProveedor[$cacheKey];
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
