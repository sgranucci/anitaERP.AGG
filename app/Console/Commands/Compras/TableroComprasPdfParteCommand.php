<?php

namespace App\Console\Commands\Compras;

use Illuminate\Console\Command;
use RuntimeException;

/**
 * Arma una parte del PDF del tablero de compras. Lo lanza ComprasTableroOcPdfService en
 * varios procesos a la vez; no es para uso manual. No escribe en la base.
 */
class TableroComprasPdfParteCommand extends Command
{
    protected $signature = 'compras:tablero-pdf-parte {entrada : JSON con las filas} {salida : PDF a generar}';

    protected $description = 'Interno: genera una parte del PDF del tablero de compras';

    protected $hidden = true;

    public function handle(): int
    {
        $entrada = (string) $this->argument('entrada');
        $salida = (string) $this->argument('salida');

        if (! is_file($entrada)) {
            throw new RuntimeException('No existe el archivo de entrada: '.$entrada);
        }

        $datos = json_decode((string) file_get_contents($entrada), true, 512, JSON_THROW_ON_ERROR);

        $html = view('compras.tablero_oc.listado', [
            'filas' => $datos['filas'],
            'titulo' => $datos['titulo'],
            'subtitulo' => $datos['subtitulo'],
            'logosCabecera' => $datos['logos'],
            'totalFilas' => $datos['total'],
            'mostrar_cabecera' => $datos['mostrar_cabecera'],
            'ocultar_empresa' => $datos['ocultar_empresa'],
        ])->render();

        // Sin numeración ni título corto: los agrega el servicio al unir todas las partes.
        /** @var \Barryvdh\DomPDF\PDF $pdf */
        $pdf = app('dompdf.wrapper');
        $pdf->setPaper('legal', 'landscape');
        $pdf->loadHTML($html);
        $pdf->getDomPDF()->render();
        file_put_contents($salida, $pdf->getDomPDF()->output());

        return self::SUCCESS;
    }
}
