<?php

namespace App\Queries\Ventas;

use App\Models\Ventas\Cliente;

class ClienteQuery implements ClienteQueryInterface
{
    protected $model;

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Cliente $cliente)
    {
        $this->model = $cliente;
    }

    public function first()
    {
        return $this->model->first();
    }

    public function all()
    {
        return $this->model->get();
    }

    public function allQuery(array $campos)
    {
        return $this->model->select($campos)->get();
    }

    public function allQueryporEstado(array $campos, $estado)
    {
        return $this->model->select($campos)
                ->orderBy('nombre','ASC')
                ->where('estado',$estado)
                ->where('nombre','!=',' ')->get();
    }

    public function allQueryCargaPedido(array $campos)
    {
        return $this->model->select($campos)
                ->orderBy('nombre','ASC')
                ->where([['estado','0'],['nombre','!=',' ']])
                ->orWhere([['estado','!=','0'],['nombre','!=',' '],['tiposuspension_id','!=','1']])
                ->get();
    }

    public function traeClienteporCodigo($codigo)
    {
        return $this->model->select('id','codigo')->where('codigo',$codigo)->first();
    }

    public function traeClienteporId($id, $campos = null)
    {
	  	if ($campos)
        	return $this->model->with('condicionivas')->with('vendedores')->with('zonavtas')->with('transportes')->with('condicionventas')->with('abastos')->with('coeficientes')->select($campos)->where('id',$id)->first();
		else
        	return $this->model->with('condicionivas')->with('vendedores')->with('zonavtas')->with('transportes')->where('id',$id)->with('paises')->with('abastos')->with('coeficientes')->with('condicionventas')->first();
    }

    // Datos para informe maestro de clientes

    public function generaDatosRepCliente($desdecliente_id, $hastacliente_id, $estado, $tiposuspensioncliente_id,
                                        $desdevendedor_id, $hastavendedor_id)
    {
        $data = $this->model->select('cliente.*')
            ->with('localidades')
            ->with('provincias')
            ->with('tipossuspensioncliente')
            ->orderBy('nombre','ASC');

        switch($estado)
        {
            case 'TODOS':
                $data = $data
                    ->where([['nombre','!=',' ']]);
                break;
            case 'ACTIVOS':
                $data = $data
                    ->where([['estado','0'],['nombre','!=',' ']]);
                break;
            case 'SUSPENDIDOS':
                if ($tiposuspensioncliente_id != 0)
                    $data = $data
                        ->where([['estado','!=','0'],['nombre','!=',' '],['tiposuspension_id',$tiposuspensioncliente_id]]);
                else
                    $data = $data
                        ->where([['estado','!=','0'],['nombre','!=',' ']]);
                break;
        }
        // Filtra por cliente 
        if ($hastacliente_id != 99999999 || $desdecliente_id != 0)
            $data = $data->where('cliente.id', '>=', $desdecliente_id)
                        ->where('cliente.id', '<=', $hastacliente_id);

        // Filtra por vendedor
        if ($hastavendedor_id != 99999999 || $desdevendedor_id != 0)
            $data = $data->where('cliente.vendedor_id', '>=', $desdevendedor_id)
                        ->where('cliente.vendedor_id', '<=', $hastavendedor_id);

        $data = $data->get();
        return $data;
    }

}

