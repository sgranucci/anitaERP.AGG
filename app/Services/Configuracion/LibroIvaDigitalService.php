<?php

namespace App\Services\Configuracion;

use App\Services\Configuracion\LibroIvaDigital\LibroIvaDigitalAnuladosGenerador;
use App\Services\Configuracion\LibroIvaDigital\LibroIvaDigitalComprasGenerador;
use App\Services\Configuracion\LibroIvaDigital\LibroIvaDigitalImportacionesGenerador;
use App\Services\Configuracion\LibroIvaDigital\LibroIvaDigitalIvaSimpleGenerador;
use App\Services\Configuracion\LibroIvaDigital\LibroIvaDigitalVentasGenerador;
use App\Support\Configuracion\LibroIvaDigital\LibroIvaDigitalFormatoSupport;
use App\Support\Configuracion\LibroIvaDigital\LibroIvaDigitalValidacionSupport;
use Illuminate\Support\Facades\File;
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
     * @param  array<string, mixed>  $resultado
     */
    public function crearZipDescarga(array $resultado, int $empresaId): string
    {
        $periodo = (string) ($resultado['periodo']['etiqueta'] ?? date('Y-m'));
        $dir = storage_path('app/libro_iva_digital/'.$empresaId.'_'.$periodo);
        File::ensureDirectoryExists($dir);

        $archivos = [
            'LIBRO_IVA_DIGITAL_VENTAS_CBTE.txt' => LibroIvaDigitalFormatoSupport::lineasDesdeContenido(
                (string) ($resultado['ventas']['ventas_cbte'] ?? ''),
            ),
            'LIBRO_IVA_DIGITAL_VENTAS_ALICUOTAS.txt' => LibroIvaDigitalFormatoSupport::lineasDesdeContenido(
                (string) ($resultado['ventas']['ventas_alicuotas'] ?? ''),
            ),
            'LIBRO_IVA_DIGITAL_VENTAS_ANULADOS.txt' => LibroIvaDigitalFormatoSupport::lineasDesdeContenido(
                (string) ($resultado['anulados']['ventas'] ?? ''),
            ),
            'LIBRO_IVA_DIGITAL_COMPRAS_CBTE.txt' => LibroIvaDigitalFormatoSupport::lineasDesdeContenido(
                (string) ($resultado['compras']['compras_cbte'] ?? ''),
            ),
            'LIBRO_IVA_DIGITAL_COMPRAS_ALICUOTAS.txt' => LibroIvaDigitalFormatoSupport::lineasDesdeContenido(
                (string) ($resultado['compras']['compras_alicuotas'] ?? ''),
            ),
            'LIBRO_IVA_DIGITAL_COMPRAS_ANULADOS.txt' => LibroIvaDigitalFormatoSupport::lineasDesdeContenido(
                (string) ($resultado['anulados']['compras'] ?? ''),
            ),
            'LIBRO_IVA_DIGITAL_IMPORTACION_BIENES_ALICUOTAS.txt' => LibroIvaDigitalFormatoSupport::lineasDesdeContenido(
                (string) ($resultado['importaciones']['importacion_bienes_alicuotas'] ?? ''),
            ),
            'LIBRO_IVA_DIGITAL_IMPORTACION_SERVICIOS.txt' => LibroIvaDigitalFormatoSupport::lineasDesdeContenido(
                (string) ($resultado['importaciones']['importacion_servicios'] ?? ''),
            ),
            'IVA_SIMPLE_DEBITO_FISCAL.csv' => LibroIvaDigitalFormatoSupport::lineasDesdeContenido(
                (string) ($resultado['iva_simple']['debito_fiscal'] ?? ''),
            ),
            'IVA_SIMPLE_CREDITO_FISCAL.csv' => LibroIvaDigitalFormatoSupport::lineasDesdeContenido(
                (string) ($resultado['iva_simple']['credito_fiscal'] ?? ''),
            ),
            'IVA_SIMPLE_RESTITUCION_DEBITO_FISCAL.csv' => LibroIvaDigitalFormatoSupport::lineasDesdeContenido(
                (string) ($resultado['iva_simple']['restitucion_debito'] ?? ''),
            ),
            'IVA_SIMPLE_RESTITUCION_CREDITO_FISCAL.csv' => LibroIvaDigitalFormatoSupport::lineasDesdeContenido(
                (string) ($resultado['iva_simple']['restitucion_credito'] ?? ''),
            ),
        ];

        foreach ($archivos as $nombre => $contenido) {
            file_put_contents($dir.'/'.$nombre, $contenido);
        }

        $zipPath = $dir.'/libro_iva_digital_'.$periodo.'.zip';
        if (file_exists($zipPath)) {
            unlink($zipPath);
        }

        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('No se pudo crear el archivo ZIP de Libro IVA Digital.');
        }

        foreach (array_keys($archivos) as $nombre) {
            $zip->addFile($dir.'/'.$nombre, $nombre);
        }
        $zip->close();

        return $zipPath;
    }
}
