<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ValidacionTecnicoLaboratorio extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        $id = $this->route('id');

        return [
            'nombre' => [
                'required',
                'max:255',
                Rule::unique('tecnico_laboratorio', 'nombre')
                    ->where(fn ($q) => $q->where('empresa_id', $this->input('empresa_id')))
                    ->ignore($id),
            ],
            'empresa_id' => 'required|exists:empresa,id',
            'legajo' => 'nullable|integer|min:1',
            'activo' => 'required|in:S,N',
        ];
    }
}
