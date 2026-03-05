<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ActualizarRetenciongananciaRequest extends FormRequest
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
            'nombre' => [
				'required',
			],
            'porcentajeinscripto' => 'required|numeric|between:0,100',
            'porcentajenoinscripto' => 'required|numeric|between:0,100',
            'cuotas.*'    => [
                'integer',
            ],
            'cuotas'      => [
                'array',
            ],
        ];
    }
}
