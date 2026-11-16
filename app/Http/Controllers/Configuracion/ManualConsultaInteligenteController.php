<?php

declare(strict_types=1);

namespace App\Http\Controllers\Configuracion;

use App\Http\Controllers\Manuales\ManualDocumentoController;
use App\Services\Configuracion\ManualConsultaInteligenteService;

class ManualConsultaInteligenteController extends ManualDocumentoController
{
    protected string $directorio = 'manual-consulta-inteligente';

    protected string $baseName = 'Manual_Usuario_AnitaERP_Consulta_Inteligente';

    protected string $configKey = 'manual_consulta_inteligente';

    protected string $imgPublicPrefix = 'docs/manual-consulta-inteligente/img';

    protected string $etiquetaModulo = 'Consulta Inteligente';

    protected array $atajos = [
        ['label' => 'Consulta Inteligente', 'route' => 'consulta_inteligente'],
        ['label' => 'Tablero', 'route' => 'tablero_consulta_inteligente'],
        ['label' => 'Alertas', 'route' => 'configuracion_consulta_inteligente'],
        ['label' => 'Diccionario NL', 'route' => 'diccionario_consulta_inteligente'],
    ];

    public function __construct(ManualConsultaInteligenteService $manual)
    {
        parent::__construct($manual);
    }

    protected static function rutaPdf(): string
    {
        return 'manual_consulta_inteligente_pdf';
    }

    protected static function rutaWord(): string
    {
        return 'manual_consulta_inteligente_word';
    }
}
