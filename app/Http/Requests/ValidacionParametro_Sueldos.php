<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ValidacionParametro_Sueldos extends FormRequest
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
                Rule::unique('parametro_sueldos', 'codigo')->ignore($id),
            ],
            'descripcion' => 'required|string|max:120',
            'tipo' => ['required', 'string', Rule::in(['numero', 'texto'])],
            'unidad' => 'nullable|string|max:20',
            'activo' => 'nullable|boolean',
            'empresa_id' => 'nullable|exists:empresa,id',
            'valores' => 'nullable|array',
            'valores.*.fecha_vigencia' => 'required_with:valores|date',
            'valores.*.valor' => 'nullable|numeric',
            'valores.*.valor_texto' => 'nullable|string|max:120',
        ];
    }

    public function attributes()
    {
        return [
            'codigo' => 'código',
            'descripcion' => 'descripción',
            'tipo' => 'tipo',
            'unidad' => 'unidad',
            'activo' => 'activo',
            'valores.*.fecha_vigencia' => 'fecha de vigencia',
            'valores.*.valor' => 'valor numérico',
            'valores.*.valor_texto' => 'valor texto',
        ];
    }

    protected function prepareForValidation(): void
    {
        $merge = [
            'activo' => $this->has('activo') ? $this->boolean('activo') : true,
        ];

        if ($this->filled('codigo')) {
            $merge['codigo'] = strtoupper(trim((string) $this->input('codigo')));
        }

        $valores = $this->input('valores', []);
        if (is_array($valores)) {
            $valores = array_values(array_filter($valores, static function ($fila) {
                return is_array($fila) && trim((string) ($fila['fecha_vigencia'] ?? '')) !== '';
            }));
        } else {
            $valores = [];
        }
        $merge['valores'] = $valores === [] ? null : $valores;

        $this->merge($merge);
    }
}
