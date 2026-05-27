<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ValidacionAreaComandaGastronomia extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        $id = $this->route('id');
        $empresaId = (int) $this->input('empresa_id');

        return [
            'nombre' => 'required|max:255',
            'codigo' => [
                'required',
                'max:255',
                Rule::unique('area_comanda_gastronomia', 'codigo')
                    ->ignore($id)
                    ->where(fn ($q) => $q->where('empresa_id', $empresaId)),
            ],
            'empresa_id' => 'required|exists:empresa,id',
        ];
    }

    public function messages()
    {
        return [
            'codigo.unique' => 'El código ya está en uso para esta empresa.',
        ];
    }
}
