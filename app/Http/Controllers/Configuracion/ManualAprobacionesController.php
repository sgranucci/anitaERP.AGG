<?php

declare(strict_types=1);

namespace App\Http\Controllers\Configuracion;

use App\Http\Controllers\Manuales\ManualDocumentoController;
use App\Services\Configuracion\ManualAprobacionesService;

class ManualAprobacionesController extends ManualDocumentoController
{
    protected string $directorio = 'manual-aprobaciones';

    protected string $baseName = 'Manual_Usuario_AnitaERP_Mis_Aprobaciones';

    protected string $configKey = 'manual_aprobaciones';

    protected string $imgPublicPrefix = 'docs/manual-aprobaciones/img';

    protected string $etiquetaModulo = 'Mis aprobaciones';

    protected array $atajos = [
        ['label' => 'Mis aprobaciones', 'route' => 'mis_aprobaciones_arbol'],
        ['label' => 'Árbol de aprobación', 'route' => 'consulta_arbolaprobacion'],
        ['label' => 'Config. general', 'route' => 'configuracion_general'],
    ];

    public function __construct(ManualAprobacionesService $manual)
    {
        parent::__construct($manual);
    }

    protected static function rutaPdf(): string
    {
        return 'manual_aprobaciones_pdf';
    }

    protected static function rutaWord(): string
    {
        return 'manual_aprobaciones_word';
    }
}
