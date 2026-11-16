<?php

declare(strict_types=1);

namespace App\Services\Compras;

use App\Services\Manuales\ManualContenidoService;

class ManualImportacionService extends ManualContenidoService
{
    protected string $contenidoRelativo = 'docs/manual-importacion/contenido.php';

    protected string $configKey = 'manual_importacion';
}
