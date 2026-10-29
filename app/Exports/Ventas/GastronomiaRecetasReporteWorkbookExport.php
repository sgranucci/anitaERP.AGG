<?php

declare(strict_types=1);

namespace App\Exports\Ventas;

use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class GastronomiaRecetasReporteWorkbookExport implements WithMultipleSheets
{
    use Exportable;

    /**
     * @param  array<string, mixed>  $resultado
     */
    public function __construct(
        private readonly array $resultado,
    ) {}

    public function sheets(): array
    {
        return [
            $this->hojaResumen(),
            $this->hojaRecetas(),
            $this->hojaDetalle(),
            $this->hojaLineas(),
            $this->hojaAlertas(),
        ];
    }

    private function hojaResumen(): GastronomiaRecetasReporteHojaExport
    {
        $filas = [];
        foreach ($this->resultado['resumen'] ?? [] as $fila) {
            $filas[] = [
                $fila['categoria'],
                $fila['subcategoria'],
                (int) $fila['recetas'],
                (int) $fila['alta'],
                (int) $fila['media'],
            ];
        }
        $totales = $this->resultado['totales'] ?? [];
        $filas[] = ['Total', '', (int) ($totales['recetas'] ?? 0), (int) ($totales['alta'] ?? 0), (int) ($totales['media'] ?? 0)];

        return new GastronomiaRecetasReporteHojaExport(
            'Resumen',
            ['Categoría', 'Subcategoría', 'Recetas', 'Con alerta Alta', 'Con alerta Media'],
            $filas,
            ['C' => 0, 'D' => 0, 'E' => 0],
        );
    }

    private function hojaRecetas(): GastronomiaRecetasReporteHojaExport
    {
        $filas = [];
        foreach ($this->resultado['recetas'] ?? [] as $fila) {
            $filas[] = [
                $fila['codigo'],
                $fila['nombre'],
                $fila['sku'],
                $fila['estado_articulo'],
                $fila['categoria'],
                $fila['subcategoria'],
                (float) $fila['porciones'],
                (float) $fila['costo'],
                (float) ($fila['costo_lote'] ?? 0),
                (float) $fila['precio'],
                $fila['ratio'] === null ? null : (float) $fila['ratio'],
                (int) $fila['lineas'],
                $fila['alerta'] ?? '',
                (int) $fila['cant_alertas'],
            ];
        }

        return new GastronomiaRecetasReporteHojaExport(
            'Recetas',
            ['Código', 'Receta', 'SKU venta', 'Estado artículo', 'Categoría', 'Subcategoría', 'Porciones', 'Costo porción', 'Costo lote', 'Precio vta', 'Precio / costo', 'Líneas', 'Alerta', 'Cant. alertas'],
            $filas,
            ['G' => 2, 'H' => 2, 'I' => 2, 'J' => 2, 'K' => 2, 'L' => 0, 'N' => 0],
        );
    }

    private function hojaDetalle(): GastronomiaRecetasReporteHojaExport
    {
        $filas = [];
        foreach ($this->resultado['detalle'] ?? [] as $fila) {
            $filas[] = [
                $fila['receta'],
                $fila['codigo'],
                $fila['tipo'],
                $fila['codigo_linea'],
                $fila['descripcion'],
                $fila['um'],
                $fila['cant_desp'],
                $fila['costo_unitario'],
                $fila['costo_linea'],
                $fila['costo_porcion'] ?? null,
                $fila['precio'],
                $fila['porciones'],
                $fila['entra_costo'],
            ];
        }

        return new GastronomiaRecetasReporteHojaExport(
            'Detalle',
            ['Receta', 'Código', 'Tipo línea', 'SKU / subreceta', 'Descripción', 'UM', 'Cant. c/ desp.', 'Costo unitario', 'Costo línea', 'Costo porción', 'Precio vta', 'Porciones', 'Entra al costo'],
            $filas,
            ['G' => 4, 'H' => 2, 'I' => 2, 'J' => 2, 'K' => 2, 'L' => 2],
            true,
        );
    }

    private function hojaLineas(): GastronomiaRecetasReporteHojaExport
    {
        $filas = [];
        $yaMostroCabecera = [];
        foreach ($this->resultado['lineas'] ?? [] as $fila) {
            $formulaId = (int) ($fila['formula_id'] ?? 0);
            $cabecera = ! isset($yaMostroCabecera[$formulaId]);
            $yaMostroCabecera[$formulaId] = true;
            $filas[] = [
                $fila['receta'],
                $fila['codigo_receta'],
                $fila['codigo_linea'],
                $fila['descripcion'],
                $fila['um'],
                $fila['tipo'],
                (float) $fila['cantidad'],
                (float) $fila['factor'],
                (float) $fila['cant_desp'],
                (float) $fila['costo_unitario'],
                (float) $fila['costo_linea'],
                $cabecera ? (float) $fila['costo_receta'] : null,
                $cabecera ? (float) ($fila['costo_lote'] ?? 0) : null,
                $cabecera ? (float) $fila['precio'] : null,
                $fila['opcional'],
                $fila['entra_costo'],
                $cabecera ? (float) $fila['porciones'] : null,
                $fila['categoria'],
                $fila['subcategoria'],
                $fila['estado_articulo'],
                $fila['alerta'] ?? '',
            ];
        }

        return new GastronomiaRecetasReporteHojaExport(
            'Líneas',
            ['Receta', 'Código', 'SKU / subreceta', 'Descripción', 'UM', 'Tipo línea', 'Cantidad', 'Factor desp.', 'Cant. c/ desp.', 'Costo unitario', 'Costo línea', 'Costo porción', 'Costo lote', 'Precio vta', 'Opcional', 'Entra al costo', 'Porciones', 'Categoría', 'Subcategoría', 'Estado artículo', 'Alerta'],
            $filas,
            ['G' => 4, 'H' => 4, 'I' => 4, 'J' => 2, 'K' => 2, 'L' => 2, 'M' => 2, 'N' => 2, 'Q' => 2],
        );
    }

    private function hojaAlertas(): GastronomiaRecetasReporteHojaExport
    {
        $filas = [];
        foreach ($this->resultado['alertas'] ?? [] as $fila) {
            $filas[] = [
                $fila['codigo'],
                $fila['receta'],
                $fila['categoria'],
                $fila['gravedad'],
                $fila['tipo'],
                $fila['detalle'],
            ];
        }

        return new GastronomiaRecetasReporteHojaExport(
            'Alertas',
            ['Código', 'Receta', 'Categoría', 'Gravedad', 'Tipo', 'Detalle'],
            $filas,
        );
    }
}
