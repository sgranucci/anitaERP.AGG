<?php

declare(strict_types=1);

namespace App\Support\ConsultaInteligente;

use App\Exports\Direccion\ConsultaInteligenteListadoExport;
use App\Mail\Direccion\ConsultaInteligenteListadoMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Maatwebsite\Excel\Facades\Excel;

final class ConsultaInteligenteEnvioSupport
{
    public const MAX_FILAS = 2000;

    /**
     * @param  array<string, mixed>  $filtros
     */
    public static function enviar(string $claveEntidad, array $filtros, string $email): int
    {
        ini_set('memory_limit', '512M');
        ini_set('max_execution_time', '120');

        $entidad = EntidadSemanticaCatalogo::porClave($claveEntidad);
        $request = Request::create('/', 'GET', $filtros);
        $filtrosResueltos = $entidad->resolverFiltros($request);
        $filas = $entidad->consultar($filtrosResueltos, false);
        $coleccion = collect($filas);
        $total = $coleccion->count();
        $recorte = $total > self::MAX_FILAS;
        if ($recorte) {
            $coleccion = $coleccion->take(self::MAX_FILAS);
        }

        $binario = Excel::raw(
            new ConsultaInteligenteListadoExport(
                $entidad,
                $coleccion,
                $entidad->columnasVisibles(),
                $entidad->etiquetasColumnas()
            ),
            \Maatwebsite\Excel\Excel::XLSX
        );

        Mail::to($email)->send(new ConsultaInteligenteListadoMail(
            'ci_'.$claveEntidad.'_'.date('Ymd_His').'.xlsx',
            $binario,
            $entidad->nombre(),
            $coleccion->count(),
            $recorte
        ));

        return $coleccion->count();
    }
}
