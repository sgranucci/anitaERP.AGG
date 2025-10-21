<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Rules\Ventas\RuleCliente;
use App\Models\Ventas\Ordenventa;

class ValidacionOrdenventa extends FormRequest
{
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
            'localidad_id' => ['integer', 'nullable'],
            'provincia_id' => ['integer', 'nullable'],
            'pais_id' => ['integer', 'nullable'],
            'condicionventa_id' => ['integer', 'nullable'],
            'nroinscripcion' => [new RuleCliente('nroinscripcion')],
            'detalle' => 'required',
            'monto' => 'required'
        ];
    }
}
