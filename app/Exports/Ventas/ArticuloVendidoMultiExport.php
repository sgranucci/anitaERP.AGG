<?php

namespace App\Exports\Ventas;

use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class ArticuloVendidoMultiExport implements WithMultipleSheets
{
    use Exportable;

    private $reporteService;

    private $desdefecha;

    private $hastafecha;

    private $desdearticulo_id;

    private $hastaarticulo_id;

    private $desdecliente_id;

    private $hastacliente_id;

    private $desdelinea_id;

    private $hastalinea_id;

    private $mventa_id;

    private $nombremventa;

    public function __construct($reporteService, $params)
    {
        $this->reporteService = $reporteService;
        $this->desdefecha = $params['desdefecha'];
        $this->hastafecha = $params['hastafecha'];
        $this->desdearticulo_id = $params['desdearticulo_id'];
        $this->hastaarticulo_id = $params['hastaarticulo_id'];
        $this->desdecliente_id = $params['desdecliente_id'];
        $this->hastacliente_id = $params['hastacliente_id'];
        $this->desdelinea_id = $params['desdelinea_id'];
        $this->hastalinea_id = $params['hastalinea_id'];
        $this->mventa_id = $params['mventa_id'];
        $this->nombremventa = $params['nombremventa'];
    }

    public function sheets(): array
    {
        return [
            (new ArticuloVendidoExport($this->reporteService))
                ->parametros('IMPORTADO', $this->desdefecha, $this->hastafecha,
                    $this->desdearticulo_id, $this->hastaarticulo_id,
                    $this->desdecliente_id, $this->hastacliente_id,
                    $this->desdelinea_id, $this->hastalinea_id,
                    $this->mventa_id, $this->nombremventa),
            (new ArticuloVendidoExport($this->reporteService))
                ->parametros('NACIONAL', $this->desdefecha, $this->hastafecha,
                    $this->desdearticulo_id, $this->hastaarticulo_id,
                    $this->desdecliente_id, $this->hastacliente_id,
                    $this->desdelinea_id, $this->hastalinea_id,
                    $this->mventa_id, $this->nombremventa),
        ];
    }
}
