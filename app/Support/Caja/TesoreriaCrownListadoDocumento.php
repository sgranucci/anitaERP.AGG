<?php

namespace App\Support\Caja;

use App\Support\Configuracion\EmpresaLogoArchivo;
use App\Support\Reportes\DompdfListadoSupport;
use Maatwebsite\Excel\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class TesoreriaCrownListadoDocumento
{
    /**
     * @param  array{
     *     titulo: string,
     *     subtitulo: string,
     *     archivo: string,
     *     titulo_corto: string,
     *     columnas: list<array{label: string, ancho: int, numero?: bool, texto?: bool, wrap?: bool}>,
     *     filas: list<list<mixed>>,
     *     totales?: list<mixed>,
     *     coleccion: mixed
     * }  $armado
     */
    public static function responder(string $formato, array $armado): ?BinaryFileResponse
    {
        return match (strtoupper($formato)) {
            'PDF' => self::pdf($armado),
            'EXCEL' => self::excel($armado, 'xlsx'),
            'CSV' => self::excel($armado, 'csv'),
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $armado
     */
    private static function pdf(array $armado): BinaryFileResponse
    {
        $coleccion = $armado['coleccion'];
        $logos = EmpresaLogoArchivo::logosCabeceraDesdeColeccion($coleccion);
        $html = view('caja.tesoreria_crown.listado', [
            'titulo' => $armado['titulo'],
            'subtitulo' => $armado['subtitulo'],
            'columnas' => $armado['columnas'],
            'filas' => $armado['filas'],
            'totales' => $armado['totales'] ?? null,
            'logosCabecera' => $logos,
            'totalFilas' => count($armado['filas']),
        ])->render();

        $directorio = storage_path('pdf/listados');
        if (! is_dir($directorio)) {
            mkdir($directorio, 0775, true);
        }
        $ruta = $directorio.'/'.$armado['archivo'].'.pdf';
        // Sin titulo_corto en canvas: el título ya va en la cabecera thead (no repetir arriba a la izquierda).
        DompdfListadoSupport::guardarLegalLandscape($html, $ruta, [
            'titulo_corto' => '',
        ]);

        return response()->download($ruta, $armado['archivo'].'.pdf');
    }

    /**
     * @param  array<string, mixed>  $armado
     */
    private static function excel(array $armado, string $extension): BinaryFileResponse
    {
        $writer = $extension === 'csv' ? Excel::CSV : Excel::XLSX;

        return (new \App\Exports\Caja\TesoreriaCrownListadoExport)
            ->parametros($armado)
            ->download($armado['archivo'].'.'.$extension, $writer);
    }
}
