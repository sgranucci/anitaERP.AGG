<?php

declare(strict_types=1);

namespace App\Http\Controllers\Contable;

use App\Http\Controllers\Manuales\ManualDocumentoController;
use App\Services\Contable\ManualReporteDefinibleService;

class ManualReporteDefinibleController extends ManualDocumentoController
{
    protected string $directorio = 'manual-reporte-definible';

    protected string $baseName = 'Manual_Usuario_AnitaERP_Reportes_Definibles';

    protected string $configKey = 'manual_reporte_definible';

    protected string $imgPublicPrefix = 'docs/manual-reporte-definible/img';

    protected string $etiquetaModulo = 'Reportes definibles';

    protected array $atajos = [
        ['label' => 'Catálogo', 'route' => 'reporte_definible'],
    ];

    public function __construct(ManualReporteDefinibleService $manual)
    {
        parent::__construct($manual);
    }

    protected static function rutaPdf(): string
    {
        return 'manual_reporte_definible_pdf';
    }

    protected static function rutaWord(): string
    {
        return 'manual_reporte_definible_word';
    }
}
