<?php

namespace App\Exports\Caja;

use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * Una hoja por mes cuando la posición financiera se pide en rango.
 */
class PosicionFinancieraMultiExport implements WithMultipleSheets
{
    use Exportable;

    /**
     * @param  list<array{
     *   filas: list<array{etiqueta: string, valor: float, por_dia?: array<int, float>}>,
     *   dias: list<int>,
     *   periodo_texto: string,
     *   titulo_hoja?: string
     * }>  $periodos
     */
    public function __construct(
        private readonly array $periodos,
        private readonly mixed $empresa,
    ) {
    }

    public function sheets(): array
    {
        $hojas = [];
        foreach ($this->periodos as $periodo) {
            $titulo = (string) ($periodo['titulo_hoja'] ?? $periodo['periodo_texto'] ?? 'Posición financiera');
            $hojas[] = (new PosicionFinancieraExport)->parametros(
                $periodo['filas'] ?? [],
                $periodo['dias'] ?? [],
                $this->empresa,
                (string) ($periodo['periodo_texto'] ?? ''),
                false,
                $titulo,
            );
        }

        return $hojas !== [] ? $hojas : [
            (new PosicionFinancieraExport)->parametros([], [], $this->empresa, ''),
        ];
    }
}
