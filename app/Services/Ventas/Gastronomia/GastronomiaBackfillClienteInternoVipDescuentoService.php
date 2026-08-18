<?php

declare(strict_types=1);

namespace App\Services\Ventas\Gastronomia;

use App\ApiAnita;
use App\Models\Ventas\DescuentoGastronomia;
use App\Support\Ventas\GastronomiaAnitaImport\GastronomiaAnitaImportBridgeSupport;
use App\Support\Ventas\GastronomiaAnitaImport\GastronomiaAnitaImportCacheReader;
use App\Support\Ventas\GastronomiaAnitaImport\GastronomiaAnitaImportCacheSupport;
use App\Support\Ventas\GastronomiaAnitaImport\GastronomiaAnitaImportDescuentoSupport;
use App\Support\Ventas\GastronomiaAnitaImportEmpresaSupport;
use App\Support\Ventas\GastronomiaDescuentoClienteInternoSupport;
use Illuminate\Support\Facades\DB;
use stdClass;

/**
 * Corrige cliente_interno_descuento_id en ventas gastronomía según circuito Anita:
 * - Desc. 40 (VIP / marketing): quitar imputación errónea a CANJE PLATINO (1500).
 * - Desc. 10 (canje premio): alinear resv_cliente Anita (001500, 000500, …).
 * - Import Anita sin descuento en cuenta: asignar desc. + cliente interno desde resvta.
 */
final class GastronomiaBackfillClienteInternoVipDescuentoService
{
    public function __construct(
        private readonly GastronomiaAnitaImportCacheSupport $cacheSupport,
    ) {}

    /**
     * @param  list<int>  $empresaIds
     * @return array{
     *   desc40_limpiadas_platino:int,
     *   desc40_reasignadas:int,
     *   desc10_corregidas:int,
     *   import_desc_asignadas:int,
     *   omitidas:int,
     *   sin_resvta:int,
     *   errores:list<string>,
     *   por_empresa:array<int, array{
     *     desc40_limpiadas_platino:int,
     *     desc40_reasignadas:int,
     *     desc10_corregidas:int,
     *     import_desc_asignadas:int
     *   }>
     * }
     */
    public function ejecutar(
        string $fechaDesde,
        string $fechaHasta,
        array $empresaIds,
        bool $dryRun = false,
        bool $usarCacheAnita = true,
    ): array {
        $codigoDesc40 = trim((string) config('gastronomia.canje_marketing_descuento_codigo', '40'));
        $desc40Id = DescuentoGastronomia::query()->where('codigo', $codigoDesc40)->value('id');
        if ($desc40Id === null) {
            throw new \InvalidArgumentException('No existe descuento gastronomía código '.$codigoDesc40.'.');
        }

        $codigoDesc10 = trim((string) config('gastronomia.canje_premio_descuento_codigo', '10'));
        $desc10Id = DescuentoGastronomia::query()->where('codigo', $codigoDesc10)->value('id');
        if ($desc10Id === null) {
            throw new \InvalidArgumentException('No existe descuento gastronomía código '.$codigoDesc10.'.');
        }

        $cliPlatinoId = GastronomiaDescuentoClienteInternoSupport::clienteInternoIdCanjePremioPlatino();

        $ret = [
            'desc40_limpiadas_platino' => 0,
            'desc40_reasignadas' => 0,
            'desc10_corregidas' => 0,
            'import_desc_asignadas' => 0,
            'omitidas' => 0,
            'sin_resvta' => 0,
            'errores' => [],
            'por_empresa' => [],
        ];

        foreach ($empresaIds as $empresaId) {
            $empresaId = (int) $empresaId;
            if ($empresaId <= 0) {
                continue;
            }

            $ret['por_empresa'][$empresaId] = [
                'desc40_limpiadas_platino' => 0,
                'desc40_reasignadas' => 0,
                'desc10_corregidas' => 0,
                'import_desc_asignadas' => 0,
            ];

            $reader = $usarCacheAnita
                ? $this->resolverCacheReaderExistente($empresaId, $fechaDesde, $fechaHasta)
                : null;

            if ($cliPlatinoId !== null && $cliPlatinoId > 0) {
                $this->corregirDesc40ConPlatinoIncorrecto(
                    $empresaId,
                    $fechaDesde,
                    $fechaHasta,
                    (int) $desc40Id,
                    $cliPlatinoId,
                    $codigoDesc40,
                    $reader,
                    $dryRun,
                    $ret,
                );
            }

            $this->corregirDesc10DesdeAnita(
                $empresaId,
                $fechaDesde,
                $fechaHasta,
                (int) $desc10Id,
                $codigoDesc10,
                $reader,
                $dryRun,
                $ret,
            );

            $this->asignarDescuentoImportAnitaSinCabecera(
                $empresaId,
                $fechaDesde,
                $fechaHasta,
                $reader,
                $dryRun,
                $ret,
            );
        }

        return $ret;
    }

    /**
     * @param  array<string, mixed>  $ret
     */
    private function corregirDesc40ConPlatinoIncorrecto(
        int $empresaId,
        string $fechaDesde,
        string $fechaHasta,
        int $desc40Id,
        int $cliPlatinoId,
        string $codigoDesc40,
        ?GastronomiaAnitaImportCacheReader $reader,
        bool $dryRun,
        array &$ret,
    ): void {
        $filas = DB::table('cuenta_gastronomia as cg')
            ->join('venta_gastronomia_emision as vge', 'vge.cuenta_gastronomia_id', '=', 'cg.id')
            ->join('venta as v', 'v.id', '=', 'vge.venta_id')
            ->join('puntoventa as pv', 'pv.id', '=', 'v.puntoventa_id')
            ->join('tipotransaccion as tt', 'tt.id', '=', 'v.tipotransaccion_id')
            ->whereNull('vge.venta_factura_origen_id')
            ->where('cg.empresa_id', $empresaId)
            ->where('cg.descuento_gastronomia_id', $desc40Id)
            ->where('cg.cliente_interno_descuento_id', $cliPlatinoId)
            ->whereBetween('v.fechajornada', [$fechaDesde, $fechaHasta])
            ->select([
                'cg.id as cuenta_id',
                'cg.origen_pos',
                'cg.cliente_vip_gastronomia_id',
                'pv.codigo as puntoventa_codigo',
                'v.numerocomprobante',
                'tt.abreviatura as tipo_comprobante',
            ])
            ->orderBy('cg.id')
            ->get();

        foreach ($filas as $row) {
            try {
                $nuevoId = $this->resolverClienteInternoDesc40Corregido($row, $empresaId, $codigoDesc40, $reader);

                if ($nuevoId === $cliPlatinoId) {
                    $ret['omitidas']++;

                    continue;
                }

                if ($dryRun) {
                    if ($nuevoId === null) {
                        $ret['desc40_limpiadas_platino']++;
                        $ret['por_empresa'][$empresaId]['desc40_limpiadas_platino']++;
                    } else {
                        $ret['desc40_reasignadas']++;
                        $ret['por_empresa'][$empresaId]['desc40_reasignadas']++;
                    }

                    continue;
                }

                $actualizadas = DB::table('cuenta_gastronomia')
                    ->where('id', (int) $row->cuenta_id)
                    ->where('cliente_interno_descuento_id', $cliPlatinoId)
                    ->update(['cliente_interno_descuento_id' => $nuevoId]);

                if ($actualizadas <= 0) {
                    $ret['omitidas']++;

                    continue;
                }

                if ($nuevoId === null) {
                    $ret['desc40_limpiadas_platino']++;
                    $ret['por_empresa'][$empresaId]['desc40_limpiadas_platino']++;
                } else {
                    $ret['desc40_reasignadas']++;
                    $ret['por_empresa'][$empresaId]['desc40_reasignadas']++;
                }
            } catch (\Throwable $e) {
                $ret['errores'][] = 'desc40 cuenta_id='.(int) $row->cuenta_id.': '.$e->getMessage();
            }
        }
    }

    /**
     * @param  array<string, mixed>  $ret
     */
    private function corregirDesc10DesdeAnita(
        int $empresaId,
        string $fechaDesde,
        string $fechaHasta,
        int $desc10Id,
        string $codigoDesc10,
        ?GastronomiaAnitaImportCacheReader $reader,
        bool $dryRun,
        array &$ret,
    ): void {
        $filas = DB::table('cuenta_gastronomia as cg')
            ->join('venta_gastronomia_emision as vge', 'vge.cuenta_gastronomia_id', '=', 'cg.id')
            ->join('venta as v', 'v.id', '=', 'vge.venta_id')
            ->join('puntoventa as pv', 'pv.id', '=', 'v.puntoventa_id')
            ->join('tipotransaccion as tt', 'tt.id', '=', 'v.tipotransaccion_id')
            ->whereNull('vge.venta_factura_origen_id')
            ->where('cg.empresa_id', $empresaId)
            ->where('cg.descuento_gastronomia_id', $desc10Id)
            ->whereBetween('v.fechajornada', [$fechaDesde, $fechaHasta])
            ->select([
                'cg.id as cuenta_id',
                'cg.cliente_interno_descuento_id',
                'pv.codigo as puntoventa_codigo',
                'v.numerocomprobante',
                'tt.abreviatura as tipo_comprobante',
            ])
            ->orderBy('cg.id')
            ->get();

        foreach ($filas as $row) {
            try {
                $resvta = $this->leerResvta(
                    $empresaId,
                    (int) preg_replace('/\D+/', '', (string) $row->puntoventa_codigo),
                    (int) $row->numerocomprobante,
                    trim((string) ($row->tipo_comprobante ?? 'FAC')),
                    $reader,
                );

                if ($resvta === null) {
                    $ret['sin_resvta']++;

                    continue;
                }

                $resolved = GastronomiaAnitaImportDescuentoSupport::resolverDesdeResvta($resvta, $empresaId);
                $nuevoId = $resolved['cliente_interno_descuento_id'] !== null
                    ? (int) $resolved['cliente_interno_descuento_id']
                    : null;

                $actualId = (int) ($row->cliente_interno_descuento_id ?? 0);
                if ($actualId === ($nuevoId ?? 0)) {
                    $ret['omitidas']++;

                    continue;
                }

                if ($dryRun) {
                    $ret['desc10_corregidas']++;
                    $ret['por_empresa'][$empresaId]['desc10_corregidas']++;

                    continue;
                }

                $actualizadas = DB::table('cuenta_gastronomia')
                    ->where('id', (int) $row->cuenta_id)
                    ->update(['cliente_interno_descuento_id' => $nuevoId]);

                if ($actualizadas > 0) {
                    $ret['desc10_corregidas']++;
                    $ret['por_empresa'][$empresaId]['desc10_corregidas']++;
                } else {
                    $ret['omitidas']++;
                }
            } catch (\Throwable $e) {
                $ret['errores'][] = 'desc10 cuenta_id='.(int) $row->cuenta_id.': '.$e->getMessage();
            }
        }
    }

    /**
     * Ventas importadas de Anita cuya cuenta quedó sin descuento_gastronomia_id (vincularEmisionGastronomia).
     *
     * @param  array<string, mixed>  $ret
     */
    private function asignarDescuentoImportAnitaSinCabecera(
        int $empresaId,
        string $fechaDesde,
        string $fechaHasta,
        ?GastronomiaAnitaImportCacheReader $reader,
        bool $dryRun,
        array &$ret,
    ): void {
        $filas = DB::table('cuenta_gastronomia as cg')
            ->join('venta_gastronomia_emision as vge', 'vge.cuenta_gastronomia_id', '=', 'cg.id')
            ->join('venta as v', 'v.id', '=', 'vge.venta_id')
            ->join('puntoventa as pv', 'pv.id', '=', 'v.puntoventa_id')
            ->join('tipotransaccion as tt', 'tt.id', '=', 'v.tipotransaccion_id')
            ->whereNull('vge.venta_factura_origen_id')
            ->where('cg.empresa_id', $empresaId)
            ->whereNull('cg.descuento_gastronomia_id')
            ->whereBetween('v.fechajornada', [$fechaDesde, $fechaHasta])
            ->where(function ($q): void {
                $q->where('v.leyenda', 'like', '%Importación Anita%')
                    ->orWhere('v.leyenda', 'like', '%import Anita%')
                    ->orWhere('v.leyenda', 'like', '%importación Anita%');
            })
            ->select([
                'cg.id as cuenta_id',
                'cg.cliente_interno_descuento_id',
                'pv.codigo as puntoventa_codigo',
                'v.numerocomprobante',
                'v.id as venta_id',
                'tt.abreviatura as tipo_comprobante',
            ])
            ->orderBy('cg.id')
            ->get();

        foreach ($filas as $row) {
            try {
                $resvta = $this->leerResvta(
                    $empresaId,
                    (int) preg_replace('/\D+/', '', (string) $row->puntoventa_codigo),
                    (int) $row->numerocomprobante,
                    trim((string) ($row->tipo_comprobante ?? 'FAC')),
                    $reader,
                );

                if ($resvta === null) {
                    $ret['sin_resvta']++;

                    continue;
                }

                if (! GastronomiaAnitaImportDescuentoSupport::tieneCodigoDescuentoResvta($resvta)) {
                    $ret['omitidas']++;

                    continue;
                }

                $resolved = GastronomiaAnitaImportDescuentoSupport::resolverDesdeResvta($resvta, $empresaId);
                $nuevoDescId = $resolved['descuento_gastronomia_id'] !== null
                    ? (int) $resolved['descuento_gastronomia_id']
                    : null;

                if ($nuevoDescId === null || $nuevoDescId <= 0) {
                    $ret['omitidas']++;

                    continue;
                }

                $nuevoCliId = $resolved['cliente_interno_descuento_id'] !== null
                    ? (int) $resolved['cliente_interno_descuento_id']
                    : null;

                $actualCliId = (int) ($row->cliente_interno_descuento_id ?? 0);
                if ($actualCliId === ($nuevoCliId ?? 0)) {
                    // Sin descuento en cuenta pero cliente ya coincide: igual hay que asignar desc.
                }

                if ($dryRun) {
                    $ret['import_desc_asignadas']++;
                    $ret['por_empresa'][$empresaId]['import_desc_asignadas']++;

                    continue;
                }

                $actualizadas = DB::table('cuenta_gastronomia')
                    ->where('id', (int) $row->cuenta_id)
                    ->whereNull('descuento_gastronomia_id')
                    ->update([
                        'descuento_gastronomia_id' => $nuevoDescId,
                        'cliente_interno_descuento_id' => $nuevoCliId,
                    ]);

                if ($actualizadas <= 0) {
                    $ret['omitidas']++;

                    continue;
                }

                $ret['import_desc_asignadas']++;
                $ret['por_empresa'][$empresaId]['import_desc_asignadas']++;
            } catch (\Throwable $e) {
                $ret['errores'][] = 'import_sin_desc cuenta_id='.(int) $row->cuenta_id
                    .' venta_id='.(int) $row->venta_id.': '.$e->getMessage();
            }
        }
    }

    private function resolverClienteInternoDesc40Corregido(
        object $row,
        int $empresaId,
        string $codigoDesc40,
        ?GastronomiaAnitaImportCacheReader $reader,
    ): ?int {
        $origen = trim((string) ($row->origen_pos ?? ''));
        $vipId = (int) ($row->cliente_vip_gastronomia_id ?? 0);

        if ($origen === 'canje_marketing' || $vipId > 0) {
            return GastronomiaDescuentoClienteInternoSupport::clienteInternoIdCanjeMarketing();
        }

        $sucursal = (int) preg_replace('/\D+/', '', (string) $row->puntoventa_codigo);
        $nro = (int) $row->numerocomprobante;
        $tipo = trim((string) ($row->tipo_comprobante ?? 'FAC'));

        $resvta = $this->leerResvta($empresaId, $sucursal, $nro, $tipo, $reader);
        if ($resvta === null) {
            return GastronomiaDescuentoClienteInternoSupport::clienteInternoIdCanjeMarketing();
        }

        $resolved = GastronomiaAnitaImportDescuentoSupport::resolverDesdeResvta($resvta, $empresaId);

        return $resolved['cliente_interno_descuento_id'] !== null
            ? (int) $resolved['cliente_interno_descuento_id']
            : null;
    }

    private function leerResvta(
        int $empresaId,
        int $sucursal,
        int $nro,
        string $tipoComprobante,
        ?GastronomiaAnitaImportCacheReader $reader,
    ): ?stdClass {
        if ($sucursal <= 0 || $nro <= 0) {
            return null;
        }

        $tipos = GastronomiaAnitaImportEmpresaSupport::tiposDetalleAnita($tipoComprobante);

        if ($reader !== null) {
            foreach ($tipos as $tipo) {
                $resvta = $reader->resvta($sucursal, $nro, [$tipo]);
                if ($resvta !== null) {
                    return $resvta;
                }
            }
        }

        $api = new ApiAnita;
        foreach ($tipos as $tipo) {
            $where = " WHERE resv_sucursal='".$sucursal."' AND resv_letra='B' AND resv_nro=".$nro." AND resv_tipo='".$tipo."' ";
            $raw = $api->apiCall(GastronomiaAnitaImportBridgeSupport::mergePayload([
                'acc' => 'list',
                'tabla' => 'resvta',
                'campos' => 'resv_tipo_dto,resv_cliente',
                'whereArmado' => $where,
            ], $empresaId));
            $cab = ApiAnita::primeraFilaLista((string) $raw);
            if ($cab !== null) {
                return $cab;
            }
        }

        return null;
    }

    private function resolverCacheReaderExistente(int $empresaId, string $fechaDesde, string $fechaHasta): ?GastronomiaAnitaImportCacheReader
    {
        $sufijos = ['desc40legacy', 'vipcli'.date('Ymd'), ''];

        foreach ($sufijos as $sufijo) {
            if (! $this->cacheSupport->cacheCompleta($empresaId, $fechaDesde, $fechaHasta, $sufijo)) {
                continue;
            }

            try {
                return $this->cacheSupport->crearReader($empresaId, $fechaDesde, $fechaHasta, $sufijo);
            } catch (\Throwable) {
                continue;
            }
        }

        return null;
    }
}
