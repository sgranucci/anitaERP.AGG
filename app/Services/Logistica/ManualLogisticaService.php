<?php

declare(strict_types=1);

namespace App\Services\Logistica;

use App\Services\Manuales\ManualContenidoService;

class ManualLogisticaService extends ManualContenidoService
{
    protected string $contenidoRelativo = 'docs/manual-logistica/contenido.php';

    protected string $configKey = 'manual_logistica';
}
