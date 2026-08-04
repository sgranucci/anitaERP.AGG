<?php

namespace App\Imports\Stock;

use Maatwebsite\Excel\Concerns\OnEachRow;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Row;

class RecuentoImport implements OnEachRow, WithHeadingRow
{
    /** @var list<array{sku:string, cantidad_contada:float, detalle?:string|null, color?:string|null, talle?:string|null}> */
    private array $filas = [];

    public function __construct(
        private readonly string $colSku,
        private readonly string $colCantidad,
        private readonly ?string $colDetalle = null,
        private readonly ?string $colColor = null,
        private readonly ?string $colTalle = null,
    ) {}

    public function onRow(Row $row): void
    {
        $data = $row->toArray();
        $skuKey = $this->normalizarClave($this->colSku);
        $cantKey = $this->normalizarClave($this->colCantidad);
        $detKey = $this->colDetalle ? $this->normalizarClave($this->colDetalle) : null;
        $colorKey = $this->colColor ? $this->normalizarClave($this->colColor) : null;
        $talleKey = $this->colTalle ? $this->normalizarClave($this->colTalle) : null;

        $sku = trim((string) ($data[$skuKey] ?? ''));
        if ($sku === '') {
            return;
        }

        $this->filas[] = [
            'sku' => $sku,
            'cantidad_contada' => (float) ($data[$cantKey] ?? 0),
            'detalle' => $detKey ? trim((string) ($data[$detKey] ?? '')) : null,
            'color' => $colorKey ? trim((string) ($data[$colorKey] ?? '')) : null,
            'talle' => $talleKey ? trim((string) ($data[$talleKey] ?? '')) : null,
        ];
    }

    /**
     * @return list<array{sku:string, cantidad_contada:float, detalle?:string|null, color?:string|null, talle?:string|null}>
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
