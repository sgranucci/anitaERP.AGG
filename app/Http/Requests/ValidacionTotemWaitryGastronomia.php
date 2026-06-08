<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ValidacionTotemWaitryGastronomia extends FormRequest
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
            'ubicacion_id' => [
                'required',
                Rule::exists('ubicaciones_gastronomia', 'id')->where(fn ($q) => $q->where('empresa_id', $empresaId)),
                Rule::unique('totem_waitry_gastronomia', 'ubicacion_id')
                    ->ignore($id)
                    ->where(fn ($q) => $q->where('empresa_id', $empresaId)),
            ],
            'waitry_layout_id' => [
                'nullable',
                'integer',
                'min:1',
                Rule::unique('totem_waitry_gastronomia', 'waitry_layout_id')
                    ->ignore($id)
                    ->where(fn ($q) => $q->where('empresa_id', $empresaId)->whereNotNull('waitry_layout_id')),
            ],
            'waitry_layout_ids_adicionales' => ['nullable', 'string', 'max:255', 'regex:/^(\d+\s*(,\s*\d+)*)?$/'],
            'waitry_table_id' => 'nullable|integer|min:1',
            'waitry_table_ids_adicionales' => ['nullable', 'string', 'max:255', 'regex:/^(\d+\s*(,\s*\d+)*)?$/'],
            'informe_z_habilitado' => 'nullable|boolean',
            'detalle' => 'nullable|string|max:2000',
        ];
    }

    public function messages()
    {
        return [
            'ubicacion_id.unique' => 'Ya existe un tótem Waitry registrado para esta ubicación y empresa.',
            'ubicacion_id.exists' => 'La ubicación no existe o no pertenece a la empresa seleccionada.',
            'waitry_layout_id.unique' => 'Ya hay un tótem con ese Layout ID Waitry (punto de acceso) en la empresa.',
        ];
    }
}
