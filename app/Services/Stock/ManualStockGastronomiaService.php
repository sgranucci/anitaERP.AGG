<?php

declare(strict_types=1);

namespace App\Services\Stock;

use App\Services\Manuales\ManualContenidoService;

class ManualStockGastronomiaService extends ManualContenidoService
{
    protected string $contenidoRelativo = 'docs/manual-stock-gastronomia/contenido.php';

    protected string $configKey = 'manual_stock_gastronomia';

    protected ?string $herramientasRelativo = 'docs/manual-stock-gastronomia/herramientas.php';
}
