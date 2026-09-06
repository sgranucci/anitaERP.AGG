<?php

declare(strict_types=1);

namespace App\Services\Compras;

use App\Services\Manuales\ManualContenidoService;

class ManualSuscripcionesService extends ManualContenidoService
{
    protected string $contenidoRelativo = 'docs/manual-suscripciones/contenido.php';

    protected string $configKey = 'manual_suscripciones';
}
