<?php

declare(strict_types=1);

namespace App\Services\Caja;

use App\Services\Manuales\ManualContenidoService;

class ManualTesoreriaCrownService extends ManualContenidoService
{
    protected string $contenidoRelativo = 'docs/manual-tesoreria-crown/contenido.php';

    protected string $configKey = 'manual_tesoreria_crown';

    protected ?string $herramientasRelativo = 'docs/manual-tesoreria-crown/herramientas.php';
}
