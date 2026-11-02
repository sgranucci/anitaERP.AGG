<?php

declare(strict_types=1);

namespace App\Services\Ticket;

use App\Services\Manuales\ManualContenidoService;

class ManualTicketService extends ManualContenidoService
{
    protected string $contenidoRelativo = 'docs/manual-ticket/contenido.php';

    protected string $configKey = 'manual_ticket';
}
