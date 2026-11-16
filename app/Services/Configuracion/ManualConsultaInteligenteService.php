<?php

declare(strict_types=1);

namespace App\Services\Configuracion;

use App\Services\Manuales\ManualContenidoService;

class ManualConsultaInteligenteService extends ManualContenidoService
{
    protected string $contenidoRelativo = 'docs/manual-consulta-inteligente/contenido.php';

    protected string $configKey = 'manual_consulta_inteligente';
}
