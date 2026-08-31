<?php

declare(strict_types=1);

namespace App\Http\Controllers\Sueldos;

use App\Http\Controllers\Manuales\ManualDocumentoController;
use App\Services\Sueldos\ManualLsdSueldosService;

class ManualLsdSueldosController extends ManualDocumentoController
{
    protected string $directorio = 'manual-lsd-sueldos';

    protected string $baseName = 'Manual_Usuario_AnitaERP_Libro_Sueldos_Digital';

    protected string $configKey = 'manual_lsd_sueldos';

    protected string $imgPublicPrefix = 'docs/manual-lsd-sueldos/img';

    protected string $etiquetaModulo = 'LSD';

    protected array $atajos = [
        ['label' => 'Libro de Sueldos Digital', 'route' => 'consultar_lsd_sueldos'],
        ['label' => 'Conceptos', 'route' => 'consultar_concepto_sueldos'],
        ['label' => 'Parámetros', 'route' => 'consultar_parametro_sueldos'],
    ];

    public function __construct(ManualLsdSueldosService $manual)
    {
        parent::__construct($manual);
    }

    protected static function rutaPdf(): string
    {
        return 'manual_lsd_sueldos_pdf';
    }

    protected static function rutaWord(): string
    {
        return 'manual_lsd_sueldos_word';
    }
}
