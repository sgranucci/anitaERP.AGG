<?php

declare(strict_types=1);

namespace App\Http\Controllers\Configuracion;

use App\Http\Controllers\Manuales\ManualDocumentoController;
use App\Services\Configuracion\ManualModuloAvisoService;

class ManualModuloAvisoController extends ManualDocumentoController
{
    protected string $directorio = 'manual-modulo-aviso';

    protected string $baseName = 'Manual_Usuario_AnitaERP_Avisos_Modulo';

    protected string $configKey = 'manual_modulo_aviso';

    protected string $imgPublicPrefix = 'docs/manual-modulo-aviso/img';

    protected string $etiquetaModulo = 'Avisos por módulo';

    protected array $atajos = [
        ['label' => 'Avisos por módulo', 'route' => 'consultar_modulo_aviso'],
        ['label' => 'Reglas de aviso', 'route' => 'consultar_aviso_regla'],
        ['label' => 'WhatsApp por empresa', 'route' => 'consultar_empresa_whatsapp'],
        ['label' => 'SLA y escalamiento', 'route' => 'editar_user_task_sla'],
        ['label' => 'Árboles de aprobación', 'route' => 'consulta_arbolaprobacion'],
        ['label' => 'Mis aprobaciones', 'route' => 'mis_aprobaciones_arbol'],
    ];

    public function __construct(ManualModuloAvisoService $manual)
    {
        parent::__construct($manual);
    }

    protected static function rutaPdf(): string
    {
        return 'manual_modulo_aviso_pdf';
    }

    protected static function rutaWord(): string
    {
        return 'manual_modulo_aviso_word';
    }
}
