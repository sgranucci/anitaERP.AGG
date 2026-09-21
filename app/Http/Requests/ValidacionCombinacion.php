<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ValidacionCombinacion extends FormRequest
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
            'articulo_id' => ['required'],
			//'codigo' => ['unique_with:combinacion, articulo_id, codigo'],
            'nombre' => ['string','max:20'],
            'observacion' => ['string', 'nullable'],
            'estado' => ['nullable', 'string', 'max:1'],
            'estado_fabrica' => ['nullable', 'string', 'max:1', 'in:A,I'],
            'estado_local' => ['nullable', 'string', 'max:1', 'in:A,I'],
        ];
    }
}

