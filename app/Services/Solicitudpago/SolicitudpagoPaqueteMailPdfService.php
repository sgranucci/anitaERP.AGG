<?php

namespace App\Services\Solicitudpago;

use App\Models\Solicitudpago\Solicitudpago;
use Jurosh\PDFMerge\PDFMerger;
use RuntimeException;

/**
 * PDF único para el mail de árbol: comprobante de la SP + adjuntos (PDF/imágenes).
 */
class SolicitudpagoPaqueteMailPdfService
{
    public function __construct(
        private SolicitudpagoComprobantePdfService $comprobantePdfService,
        private SolicitudpagoArchivosFusionService $archivosFusionService,
    ) {
    }

    /**
     * @return array{contenido: string, nombre: string}
     */
    public function generar(int $solicitudpagoId): array
    {
        ini_set('memory_limit', '1024M');
        ini_set('max_execution_time', '300');

        $sp = Solicitudpago::query()
            ->with(['archivos'])
            ->findOrFail($solicitudpagoId);

        $dir = storage_path('pdf/tmp/solicitudpago_paquete');
        if (! is_dir($dir) && ! @mkdir($dir, 0775, true) && ! is_dir($dir)) {
            $dir = rtrim(sys_get_temp_dir(), '/').'/anitaERP_solicitudpago_paquete';
            if (! is_dir($dir) && ! @mkdir($dir, 0775, true) && ! is_dir($dir)) {
                throw new RuntimeException('No hay directorio temporal para armar el PDF del mail.');
            }
        }
        @chmod($dir, 0775);

        $temporales = [];

        try {
            $comprobante = $this->comprobantePdfService->generar((int) $sp->id);
            $rutaComprobante = $dir.'/comp_'.uniqid('', true).'.pdf';
            file_put_contents($rutaComprobante, $comprobante['pdf']->output());
            $temporales[] = $rutaComprobante;

            $partes = [$rutaComprobante];

            if ($sp->archivos->isNotEmpty()) {
                try {
                    $fusion = $this->archivosFusionService->fusionar($sp);
                    $rutaAdjuntos = $dir.'/adj_'.uniqid('', true).'.pdf';
                    file_put_contents($rutaAdjuntos, $fusion['contenido']);
                    $temporales[] = $rutaAdjuntos;
                    $partes[] = $rutaAdjuntos;
                } catch (\Throwable $e) {
                    // Sin adjuntos unibles: se entrega solo el comprobante.
                    report($e);
                }
            }

            $codigo = (int) ($sp->codigo ?? $sp->id);
            $nombre = 'SP_'.$codigo.'_comprobante_y_adjuntos.pdf';

            if (count($partes) === 1) {
                $bytes = file_get_contents($partes[0]);
                if ($bytes === false || $bytes === '') {
                    throw new RuntimeException('No se pudo generar el PDF del comprobante.');
                }

                return ['contenido' => $bytes, 'nombre' => $nombre];
            }

            $merger = new PDFMerger;
            foreach ($partes as $ruta) {
                $merger->addPDF($ruta, 'all', 'vertical');
            }
            $mergedTmp = $dir.'/pkg_'.uniqid('', true).'.pdf';
            $temporales[] = $mergedTmp;
            $merger->merge('file', $mergedTmp);

            $bytes = file_get_contents($mergedTmp);
            if ($bytes === false || $bytes === '') {
                throw new RuntimeException('Falló la fusión del comprobante con los adjuntos.');
            }

            return ['contenido' => $bytes, 'nombre' => $nombre];
        } finally {
            foreach ($temporales as $ruta) {
                if (is_string($ruta) && is_file($ruta)) {
                    @unlink($ruta);
                }
            }
        }
    }
}
