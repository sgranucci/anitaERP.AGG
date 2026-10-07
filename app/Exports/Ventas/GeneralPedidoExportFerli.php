<?php

namespace App\Exports\Ventas;

use Illuminate\Contracts\View\View;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class GeneralPedidoExportFerli extends GeneralPedidoExport
{
    public function view(): View
    {
        $data = $this->pedidoService->generaDatosRepGeneralPedidos(
            $this->tipolistado,
            $this->estado,
            $this->mventa_id,
            $this->desdefecha,
            $this->hastafecha,
            $this->desdevendedor_id,
            $this->hastavendedor_id,
            $this->desdecliente_id,
            $this->hastacliente_id,
            $this->desdearticulo_id,
            $this->hastaarticulo_id,
            $this->desdelinea_id,
            $this->hastalinea_id,
            $this->desdefondo_id,
            $this->hastafondo_id
        );

        return view('exports.ventas.reportegeneralpedido_ferli.reportegeneralpedido', [
            'comprobantes' => $data,
            'tipolistado' => $this->tipolistado,
            'estado' => $this->estado,
            'marca' => $this->nombremventa,
            'desdevendedor_id' => $this->desdevendedor_id,
            'hastavendedor_id' => $this->hastavendedor_id,
            'desdecliente_id' => $this->desdecliente_id,
            'hastacliente_id' => $this->hastacliente_id,
            'desdearticulo_id' => $this->desdearticulo_id,
            'hastaarticulo_id' => $this->hastaarticulo_id,
            'desdelinea_id' => $this->desdelinea_id,
            'hastalinea_id' => $this->hastalinea_id,
            'desdefondo_id' => $this->desdefondo_id,
            'hastafondo_id' => $this->hastafondo_id,
            'desdefecha' => $this->desdefecha,
            'hastafecha' => $this->hastafecha,
        ]);
    }

    public function styles(Worksheet $sheet)
    {
        $estilos = parent::styles($sheet);
        unset($estilos['E'], $estilos['F'], $estilos['H'], $estilos['J'], $estilos['AN']);

        $negrita = ['font' => ['bold' => true]];
        $estilos['A'] = $negrita;
        $estilos['F'] = $negrita;
        $estilos['G'] = $negrita;
        $estilos['I'] = $negrita;
        $estilos['K'] = $negrita;
        $estilos[$this->columnaTotal()] = $negrita;

        return $estilos;
    }

    public function columnWidths(): array
    {
        $anchos = [
            'A' => 10,
            'C' => 15,
            'E' => 14,
        ];

        $columna = 15;
        $desde = (int) config('consprod.DESDE_MEDIDA');
        $hasta = (int) config('consprod.HASTA_MEDIDA');
        for ($medida = $desde; $medida <= $hasta; $medida++) {
            $anchos[Coordinate::stringFromColumnIndex($columna)] = 5;
            $columna++;
        }
        $anchos[$this->columnaTotal()] = 8;

        return $anchos;
    }

    private function columnaTotal(): string
    {
        $medidas = (int) config('consprod.HASTA_MEDIDA') - (int) config('consprod.DESDE_MEDIDA') + 1;

        return Coordinate::stringFromColumnIndex(14 + $medidas + 1);
    }
}
