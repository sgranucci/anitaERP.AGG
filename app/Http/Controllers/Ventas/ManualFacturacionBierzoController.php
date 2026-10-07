<?php

declare(strict_types=1);

namespace App\Http\Controllers\Ventas;

use App\Http\Controllers\Manuales\ManualDocumentoController;
use App\Services\Ventas\ManualFacturacionBierzoService;
use App\Support\Configuracion\EntornoEmpresaSupport;

class ManualFacturacionBierzoController extends ManualDocumentoController
{
    protected string $directorio = 'manual-facturacion-bierzo';

    protected string $baseName = 'Manual_Usuario_AnitaERP_Facturacion_Remitos_COT_EL_BIERZO';

    protected string $configKey = 'manual_facturacion_bierzo';

    protected string $imgPublicPrefix = 'docs/manual-facturacion-bierzo/img';

    protected string $etiquetaModulo = 'Facturación El Bierzo';

    public function __construct(ManualFacturacionBierzoService $manual)
    {
        parent::__construct($manual);

        $this->atajos = [
            ['label' => 'Facturas', 'route' => 'factura'],
            ['label' => 'Remitos', 'route' => 'remito'],
            ['label' => 'COT', 'route' => 'cot_electronico'],
            ['label' => 'Certificados', 'route' => 'consultar_certificado_sanitario'],
        ];
    }

    public function index()
    {
        $this->soloElBierzo();

        return parent::index();
    }

    public function descargarPdf(): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $this->soloElBierzo();

        return parent::descargarPdf();
    }

    public function descargarWord(): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $this->soloElBierzo();

        return parent::descargarWord();
    }

    protected static function rutaPdf(): string
    {
        return 'manual_facturacion_bierzo_pdf';
    }

    protected static function rutaWord(): string
    {
        return 'manual_facturacion_bierzo_word';
    }

    private function soloElBierzo(): void
    {
        if (! EntornoEmpresaSupport::esElBierzo()) {
            abort(404);
        }
    }
}
