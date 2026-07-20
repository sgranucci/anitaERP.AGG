<?php

namespace App\Http\Requests;

use App\Models\Sueldos\Tipo_Ausencia_Sueldos;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ValidacionTipo_Ausencia_Sueldos extends FormRequest
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
                Rule::unique('tipo_ausencia_sueldos', 'codigo')->ignore($id),
            ],
            'nombre' => 'required|string|max:60',
            'categoria' => ['required', Rule::in(array_keys(Tipo_Ausencia_Sueldos::CATEGORIAS))],
            'tipo_dias' => ['required', Rule::in(['corridos', 'habiles'])],
            'tope_dias_anio' => 'nullable|integer|min:0|max:9999',
            'concepto_id' => 'nullable|integer|exists:concepto_sueldos,id',
            'color' => 'nullable|string|max:9',
            'orden' => 'nullable|integer|min:0',
            'afecta_saldo_vacaciones' => 'nullable|boolean',
            'goza_sueldo' => 'nullable|boolean',
            'computa_antiguedad' => 'nullable|boolean',
            'requiere_certificado' => 'nullable|boolean',
            'activo' => 'nullable|boolean',
        ];
    }

    public function attributes()
    {
        return [
            'codigo' => 'código',
            'nombre' => 'nombre',
            'categoria' => 'categoría',
            'tipo_dias' => 'tipo de días',
            'tope_dias_anio' => 'tope de días por año',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'afecta_saldo_vacaciones' => $this->boolean('afecta_saldo_vacaciones'),
            'goza_sueldo' => $this->boolean('goza_sueldo'),
            'computa_antiguedad' => $this->boolean('computa_antiguedad'),
            'requiere_certificado' => $this->boolean('requiere_certificado'),
            'activo' => $this->has('activo') ? $this->boolean('activo') : true,
        ]);
    }
}
