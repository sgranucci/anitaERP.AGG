<?php

namespace App\Services\Compras;

use App\Models\Compras\Ordencompra;
use App\Repositories\Compras\OrdencompraRepositoryInterface;
use App\Services\Configuracion\ImpuestoService;
use App\Support\Compras\NotaOcPdfRecorteMargenIzquierdo;
use App\Support\Compras\OrdencompraPdfContextoRequisicion;
use App\Support\Compras\OrdencompraTotalesCabecera;
use App\Support\Compras\OrdencompraTotalesResumen;
use App\Queries\Configuracion\CotizacionQueryInterface;
use Jurosh\PDFMerge\PDFMerger;

class OrdencompraPdfService
{
    public function __construct(
        private OrdencompraRepositoryInterface $ordencompraRepository,
        private CotizacionQueryInterface $cotizacionQuery,
        private ImpuestoService $impuestoService,
    ) {}

    /**
     * Genera el PDF de la OC (con nota adjunta si existe) y devuelve ruta absoluta + nombre de archivo.
     *
     * @return array{ruta: string, nombre: string}
     */
    public function generarArchivo(int $ordencompraId, bool $layoutApaisado = false): array
    {
        $data = $this->ordencompraRepository->find($ordencompraId);
        $data->loadMissing([
            'requisiciones.usuarios',
            'requisiciones.requisicion_estados.usuarios',
            'ordencompra_estados.usuarios',
        ]);
        OrdencompraTotalesCabecera::aplicarAtributosVirtuales($data, $this->cotizacionQuery);

        $totalesOc = OrdencompraTotalesResumen::desdeModelo($data, $this->cotizacionQuery, $this->impuestoService);
        $monedaPdf = $totalesOc['moneda_abrev'] !== ''
            ? $totalesOc['moneda_abrev']
            : (string) ($data->monedacabecera_abreviatura ?? '');

        $req = ($data->requisicion_id && $data->requisiciones) ? $data->requisiciones : null;
        [$reqUsuarioEmitio, $reqUsuarioAprobador] = OrdencompraPdfContextoRequisicion::emitioYUltimoAprobador($req);

        $vistaPdf = $layoutApaisado
            ? 'compras.ordencompra.pdf-legal-landscape'
            : 'compras.ordencompra.pdf';

        $html = view($vistaPdf, compact('data', 'reqUsuarioEmitio', 'reqUsuarioAprobador', 'totalesOc', 'monedaPdf'))->render();

        $dir = storage_path('pdf/ordencompra');
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $nombreArchivo = $this->nombreArchivoPdf($data);
        $rutaPdfOc = $dir.'/'.$nombreArchivo;

        $pdf = \App::make('dompdf.wrapper');
        if ($layoutApaisado) {
            $pdf->setPaper([0.0, 0.0, 1008.0, 612.0]);
        } else {
            $pdf->setPaper('legal', 'portrait');
        }
        $pdf->loadHTML($html, 'UTF-8');
        $pdf->save($rutaPdfOc);

        $this->fusionarNotaOcSiExiste($rutaPdfOc, $nombreArchivo, $layoutApaisado);

        return ['ruta' => $rutaPdfOc, 'nombre' => $nombreArchivo];
    }

    public function nombreArchivoPdf(Ordencompra $ordencompra): string
    {
        return 'Ordencompra_'.preg_replace('/[^\w\-]+/', '_', (string) $ordencompra->numeroordencompra).'.pdf';
    }

    private function fusionarNotaOcSiExiste(string $rutaPdfOc, string $nombreArchivo, bool $layoutApaisado): void
    {
        $rutaNota = storage_path('app/public/imagenes/nota_oc.pdf');
        if (! is_file($rutaNota)) {
            return;
        }

        $recorteIzqPt = env('NOTA_OC_RECORTE_IZQUIERDO_PT');
        $recorteIzqPt = $recorteIzqPt !== null && $recorteIzqPt !== ''
            ? (float) $recorteIzqPt
            : NotaOcPdfRecorteMargenIzquierdo::RECORTE_POR_DEFECTO_PT;
        $escalaNota = env('NOTA_OC_ESCALA');
        $escalaNota = $escalaNota !== null && $escalaNota !== ''
            ? (float) $escalaNota
            : NotaOcPdfRecorteMargenIzquierdo::ESCALA_POR_DEFECTO;
        $margenIzqNota = env('NOTA_OC_MARGEN_IZQUIERDO_PT');
        if ($margenIzqNota !== null && $margenIzqNota !== '') {
            $margenIzqNota = (float) $margenIzqNota;
        } else {
            $margenIzqNota = ($recorteIzqPt > 0.01 || $escalaNota < 0.999)
                ? NotaOcPdfRecorteMargenIzquierdo::MARGEN_IZQUIERDO_POR_DEFECTO_PT
                : 0.0;
        }
        $rutaNotaParaFusion = NotaOcPdfRecorteMargenIzquierdo::generarTemporal($rutaNota, $recorteIzqPt, $escalaNota, $margenIzqNota) ?? $rutaNota;

        try {
            $orientacionOc = $layoutApaisado ? 'horizontal' : 'vertical';
            $merger = new PDFMerger;
            $merger->addPDF($rutaPdfOc, 'all', $orientacionOc)
                ->addPDF($rutaNotaParaFusion, 'all', 'vertical');
            $fusion = $merger->merge('string', $nombreArchivo);
            file_put_contents($rutaPdfOc, $fusion);
        } catch (\Throwable $e) {
            report($e);
        } finally {
            if ($rutaNotaParaFusion !== $rutaNota && is_file($rutaNotaParaFusion)) {
                @unlink($rutaNotaParaFusion);
            }
        }
    }
}
