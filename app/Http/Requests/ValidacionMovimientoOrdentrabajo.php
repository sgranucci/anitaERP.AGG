<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Rules\Produccion\RuleMovimientoOrdentrabajo;
use App\Services\Produccion\MovimientoOrdentrabajoService;

class ValidacionMovimientoOrdentrabajo extends FormRequest
{
    protected $movimientoOrdentrabajoService;
    
    public function __construct(MovimientoOrdentrabajoService $movimientoordentrabajoservice)
    {
        $this->movimientoOrdentrabajoService = $movimientoordentrabajoservice;
    }

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'tarea_id' => 'required',
            'operacion_id' => 'required',
            'fecha' => 'required',
            'ordenestrabajo' => ['required', new RuleMovimientoOrdentrabajo('ordenestrabajo', request()->tarea_id, 
                                    request()->operacion_id, request()->movimiento_id,
                                    $this->movimientoOrdentrabajoService)],
        ];
    }
}
