<?php

declare(strict_types=1);

namespace App\Http\Controllers\Sueldos;

use App\Http\Controllers\Manuales\ManualDocumentoController;
use App\Services\Sueldos\ManualSueldosService;

class ManualSueldosController extends ManualDocumentoController
{
    protected string $directorio = 'manual-sueldos';

    protected string $baseName = 'Manual_Usuario_AnitaERP_Modulo_Sueldos';

    protected string $configKey = 'manual_sueldos';

    protected string $imgPublicPrefix = 'docs/manual-sueldos/img';

    protected string $etiquetaModulo = 'Sueldos';

    protected array $atajos = [
        ['label' => 'Tipos de sanción', 'route' => 'consultar_tipo_sancion_sueldos'],
        ['label' => 'Motivos', 'route' => 'consultar_motivo_sancion_sueldos'],
        ['label' => 'Reporte', 'route' => 'sancion_reporte_sueldos'],
    ];

    public function __construct(ManualSueldosService $manual)
    {
        parent::__construct($manual);
    }

    protected static function rutaPdf(): string
    {
        return 'manual_sueldos_pdf';
    }

    protected static function rutaWord(): string
    {
        return 'manual_sueldos_word';
    }
}
