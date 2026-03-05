<?php

namespace App\Repositories\Ventas;

use App\Models\Ventas\Cliente_Cuentacorriente_Aplicacion;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Carbon\Carbon;
use Auth;
use DB;

class Cliente_Cuentacorriente_AplicacionRepository implements Cliente_Cuentacorriente_AplicacionRepositoryInterface
{
    protected $model;

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Cliente_Cuentacorriente_Aplicacion $cliente_cuentacorriente_aplicacion)
    {
        $this->model = $cliente_cuentacorriente_aplicacion;
    }

    public function create(array $data)
    {
        return $this->model->create($data);
    }

    public function update(array $data, $id)
    {
        $cliente_cuentacorriente_aplicacion = $this->model->findOrFail($id)->update($data);

        return $cliente_cuentacorriente_aplicacion;
    }

    public function delete($id)
    {
    	$cliente_cuentacorriente_aplicacion = $this->model->destroy($id);

		return $cliente_cuentacorriente_aplicacion;
    }

    public function find($id)
    {
        if (null == $cliente_cuentacorriente_aplicacion = $this->model->with("cliente_cuentacorrientes")
                                                                        ->with('ventas')->with('monedas')->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $cliente_cuentacorriente_aplicacion;
    }

    public function findOrFail($id)
    {
        if (null == $cliente_cuentacorriente_aplicacion = $this->model->with("cliente_cuentacorrientes")
                                                                        ->with('ventas')->with('monedas')->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $cliente_cuentacorriente_aplicacion;
    }

    public function buscaPorCuentaCorrienteCobranza($cliente_cuentacorriente_id, $cobranza_id)
    {
        return ($this->model->with("cliente_cuentacorrientes")->with('ventas')->with('monedas')
                            ->where('cliente_cuentacorriente_id', $cliente_cuentacorriente_id)
                            ->where('cobranza_id', $cobranza_id)
                            ->first());
    }

    public function borraPorCuentaCorrienteCobranza($cliente_cuentacorriente_id, $cobranza_id)
    {
        return ($this->model->where('cliente_cuentacorriente_id', $cliente_cuentacorriente_id)
                            ->where('cobranza_id', $cobranza_id)
                            ->delete());
    }

    public function buscaPorCuentaCorrienteComprobanteAplicado($cliente_cuentacorriente_id, $cobranza_id)
    {
        return ($this->model->with("cliente_cuentacorrientes")->with('ventas')->with('monedas')
                            ->where('cliente_cuentacorriente_aplicado_id', $cliente_cuentacorriente_id)
                            ->where('cobranza_id', $cobranza_id)
                            ->first());
    }

}

