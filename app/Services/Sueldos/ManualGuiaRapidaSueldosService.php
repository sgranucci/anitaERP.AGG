<?php

declare(strict_types=1);

namespace App\Services\Sueldos;

use App\Services\Manuales\ManualContenidoService;

class ManualGuiaRapidaSueldosService extends ManualContenidoService
{
    protected string $contenidoRelativo = 'docs/guia-rapida-sueldos/contenido.php';

    protected string $configKey = 'manual_guia_rapida_sueldos';
}
