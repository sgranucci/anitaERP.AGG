<?php

declare(strict_types=1);

namespace App\Services\Contable;

use App\Services\Manuales\ManualContenidoService;

class ManualReporteDefinibleService extends ManualContenidoService
{
    protected string $contenidoRelativo = 'docs/manual-reporte-definible/contenido.php';

    protected string $configKey = 'manual_reporte_definible';

    protected ?string $herramientasRelativo = 'docs/manual-reporte-definible/herramientas.php';
}
