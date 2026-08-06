<?php

declare(strict_types=1);

namespace App\Support\Ventas\Gastronomia;

use App\ApiAnita;
use App\Models\Configuracion\Empresa;
use App\Models\Ventas\Puntoventa;
use App\Models\Ventas\Venta;
use App\Support\Ventas\KandikoAnitaVentaTipoSupport;
use App\Support\Caja\AnitaSync\RendicionGastronomiaAnitaRendgastroSupport;
use App\Support\Ventas\GastronomiaAnitaImportEmpresaSupport;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\File;

/**
 * Descarga Anita (venta, vengrav, ctamov, rendgastro) a storage local para auditorías sin bloquear el bridge.
 */
final class GastronomiaAnitaMesCacheSupport
{
    private const VENTA_CAMPOS = 'ven_tipo,ven_letra,ven_sucursal,ven_nro,ven_empresa,ven_fecha,ven_fecha_vto,ven_monto,ven_gravado,ven_exento,ven_impuesto1,ven_monto_desc';

    private const VENGRAV_CAMPOS = 'veng_tipo,veng_letra,veng_sucursal,veng_nro,veng_codigo_tasa,veng_gravado,veng_impuesto,veng_tasa';

    private const VENCAE_CAMPOS = 'venc_tipo,venc_letra,venc_sucursal,venc_nro,venc_nro_cae,venc_fecha_vto';

    /** ctav_desc_mov último: el bridge parte el CSV por `|` y una descripción con `|` corre el resto. */
    private const CTAMOV_CAMPOS = 'ctav_empresa,ctav_nro_asiento,ctav_nro_linea,ctav_d_h,ctav_cuenta,ctav_fecha,ctav_tipo,ctav_letra,ctav_sucursal,ctav_nro,ctav_importe,ctav_cotizacion,ctav_cod_mon,ctav_ccosto,ctav_desc_mov';

    public function __construct(
        private readonly RendicionGastronomiaAnitaRendgastroSupport $rendgastroSupport,
    ) {
    }

    public function directorioCache(int $empresaId, string $fechaDesde, string $fechaHasta): string
    {
        $desde = Carbon::parse($fechaDesde)->format('Ymd');
        $hasta = Carbon::parse($fechaHasta)->format('Ymd');

        return storage_path('app/anita_audit_cache/empresa_'.$empresaId.'_'.$desde.'_'.$hasta);
    }

    public function cacheCompleta(int $empresaId, string $fechaDesde, string $fechaHasta): bool
    {
        $dir = $this->directorioCache($empresaId, $fechaDesde, $fechaHasta);

        return is_file($dir.'/manifest.json')
            && is_file($dir.'/venta.json')
            && is_file($dir.'/vengrav.json')
            && is_file($dir.'/vencae.json')
            && is_file($dir.'/ctamov.json')
            && is_file($dir.'/rendgastro.json');
    }

    /**
     * @return array<string, mixed>
     */
    public function descargar(int $empresaId, string $fechaDesde, string $fechaHasta, bool $forzar = false): array
    {
        $desde = Carbon::parse($fechaDesde)->toDateString();
        $hasta = Carbon::parse($fechaHasta)->toDateString();
        if ($desde > $hasta) {
            throw new \InvalidArgumentException('fecha-desde no puede ser posterior a fecha-hasta.');
        }

        $dir = $this->directorioCache($empresaId, $desde, $hasta);
        if (! $forzar && $this->cacheCompleta($empresaId, $desde, $hasta)) {
            return $this->cargar($empresaId, $desde, $hasta)['manifest'];
        }

        File::ensureDirectoryExists($dir);

        $empresa = Empresa::query()->findOrFail($empresaId);
        $empresaCodigo = trim((string) ($empresa->codigo ?? $empresaId));
        $fechaDesdeEntera = (int) str_replace('-', '', $desde);
        $fechaHastaEntera = (int) str_replace('-', '', $hasta);

        $venta = $this->listarVentaBulk($empresaCodigo, $fechaDesdeEntera, $fechaHastaEntera);
        $vengrav = $this->listarVengravPorCabeceras($venta, $empresaId);
        $vencae = $this->listarVencaePorRangosErp($empresaId, $desde, $hasta, $empresaCodigo);
        $ctamov = $this->listarCtamovBulk((int) $empresaCodigo, $fechaDesdeEntera, $fechaHastaEntera);
        $rendgastro = $this->listarRendgastroPorDia($empresaId, $desde, $hasta);

        $this->guardarJson($dir.'/venta.json', $venta);
        $this->guardarJson($dir.'/vengrav.json', $vengrav);
        $this->guardarJson($dir.'/vencae.json', $vencae);
        $this->guardarJson($dir.'/ctamov.json', $ctamov);
        $this->guardarJson($dir.'/rendgastro.json', $rendgastro);

        $manifest = [
            'empresa_id' => $empresaId,
            'empresa_codigo' => $empresaCodigo,
            'empresa_nombre' => (string) ($empresa->nombre ?? ''),
            'fecha_desde' => $desde,
            'fecha_hasta' => $hasta,
            'generado_at' => now()->toIso8601String(),
            'bridge' => ApiAnita::urlBridge(),
            'directorio' => $dir,
            'counts' => [
                'venta' => count($venta),
                'vengrav' => count($vengrav),
                'vencae' => count($vencae),
                'ctamov' => count($ctamov),
                'rendgastro_dias' => count($rendgastro),
                'rendgastro_filas' => array_sum(array_map('count', $rendgastro)),
            ],
        ];

        $this->guardarJson($dir.'/manifest.json', $manifest);

        return $manifest;
    }

    /**
     * @return array{
     *   manifest: array<string, mixed>,
     *   venta: list<object>,
     *   vengrav: list<object>,
     *   vencae: list<object>,
     *   ctamov: list<object>,
     *   rendgastro: array<string, list<object>>
     * }
     */
    public function cargar(int $empresaId, string $fechaDesde, string $fechaHasta): array
    {
        $dir = $this->directorioCache($empresaId, $fechaDesde, $fechaHasta);
        if (! $this->cacheCompleta($empresaId, $fechaDesde, $fechaHasta)) {
            throw new \RuntimeException('Cache Anita inexistente o incompleta: '.$dir);
        }

        return [
            'manifest' => $this->leerJson($dir.'/manifest.json'),
            'venta' => $this->leerJsonFilas($dir.'/venta.json'),
            'vengrav' => $this->leerJsonFilas($dir.'/vengrav.json'),
            'vencae' => $this->leerJsonFilas($dir.'/vencae.json'),
            'ctamov' => $this->leerJsonFilas($dir.'/ctamov.json'),
            'rendgastro' => $this->leerJsonRendgastro($dir.'/rendgastro.json'),
        ];
    }

    /**
     * Cache para cuadre jornada: ctamov + rendgastro + venta Informix.
     *
     * @return array{
     *   manifest: array<string, mixed>,
     *   ctamov: list<object>,
     *   rendgastro: array<string, list<object>>,
     *   venta: list<object>
     * }
     */
    public function cargarCtamovRendg(int $empresaId, string $fechaDesde, string $fechaHasta): array
    {
        $dir = $this->directorioCache($empresaId, $fechaDesde, $fechaHasta);
        if (! is_file($dir.'/ctamov.json') || ! is_file($dir.'/rendgastro.json')) {
            throw new \RuntimeException('Cache Anita ctamov/rendg inexistente: '.$dir);
        }

        $manifest = is_file($dir.'/manifest.json')
            ? $this->leerJson($dir.'/manifest.json')
            : [
                'empresa_id' => $empresaId,
                'fecha_desde' => Carbon::parse($fechaDesde)->toDateString(),
                'fecha_hasta' => Carbon::parse($fechaHasta)->toDateString(),
                'directorio' => $dir,
            ];

        return [
            'manifest' => $manifest,
            'ctamov' => $this->leerJsonFilas($dir.'/ctamov.json'),
            'rendgastro' => $this->leerJsonRendgastro($dir.'/rendgastro.json'),
            'venta' => is_file($dir.'/venta.json') ? $this->leerJsonFilas($dir.'/venta.json') : [],
        ];
    }

    public function cacheCtamovRendgCompleta(int $empresaId, string $fechaDesde, string $fechaHasta): bool
    {
        $dir = $this->directorioCache($empresaId, $fechaDesde, $fechaHasta);

        return is_file($dir.'/ctamov.json')
            && is_file($dir.'/rendgastro.json')
            && is_file($dir.'/venta.json');
    }

    /**
     * Descarga ctamov + rendgastro + venta Informix (cuadre jornada).
     *
     * @return array<string, mixed>
     */
    public function descargarCtamovRendg(int $empresaId, string $fechaDesde, string $fechaHasta, bool $forzar = false): array
    {
        $desde = Carbon::parse($fechaDesde)->toDateString();
        $hasta = Carbon::parse($fechaHasta)->toDateString();
        if ($desde > $hasta) {
            throw new \InvalidArgumentException('fecha-desde no puede ser posterior a fecha-hasta.');
        }

        $dir = $this->directorioCache($empresaId, $desde, $hasta);
        if (! $forzar && $this->cacheCtamovRendgCompleta($empresaId, $desde, $hasta)) {
            return $this->cargarCtamovRendg($empresaId, $desde, $hasta)['manifest'];
        }

        File::ensureDirectoryExists($dir);

        $empresa = Empresa::query()->findOrFail($empresaId);
        $empresaCodigo = trim((string) ($empresa->codigo ?? $empresaId));
        $fechaDesdeEntera = (int) str_replace('-', '', $desde);
        $fechaHastaEntera = (int) str_replace('-', '', $hasta);

        $ctamov = $this->listarCtamovBulk((int) $empresaCodigo, $fechaDesdeEntera, $fechaHastaEntera);
        $rendgastro = $this->listarRendgastroPorDia($empresaId, $desde, $hasta);
        $venta = $this->listarVentaBulk($empresaCodigo, $fechaDesdeEntera, $fechaHastaEntera);

        $this->guardarJson($dir.'/ctamov.json', $ctamov);
        $this->guardarJson($dir.'/rendgastro.json', $rendgastro);
        $this->guardarJson($dir.'/venta.json', $venta);

        $manifest = [
            'empresa_id' => $empresaId,
            'empresa_codigo' => $empresaCodigo,
            'empresa_nombre' => (string) ($empresa->nombre ?? ''),
            'fecha_desde' => $desde,
            'fecha_hasta' => $hasta,
            'generado_at' => now()->toIso8601String(),
            'bridge' => ApiAnita::urlBridge(),
            'directorio' => $dir,
            'modo' => 'cuadre_jornada',
            'counts' => [
                'ctamov' => count($ctamov),
                'venta' => count($venta),
                'rendgastro_dias' => count($rendgastro),
                'rendgastro_filas' => array_sum(array_map('count', $rendgastro)),
            ],
        ];

        $this->guardarJson($dir.'/manifest.json', $manifest);

        return $manifest;
    }

    public function directorioCacheExportPk(int $empresaId, string $fechaDesde, string $fechaHasta): string
    {
        return $this->directorioCache($empresaId, $fechaDesde, $fechaHasta).'_export_pk';
    }

    public function cacheExportPkCompleta(int $empresaId, string $fechaDesde, string $fechaHasta): bool
    {
        $dir = $this->directorioCacheExportPk($empresaId, $fechaDesde, $fechaHasta);

        return is_file($dir.'/manifest.json')
            && is_file($dir.'/venta.json')
            && is_file($dir.'/vengrav.json')
            && is_file($dir.'/vencae.json');
    }

    /**
     * Anita venta/vengrav/vencae por PK (tipo/letra/sucursal/nro) en rangos ERP — sin filtro ven_fecha_vto.
     *
     * @return array{
     *   manifest: array<string, mixed>,
     *   venta: list<object>,
     *   vengrav: list<object>,
     *   vencae: list<object>
     * }
     */
    public function descargarParaExportUnl(int $empresaId, string $fechaDesde, string $fechaHasta, bool $forzar = false): array
    {
        $desde = Carbon::parse($fechaDesde)->toDateString();
        $hasta = Carbon::parse($fechaHasta)->toDateString();
        $dir = $this->directorioCacheExportPk($empresaId, $desde, $hasta);

        if (! $forzar && $this->cacheExportPkCompleta($empresaId, $desde, $hasta)) {
            return $this->cargarExportPk($empresaId, $desde, $hasta);
        }

        File::ensureDirectoryExists($dir);

        $empresa = Empresa::query()->findOrFail($empresaId);
        $empresaCodigo = trim((string) ($empresa->codigo ?? $empresaId));

        $venta = $this->listarVentaPorRangosErp($empresaId, $desde, $hasta, $empresaCodigo);
        $vengrav = $this->listarVengravPorRangosErp($empresaId, $desde, $hasta);
        $vencae = $this->listarVencaePorRangosErp($empresaId, $desde, $hasta, $empresaCodigo);

        $this->guardarJson($dir.'/venta.json', $venta);
        $this->guardarJson($dir.'/vengrav.json', $vengrav);
        $this->guardarJson($dir.'/vencae.json', $vencae);

        $manifest = [
            'empresa_id' => $empresaId,
            'empresa_codigo' => $empresaCodigo,
            'fecha_desde' => $desde,
            'fecha_hasta' => $hasta,
            'generado_at' => now()->toIso8601String(),
            'modo' => 'export_pk_rangos_erp',
            'bridge' => ApiAnita::urlBridge(),
            'directorio' => $dir,
            'counts' => [
                'venta' => count($venta),
                'vengrav' => count($vengrav),
                'vencae' => count($vencae),
            ],
        ];
        $this->guardarJson($dir.'/manifest.json', $manifest);

        return [
            'manifest' => $manifest,
            'venta' => $venta,
            'vengrav' => $vengrav,
            'vencae' => $vencae,
        ];
    }

    /**
     * @return array{manifest: array<string, mixed>, venta: list<object>, vengrav: list<object>, vencae: list<object>}
     */
    public function cargarExportPk(int $empresaId, string $fechaDesde, string $fechaHasta): array
    {
        $dir = $this->directorioCacheExportPk($empresaId, $fechaDesde, $fechaHasta);
        if (! $this->cacheExportPkCompleta($empresaId, $fechaDesde, $fechaHasta)) {
            throw new \RuntimeException('Cache export PK inexistente: '.$dir);
        }

        return [
            'manifest' => $this->leerJson($dir.'/manifest.json'),
            'venta' => $this->leerJsonFilas($dir.'/venta.json'),
            'vengrav' => $this->leerJsonFilas($dir.'/vengrav.json'),
            'vencae' => $this->leerJsonFilas($dir.'/vencae.json'),
        ];
    }

    /**
     * @return list<object>
     */
    private function listarVentaBulk(string $empresaCodigo, int $fechaDesdeEntera, int $fechaHastaEntera): array
    {
        $where = " WHERE ven_letra = 'B'"
            ." AND ven_fecha_vto >= '".$fechaDesdeEntera."'"
            ." AND ven_fecha_vto <= '".$fechaHastaEntera."' "
            .GastronomiaAnitaImportEmpresaSupport::whereEmpresa('ven', $empresaCodigo);

        $parsed = ApiAnita::parsearRespuestaLista((new ApiAnita)->apiCall([
            'acc' => 'list',
            'tabla' => 'venta',
            'campos' => self::VENTA_CAMPOS,
            'whereArmado' => $where,
            'orderBy' => 'ven_fecha_vto, ven_sucursal, ven_nro',
        ]));

        if ($parsed['error_lectura'] !== null) {
            throw new \RuntimeException('No se pudo listar venta Anita (bulk): '.$parsed['error_lectura']);
        }

        return $parsed['filas'];
    }

    /**
     * @param  list<object>  $venta
     * @return list<object>
     */
    private function listarVengravPorCabeceras(array $venta, int $empresaId): array
    {
        /** @var array<int, array{min:int,max:int}> $rangos */
        $rangos = [];
        foreach ($venta as $fila) {
            $sucursal = (int) preg_replace('/\D+/', '', (string) ($fila->ven_sucursal ?? ''));
            $nro = (int) ($fila->ven_nro ?? 0);
            if ($sucursal <= 0 || $nro <= 0) {
                continue;
            }
            if (! isset($rangos[$sucursal])) {
                $rangos[$sucursal] = ['min' => $nro, 'max' => $nro];
            } else {
                $rangos[$sucursal]['min'] = min($rangos[$sucursal]['min'], $nro);
                $rangos[$sucursal]['max'] = max($rangos[$sucursal]['max'], $nro);
            }
        }

        $filas = [];
        $api = new ApiAnita;
        $puntoventasPorSucursal = $this->puntoventasPorSucursalEmpresa($empresaId);

        foreach ($rangos as $sucursal => $rango) {
            $where = " WHERE veng_letra = 'B'"
                ." AND veng_sucursal = '".$sucursal."'"
                ." AND veng_nro >= '".$rango['min']."'"
                ." AND veng_nro <= '".$rango['max']."'";

            $parsed = ApiAnita::parsearRespuestaLista($api->apiCall([
                'acc' => 'list',
                'tabla' => 'vengrav',
                'campos' => self::VENGRAV_CAMPOS,
                'whereArmado' => $where,
            ]));

            if ($parsed['error_lectura'] !== null) {
                throw new \RuntimeException('No se pudo listar vengrav sucursal '.$sucursal.': '.$parsed['error_lectura']);
            }

            $pv = $puntoventasPorSucursal[$sucursal] ?? null;
            foreach ($parsed['filas'] as $fila) {
                if ($pv !== null) {
                    $tipo = strtoupper(trim((string) ($fila->veng_tipo ?? '')));
                    $empresaCodigo = $pv->empresas?->codigo ?? $pv->empresa_id;
                    if (! KandikoAnitaVentaTipoSupport::cabeceraAnitaCorrespondeAlPv(
                        $tipo,
                        (string) $pv->codigo,
                        $empresaCodigo,
                        $pv->modofacturacion ?? null,
                    )) {
                        continue;
                    }
                }
                $filas[] = $fila;
            }
        }

        return $filas;
    }

    /**
     * venta por rangos ERP + ven_empresa (PK Informix, sin filtro fecha).
     *
     * @return list<object>
     */
    private function listarVentaPorRangosErp(
        int $empresaId,
        string $fechaDesde,
        string $fechaHasta,
        string $empresaCodigo,
    ): array {
        $rangos = $this->rangosNumeracionErpPorSucursal($empresaId, $fechaDesde, $fechaHasta);
        $filas = [];
        $api = new ApiAnita;
        $pvs = $this->puntoventasPorSucursalEmpresa($empresaId);

        foreach ($rangos as $sucursal => $rango) {
            $where = " WHERE ven_letra = 'B'"
                ." AND ven_sucursal = '".$sucursal."'"
                ." AND ven_nro >= '".$rango['min']."'"
                ." AND ven_nro <= '".$rango['max']."'"
                .GastronomiaAnitaImportEmpresaSupport::whereEmpresa('ven', $empresaCodigo);

            $parsed = ApiAnita::parsearRespuestaLista($api->apiCall([
                'acc' => 'list',
                'tabla' => 'venta',
                'campos' => self::VENTA_CAMPOS,
                'whereArmado' => $where,
            ]));

            if ($parsed['error_lectura'] !== null) {
                throw new \RuntimeException('No se pudo listar venta PK sucursal '.$sucursal.': '.$parsed['error_lectura']);
            }

            $pv = $pvs[$sucursal] ?? null;
            foreach ($parsed['filas'] as $fila) {
                if ($pv !== null) {
                    $tipo = strtoupper(trim((string) ($fila->ven_tipo ?? '')));
                    $empresaCodigoPv = $pv->empresas?->codigo ?? $pv->empresa_id;
                    if (! KandikoAnitaVentaTipoSupport::cabeceraAnitaCorrespondeAlPv(
                        $tipo,
                        (string) $pv->codigo,
                        $empresaCodigoPv,
                        $pv->modofacturacion ?? null,
                    )) {
                        continue;
                    }
                }
                $filas[] = $fila;
            }
        }

        return $filas;
    }

    /**
     * vengrav por rangos ERP (PK + codigo_tasa).
     *
     * @return list<object>
     */
    private function listarVengravPorRangosErp(int $empresaId, string $fechaDesde, string $fechaHasta): array
    {
        $rangos = $this->rangosNumeracionErpPorSucursal($empresaId, $fechaDesde, $fechaHasta);
        $filas = [];
        $api = new ApiAnita;
        $pvs = $this->puntoventasPorSucursalEmpresa($empresaId);

        foreach ($rangos as $sucursal => $rango) {
            $where = " WHERE veng_letra = 'B'"
                ." AND veng_sucursal = '".$sucursal."'"
                ." AND veng_nro >= '".$rango['min']."'"
                ." AND veng_nro <= '".$rango['max']."'";

            $parsed = ApiAnita::parsearRespuestaLista($api->apiCall([
                'acc' => 'list',
                'tabla' => 'vengrav',
                'campos' => self::VENGRAV_CAMPOS,
                'whereArmado' => $where,
            ]));

            if ($parsed['error_lectura'] !== null) {
                throw new \RuntimeException('No se pudo listar vengrav PK sucursal '.$sucursal.': '.$parsed['error_lectura']);
            }

            $pv = $pvs[$sucursal] ?? null;
            foreach ($parsed['filas'] as $fila) {
                if ($pv !== null) {
                    $tipo = strtoupper(trim((string) ($fila->veng_tipo ?? '')));
                    $empresaCodigoPv = $pv->empresas?->codigo ?? $pv->empresa_id;
                    if (! KandikoAnitaVentaTipoSupport::cabeceraAnitaCorrespondeAlPv(
                        $tipo,
                        (string) $pv->codigo,
                        $empresaCodigoPv,
                        $pv->modofacturacion ?? null,
                    )) {
                        continue;
                    }
                }
                $filas[] = $fila;
            }
        }

        return $filas;
    }

    /**
     * @return array<int, array{min:int,max:int}>
     */
    private function rangosNumeracionErpPorSucursal(int $empresaId, string $fechaDesde, string $fechaHasta): array
    {
        /** @var array<int, array{min:int,max:int}> $rangos */
        $rangos = [];
        $filasErp = Venta::query()
            ->selectRaw('puntoventa.codigo as codigo_pv, MIN(venta.numerocomprobante) as nro_min, MAX(venta.numerocomprobante) as nro_max')
            ->join('puntoventa', 'puntoventa.id', '=', 'venta.puntoventa_id')
            ->where('puntoventa.empresa_id', $empresaId)
            ->where('puntoventa.modofacturacion', '!=', 'M')
            ->whereDate('venta.fechajornada', '>=', $fechaDesde)
            ->whereDate('venta.fechajornada', '<=', $fechaHasta)
            ->whereHas('gastronomiaEmision')
            ->groupBy('codigo_pv')
            ->get();

        foreach ($filasErp as $row) {
            $sucursal = (int) preg_replace('/\D+/', '', (string) ($row->codigo_pv ?? ''));
            $min = (int) ($row->nro_min ?? 0);
            $max = (int) ($row->nro_max ?? 0);
            if ($sucursal <= 0 || $min <= 0 || $max <= 0) {
                continue;
            }
            if (! isset($rangos[$sucursal])) {
                $rangos[$sucursal] = ['min' => $min, 'max' => $max];
            } else {
                $rangos[$sucursal]['min'] = min($rangos[$sucursal]['min'], $min);
                $rangos[$sucursal]['max'] = max($rangos[$sucursal]['max'], $max);
            }
        }

        return $rangos;
    }

    /**
     * vencae por rangos de numeración ERP (incluye CAE sin cabecera venta).
     *
     * @return list<object>
     */
    private function listarVencaePorRangosErp(
        int $empresaId,
        string $fechaDesde,
        string $fechaHasta,
        string $empresaCodigo,
    ): array {
        $rangos = $this->rangosNumeracionErpPorSucursal($empresaId, $fechaDesde, $fechaHasta);
        $filas = [];
        $api = new ApiAnita;

        foreach ($rangos as $sucursal => $rango) {
            $where = " WHERE venc_letra = 'B'"
                ." AND venc_sucursal = '".$sucursal."'"
                ." AND venc_nro >= '".$rango['min']."'"
                ." AND venc_nro <= '".$rango['max']."'";

            $parsed = ApiAnita::parsearRespuestaLista($api->apiCall([
                'acc' => 'list',
                'tabla' => 'vencae',
                'campos' => self::VENCAE_CAMPOS,
                'whereArmado' => $where,
            ]));

            if ($parsed['error_lectura'] !== null) {
                throw new \RuntimeException('No se pudo listar vencae sucursal '.$sucursal.': '.$parsed['error_lectura']);
            }

            $pv = $this->puntoventasPorSucursalEmpresa($empresaId)[$sucursal] ?? null;
            foreach ($parsed['filas'] as $fila) {
                if ($pv !== null) {
                    $tipo = strtoupper(trim((string) ($fila->venc_tipo ?? '')));
                    $empresaCodigoPv = $pv->empresas?->codigo ?? $pv->empresa_id;
                    if (! KandikoAnitaVentaTipoSupport::cabeceraAnitaCorrespondeAlPv(
                        $tipo,
                        (string) $pv->codigo,
                        $empresaCodigoPv,
                        $pv->modofacturacion ?? null,
                    )) {
                        continue;
                    }
                }
                $filas[] = $fila;
            }
        }

        return $filas;
    }

    /**
     * @return array{manifest: array<string, mixed>, venta: list<object>}
     */
    public function resolverVentaCache(
        int $empresaId,
        string $fechaDesde,
        string $fechaHasta,
        bool $forzarDescarga = false,
    ): array {
        $desde = Carbon::parse($fechaDesde)->toDateString();
        $hasta = Carbon::parse($fechaHasta)->toDateString();
        $manifest = $this->descargar($empresaId, $desde, $hasta, $forzarDescarga);
        $cargado = $this->cargar($empresaId, $desde, $hasta);

        return [
            'manifest' => $manifest ?: $cargado['manifest'],
            'venta' => $cargado['venta'],
        ];
    }

    /**
     * Índice sucursal → jornada Y-m-d → clave comprobante → cabecera Anita (misma lógica que auditoría bulk).
     *
     * @param  list<object>  $filasAnita
     * @param  array<int, Puntoventa>  $pvPorSucursal
     * @return array<int, array<string, array<string, object>>>
     */
    public function indexarCabecerasPorSucursalJornada(array $filasAnita, array $pvPorSucursal): array
    {
        $map = [];

        foreach ($filasAnita as $fila) {
            $sucursal = (int) preg_replace('/\D+/', '', (string) ($fila->ven_sucursal ?? ''));
            $pv = $pvPorSucursal[$sucursal] ?? null;
            if ($pv === null) {
                continue;
            }

            $tipo = strtoupper(trim((string) ($fila->ven_tipo ?? '')));
            $nro = (int) ($fila->ven_nro ?? 0);
            if ($tipo === '' || $nro <= 0) {
                continue;
            }

            $empresaCodigo = $pv->empresas?->codigo ?? $pv->empresa_id;
            if (! KandikoAnitaVentaTipoSupport::cabeceraAnitaCorrespondeAlPv(
                $tipo,
                (string) $pv->codigo,
                $empresaCodigo,
                $pv->modofacturacion ?? null,
            )) {
                continue;
            }

            $fechaJornada = $this->fechaJornadaDesdeAnita((string) ($fila->ven_fecha_vto ?? ''));
            if ($fechaJornada === null) {
                continue;
            }

            $esKandikoCaea = KandikoAnitaVentaTipoSupport::esPvCaeaKandiko(
                (string) $pv->codigo,
                $empresaCodigo,
                $pv->modofacturacion ?? null,
            );

            if ($esKandikoCaea && in_array($tipo, KandikoAnitaVentaTipoSupport::tiposAnitaEquivalentesFacErp(), true)) {
                foreach (GastronomiaAnitaComprobantePkSupport::clavesConciliacionDesdeCabeceraAnita($fila, true) as $clave) {
                    $existente = $map[$sucursal][$fechaJornada][$clave] ?? null;
                    $tipoExistente = $existente !== null
                        ? strtoupper(trim((string) ($existente->ven_tipo ?? '')))
                        : '';
                    if ($existente === null || ($tipo === KandikoAnitaVentaTipoSupport::TIPO_VENTA_BRIDGE && $tipoExistente !== KandikoAnitaVentaTipoSupport::TIPO_VENTA_BRIDGE)) {
                        $map[$sucursal][$fechaJornada][$clave] = $fila;
                    }
                }

                continue;
            }

            $clave = GastronomiaAnitaComprobantePkSupport::claveVentaDesdeCabeceraAnita($fila);
            if ($clave !== null) {
                $map[$sucursal][$fechaJornada][$clave] = $fila;
            }
        }

        return $map;
    }

    private function fechaJornadaDesdeAnita(string $fechaEntera): ?string
    {
        $fechaEntera = preg_replace('/\D+/', '', $fechaEntera);
        if ($fechaEntera === null || strlen($fechaEntera) !== 8) {
            return null;
        }

        return substr($fechaEntera, 0, 4).'-'.substr($fechaEntera, 4, 2).'-'.substr($fechaEntera, 6, 2);
    }

    /**
     * @return array<int, Puntoventa>
     */
    public function puntoventasPorSucursalEmpresa(int $empresaId): array
    {
        $map = [];
        $puntoventas = Puntoventa::query()
            ->with('empresas')
            ->where('empresa_id', $empresaId)
            ->where('modofacturacion', '!=', 'M')
            ->get();

        foreach ($puntoventas as $pv) {
            $suc = (int) preg_replace('/\D+/', '', (string) $pv->codigo);
            if ($suc > 0) {
                $map[$suc] = $pv;
            }
        }

        return $map;
    }

    /**
     * @return list<object>
     */
    private function listarCtamovBulk(int $empresaCodigo, int $fechaDesdeEntera, int $fechaHastaEntera): array
    {
        if ($empresaCodigo <= 0) {
            return [];
        }

        $where = ' WHERE ctav_empresa='.$empresaCodigo
            .' AND ctav_fecha BETWEEN '.$fechaDesdeEntera.' AND '.$fechaHastaEntera;

        $parsed = ApiAnita::parsearRespuestaLista((new ApiAnita)->apiCall([
            'acc' => 'list',
            'sistema' => 'contab',
            'tabla' => 'ctamov',
            'campos' => self::CTAMOV_CAMPOS,
            'whereArmado' => $where,
            'orderBy' => 'ctav_fecha, ctav_nro_asiento, ctav_nro_linea',
        ]));

        if ($parsed['error_lectura'] !== null) {
            throw new \RuntimeException('No se pudo listar ctamov Anita: '.$parsed['error_lectura']);
        }

        return $parsed['filas'];
    }

    /**
     * @return array<string, list<object>>
     */
    private function listarRendgastroPorDia(int $empresaId, string $fechaDesde, string $fechaHasta): array
    {
        $porDia = [];

        foreach (CarbonPeriod::create($fechaDesde, $fechaHasta) as $dia) {
            $fecha = $dia->toDateString();
            $fechaEntera = (int) str_replace('-', '', $fecha);
            $porDia[$fecha] = $this->rendgastroSupport->listarCabecerasEmpresaFechaDetalle($empresaId, $fechaEntera);
        }

        return $porDia;
    }

    private function guardarJson(string $ruta, mixed $data): void
    {
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        if ($json === false) {
            throw new \RuntimeException('No se pudo serializar JSON: '.$ruta);
        }

        if (file_put_contents($ruta, $json) === false) {
            throw new \RuntimeException('No se pudo escribir: '.$ruta);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function leerJson(string $ruta): array
    {
        $raw = file_get_contents($ruta);
        if ($raw === false) {
            throw new \RuntimeException('No se pudo leer: '.$ruta);
        }

        $data = json_decode($raw, true);
        if (! is_array($data)) {
            throw new \RuntimeException('JSON inválido: '.$ruta);
        }

        return $data;
    }

    /**
     * @return list<object>
     */
    private function leerJsonFilas(string $ruta): array
    {
        $data = $this->leerJson($ruta);
        $filas = [];
        foreach ($data as $row) {
            $filas[] = (object) $row;
        }

        return $filas;
    }

    /**
     * @return array<string, list<object>>
     */
    private function leerJsonRendgastro(string $ruta): array
    {
        $data = $this->leerJson($ruta);
        $out = [];
        foreach ($data as $fecha => $filas) {
            if (! is_array($filas)) {
                continue;
            }
            $out[(string) $fecha] = array_map(static fn (array $row): object => (object) $row, $filas);
        }

        return $out;
    }
}
