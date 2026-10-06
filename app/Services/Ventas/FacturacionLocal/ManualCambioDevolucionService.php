<?php

declare(strict_types=1);

namespace App\Services\Ventas\FacturacionLocal;

use App\Services\Manuales\ManualContenidoService;

class ManualCambioDevolucionService extends ManualContenidoService
{
    protected string $contenidoRelativo = 'docs/manual-facturacion-local/contenido.php';

    protected string $configKey = 'manual_facturacion_local';
}
