<?php

declare(strict_types=1);

namespace App\Support\Compras;

use App\Exports\Compras\PagoproveedorListadoExport;
use App\Mail\Compras\PagoproveedorListadoMail;
use Illuminate\Support\Facades\Mail;
use Maatwebsite\Excel\Facades\Excel;

final class PagoproveedorListadoEnvioSupport
{
    public const MAX_FILAS = 2000;

    /**
     * @param  array<string, mixed>  $filtros
     */
    public static function enviar(array $filtros, string $email): int
    {
        ini_set('memory_limit', '512M');
        ini_set('max_execution_time', '120');
        $repositorio = app(\App\Repositories\Compras\PagoproveedorRepositoryInterface::class);
        $pagina = $repositorio->leePagoproveedor($filtros, true);
        $total = method_exists($pagina, 'total') ? (int) $pagina->total() : $pagina->count();
        $recorte = $total > self::MAX_FILAS;
        $filtros['_mail_limite'] = self::MAX_FILAS;
        $binario = Excel::raw(
            app(PagoproveedorListadoExport::class)->parametros($filtros, false),
            \Maatwebsite\Excel\Excel::XLSX
        );
        $filas = $recorte ? self::MAX_FILAS : $total;
        Mail::to($email)->send(new PagoproveedorListadoMail(
            'pagoproveedor_'.date('Ymd_His').'.xlsx',
            $binario,
            $filas,
            $recorte
        ));

        return $filas;
    }
}
