<?php

declare(strict_types=1);

namespace App\Services\Compras;

use App\Services\Manuales\ManualContenidoService;

class ManualConsultasQbeService extends ManualContenidoService
{
    protected string $contenidoRelativo = 'docs/manual-consultas-qbe/contenido.php';

    protected string $configKey = 'manual_consultas_qbe';
}
