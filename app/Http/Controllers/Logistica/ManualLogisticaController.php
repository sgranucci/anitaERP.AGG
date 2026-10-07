<?php

declare(strict_types=1);

namespace App\Http\Controllers\Logistica;

use App\Http\Controllers\Manuales\ManualDocumentoController;
use App\Services\Logistica\ManualLogisticaService;

class ManualLogisticaController extends ManualDocumentoController
{
    protected string $directorio = 'manual-logistica';

    protected string $baseName = 'Manual_Usuario_AnitaERP_Logistica';

    protected string $configKey = 'manual_logistica';

    protected string $imgPublicPrefix = 'docs/manual-logistica/img';

    protected string $etiquetaModulo = 'Logística';

    public function __construct(ManualLogisticaService $manual)
    {
        parent::__construct($manual);

        $this->atajos = [
            ['label' => 'Solicitudes', 'route' => 'logistica_solicitud'],
            ['label' => 'Nueva solicitud', 'route' => 'crear_logistica_solicitud'],
            ['label' => 'Catálogo de logística', 'route' => 'editar_configuracion_logistica'],
        ];
    }

    protected static function rutaPdf(): string
    {
        return 'manual_logistica_pdf';
    }

    protected static function rutaWord(): string
    {
        return 'manual_logistica_word';
    }
}
