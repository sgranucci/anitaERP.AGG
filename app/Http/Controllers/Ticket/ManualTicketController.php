<?php

declare(strict_types=1);

namespace App\Http\Controllers\Ticket;

use App\Http\Controllers\Manuales\ManualDocumentoController;
use App\Services\Ticket\ManualTicketService;

class ManualTicketController extends ManualDocumentoController
{
    protected string $directorio = 'manual-ticket';

    protected string $baseName = 'Manual_Usuario_AnitaERP_Tickets';

    protected string $configKey = 'manual_ticket';

    protected string $imgPublicPrefix = 'docs/manual-ticket/img';

    protected string $etiquetaModulo = 'Tickets';

    public function __construct(ManualTicketService $manual)
    {
        parent::__construct($manual);

        $this->atajos = [
            ['label' => 'Carga de Tickets', 'route' => 'consulta_ticket'],
            ['label' => 'Bandeja', 'route' => 'consulta_bandeja_ticket'],
            ['label' => 'Adm. de Tickets', 'route' => 'consulta_administracion_ticket'],
            ['label' => 'Informe', 'route' => 'informe_estadistico_ticket'],
        ];
    }

    protected static function rutaPdf(): string
    {
        return 'manual_ticket_pdf';
    }

    protected static function rutaWord(): string
    {
        return 'manual_ticket_word';
    }
}
