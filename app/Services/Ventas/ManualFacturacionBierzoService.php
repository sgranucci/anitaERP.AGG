<?php

declare(strict_types=1);

namespace App\Services\Ventas;

use App\Services\Manuales\ManualContenidoService;

class ManualFacturacionBierzoService extends ManualContenidoService
{
    protected string $contenidoRelativo = 'docs/manual-facturacion-bierzo/contenido.php';

    protected string $configKey = 'manual_facturacion_bierzo';
}
