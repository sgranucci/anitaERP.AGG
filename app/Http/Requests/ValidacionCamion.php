<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ValidacionCamion extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'dominio' => 'required|max:15',
            'habilitacion' => 'nullable|max:30',
            'tipo' => 'nullable|max:15',
            'dominio_acoplado' => 'nullable|max:10',
            'cuit_chofer' => 'nullable|max:13',
            'cantidad_precinto' => 'nullable|integer|min:0|max:99',
            'codigo' => 'nullable|max:20',
        ];
    }
}
