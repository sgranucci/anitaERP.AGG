<?php

declare(strict_types=1);

namespace App\Http\Controllers\Caja;

use App\Http\Controllers\Manuales\ManualDocumentoController;
use App\Services\Caja\ManualCajaService;

class ManualCajaController extends ManualDocumentoController
{
    protected string $directorio = 'manual-caja';

    protected string $baseName = 'Manual_Usuario_AnitaERP_Modulo_Caja';

    protected string $configKey = 'manual_caja';

    protected string $imgPublicPrefix = 'docs/manual-caja/img';

    protected string $etiquetaModulo = 'Caja';

    protected array $atajos = [
        ['label' => 'Posición financiera', 'route' => 'posicion_financiera'],
        ['label' => 'Flash', 'route' => 'flash_caja'],
        ['label' => 'Rendición máquinas', 'route' => 'rendicion_maquina'],
    ];

    public function __construct(ManualCajaService $manual)
    {
        parent::__construct($manual);
    }

    protected static function rutaPdf(): string
    {
        return 'manual_caja_pdf';
    }

    protected static function rutaWord(): string
    {
        return 'manual_caja_word';
    }
}
