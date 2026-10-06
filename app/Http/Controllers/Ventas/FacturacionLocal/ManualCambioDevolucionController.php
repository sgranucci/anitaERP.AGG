<?php

declare(strict_types=1);

namespace App\Http\Controllers\Ventas\FacturacionLocal;

use App\Http\Controllers\Manuales\ManualDocumentoController;
use App\Services\Ventas\FacturacionLocal\ManualCambioDevolucionService;
use App\Support\Configuracion\EntornoEmpresaSupport;

/**
 * Guía del circuito de cambios y devoluciones de e-commerce (Facturación Local Ferli).
 */
class ManualCambioDevolucionController extends ManualDocumentoController
{
    protected string $directorio = 'manual-facturacion-local';

    protected string $baseName = 'Manual_Usuario_AnitaERP_Cambios_Devoluciones_Ecommerce';

    protected string $configKey = 'manual_facturacion_local';

    protected string $imgPublicPrefix = 'docs/manual-facturacion-local/img';

    protected string $etiquetaModulo = 'Facturación Local';

    public function __construct(ManualCambioDevolucionService $manual)
    {
        parent::__construct($manual);

        $this->atajos = [
            ['label' => 'Cambios / devoluciones', 'route' => 'facturacion_local_cambios_devolucion'],
        ];
    }

    public function index()
    {
        $this->assertFerli();

        return parent::index();
    }

    public function descargarPdf(): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $this->assertFerli();

        return parent::descargarPdf();
    }

    public function descargarWord(): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $this->assertFerli();

        return parent::descargarWord();
    }

    protected static function rutaPdf(): string
    {
        return 'manual_cambio_devolucion_marketplace_pdf';
    }

    protected static function rutaWord(): string
    {
        return 'manual_cambio_devolucion_marketplace_word';
    }

    private function assertFerli(): void
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            abort(404);
        }

        can('listar-cambio-devolucion-marketplace-facturacion-local');
    }
}
