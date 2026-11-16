<?php

declare(strict_types=1);

namespace App\Services\Configuracion;

use App\Services\Manuales\ManualContenidoService;

class ManualModuloAvisoService extends ManualContenidoService
{
    protected string $contenidoRelativo = 'docs/manual-modulo-aviso/contenido.php';

    protected string $configKey = 'manual_modulo_aviso';
}
