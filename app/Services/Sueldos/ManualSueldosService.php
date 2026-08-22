<?php

declare(strict_types=1);

namespace App\Services\Sueldos;

use App\Services\Manuales\ManualContenidoService;

class ManualSueldosService extends ManualContenidoService
{
    protected string $contenidoRelativo = 'docs/manual-sueldos/contenido.php';

    protected string $configKey = 'manual_sueldos';

    protected ?string $herramientasRelativo = 'docs/manual-sueldos/herramientas.php';
}
