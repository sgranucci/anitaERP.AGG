<?php

namespace App\Imports\Stock;

use Maatwebsite\Excel\Concerns\OnEachRow;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Row;

class RecuentoImport implements OnEachRow, WithHeadingRow
{
    /** @var list<array{sku:string, cantidad_contada:float, detalle?:string}> */
    private array $filas = [];

    public function __construct(
        private readonly string $colSku,
        private readonly string $colCantidad,
        private readonly ?string $colDetalle = null,
    ) {}

    public function onRow(Row $row): void
    {
        $data = $row->toArray();
        $skuKey = $this->normalizarClave($this->colSku);
        $cantKey = $this->normalizarClave($this->colCantidad);
        $detKey = $this->colDetalle ? $this->normalizarClave($this->colDetalle) : null;

        $sku = trim((string) ($data[$skuKey] ?? ''));
        if ($sku === '') {
            return;
        }

        $this->filas[] = [
            'sku' => $sku,
            'cantidad_contada' => (float) ($data[$cantKey] ?? 0),
            'detalle' => $detKey ? trim((string) ($data[$detKey] ?? '')) : null,
        ];
    }

    /**
     * @return list<array{sku:string, cantidad_contada:float, detalle?:string}>
     */
    public function filas(): array
    {
        return $this->filas;
    }

    private function normalizarClave(string $nombre): string
    {
        return strtolower(str_replace([' ', '-'], '_', trim($nombre)));
    }
}
