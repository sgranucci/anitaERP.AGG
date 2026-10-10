<?php

declare(strict_types=1);

namespace App\Support\Ticket;

use App\Exports\Ticket\AdministracionTicketListadoExport;
use App\Mail\Ticket\AdministracionTicketListadoMail;
use App\Queries\Ticket\TicketQueryInterface;
use Illuminate\Support\Facades\Mail;
use Maatwebsite\Excel\Facades\Excel;

final class AdministracionTicketListadoEnvioSupport
{
    public const MAX_FILAS = 2000;

    /**
     * El query ya trae el alcance de rol y usuario.
     *
     * @param  array<string, mixed>  $filtros
     */
    public static function enviar(array $filtros, string $email): int
    {
        ini_set('memory_limit', '512M');
        ini_set('max_execution_time', '120');
        $query = app(TicketQueryInterface::class);
        $total = $query->leeTicketAdministracion($filtros, false)->count();
        $recorte = $total > self::MAX_FILAS;
        $filtros['_mail_limite'] = self::MAX_FILAS;
        $binario = Excel::raw(
            (new AdministracionTicketListadoExport($query))->parametros($filtros),
            \Maatwebsite\Excel\Excel::XLSX
        );
        $filas = $recorte ? self::MAX_FILAS : $total;
        Mail::to($email)->send(new AdministracionTicketListadoMail(
            'administracion_ticket_'.date('Ymd_His').'.xlsx',
            $binario,
            $filas,
            $recorte
        ));

        return $filas;
    }
}
