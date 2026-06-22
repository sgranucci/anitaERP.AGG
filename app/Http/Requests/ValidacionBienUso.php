<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ValidacionBienUso extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('id');

        return [
            'codigo_inventario' => [
                'nullable',
                'integer',
                'min:1',
                Rule::unique('bien_uso', 'codigo_inventario')->ignore($id),
            ],
            'hostname' => 'required|max:255',
            'ip' => 'nullable|max:45',
            'modelo' => 'nullable|max:255',
            'numero_serie' => 'nullable|max:100',
            'estado' => 'required|in:A,I',
            'centrocosto_id' => 'required|integer|exists:centrocosto,id',
            'tipo_bien' => 'required|in:I,M,P',
            'observaciones' => 'nullable|max:2000',
        ];
    }

    public function attributes(): array
    {
        return [
            'codigo_inventario' => 'código de inventario',
            'numero_serie' => 'número de serie',
            'centrocosto_id' => 'centro de costo',
            'tipo_bien' => 'tipo de bien',
        ];
    }
}
