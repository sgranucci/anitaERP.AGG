<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Rules\Ventas\RuleCliente;
use App\Models\Ventas\Cliente;

class ValidacionClienteProvisorio extends FormRequest
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
            'nombre' => 'required|max:255|',
            'domicilio' => 'required|max:255|',
            'descuento' => 'numeric|nullable|max:100',
            'localidad_id' => ['integer', 'nullable'],
            'provincia_id' => 'required',
            'pais_id' => 'required',
            'zonavta_id' => ['integer', 'nullable'],
            'subzonavta_id' => ['integer', 'nullable'],
            'vendedor_id' => ['integer', 'nullable'],
            'condicioniva_id' => ['integer', 'nullable'],
            'condicionventa_id' => ['integer', 'nullable'],
            'listaprecio_id' => ['integer', 'nullable'],
            'nroinscripcion' => ['required', new RuleCliente('nroinscripcion')],
            'vaweb' => ['required', new RuleCliente('vaweb')],
        ];
    }
}
