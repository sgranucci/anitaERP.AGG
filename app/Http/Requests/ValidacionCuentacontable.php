<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ValidacionCuentacontable extends FormRequest
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
        $id = $this->route('id');
        $empresaId = (int) $this->input('empresa_id');

        return [
            'empresa_id' => 'required|integer',
            'rubrocontable_id' => 'required|integer',
            'nombre' => [
                'required',
                'max:100',
                Rule::unique('cuentacontable', 'nombre')
                    ->where(fn ($q) => $q->where('empresa_id', $empresaId))
                    ->ignore($id),
            ],
            'codigo' => [
                'required',
                'max:50',
                Rule::unique('cuentacontable', 'codigo')
                    ->where(fn ($q) => $q->where('empresa_id', $empresaId))
                    ->ignore($id),
            ],
            'tipocuenta' => 'required|min:1|max:2,',
            'nivel' => 'required|min:1|max:9,',
            'monetaria' => 'required|min:1|max:2,',
            'manejaccosto' => 'required|min:1|max:2,',
        ];
    }

    public function messages()
    {
        return [
            'nombre.unique' => 'El nombre ya está en uso para esta empresa.',
            'codigo.unique' => 'El código ya está en uso para esta empresa.',
        ];
    }
}
