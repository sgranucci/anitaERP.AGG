<?php

namespace App\Exports\Stock;

use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class ContaduriaInventarioWorkbookExport implements WithMultipleSheets
{
    use Exportable;

    /**
     * Solapas de un depósito, una por rubro.
     *
     * @param  list<array<string, mixed>>  $detalles
     */
    public function __construct(
        private readonly array $detalles,
    ) {}

    public function sheets(): array
    {
        $usados = [];
        $sheets = [];
        foreach ($this->detalles as $detalle) {
            $sheets[] = new ContaduriaInventarioHojaExport(
                $this->unico((string) ($detalle['titulo_hoja'] ?? 'Hoja'), $usados),
                $detalle,
            );
        }

        return $sheets;
    }

    /**
     * @param  array<string, true>  $usados
     */
    private function unico(string $nombre, array &$usados): string
    {
        $nombre = trim($nombre) !== '' ? trim($nombre) : 'Hoja';
        $candidato = mb_substr($nombre, 0, 31);
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
