<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ValidacionWaitryMesaGastronomia extends FormRequest
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
            'servicio' => 'required|max:40',
            'nombre' => 'required|max:80',
            'table_id' => [
                'required',
                'integer',
                'min:1',
                Rule::unique('waitry_mesa_gastronomia', 'table_id')
                    ->where(fn ($q) => $q->where('empresa_id', $empresaId))
                    ->ignore($id),
            ],
            'activo' => 'required|in:0,1',
        ];
    }

    public function attributes()
    {
        return [
            'servicio' => 'servicio',
            'nombre' => 'nombre de la mesa',
            'table_id' => 'tableId de Waitry',
            'activo' => 'activa',
        ];
    }
}
