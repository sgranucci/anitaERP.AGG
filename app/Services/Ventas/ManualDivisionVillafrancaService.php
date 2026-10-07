<?php

declare(strict_types=1);

namespace App\Services\Ventas;

use App\Services\Manuales\ManualContenidoService;

/**
 * Ejemplar completo (mesa de ayuda + división). Solo se genera hacia el NAS.
 */
class ManualDivisionVillafrancaService extends ManualContenidoService
{
    protected string $contenidoRelativo = 'docs/interno-division-villafranca/contenido.php';

    protected string $configKey = 'manual_division_villafranca';
}
