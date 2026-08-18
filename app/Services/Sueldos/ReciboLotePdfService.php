<?php

namespace App\Services\Sueldos;

use App\Models\Sueldos\Liquidacion_Recibo_Sueldos;
use App\Models\Sueldos\Liquidacion_Sueldos;
use App\Support\Sueldos\LiquidacionConfidencialSeguridadSupport;
use Illuminate\Support\Facades\File;
use Jurosh\PDFMerge\PDFMerger;

/**
 * Emite PDF Anexo III de toda la corrida (con opción multiempresa).
 */
class ReciboLotePdfService
{
    private const CHUNK = 20;

    public function __construct(
        private ReciboAnexoIIIArmadorService $armador,
        private ReciboMultiempresaService $multi,
    ) {}

    /**
     * @return array{ruta:string,nombre:string}
     */
    public function generar(Liquidacion_Sueldos $liq, bool $multiempresa): array
    {
        LiquidacionConfidencialSeguridadSupport::assertLiquidacionVisible($liq);

        ini_set('memory_limit', '1024M');
        set_time_limit(0);

        $query = Liquidacion_Recibo_Sueldos::query()
            ->where('liquidacion_id', $liq->id)
            ->with(['detalles', 'empleado', 'liquidacion.empresa'])
            ->orderBy('numero_recibo')
            ->orderBy('id');
        LiquidacionConfidencialSeguridadSupport::aplicarVisibilidadRecibos($query);
        $recibos = $query->get();

        if ($recibos->isEmpty()) {
            throw new \RuntimeException('La corrida no tiene recibos visibles para emitir.');
        }

        $cadenas = $this->multi->cadenasPorRecibos($recibos, $multiempresa);
        $todos = $cadenas->flatten(1)->unique('id')->values();
        $dtos = $this->armador->armarMuchos($todos);

        $dir = storage_path('pdf/recibos_lote');
        File::ensureDirectoryExists($dir);
        // Alineado con storage/pdf/*: grupo www-data y escritura compartida.
        @chmod($dir, 02775);
        $stamp = $liq->id.'_'.date('YmdHis').'_'.bin2hex(random_bytes(3));
        $partes = [];
        $bloquesFlat = [];

        foreach ($cadenas as $cadena) {
            foreach ($cadena as $idx => $rec) {
                $datos = $dtos->get($rec->id) ?? $this->armador->armar($rec);
                $datos['modo_preview'] = false;
                $datos['multiempresa_activo'] = $multiempresa && $cadena->count() > 1;
                $datos['multiempresa_indice'] = $idx + 1;
                $datos['multiempresa_total'] = $cadena->count();
                $bloquesFlat[] = $datos;
            }
        }

        try {
            foreach (array_chunk($bloquesFlat, self::CHUNK) as $i => $chunk) {
                $pdf = \App::make('dompdf.wrapper');
                $pdf->loadView('sueldos.liquidacion.recibo_anexo_iii_cadena', [
                    'bloques' => $chunk,
                    'es_pdf' => true,
                    'liq' => $liq,
                    'recibo' => $recibos->first(),
                    'multiempresa' => $multiempresa,
                ])->setPaper('a4', 'portrait');

                $rutaParte = $dir.'/parte_'.$stamp.'_'.$i.'.pdf';
                file_put_contents($rutaParte, $pdf->output());
                $partes[] = $rutaParte;
            }

            $nombre = 'recibos_corrida_'.$liq->numero.($multiempresa ? '_multi' : '').'.pdf';
            $rutaFinal = $dir.'/'.$stamp.'_'.$nombre;

            if (count($partes) === 1) {
                rename($partes[0], $rutaFinal);
                $partes = [];
            } else {
                $merger = new PDFMerger;
                foreach ($partes as $p) {
                    $merger->addPDF($p, 'all', 'vertical');
                }
                $merger->merge('file', $rutaFinal);
            }

            return ['ruta' => $rutaFinal, 'nombre' => $nombre];
        } finally {
            foreach ($partes as $p) {
                if (is_file($p)) {
                    @unlink($p);
                }
            }
        }
    }
}
