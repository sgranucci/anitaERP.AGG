<?php

namespace App\Exports\Compras;

use App\Services\Compras\AhorroImportacionService;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/** Plantilla vacía para la carga histórica de ahorros: solo los títulos de columna. */
class AhorroImportacionPlantillaExport implements FromArray, ShouldAutoSize, WithHeadings, WithStyles
{
    /** Títulos legibles; el importador los reconoce por alias. */
    private const TITULOS = [
        'documento' => 'Documento (REQ u OC)',
        'numero' => 'Número',
        'sku' => 'SKU',
        'precio_anterior' => 'Precio anterior',
        'moneda' => 'Moneda',
        'origen' => 'Origen',
        'referencia' => 'Referencia',
        'tipo_ahorro' => 'Tipo de ahorro',
        'observacion' => 'Observación',
        'empresa' => 'Empresa',
    ];

    public function array(): array
    {
        return [];
    }

    public function headings(): array
    {
        return array_map(fn (string $c) => self::TITULOS[$c], AhorroImportacionService::COLUMNAS);
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => ['bold' => true, 'name' => 'Arial', 'size' => 11, 'color' => ['rgb' => '17202A']],
                'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '85C1E9']],
            ],
        ];
    }
}
