<?php

namespace App\Exports\Ventas;

use Illuminate\Contracts\View\View;

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
}
