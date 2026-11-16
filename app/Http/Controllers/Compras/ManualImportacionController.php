<?php

declare(strict_types=1);

namespace App\Http\Controllers\Compras;

use App\Http\Controllers\Manuales\ManualDocumentoController;
use App\Services\Compras\ManualImportacionService;

/**
 * Manual del módulo Importaciones (carpeta, landed cost, nacionalización).
 */
class ManualImportacionController extends ManualDocumentoController
{
    protected string $directorio = 'manual-importacion';

    protected string $baseName = 'Manual_Usuario_AnitaERP_Importaciones';

    protected string $configKey = 'manual_importacion';

    protected string $imgPublicPrefix = 'docs/manual-importacion/img';

    protected string $etiquetaModulo = 'Importaciones';

    public function __construct(ManualImportacionService $manual)
    {
        parent::__construct($manual);

        $this->atajos = [
            ['label' => 'Importaciones', 'route' => 'importacion'],
            ['label' => 'Nueva carpeta', 'route' => 'crear_importacion'],
            ['label' => 'Cashflow / simulador', 'route' => 'importacion_cashflow'],
            ['label' => 'Tipos de gasto', 'route' => 'importacion_gasto_tipo'],
        ];
    }

    protected static function rutaPdf(): string
    {
        return 'manual_importacion_pdf';
    }

    protected static function rutaWord(): string
    {
        return 'manual_importacion_word';
    }
}
