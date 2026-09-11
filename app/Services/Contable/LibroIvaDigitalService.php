<?php

namespace App\Services\Contable;

use App\Services\Contable\LibroIvaDigital\LibroIvaDigitalAnuladosGenerador;
use App\Services\Contable\LibroIvaDigital\LibroIvaDigitalComprasGenerador;
use App\Services\Contable\LibroIvaDigital\LibroIvaDigitalImportacionesGenerador;
use App\Services\Contable\LibroIvaDigital\LibroIvaDigitalIvaSimpleGenerador;
use App\Services\Contable\LibroIvaDigital\LibroIvaDigitalVentasGenerador;
use App\Support\Contable\LibroIvaDigital\LibroIvaDigitalArchivosSupport;
use App\Support\Contable\LibroIvaDigital\LibroIvaDigitalCacheSupport;
use App\Support\Contable\LibroIvaDigital\LibroIvaDigitalValidacionSupport;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use ZipArchive;

class LibroIvaDigitalService
{
    public function __construct(
        private readonly LibroIvaDigitalVentasGenerador $ventasGenerador,
        private readonly LibroIvaDigitalComprasGenerador $comprasGenerador,
        private readonly LibroIvaDigitalIvaSimpleGenerador $ivaSimpleGenerador,
        private readonly LibroIvaDigitalAnuladosGenerador $anuladosGenerador,
        private readonly LibroIvaDigitalImportacionesGenerador $importacionesGenerador,
    ) {
    }

    /**
     * @param  array{
     *     por_fecha_jornada?: bool,
     *     prorrateo_cf_global?: bool,
     *     completar_compras_anita?: bool,
     *     completar_fsl_anita?: bool
     * }  $opciones
     * @return array<string, mixed>
     */
    public function generar(int $empresaId, int $anio, int $mes, array $opciones = []): array
    {
        $t0 = microtime(true);
        $ventas = $this->ventasGenerador->generar($empresaId, $anio, $mes, $opciones);
        Log::info('libro_iva_digital.generar.ventas', [
            'empresa_id' => $empresaId,
            'periodo' => sprintf('%04d-%02d', $anio, $mes),
            'comprobantes' => $ventas['resumen']['comprobantes'] ?? 0,
            'ms' => round((microtime(true) - $t0) * 1000, 1),
        ]);

        $t1 = microtime(true);
        $compras = $this->comprasGenerador->generar($empresaId, $anio, $mes, $opciones);
        Log::info('libro_iva_digital.generar.compras', [
            'empresa_id' => $empresaId,
            'comprobantes' => $compras['resumen']['comprobantes'] ?? 0,
            'ms' => round((microtime(true) - $t1) * 1000, 1),
        ]);

        $importaciones = $this->importacionesGenerador->generar($empresaId, $anio, $mes, $opciones);
        $anulados = $this->anuladosGenerador->generar($empresaId, $anio, $mes, $opciones);
        $comprasRegistros = array_merge(
            $compras['registros'] ?? [],
            $importaciones['registros'] ?? [],
        );
        $ivaSimple = $this->ivaSimpleGenerador->generar($empresaId, $anio, $mes, array_merge($opciones, [
            'ventas_registros' => $ventas['registros'] ?? [],
            'compras_registros' => $comprasRegistros,
        ]));

        $cabecerasImportacion = $importaciones['compras_cbte_importacion'] ?? [];
        if ($cabecerasImportacion !== []) {
            $comprasCbte = trim((string) ($compras['compras_cbte'] ?? ''));
            $extra = implode("\r\n", $cabecerasImportacion);
            $compras['compras_cbte'] = $comprasCbte === '' ? $extra : $comprasCbte."\r\n".$extra;
            $compras['resumen']['comprobantes'] = (int) ($compras['resumen']['comprobantes'] ?? 0) + count($cabecerasImportacion);
        }

        $resultado = [
            'ventas' => $ventas,
            'compras' => $compras,
            'importaciones' => $importaciones,
            'anulados' => $anulados,
            'iva_simple' => $ivaSimple,
            'periodo' => [
                'anio' => $anio,
                'mes' => $mes,
                'etiqueta' => sprintf('%04d-%02d', $anio, $mes),
            ],
            'opciones' => [
                'por_fecha_jornada' => (bool) ($opciones['por_fecha_jornada'] ?? false),
                'prorrateo_cf_global' => (bool) ($opciones['prorrateo_cf_global'] ?? false),
                'completar_compras_anita' => (bool) ($opciones['completar_compras_anita'] ?? true),
                'completar_fsl_anita' => (bool) ($opciones['completar_fsl_anita'] ?? true),
            ],
            'validaciones' => [],
        ];

        $resultado['validaciones'] = LibroIvaDigitalValidacionSupport::validar($resultado);

        Log::info('libro_iva_digital.generar.ok', [
            'empresa_id' => $empresaId,
            'periodo' => sprintf('%04d-%02d', $anio, $mes),
            'ms' => round((microtime(true) - $t0) * 1000, 1),
            'mem_mb' => round(memory_get_peak_usage(true) / 1048576, 1),
        ]);

        return $resultado;
    }

    /**
     * @param  array{por_fecha_jornada?: bool, prorrateo_cf_global?: bool, completar_compras_anita?: bool, completar_fsl_anita?: bool}  $opciones
     * @return array<string, mixed>
     */
    public function generarYCachear(int $empresaId, int $anio, int $mes, array $opciones = []): array
    {
        $firma = LibroIvaDigitalCacheSupport::firma($empresaId, $anio, $mes, $opciones);
        $lock = Cache::lock(LibroIvaDigitalCacheSupport::lockKey($firma), 600);

        try {
            $lock->block(120);
        } catch (\Illuminate\Contracts\Cache\LockTimeoutException) {
            $cached = LibroIvaDigitalCacheSupport::leer($firma);
            if ($cached !== null) {
                return $cached;
            }

            throw new \RuntimeException(
                'El Libro IVA Digital ya se está generando para este período. Espere un momento y vuelva a descargar el ZIP.'
            );
        }

        try {
            $cached = LibroIvaDigitalCacheSupport::leer($firma);
            if ($cached !== null) {
                return $cached;
            }

            $resultado = $this->generar($empresaId, $anio, $mes, $opciones);
            LibroIvaDigitalCacheSupport::guardar($firma, $resultado);

            return LibroIvaDigitalCacheSupport::compactar($resultado);
        } finally {
            $lock->release();
        }
    }

    /**
     * @param  array{por_fecha_jornada?: bool, prorrateo_cf_global?: bool, completar_compras_anita?: bool, completar_fsl_anita?: bool}  $opciones
     * @return array<string, mixed>|null
     */
    public function leerCache(int $empresaId, int $anio, int $mes, array $opciones = []): ?array
    {
        return LibroIvaDigitalCacheSupport::leer(
            LibroIvaDigitalCacheSupport::firma($empresaId, $anio, $mes, $opciones),
        );
    }

    /**
     * Cache si existe; si no, genera una sola vez (consultar o ZIP).
     *
     * @param  array{por_fecha_jornada?: bool, prorrateo_cf_global?: bool, completar_compras_anita?: bool, completar_fsl_anita?: bool}  $opciones
     * @return array<string, mixed>
     */
    public function obtenerParaExportar(int $empresaId, int $anio, int $mes, array $opciones = []): array
    {
        $cached = $this->leerCache($empresaId, $anio, $mes, $opciones);
        if ($cached !== null) {
            return $cached;
        }

        return $this->generarYCachear($empresaId, $anio, $mes, $opciones);
    }

    /**
     * @param  array{por_fecha_jornada?: bool, prorrateo_cf_global?: bool, completar_compras_anita?: bool}  $opciones
     * @return array<string, mixed>
     */
    public function generarIvaSimple(int $empresaId, int $anio, int $mes, array $opciones = []): array
    {
        $ventas = $this->ventasGenerador->generar($empresaId, $anio, $mes, $opciones);
        $compras = $this->comprasGenerador->generar($empresaId, $anio, $mes, $opciones);
        $importaciones = $this->importacionesGenerador->generar($empresaId, $anio, $mes, $opciones);

        return [
            'iva_simple' => $this->ivaSimpleGenerador->generar($empresaId, $anio, $mes, array_merge($opciones, [
                'ventas_registros' => $ventas['registros'] ?? [],
                'compras_registros' => array_merge(
                    $compras['registros'] ?? [],
                    $importaciones['registros'] ?? [],
                ),
            ])),
            'periodo' => [
                'anio' => $anio,
                'mes' => $mes,
                'etiqueta' => sprintf('%04d-%02d', $anio, $mes),
            ],
            'opciones' => [
                'por_fecha_jornada' => (bool) ($opciones['por_fecha_jornada'] ?? false),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $resultado
     */
    public function crearZipDescarga(array $resultado, int $empresaId): string
    {
        $archivos = array_merge(
            LibroIvaDigitalArchivosSupport::archivosLibroIvaDigital($resultado),
            LibroIvaDigitalArchivosSupport::archivosIvaSimple($resultado),
        );

        return $this->crearZip($resultado, $empresaId, $archivos, 'libro_iva_digital');
    }

    /**
     * @param  array<string, mixed>  $resultado
     */
    public function crearZipIvaSimple(array $resultado, int $empresaId): string
    {
        return $this->crearZip(
            $resultado,
            $empresaId,
            LibroIvaDigitalArchivosSupport::archivosIvaSimple($resultado),
            'iva_simple',
        );
    }

    /**
     * @param  array<string, mixed>  $resultado
     * @param  array<string, string>  $archivos
     */
    private function crearZip(array $resultado, int $empresaId, array $archivos, string $prefijoZip): string
    {
        $periodo = (string) ($resultado['periodo']['etiqueta'] ?? date('Y-m'));
        $sufijoFecha = ! empty($resultado['opciones']['por_fecha_jornada']) ? '_jornada' : '';
        $sufijoProrrateo = ! empty($resultado['opciones']['prorrateo_cf_global']) ? '_prorrateo' : '';
        $dir = storage_path('framework/cache');
        if (! is_dir($dir)) {
            throw new \RuntimeException('No existe el directorio de caché de Laravel.');
        }

        $zipPath = $dir.'/'.$prefijoZip.'_'.$empresaId.'_'.$periodo.$sufijoFecha.$sufijoProrrateo.'.zip';
        if (file_exists($zipPath)) {
            @unlink($zipPath);
        }

        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('No se pudo crear el archivo ZIP de Libro IVA Digital.');
        }

        foreach ($archivos as $nombre => $contenido) {
            $zip->addFromString($nombre, $contenido);
        }
        $zip->close();

        return $zipPath;
    }
}
