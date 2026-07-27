<?php

namespace App\Services\Solicitudpago;

use App\Models\Solicitudpago\Solicitudpago;
use App\Models\Solicitudpago\Solicitudpago_Archivo;
use App\Support\Solicitudpago\SolicitudpagoArchivoStorageSupport;
use Jurosh\PDFMerge\PDFMerger;
use RuntimeException;

/**
 * Une los adjuntos de una SP (PDF e imágenes) en un único PDF descargable.
 */
class SolicitudpagoArchivosFusionService
{
    private const EXT_PDF = ['pdf'];

    private const EXT_IMAGEN = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    /**
     * @return array{contenido: string, nombre: string, omitidos: list<string>}
     */
    public function fusionar(Solicitudpago $sp): array
    {
        ini_set('memory_limit', '1024M');
        ini_set('max_execution_time', '300');

        $archivos = $sp->relationLoaded('archivos')
            ? $sp->archivos
            : $sp->archivos()->orderBy('nro_linea')->get();

        if ($archivos->isEmpty()) {
            throw new RuntimeException('La solicitud no tiene archivos asociados.');
        }

        $dir = $this->resolverDirectorioTemporal();
        $temporales = [];
        $omitidos = [];

        try {
            $codigoSp = (int) ($sp->codigo ?? 0);
            foreach ($archivos as $arch) {
                $resultado = $this->prepararPdfTemporal($arch, $dir, $codigoSp);
                if ($resultado['ruta'] !== null) {
                    $temporales[] = $resultado['ruta'];
                } else {
                    $omitidos[] = $resultado['nombre'];
                }
            }

            if ($temporales === []) {
                throw new RuntimeException(
                    'No hay archivos PDF o imágenes para unir.'
                    .($omitidos !== [] ? ' Omitidos: '.implode(', ', $omitidos).'.' : '')
                );
            }

            $codigo = (int) ($sp->codigo ?? $sp->id);
            $nombre = 'SP_'.$codigo.'_archivos_unidos.pdf';

            if (count($temporales) === 1) {
                $bytes = file_get_contents($temporales[0]);
                if ($bytes === false || $bytes === '') {
                    throw new RuntimeException('No se pudo leer el archivo unificado.');
                }

                return [
                    'contenido' => $bytes,
                    'nombre' => $nombre,
                    'omitidos' => $omitidos,
                ];
            }

            $merger = new PDFMerger;
            foreach ($temporales as $ruta) {
                $merger->addPDF($ruta, 'all', 'vertical');
            }
            $mergedTmp = $dir.'/merged_'.uniqid('', true).'.pdf';
            $temporales[] = $mergedTmp;
            $merger->merge('file', $mergedTmp);

            $bytes = file_get_contents($mergedTmp);
            if ($bytes === false || $bytes === '') {
                throw new RuntimeException('Falló la fusión de los PDFs.');
            }

            return [
                'contenido' => $bytes,
                'nombre' => $nombre,
                'omitidos' => $omitidos,
            ];
        } finally {
            foreach ($temporales as $ruta) {
                if (is_string($ruta) && is_file($ruta)) {
                    @unlink($ruta);
                }
            }
        }
    }

    /**
     * @return array{ruta: ?string, nombre: string}
     */
    private function prepararPdfTemporal(Solicitudpago_Archivo $arch, string $dir, int $codigoSp): array
    {
        $nombre = (string) ($arch->nombre_original ?: basename((string) $arch->archivo));
        $ext = strtolower(pathinfo($nombre, PATHINFO_EXTENSION));
        if ($ext === '' && is_string($arch->archivo)) {
            $ext = strtolower(pathinfo($arch->archivo, PATHINFO_EXTENSION));
        }

        $origen = SolicitudpagoArchivoStorageSupport::rutaAbsoluta($arch, $codigoSp > 0 ? $codigoSp : null);
        if ($origen === null) {
            return ['ruta' => null, 'nombre' => $nombre !== '' ? $nombre : 'archivo #'.$arch->id];
        }

        if (in_array($ext, self::EXT_PDF, true)) {
            $destino = $dir.'/pdf_'.uniqid('', true).'.pdf';
            if (! @copy($origen, $destino)) {
                return ['ruta' => null, 'nombre' => $nombre];
            }

            return ['ruta' => $destino, 'nombre' => $nombre];
        }

        if (in_array($ext, self::EXT_IMAGEN, true)) {
            $destino = $this->imagenAPdfTemporal($origen, $ext, $dir, $nombre);
            if ($destino === null) {
                return ['ruta' => null, 'nombre' => $nombre];
            }

            return ['ruta' => $destino, 'nombre' => $nombre];
        }

        return ['ruta' => null, 'nombre' => $nombre !== '' ? $nombre : 'archivo #'.$arch->id];
    }

    private function imagenAPdfTemporal(string $rutaImagen, string $ext, string $dir, string $titulo): ?string
    {
        $binario = @file_get_contents($rutaImagen);
        if ($binario === false || $binario === '') {
            return null;
        }

        $mime = match ($ext) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            default => 'application/octet-stream',
        };
        $dataUri = 'data:'.$mime.';base64,'.base64_encode($binario);
        $tituloSafe = e($titulo);

        $html = '<!DOCTYPE html><html><head><meta charset="utf-8">'
            .'<style>@page{margin:18px;} body{margin:0;font-family:DejaVu Sans,sans-serif;}'
            .'.t{font-size:9px;color:#555;margin-bottom:8px;word-break:break-all;}'
            .'img{max-width:100%;max-height:250mm;}</style></head><body>'
            .'<div class="t">'.$tituloSafe.'</div>'
            .'<div style="text-align:center"><img src="'.$dataUri.'" alt=""></div>'
            .'</body></html>';

        try {
            $pdf = app('dompdf.wrapper');
            $pdf->setPaper('a4', 'portrait');
            $pdf->setOptions([
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled' => true,
                'defaultFont' => 'DejaVu Sans',
            ]);
            $pdf->loadHTML($html, 'UTF-8');
            $destino = $dir.'/img_'.uniqid('', true).'.pdf';
            file_put_contents($destino, $pdf->output());

            return $destino;
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }

    private function resolverDirectorioTemporal(): string
    {
        $candidatos = [
            storage_path('pdf/tmp/solicitudpago_fusion'),
            storage_path('app/tmp/solicitudpago_fusion'),
            rtrim(sys_get_temp_dir(), '/').'/anitaERP_solicitudpago_fusion',
        ];

        $errores = [];
        foreach ($candidatos as $dir) {
            try {
                if (! is_dir($dir) && ! @mkdir($dir, 0775, true) && ! is_dir($dir)) {
                    $errores[] = $dir.' (no se pudo crear)';

                    continue;
                }
                // Asegurar escritura para www-data y operadores CLI.
                @chmod($dir, 0775);
                if (! is_writable($dir)) {
                    $errores[] = $dir.' (sin escritura)';

                    continue;
                }

                return $dir;
            } catch (\Throwable $e) {
                $errores[] = $dir.' ('.$e->getMessage().')';
            }
        }

        throw new RuntimeException(
            'No hay directorio temporal escribible para fusionar archivos. Intentados: '.implode('; ', $errores)
        );
    }
}