<?php

declare(strict_types=1);

namespace App\Services\Configuracion;

use App\Services\Manuales\ManualContenidoService;

class ManualAprobacionesService extends ManualContenidoService
{
    protected string $contenidoRelativo = 'docs/manual-aprobaciones/contenido.php';

    protected string $configKey = 'manual_aprobaciones';
}
