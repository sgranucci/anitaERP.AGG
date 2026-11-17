<?php

declare(strict_types=1);

namespace App\Http\Controllers\Sueldos;

use App\Http\Controllers\Manuales\ManualDocumentoController;
use App\Services\Sueldos\ManualGuiaRapidaSueldosService;

class ManualGuiaRapidaSueldosController extends ManualDocumentoController
{
    protected string $directorio = 'guia-rapida-sueldos';

    protected string $baseName = 'Guia_Rapida_Liquidacion_Sueldos';

    protected string $configKey = 'manual_guia_rapida_sueldos';

    protected string $imgPublicPrefix = 'docs/guia-rapida-sueldos/img';

    protected string $etiquetaModulo = 'Guía rápida — liquidación sueldos';

    protected array $atajos = [
        ['label' => 'Empleados', 'route' => 'consultar_empleado_sueldos'],
        ['label' => 'Novedades', 'route' => 'consultar_novedad_sueldos'],
        ['label' => 'Importar novedades', 'route' => 'importar_novedad_sueldos'],
        ['label' => 'Corridas', 'route' => 'consultar_liquidacion_sueldos'],
        ['label' => 'Manual completo', 'route' => 'manual_sueldos'],
    ];

    public function __construct(ManualGuiaRapidaSueldosService $manual)
    {
        parent::__construct($manual);
    }

    protected static function rutaPdf(): string
    {
        return 'guia_rapida_sueldos_pdf';
    }

    protected static function rutaWord(): string
    {
        return 'guia_rapida_sueldos_word';
    }
}
