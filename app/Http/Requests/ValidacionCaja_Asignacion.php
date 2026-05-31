<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ValidacionCaja_Asignacion  extends FormRequest
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
            'fecha' => 'required|date',
            'usuario_id' => 'required|integer|exists:usuario,id',
            'caja_id' => 'required|integer|exists:caja,id',
            'empresa_id' => 'required|integer|exists:empresa,id',
        ];
    }
}
