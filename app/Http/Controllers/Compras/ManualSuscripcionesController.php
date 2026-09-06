<?php

declare(strict_types=1);

namespace App\Http\Controllers\Compras;

use App\Http\Controllers\Manuales\ManualDocumentoController;
use App\Services\Compras\ManualSuscripcionesService;

/**
 * El "Circuito" del módulo: el manual del proceso de suscripciones, con descarga en PDF y Word.
 */
class ManualSuscripcionesController extends ManualDocumentoController
{
    protected string $directorio = 'manual-suscripciones';

    protected string $baseName = 'Manual_Usuario_AnitaERP_Suscripciones';

    protected string $configKey = 'manual_suscripciones';

    protected string $imgPublicPrefix = 'docs/manual-suscripciones/img';

    protected string $etiquetaModulo = 'Suscripciones';

    public function __construct(ManualSuscripcionesService $manual)
    {
        parent::__construct($manual);

        $this->atajos = [
            ['label' => 'Suscripciones', 'route' => 'consultar_suscripcion'],
            ['label' => 'Aprobadores', 'route' => 'aprobadores_suscripcion'],
            ['label' => 'Tarjetas', 'route' => 'tarjetas_suscripcion'],
            ['label' => 'Conciliación mensual', 'route' => 'conciliacion_suscripcion'],
            ['label' => 'Reportes', 'route' => 'reporte_suscripcion'],
        ];
    }

    protected static function rutaPdf(): string
    {
        return 'manual_suscripciones_pdf';
    }

    protected static function rutaWord(): string
    {
        return 'manual_suscripciones_word';
    }
}
