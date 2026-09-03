<?php

namespace App\Exports\Compras;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class OrdencompraLegajoBandejaExport implements FromCollection, ShouldAutoSize, WithHeadings, WithTitle
{
    use Exportable;

    /**
     * @param  list<array<string, mixed>>  $filas
     */
    public function __construct(
        private array $filas,
        private string $titulo = 'Legajos de compras',
        private string $subtitulo = '',
    ) {}

    public function headings(): array
    {
        $head = [
            'Id',
            'OC',
            'Fecha',
            'Empresa',
            'Proveedor',
            'Centro de costo',
            'Sector',
            'Días en ubicación',
            'Factura',
            'COM',
            'Paquete',
            'Decisión',
            'Autorizó / rechazó',
            'Fecha decisión',
            'Comentario',
        ];
        if ($this->subtitulo !== '') {
            return $head;
        }

        return $head;
    }

    public function collection(): Collection
    {
        return collect($this->filas)->map(static fn (array $f) => [
            $f['id'] ?? '',
            $f['numero'] ?? '',
            $f['fecha'] ?? '',
            $f['empresa'] ?? '',
            $f['proveedor'] ?? '',
            $f['centrocosto'] ?? '',
            $f['sector'] ?? '',
            $f['dias'] ?? 0,
            ! empty($f['tiene_factura']) ? 'Sí' : 'No',
            ! empty($f['tiene_com']) ? 'Sí' : 'No',
            ! empty($f['paquete_ok']) ? 'Completo' : 'Incompleto',
            $f['decision'] ?? '',
            $f['firmante'] ?? '',
            $f['fecha_decision'] ?? '',
            $f['comentario_decision'] ?? '',
        ]);
    }

    public function title(): string
    {
        return mb_substr($this->titulo !== '' ? $this->titulo : 'Legajos', 0, 31);
    }
}
