<?php

declare(strict_types=1);

namespace App\Services\Sueldos;

use App\Services\Manuales\ManualContenidoService;

class ManualLsdSueldosService extends ManualContenidoService
{
    protected string $contenidoRelativo = 'docs/manual-lsd-sueldos/contenido.php';

    protected string $configKey = 'manual_lsd_sueldos';

    protected ?string $herramientasRelativo = 'docs/manual-lsd-sueldos/herramientas.php';
}
