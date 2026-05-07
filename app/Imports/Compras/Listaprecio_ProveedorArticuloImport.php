<?php

namespace App\Imports\Compras;

use App\Repositories\Compras\Listaprecio_Proveedor_ArticuloRepositoryInterface;
use App\Repositories\Stock\ArticuloRepositoryInterface;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;

class Listaprecio_ProveedorArticuloImport implements ToCollection
{
    public array $errores = [];

    public int $importados = 0;

    public function __construct(
        private int $listaprecioProveedorId,
        private string $fechavigencia,
        private int $usuarioId,
        private Listaprecio_Proveedor_ArticuloRepositoryInterface $articuloRepo,
        private ArticuloRepositoryInterface $articuloRepository,
    ) {}

    public function collection(Collection $rows)
    {
        $start = 0;
        if ($rows->isNotEmpty()) {
            $c0 = strtolower(trim((string) ($rows->first()[0] ?? '')));
            if (in_array($c0, ['sku', 'codigo', 'código', 'articulo', 'artículo'], true)) {
                $start = 1;
            }
        }

        for ($i = $start; $i < $rows->count(); $i++) {
            $row = $rows[$i];
            $sku = isset($row[0]) ? trim((string) $row[0]) : '';
            if ($sku === '') {
                continue;
            }

            $precioRaw = $row[1] ?? null;
            if ($precioRaw === null || $precioRaw === '') {
                $this->errores[] = 'Fila '.($i + 1).': falta precio para SKU '.$sku;

                continue;
            }
            $precio = (float) str_replace(',', '.', preg_replace('/[^\d,.-]/', '', (string) $precioRaw));

            $descuento = isset($row[2]) && $row[2] !== '' ? (float) str_replace(',', '.', preg_replace('/[^\d,.-]/', '', (string) $row[2])) : 0;
            $codProv = isset($row[3]) ? substr(trim((string) $row[3]), 0, 100) : '';

            $art = $this->articuloRepository->findPorSku($sku);
            if (! $art) {
                $this->errores[] = 'SKU no encontrado: '.$sku;

                continue;
            }

            $this->articuloRepo->createRow([
                'listaprecio_proveedor_id' => $this->listaprecioProveedorId,
                'articulo_id' => $art->id,
                'precio' => $precio,
                'descuento' => min(100, max(0, $descuento)),
                'articulo_proveedor' => $codProv !== '' ? $codProv : $sku,
                'fechavigencia' => $this->fechavigencia,
                'usuarioultcambio_id' => $this->usuarioId,
            ]);
            $this->importados++;
        }
    }
}
