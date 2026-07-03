<?php

declare(strict_types=1);

namespace App\Support\Ventas\GastronomiaAnitaImport;

use App\ApiAnita;
use App\Models\Configuracion\Empresa;
use App\Support\Ventas\GastronomiaAnitaImportEmpresaSupport;
use Carbon\Carbon;
use Illuminate\Support\Facades\File;

/**
 * Descarga venta/stkmov/vengrav/vencae/resvta a storage local (una pasada por empresa/jornada).
 * Patrón similar a {@see \App\Support\Ventas\Gastronomia\GastronomiaAnitaMesCacheSupport} para auditorías.
 */
final class GastronomiaAnitaImportCacheSupport
{
    private const VENTA_CAMPOS = 'ven_tipo,ven_letra,ven_sucursal,ven_nro,ven_empresa,ven_fecha,ven_fecha_vto,ven_monto,ven_gravado,ven_exento,ven_impuesto1,ven_porc_desc,ven_monto_desc,ven_cod_mon,ven_cotizacion,ven_nombre_cliente,ven_direccion_cli,ven_cod_postal_cli,ven_cuit_cli,ven_vendedor';

    private const STKMOV_CAMPOS = 'stkv_tipo,stkv_letra,stkv_sucursal,stkv_nro,stkv_articulo,stkv_cantidad,stkv_precio,stkv_cod_impuesto,stkv_descuento';

    private const VENGRAV_CAMPOS = 'veng_tipo,veng_letra,veng_sucursal,veng_nro,veng_codigo_tasa,veng_gravado,veng_impuesto,veng_tasa';

    private const VENCAE_CAMPOS = 'venc_tipo,venc_letra,venc_sucursal,venc_nro,venc_nro_cae,venc_fecha_vto';

    private const RESVTA_CAMPOS = 'resv_tipo,resv_letra,resv_sucursal,resv_nro,resv_fecha,resv_hora,resv_host,resv_cubierto,resv_mozo,resv_total,resv_tot_efectivo,resv_tot_fiserv,resv_tot_qr,resv_tot_ctacte,resv_tot_tarjeta,resv_tipo_dto,resv_cliente';

    public function directorioCache(int $empresaId, string $fechaDesde, string $fechaHasta, string $sufijo = ''): string
    {
        $desde = Carbon::parse($fechaDesde)->format('Ymd');
        $hasta = Carbon::parse($fechaHasta)->format('Ymd');
        $base = trim((string) config('gastronomia_anita_import.cache_directorio', 'anita_import_cache'));
        $sufijo = preg_replace('/[^a-zA-Z0-9_-]+/', '', $sufijo) ?? '';

        return storage_path('app/'.$base.'/empresa_'.$empresaId.'_'.$desde.'_'.$hasta.($sufijo !== '' ? '_'.$sufijo : ''));
    }

    public function cacheCompleta(int $empresaId, string $fechaDesde, string $fechaHasta, string $sufijo = ''): bool
    {
        $dir = $this->directorioCache($empresaId, $fechaDesde, $fechaHasta, $sufijo);

        return is_file($dir.'/manifest.json')
            && is_file($dir.'/venta.json')
            && is_file($dir.'/stkmov.json')
            && is_file($dir.'/vengrav.json')
            && is_file($dir.'/vencae.json')
            && is_file($dir.'/resvta.json');
    }

    /**
     * @param  array<int, array{min:int,max:int}|list<array{min:int,max:int}>>|null  $rangosPorSucursal  Si se indica, solo consulta esas sucursal/número al bridge (no todo el mes).
     * @return array<string, mixed>
     */
    public function descargar(
        int $empresaId,
        string $fechaDesde,
        string $fechaHasta,
        bool $forzar = false,
        ?array $rangosPorSucursal = null,
        string $sufijoCache = '',
    ): array {
        $desde = Carbon::parse($fechaDesde)->toDateString();
        $hasta = Carbon::parse($fechaHasta)->toDateString();
        if ($desde > $hasta) {
            throw new \InvalidArgumentException('fecha-desde no puede ser posterior a fecha-hasta.');
        }

        if (! $forzar && $this->cacheCompleta($empresaId, $desde, $hasta, $sufijoCache)) {
            return $this->leerManifest($empresaId, $desde, $hasta, $sufijoCache);
        }

        $dir = $this->directorioCache($empresaId, $desde, $hasta, $sufijoCache);
        File::ensureDirectoryExists($dir);

        $empresa = Empresa::query()->findOrFail($empresaId);
        $empresaCodigo = trim((string) ($empresa->codigo ?? $empresaId));
        $fechaDesdeEntera = (int) str_replace('-', '', $desde);
        $fechaHastaEntera = (int) str_replace('-', '', $hasta);

        $consultasBridge = 0;
        if ($rangosPorSucursal !== null && $rangosPorSucursal !== []) {
            $venta = $this->listarVentaBulkPorSucursales(
                $empresaId,
                $empresaCodigo,
                $fechaDesdeEntera,
                $fechaHastaEntera,
                $rangosPorSucursal,
                $consultasBridge,
            );
            $rangos = $rangosPorSucursal;
            $modo = 'import_bulk_scoped';
        } else {
            $venta = $this->listarVentaBulk($empresaId, $empresaCodigo, $fechaDesdeEntera, $fechaHastaEntera, $consultasBridge);
            $rangos = $this->rangosPorSucursalDesdeVenta($venta);
            $modo = 'import_bulk';
        }

        $stkmov = $this->listarDetalleBulk($empresaId, 'stkmov', self::STKMOV_CAMPOS, 'stkv', $rangos, $empresaCodigo, $consultasBridge);
        $vengrav = $this->listarDetalleBulk($empresaId, 'vengrav', self::VENGRAV_CAMPOS, 'veng', $rangos, null, $consultasBridge);
        $vencae = $this->listarDetalleBulk($empresaId, 'vencae', self::VENCAE_CAMPOS, 'venc', $rangos, null, $consultasBridge);
        $resvta = $this->listarDetalleBulk($empresaId, 'resvta', self::RESVTA_CAMPOS, 'resv', $rangos, null, $consultasBridge);

        $this->guardarJson($dir.'/venta.json', $venta);
        $this->guardarJson($dir.'/stkmov.json', $stkmov);
        $this->guardarJson($dir.'/vengrav.json', $vengrav);
        $this->guardarJson($dir.'/vencae.json', $vencae);
        $this->guardarJson($dir.'/resvta.json', $resvta);

        $bridge = GastronomiaAnitaImportBridgeSupport::parametrosBridge($empresaId);
        $manifest = [
            'empresa_id' => $empresaId,
            'empresa_codigo' => $empresaCodigo,
            'empresa_nombre' => (string) ($empresa->nombre ?? ''),
            'fecha_desde' => $desde,
            'fecha_hasta' => $hasta,
            'generado_at' => now()->toIso8601String(),
            'modo' => $modo,
            'cache_sufijo' => $sufijoCache !== '' ? $sufijoCache : null,
            'rangos_sucursal' => $rangos,
            'bridge' => $bridge['servidor'] ?? ApiAnita::urlBridge(),
            'ifx_server' => $bridge['ifx_server'] ?? null,
            'directorio' => $dir,
            'consultas_bridge' => $consultasBridge,
            'counts' => [
                'venta' => count($venta),
                'stkmov' => count($stkmov),
                'vengrav' => count($vengrav),
                'vencae' => count($vencae),
                'resvta' => count($resvta),
                'sucursales' => count($rangos),
            ],
        ];

        $this->guardarJson($dir.'/manifest.json', $manifest);

        return $manifest;
    }

    public function crearReader(int $empresaId, string $fechaDesde, string $fechaHasta, string $sufijo = ''): GastronomiaAnitaImportCacheReader
    {
        $desde = Carbon::parse($fechaDesde)->toDateString();
        $hasta = Carbon::parse($fechaHasta)->toDateString();

        if (! $this->cacheCompleta($empresaId, $desde, $hasta, $sufijo)) {
            throw new \RuntimeException(
                'Cache import Anita inexistente o incompleta para empresa '.$empresaId.' '.$desde.'..'.$hasta
                .'. Ejecute descargar() o gastronomia:precargar-cache-import-anita.'
            );
        }

        $dir = $this->directorioCache($empresaId, $desde, $hasta, $sufijo);

        return new GastronomiaAnitaImportCacheReader(
            $this->leerJsonFilas($dir.'/venta.json'),
            $this->leerJsonFilas($dir.'/stkmov.json'),
            $this->leerJsonFilas($dir.'/vengrav.json'),
            $this->leerJsonFilas($dir.'/vencae.json'),
            $this->leerJsonFilas($dir.'/resvta.json'),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function leerManifest(int $empresaId, string $fechaDesde, string $fechaHasta, string $sufijo = ''): array
    {
        $dir = $this->directorioCache(
            $empresaId,
            Carbon::parse($fechaDesde)->toDateString(),
            Carbon::parse($fechaHasta)->toDateString(),
            $sufijo,
        );

        return $this->leerJson($dir.'/manifest.json');
    }

    /**
     * @return list<object>
     */
    private function listarVentaBulk(
        int $empresaId,
        string $empresaCodigo,
        int $fechaDesdeEntera,
        int $fechaHastaEntera,
        int &$consultasBridge,
    ): array {
        $where = " WHERE ven_letra = 'B'"
            ." AND ven_fecha_vto >= '".$fechaDesdeEntera."'"
            ." AND ven_fecha_vto <= '".$fechaHastaEntera."' "
            .GastronomiaAnitaImportEmpresaSupport::whereEmpresa('ven', $empresaCodigo);

        $parsed = $this->apiCall($empresaId, [
            'acc' => 'list',
            'tabla' => 'venta',
            'campos' => self::VENTA_CAMPOS,
            'whereArmado' => $where,
            'orderBy' => 'ven_fecha_vto, ven_sucursal, ven_nro',
        ], $consultasBridge);

        if ($parsed['error_lectura'] !== null) {
            throw new \RuntimeException('No se pudo listar venta Anita (bulk import): '.$parsed['error_lectura']);
        }

        return $parsed['filas'];
    }

    /**
     * Una consulta bridge por sucursal (solo rangos del plan de importación).
     *
     * @param  array<int, array{min:int,max:int}|list<array{min:int,max:int}>>  $rangosPorSucursal
     * @return list<object>
     */
    private function listarVentaBulkPorSucursales(
        int $empresaId,
        string $empresaCodigo,
        int $fechaDesdeEntera,
        int $fechaHastaEntera,
        array $rangosPorSucursal,
        int &$consultasBridge,
    ): array {
        $filas = [];
        ksort($rangosPorSucursal);
        foreach ($this->rangosSucursalComoLista($rangosPorSucursal) as $sucursal => $rango) {
            $min = (int) ($rango['min'] ?? 0);
            $max = (int) ($rango['max'] ?? 0);
            if ($sucursal <= 0 || $min <= 0 || $max < $min) {
                continue;
            }

            $where = " WHERE ven_letra = 'B'"
                ." AND ven_sucursal = '".$sucursal."'"
                ." AND ven_nro >= '".$min."'"
                ." AND ven_nro <= '".$max."'"
                ." AND ven_fecha_vto >= '".$fechaDesdeEntera."'"
                ." AND ven_fecha_vto <= '".$fechaHastaEntera."' "
                .GastronomiaAnitaImportEmpresaSupport::whereEmpresa('ven', $empresaCodigo);

            $parsed = $this->apiCall($empresaId, [
                'acc' => 'list',
                'tabla' => 'venta',
                'campos' => self::VENTA_CAMPOS,
                'whereArmado' => $where,
                'orderBy' => 'ven_nro',
            ], $consultasBridge);

            if ($parsed['error_lectura'] !== null) {
                throw new \RuntimeException(
                    'No se pudo listar venta Anita sucursal '.$sucursal.' (bulk scoped): '.$parsed['error_lectura']
                );
            }

            foreach ($parsed['filas'] as $fila) {
                $filas[] = $fila;
            }
        }

        return $filas;
    }

    /**
     * @param  list<object>  $venta
     * @return array<int, array{min:int,max:int}>
     */
    private function rangosPorSucursalDesdeVenta(array $venta): array
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

        return $rangos;
    }

    /**
     * @param  array<int, array{min:int,max:int}|list<array{min:int,max:int}>>  $rangos
     * @return list<object>
     */
    private function listarDetalleBulk(
        int $empresaId,
        string $tabla,
        string $campos,
        string $prefijo,
        array $rangos,
        ?string $empresaCodigo,
        int &$consultasBridge,
    ): array {
        $filas = [];
        foreach ($this->rangosSucursalComoLista($rangos) as $sucursal => $rango) {
            $where = " WHERE ".$prefijo."_letra = 'B'"
                ." AND ".$prefijo."_sucursal = '".$sucursal."'"
                ." AND ".$prefijo."_nro >= '".$rango['min']."'"
                ." AND ".$prefijo."_nro <= '".$rango['max']."'";

            if ($empresaCodigo !== null && $prefijo === 'stkv') {
                $where .= GastronomiaAnitaImportEmpresaSupport::whereEmpresa('stkv', $empresaCodigo);
            }

            $parsed = $this->apiCall($empresaId, [
                'acc' => 'list',
                'tabla' => $tabla,
                'campos' => $campos,
                'whereArmado' => $where,
            ], $consultasBridge);

            if ($parsed['error_lectura'] !== null) {
                throw new \RuntimeException(
                    'No se pudo listar '.$tabla.' sucursal '.$sucursal.' (bulk import): '.$parsed['error_lectura']
                );
            }

            foreach ($parsed['filas'] as $fila) {
                $filas[] = $fila;
            }
        }

        return $filas;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{filas: list<object>, error_lectura: ?string}
     */
    private function apiCall(int $empresaId, array $payload, int &$consultasBridge): array
    {
        $consultasBridge++;

        return ApiAnita::parsearRespuestaLista(
            (new ApiAnita)->apiCall(GastronomiaAnitaImportBridgeSupport::mergePayload($payload, $empresaId)),
        );
    }

    /**
     * @param  array<int, array{min:int,max:int}|list<array{min:int,max:int}>>  $rangosPorSucursal
     * @return \Generator<int, array{min:int,max:int}, mixed, void>
     */
    private function rangosSucursalComoLista(array $rangosPorSucursal): \Generator
    {
        foreach ($rangosPorSucursal as $sucursal => $def) {
            $sucursal = (int) $sucursal;
            if (isset($def['min'], $def['max']) && ! isset($def[0])) {
                yield $sucursal => [
                    'min' => (int) $def['min'],
                    'max' => (int) $def['max'],
                ];

                continue;
            }

            if (! is_array($def)) {
                continue;
            }

            foreach ($def as $rango) {
                if (! is_array($rango)) {
                    continue;
                }
                $min = (int) ($rango['min'] ?? 0);
                $max = (int) ($rango['max'] ?? 0);
                if ($min <= 0 || $max < $min) {
                    continue;
                }
                yield $sucursal => ['min' => $min, 'max' => $max];
            }
        }
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
            if (is_array($row)) {
                $filas[] = (object) $row;
            }
        }

        return $filas;
    }
}
