<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ValidacionPrenda_Sueldos extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        $id = $this->route('id');

        return [
            'codigo' => [
                'nullable',
                'integer',
                'min:1',
                Rule::unique('prenda_sueldos', 'codigo')->ignore($id),
            ],
            'descripcion' => 'required|string|max:60',
            'marca' => 'nullable|string|max:30',
            'porcentaje_pedido' => 'nullable|numeric|min:0|max:999999',
            'orden' => 'nullable|integer|min:0',
            'vida_util_meses' => 'nullable|integer|min:0|max:600',
            'norma' => 'nullable|string|max:80',
            'es_seguridad' => 'nullable|boolean',
            'requiere_certificacion' => 'nullable|boolean',
            'activo' => 'nullable|boolean',
            'variantes' => 'nullable|array',
            'variantes.*.color_id' => 'nullable|integer|exists:color,id',
            'variantes.*.talle_id' => 'nullable|integer|exists:talle,id',
            'variantes.*.sku' => 'nullable|string|max:20',
        ];
    }

    public function attributes()
    {
        return [
            'codigo' => 'código',
            'descripcion' => 'descripción',
            'porcentaje_pedido' => 'porcentaje de pedido',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'es_seguridad' => $this->boolean('es_seguridad'),
            'requiere_certificacion' => $this->boolean('requiere_certificacion'),
            'activo' => $this->has('activo') ? $this->boolean('activo') : true,
        ]);
    }
}
