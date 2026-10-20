<?php

namespace App\Exports\Stock;

use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class ContaduriaInventarioWorkbookExport implements WithMultipleSheets
{
    use Exportable;

    /**
     * @param  array<string, mixed>  $armado
     */
    public function __construct(
        private readonly array $armado,
        private readonly string $titulo,
        private readonly string $subtitulo,
        private readonly string $empresaNombre,
    ) {}

    public function sheets(): array
    {
        $usados = [];
        $sheets = [
            new ContaduriaInventarioHojaExport(
                $this->unico('Subtotal', $usados),
                'subtotal',
                [
                    'depositos' => $this->armado['depositos'] ?? [],
                    'rubros' => $this->armado['subtotal'] ?? [],
                ],
                $this->titulo,
                $this->subtitulo,
                $this->empresaNombre,
            ),
        ];

        foreach ($this->armado['detalles'] ?? [] as $detalle) {
            $sheets[] = new ContaduriaInventarioHojaExport(
                $this->unico((string) ($detalle['titulo_hoja'] ?? 'Deposito'), $usados),
                'detalle',
                $detalle,
                $this->titulo,
                $this->subtitulo,
                $this->empresaNombre,
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
