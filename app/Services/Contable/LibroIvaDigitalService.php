<?php

namespace App\Services\Contable;

use App\Services\Contable\LibroIvaDigital\LibroIvaDigitalAnuladosGenerador;
use App\Services\Contable\LibroIvaDigital\LibroIvaDigitalComprasGenerador;
use App\Services\Contable\LibroIvaDigital\LibroIvaDigitalImportacionesGenerador;
use App\Services\Contable\LibroIvaDigital\LibroIvaDigitalIvaSimpleGenerador;
use App\Services\Contable\LibroIvaDigital\LibroIvaDigitalVentasGenerador;
use App\Support\Contable\LibroIvaDigital\LibroIvaDigitalArchivosSupport;
use App\Support\Contable\LibroIvaDigital\LibroIvaDigitalValidacionSupport;
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
     * @return array<string, mixed>
     */
    public function generar(int $empresaId, int $anio, int $mes): array
    {
        $ventas = $this->ventasGenerador->generar($empresaId, $anio, $mes);
        $compras = $this->comprasGenerador->generar($empresaId, $anio, $mes);
        $importaciones = $this->importacionesGenerador->generar($empresaId, $anio, $mes);
        $anulados = $this->anuladosGenerador->generar($empresaId, $anio, $mes);
        $ivaSimple = $this->ivaSimpleGenerador->generar($empresaId, $anio, $mes);

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
            'validaciones' => [],
        ];

        $resultado['validaciones'] = LibroIvaDigitalValidacionSupport::validar($resultado);

        return $resultado;
    }

    /**
     * @return array<string, mixed>
     */
    public function generarIvaSimple(int $empresaId, int $anio, int $mes): array
    {
        return [
            'iva_simple' => $this->ivaSimpleGenerador->generar($empresaId, $anio, $mes),
            'periodo' => [
                'anio' => $anio,
                'mes' => $mes,
                'etiqueta' => sprintf('%04d-%02d', $anio, $mes),
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
        $dir = storage_path('framework/cache');
        if (! is_dir($dir)) {
            throw new \RuntimeException('No existe el directorio de caché de Laravel.');
        }

        $zipPath = $dir.'/'.$prefijoZip.'_'.$empresaId.'_'.$periodo.'.zip';
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
