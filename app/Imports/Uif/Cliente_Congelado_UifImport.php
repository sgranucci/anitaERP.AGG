<?php

namespace App\Imports\Uif;

use App\Repositories\Uif\Cliente_Congelado_UifRepositoryInterface;
use Maatwebsite\Excel\Concerns\OnEachRow;
use Maatwebsite\Excel\Row;
use Auth;
use Carbon\Carbon;
use DB;

class Cliente_Congelado_UifImport implements OnEachRow
{
    private $cliente_congelado_uifRepository;

    public function __construct(Cliente_Congelado_UifRepositoryInterface $cliente_congelado_uifrepository)
    {
        $this->cliente_congelado_uifRepository = $cliente_congelado_uifrepository;
    }

    /**
    * @param array $row
    *
    * @return \Illuminate\Database\Eloquent\Model|null
    */
    public function onRow(Row $row)
    {
        $row = $row->toArray();

        // Si el documento es un numero importa
        if (substr($row[1],1,1) >= '0' and substr($row[1],1,1) <= '9')
        {
            $arrayClienteCongelado_Uif = [
                'nombre' => $row[2].' '.$row[3].' '.$row[4].' '.$row[5].' '.$row[6],
                'numerodocumento' => $row[1],
                'resolucion' => $row[7],
                'fechacaducidad' => $row[8]
            ];

            // Busca si existe el registro
            $cliente_congelado_uif = $this->cliente_congelado_uifRepository
                                            ->buscaCliente_Congelado_Uif($arrayClienteCongelado_Uif['nombre'], 
                                                                            $arrayClienteCongelado_Uif['numerodocumento'], 
                                                                            $arrayClienteCongelado_Uif['resolucion'], 
                                                                            $arrayClienteCongelado_Uif['fechacaducidad']);
            
            if ($cliente_congelado_uif)
                $this->cliente_congelado_uifRepository->update($arrayClienteCongelado_Uif);
            else
                $this->cliente_congelado_uifRepository->create($arrayClienteCongelado_Uif);
        }
    }
}
