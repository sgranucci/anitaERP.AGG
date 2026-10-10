<?php

declare(strict_types=1);

namespace App\Support\Caja;

use App\Exports\Caja\Caja_MovimientoExport;
use App\Mail\Caja\IngresoEgresoListadoMail;
use App\Queries\Caja\Caja_MovimientoQueryInterface;
use Illuminate\Support\Facades\Mail;
use Maatwebsite\Excel\Facades\Excel;

final class IngresoEgresoListadoEnvioSupport
{
    public const MAX_FILAS = 2000;

    /**
     * @param  array<string, mixed>  $filtros
     */
    public static function enviar(array $filtros, string $email): int
    {
        ini_set('memory_limit', '512M');
        ini_set('max_execution_time', '120');
        $query = app(Caja_MovimientoQueryInterface::class);
        $total = $query->builderListado($filtros)->count();
        $recorte = $total > self::MAX_FILAS;
        $filtros['_mail_limite'] = self::MAX_FILAS;
        $binario = Excel::raw(
            (new Caja_MovimientoExport($query))->parametros($filtros, false),
            \Maatwebsite\Excel\Excel::XLSX
        );
        $filas = $recorte ? self::MAX_FILAS : $total;
        Mail::to($email)->send(new IngresoEgresoListadoMail(
            'ingresoegreso_'.date('Ymd_His').'.xlsx',
            $binario,
            $filas,
            $recorte
        ));

        return $filas;
    }
}
