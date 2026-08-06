<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ValidacionAntiguedad_Tabla_Sueldos extends FormRequest
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
                'required',
                'integer',
                'min:1',
                'max:99',
                Rule::unique('antiguedad_tabla_sueldos', 'codigo')
                    ->where(fn ($q) => $q->whereNull('empresa_id'))
                    ->ignore($id),
            ],
            'descripcion' => 'required|string|max:80',
            'activo' => 'nullable|boolean',
            'empresa_id' => 'nullable|exists:empresa,id',
            'tramos' => 'nullable|array',
            'tramos.*.anio' => 'required_with:tramos|integer|min:1|max:80',
            'tramos.*.porcentaje' => 'nullable|numeric',
            'tramos.*.cantidad' => 'nullable|numeric',
            'tramos.*.nro_linea' => 'nullable|integer|min:1',
        ];
    }

    public function attributes()
    {
        return [
            'codigo' => 'código',
            'descripcion' => 'descripción',
            'tramos.*.anio' => 'años',
            'tramos.*.porcentaje' => 'porcentaje',
            'tramos.*.cantidad' => 'cantidad',
        ];
    }

    protected function prepareForValidation(): void
    {
        $tramos = $this->input('tramos', []);
        if (is_array($tramos)) {
            $tramos = array_values(array_filter($tramos, static function ($fila) {
                return is_array($fila) && (int) ($fila['anio'] ?? 0) > 0;
            }));
        } else {
            $tramos = [];
        }

        $this->merge([
            'activo' => $this->has('activo') ? $this->boolean('activo') : true,
            'tramos' => $tramos === [] ? null : $tramos,
        ]);
    }
}
