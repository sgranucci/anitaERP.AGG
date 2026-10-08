<?php

declare(strict_types=1);

namespace App\Http\Controllers\Sueldos;

use App\Http\Controllers\Manuales\ManualDocumentoController;
use App\Services\Sueldos\ManualLsdSueldosService;

class ManualLsdSueldosController extends ManualDocumentoController
{
    protected string $directorio = 'manual-sueldos';

    protected string $baseName = 'Manual_Usuario_AnitaERP_Modulo_Sueldos';

    protected string $configKey = 'manual_sueldos';

    protected string $imgPublicPrefix = 'docs/manual-sueldos/img';

    protected string $etiquetaModulo = 'Sueldos y jornales';

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
