<?php

declare(strict_types=1);

namespace App\Http\Controllers\Contable;

use App\Http\Controllers\Manuales\ManualDocumentoController;
use App\Services\Contable\ManualCierresRendicionesService;

class ManualCierresRendicionesController extends ManualDocumentoController
{
    protected string $directorio = 'manual-cierres-rendiciones';

    protected string $baseName = 'Manual_Usuario_AnitaERP_Cierres_Rendiciones';

    protected string $configKey = 'manual_cierres_rendiciones';

    protected string $imgPublicPrefix = 'docs/manual-cierres-rendiciones/img';

    protected string $etiquetaModulo = 'Cierres de rendiciones';

    protected array $atajos = [
        ['label' => 'Cierre máquinas', 'route' => 'cierre_rendicion_maquina_contable'],
        ['label' => 'Cierre bingo', 'route' => 'cierre_rendicion_bingo_contable'],
    ];

    public function __construct(ManualCierresRendicionesService $manual)
    {
        parent::__construct($manual);
    }

    protected static function rutaPdf(): string
    {
        return 'manual_cierres_rendiciones_pdf';
    }

    protected static function rutaWord(): string
    {
        return 'manual_cierres_rendiciones_word';
    }
}
