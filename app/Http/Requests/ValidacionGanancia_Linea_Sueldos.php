<?php

namespace App\Http\Requests;

use App\Models\Sueldos\Ganancia_Linea_Sueldos;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ValidacionGanancia_Linea_Sueldos extends FormRequest
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
                'string',
                'max:40',
                Rule::unique('ganancia_linea_sueldos', 'codigo')->ignore($id),
            ],
            'descripcion' => 'required|string|max:80',
            'orden' => 'nullable|integer|min:0',
            'origen' => ['required', 'string', Rule::in(array_keys(Ganancia_Linea_Sueldos::ORIGENES))],
            'formula' => 'nullable|string',
            'deduccion_codigo' => 'nullable|string|max:30',
            'concepto_afip' => 'nullable|string|max:10',
            'activo' => 'nullable|boolean',
            'va_planilla' => 'nullable|boolean',
        ];
    }

    public function attributes()
    {
        return [
            'codigo' => 'código',
            'descripcion' => 'descripción',
            'orden' => 'orden',
            'origen' => 'origen',
            'formula' => 'fórmula',
            'deduccion_codigo' => 'código deducción',
            'concepto_afip' => 'concepto AFIP',
            'activo' => 'activo',
            'va_planilla' => 'va a planilla',
        ];
    }

    protected function prepareForValidation(): void
    {
        $merge = [
            'activo' => $this->has('activo') ? $this->boolean('activo') : true,
            'va_planilla' => $this->has('va_planilla') ? $this->boolean('va_planilla') : true,
        ];

        if ($this->has('codigo')) {
            $merge['codigo'] = strtoupper(trim((string) $this->input('codigo')));
        }

        if ($this->has('deduccion_codigo') && trim((string) $this->input('deduccion_codigo')) !== '') {
            $merge['deduccion_codigo'] = strtoupper(trim((string) $this->input('deduccion_codigo')));
        }

        $this->merge($merge);
    }
}
