<?php

namespace App\Http\Requests;

use App\Support\Sueldos\VacacionTipoDia;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ValidacionVacacion_Sueldos extends FormRequest
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
                Rule::unique('vacacion_sueldos', 'codigo')->ignore($id),
            ],
            'descripcion' => 'required|string|max:30',
            'nro_linea' => 'nullable|array',
            'nro_linea.*' => 'nullable|integer|min:0',
            'anios_desde' => 'nullable|array',
            'anios_desde.*' => 'nullable|integer|min:0|max:99',
            'anios_hasta' => 'nullable|array',
            'anios_hasta.*' => 'nullable|integer|min:0|max:99',
            'tipo_dia' => 'nullable|array',
            'tipo_dia.*' => ['nullable', 'string', 'max:20', Rule::in(VacacionTipoDia::valoresPermitidos())],
            'cantidad_dias' => 'nullable|array',
            'cantidad_dias.*' => 'nullable|integer|min:0|max:9999',
        ];
    }

    public function attributes()
    {
        return [
            'codigo' => 'código',
            'descripcion' => 'descripción',
            'nro_linea' => 'nº línea',
            'anios_desde' => 'antigüedad desde (años)',
            'anios_hasta' => 'antigüedad hasta (años)',
            'tipo_dia' => 'tipo de día',
            'cantidad_dias' => 'cantidad de días',
        ];
    }

    protected function prepareForValidation(): void
    {
        $tipos = $this->input('tipo_dia', []);
        if (! is_array($tipos)) {
            return;
        }

        $normalizados = [];
        foreach ($tipos as $tipo) {
            $normalizados[] = VacacionTipoDia::normalizar((string) $tipo);
        }
        $this->merge(['tipo_dia' => $normalizados]);
    }
}
