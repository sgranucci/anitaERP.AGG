<?php

namespace App\Services\Compras;

use App\Support\Configuracion\EmpresaLogoArchivo;
use App\Support\Reportes\DompdfListadoSupport;
use RuntimeException;
use setasign\Fpdi\Fpdi;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * PDF del detalle del tablero de compras.
 *
 * DomPDF tarda en proporción a la cantidad de celdas (≈ 11 ms por línea). Para no esperar
 * en serie, el detalle se parte en tandas y cada una se arma en un proceso PHP aparte
 * (`compras:tablero-pdf-parte`), varios a la vez. Después se unen en un solo PDF con la
 * numeración «Hoja N de M» (cada parte, sola, numeraría desde 1).
 */
class ComprasTableroOcPdfService
{
    private const TITULO_CORTO = 'Compras';

    /**
     * @param  list<array<string, mixed>>  $filas  detalle de ComprasTableroOcService::generar()
     * @return string ruta absoluta del PDF generado (el llamador lo mueve o lo borra)
     */
    public function generar(array $filas, string $titulo, string $subtitulo): string
    {
        $dirTrabajo = storage_path('pdf/listados/tablero_compras_trabajo_'.uniqid('', true));
        if (! is_dir($dirTrabajo) && ! mkdir($dirTrabajo, 0775, true) && ! is_dir($dirTrabajo)) {
            throw new RuntimeException('No se pudo crear el directorio de trabajo del PDF.');
        }

        try {
            return $this->generarEn($dirTrabajo, $filas, $titulo, $subtitulo);
        } finally {
            $this->borrarDirectorio($dirTrabajo);
        }
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     */
    private function generarEn(string $dir, array $filas, string $titulo, string $subtitulo): string
    {
        $datosComunes = [
            'titulo' => $titulo,
            'subtitulo' => $subtitulo,
            'total' => count($filas),
            'ocultar_empresa' => count(array_unique(array_column($filas, 'empresa'))) <= 1,
        ];
        $logos = EmpresaLogoArchivo::logosCabeceraDesdeColeccion(
            collect($filas)->map(static fn (array $f) => (object) ['nombreempresa' => $f['nombreempresa']])
        );

        $porParte = (int) config('compras.tablero_pdf.filas_por_parte', 400);
        $partes = array_chunk($filas, max(100, $porParte));
        if ($partes === []) {
            $partes = [[]];
        }

        // Una sola parte: no vale la pena otro proceso. Se arma acá, ya numerado.
        if (count($partes) === 1) {
            $destino = $dir.'/tablero_compras.pdf';
            $html = view('compras.tablero_oc.listado', $datosComunes + [
                'filas' => $partes[0],
                'logosCabecera' => $logos,
                'totalFilas' => count($filas),
                'mostrar_cabecera' => true,
            ])->render();
            DompdfListadoSupport::guardarLegalLandscape($html, $destino, ['titulo_corto' => self::TITULO_CORTO]);

            return $this->mover($destino);
        }

        $entradas = [];
        foreach ($partes as $i => $parte) {
            $entrada = $dir.'/parte_'.($i + 1).'.json';
            file_put_contents($entrada, json_encode($datosComunes + [
                'filas' => $parte,
                'logos' => $i === 0 ? $logos : [],
                'mostrar_cabecera' => $i === 0,
            ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
            $entradas[] = [$entrada, $dir.'/parte_'.($i + 1).'.pdf'];
        }

        $this->correrEnParalelo($entradas);

        return $this->mover($this->unir(array_column($entradas, 1), $dir));
    }

    /**
     * Arma cada parte en un proceso aparte, con tope de procesos simultáneos.
     *
     * @param  list<array{0: string, 1: string}>  $trabajos  [json de entrada, pdf de salida]
     */
    private function correrEnParalelo(array $trabajos): void
    {
        $php = (new PhpExecutableFinder)->find(false);
        if ($php === false) {
            throw new RuntimeException('No se encontró el ejecutable de PHP para armar el PDF en paralelo.');
        }

        $maximo = max(1, (int) config('compras.tablero_pdf.procesos', 4));
        $pendientes = $trabajos;
        /** @var list<Process> $enCurso */
        $enCurso = [];

        try {
            while ($pendientes !== [] || $enCurso !== []) {
                while ($pendientes !== [] && count($enCurso) < $maximo) {
                    [$entrada, $salida] = array_shift($pendientes);
                    $proceso = new Process(
                        [$php, '-d', 'memory_limit=1024M', base_path('artisan'), 'compras:tablero-pdf-parte', $entrada, $salida],
                        base_path()
                    );
                    $proceso->setTimeout(1800);
                    $proceso->start();
                    $enCurso[] = $proceso;
                }

                foreach ($enCurso as $clave => $proceso) {
                    if ($proceso->isRunning()) {
                        continue;
                    }
                    if (! $proceso->isSuccessful()) {
                        throw new RuntimeException(
                            'Falló una parte del PDF: '.trim($proceso->getErrorOutput().' '.$proceso->getOutput())
                        );
                    }
                    unset($enCurso[$clave]);
                }

                usleep(100000);
            }
        } finally {
            foreach ($enCurso as $proceso) {
                if ($proceso->isRunning()) {
                    $proceso->stop(1);
                }
            }
        }
    }

    /**
     * Une las partes y agrega a cada hoja el título corto y «Hoja N de M».
     *
     * @param  list<string>  $pdfs
     */
    private function unir(array $pdfs, string $dir): string
    {
        $totalHojas = 0;
        foreach ($pdfs as $ruta) {
            if (! is_file($ruta) || filesize($ruta) === 0) {
                throw new RuntimeException('Falta una parte del PDF: '.basename($ruta));
            }
            $contador = new Fpdi;
            $totalHojas += $contador->setSourceFile($ruta);
        }

        $pdf = new Fpdi;
        $pdf->SetAutoPageBreak(false);
        $pdf->SetFont('Helvetica', '', 8);
        $pdf->SetTextColor(38, 38, 38);

        $hoja = 0;
        foreach ($pdfs as $ruta) {
            $paginas = $pdf->setSourceFile($ruta);
            for ($n = 1; $n <= $paginas; $n++) {
                $plantilla = $pdf->importPage($n);
                $tamano = $pdf->getTemplateSize($plantilla);
                $pdf->AddPage($tamano['orientation'], [$tamano['width'], $tamano['height']]);
                $pdf->useTemplate($plantilla);

                $hoja++;
                $pdf->SetXY(8, 2);
                $pdf->Cell(60, 4, self::TITULO_CORTO);
                $pdf->SetXY($tamano['width'] - 50, $tamano['height'] - 7);
                $pdf->Cell(42, 4, 'Hoja '.$hoja.' de '.$totalHojas, 0, 0, 'R');
            }
        }

        $unido = $dir.'/tablero_compras.pdf';
        $pdf->Output('F', $unido);

        return $unido;
    }

    /** Saca el PDF del directorio de trabajo (que se borra al terminar). */
    private function mover(string $origen): string
    {
        $dir = storage_path('pdf/listados');
        if (! is_dir($dir) && ! mkdir($dir, 0775, true) && ! is_dir($dir)) {
            throw new RuntimeException('No se pudo crear storage/pdf/listados.');
        }
        $destino = $dir.'/tablero_compras_oc_'.uniqid('', true).'.pdf';
        if (! rename($origen, $destino)) {
            throw new RuntimeException('No se pudo guardar el PDF generado.');
        }

        return $destino;
    }

    private function borrarDirectorio(string $dir): void
    {
        foreach (glob($dir.'/*') ?: [] as $archivo) {
            if (is_file($archivo)) {
                @unlink($archivo);
            }
        }
        @rmdir($dir);
    }
}
