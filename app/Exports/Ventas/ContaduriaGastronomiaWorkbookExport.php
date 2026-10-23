<?php

namespace App\Exports\Ventas;

use App\Services\Ventas\GastronomiaDescuentoReporteService;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithTitle;

/**
 * Libro de contaduría armado con las mismas hojas del reporte de descuentos.
 * Si $detallePorCodigo trae bloques, cada código va en su solapa.
     * Una hoja con lado_a_lado pone esos mismos bloques en columnas, uno al lado del otro.
 */
class ContaduriaGastronomiaWorkbookExport implements WithMultipleSheets
{
    /**
     * @param  array<string, mixed>  $filtros
     * @param  list<array{titulo:string,resultado:array<string,mixed>,solo_totales:bool,en_columnas?:bool,lado_a_lado?:bool}>  $hojas
     * @param  array<string, mixed>|null  $detallePorCodigo
     */
    public function __construct(
        private readonly GastronomiaDescuentoReporteService $reporteService,
        private readonly array $filtros,
        private readonly string $titulo,
        private readonly string $subtitulo,
        private readonly string $empresaNombre,
        private readonly array $hojas,
        private readonly ?array $detallePorCodigo,
    ) {}

    public function sheets(): array
    {
        $sheets = [];
        $usados = [];

        foreach ($this->hojas as $hoja) {
            if (empty($hoja['solo_totales'])) {
                continue;
            }
            $sheets[] = $this->hojaDesdeResultado($hoja, $usados);
        }

        if ($this->detallePorCodigo !== null) {
            $periodo = (string) ($this->detallePorCodigo['periodo_texto'] ?? '');
            foreach ($this->detallePorCodigo['bloques'] ?? [] as $bloque) {
                $nombre = $this->nombreUnico(
                    trim((string) ($bloque['codigo'] ?? '').' '.(string) ($bloque['nombre'] ?? 'Descuento')),
                    $usados,
                );
                $export = new GastronomiaDescuentoReporteBloqueExport(
                    $bloque,
                    $periodo,
                    $this->empresaNombre,
                    $this->titulo,
                    $this->subtitulo,
                );
                $export->setTitle($nombre);
                $sheets[] = $export;
            }
        }

        foreach ($this->hojas as $hoja) {
            if (! empty($hoja['solo_totales'])) {
                continue;
            }
            $sheets[] = $this->hojaDesdeResultado($hoja, $usados);
        }

        if ($sheets === []) {
            $vacio = (new GastronomiaDescuentoReporteExport($this->reporteService))
                ->parametros(
                    $this->filtros,
                    $this->titulo,
                    $this->subtitulo,
                    $this->empresaNombre,
                    true,
                    ['bloques' => [], 'totales' => [], 'gran_total_costo' => 0, 'mes_etiqueta' => ''],
                );
            $vacio->setTitle('Subtotal');
            $sheets[] = $vacio;
        }

        return $sheets;
    }

    /**
     * @param  array{titulo:string,resultado:array<string,mixed>,solo_totales:bool,en_columnas?:bool,lado_a_lado?:bool}  $hoja
     * @param  array<string, true>  $usados
     */
    private function hojaDesdeResultado(array $hoja, array &$usados): WithTitle
    {
        $tituloHoja = $this->nombreUnico((string) ($hoja['titulo'] ?? 'Hoja'), $usados);

        if (! empty($hoja['lado_a_lado'])) {
            $export = (new GastronomiaDescuentoReporteLadoALadoExport())
                ->parametros(
                    $hoja['resultado'],
                    $this->titulo,
                    $this->subtitulo,
                    $this->empresaNombre,
                );
            $export->setTitle($tituloHoja);

            return $export;
        }

        if (! empty($hoja['en_columnas'])) {
            $export = (new GastronomiaDescuentoReporteColumnasExport())
                ->parametros(
                    $hoja['resultado'],
                    $this->titulo,
                    $this->subtitulo,
                    $this->empresaNombre,
                );
            $export->setTitle($tituloHoja);

            return $export;
        }

        $export = (new GastronomiaDescuentoReporteExport($this->reporteService))
            ->parametros(
                $this->filtros,
                $this->titulo,
                $this->subtitulo,
                $this->empresaNombre,
                (bool) ($hoja['solo_totales'] ?? false),
                $hoja['resultado'],
            );
        $export->setTitle($tituloHoja);

        return $export;
    }

    /**
     * @param  array<string, true>  $usados
     */
    private function nombreUnico(string $base, array &$usados): string
    {
        $nombre = GastronomiaDescuentoReporteExport::sanitizarNombreHoja($base);
        if ($nombre === '') {
            $nombre = 'Hoja';
        }
        $candidato = $nombre;
        $i = 2;
        while (isset($usados[$candidato])) {
            $suffix = ' ('.$i.')';
            $candidato = mb_substr($nombre, 0, max(1, 31 - mb_strlen($suffix))).$suffix;
            $i++;
        }
        $usados[$candidato] = true;

        return $candidato;
    }
}
