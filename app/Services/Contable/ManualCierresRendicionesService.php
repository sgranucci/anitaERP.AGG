<?php

declare(strict_types=1);

namespace App\Services\Contable;

use App\Services\Manuales\ManualContenidoService;

class ManualCierresRendicionesService extends ManualContenidoService
{
    protected string $contenidoRelativo = 'docs/manual-cierres-rendiciones/contenido.php';

    protected string $configKey = 'manual_cierres_rendiciones';

    protected ?string $herramientasRelativo = 'docs/manual-cierres-rendiciones/herramientas.php';
}
