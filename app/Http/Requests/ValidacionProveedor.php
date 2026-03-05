<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Rules\Compras\RuleProveedor;
use App\Models\Compras\Proveedor;

class ValidacionProveedor extends FormRequest
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
            'localidad_id' => ['integer', 'nullable'],
            'provincia_id' => 'required',
            'pais_id' => 'required',
            'condicioniva_id' => ['integer', 'nullable'],
            'condicionpago_id' => ['integer', 'nullable'],
            'cuentacontable_id' => 'required',
            'cuentacontableme_id' => 'required',
            'nroinscripcion' => ['required', new RuleProveedor('nroinscripcion')],
            'retieneiva' => ['required', new RuleProveedor('retieneiva')],
            'nroIIBB' => 'required|max:100|',
        ];
    }
}
