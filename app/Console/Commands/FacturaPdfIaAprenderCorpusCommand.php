<?php

namespace App\Console\Commands;

use App\Services\Compras\FacturaProveedorCorpusAprendizajeService;
use Illuminate\Console\Command;

class FacturaPdfIaAprenderCorpusCommand extends Command
{
    protected $signature = 'compras:factura-pdf-ia-aprender
                            {--sin-ocr : Indexar metadatos sin OCR (rápido)}
                            {--fuente= : facturas|precargas|scan|todo (default: todo)}
                            {--limite=100 : Máximo de PDFs nuevos por ejecución}
                            {--scan-desde= : docu_id inicial (desc); default: cursor o ~362500}
                            {--rebuild : Reemplaza corpus (no incremental)}
                            {--solo-precarga : Solo PDFs con precarga/conceptos en BD}';

    protected $description = 'Indexa facturas para few-shot Ollama (Facturas_scan, precargas, /scan/compras/documentos)';

    public function handle(FacturaProveedorCorpusAprendizajeService $service): int
    {
        $incluirOcr = ! $this->option('sin-ocr');
        $fuente = $this->option('fuente') ?: 'todo';
        $limite = (int) $this->option('limite');
        $scanDesde = $this->option('scan-desde');
        $scanDesde = $scanDesde !== null && $scanDesde !== '' ? (int) $scanDesde : null;

        $this->info('Corpus PDF IA — fuente: '.$fuente
            .', límite: '.$limite
            .($incluirOcr ? ', con OCR' : ', sin OCR')
            .($this->option('rebuild') ? ', rebuild' : ', incremental'));

        try {
            $resultado = $service->reconstruirCorpus([
                'incluir_ocr' => $incluirOcr,
                'fuente' => $fuente,
                'limite' => $limite,
                'scan_desde' => $scanDesde,
                'incremental' => ! $this->option('rebuild'),
                'solo_con_precarga' => (bool) $this->option('solo-precarga'),
            ]);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info('Proveedores: '.$resultado['proveedores']);
        $this->info('Muestras totales: '.$resultado['muestras']);
        $this->info('Nuevas esta corrida: '.$resultado['nuevas']);
        if ($resultado['cursor_scan'] !== null) {
            $this->info('Próximo scan-desde (más viejo): '.$resultado['cursor_scan']);
        }
        $this->info('Cache: '.$resultado['ruta_cache']);

        return self::SUCCESS;
    }
}
