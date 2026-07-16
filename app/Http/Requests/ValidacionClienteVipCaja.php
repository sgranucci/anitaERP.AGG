<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ValidacionClienteVipCaja extends FormRequest
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
            'empresa_id' => 'required|exists:empresa,id',
            'numeroid' => [
                'nullable',
                'integer',
                'min:1',
                Rule::unique('cliente_vip_caja', 'numeroid')
                    ->ignore($id)
                    ->where(fn ($q) => $q->where('empresa_id', $empresaId)),
            ],
            'nrodocumento' => 'nullable|max:20',
            'apellido' => 'required|max:40',
            'nombre' => 'required|max:40',
            'nickname' => 'nullable|max:30',
            'localidad' => 'nullable|max:15',
        ];
    }

    public function messages()
    {
        return [
            'numeroid.unique' => 'El número Anita ya está en uso para esta empresa.',
            'apellido.required' => 'El apellido es obligatorio.',
            'nombre.required' => 'El nombre es obligatorio.',
        ];
    }
}
