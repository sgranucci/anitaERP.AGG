<?php

declare(strict_types=1);

namespace App\Services\Caja;

use App\Services\Manuales\ManualContenidoService;

class ManualCajaService extends ManualContenidoService
{
    protected string $contenidoRelativo = 'docs/manual-caja/contenido.php';

    protected string $configKey = 'manual_caja';

    protected ?string $herramientasRelativo = 'docs/manual-caja/herramientas.php';
}
