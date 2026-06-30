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
        $tipoBien = (string) $this->input('tipo_bien', '');
        $empresaId = $this->input('empresa_id');

        return [
            'empresa_id' => [
                Rule::requiredIf(in_array($tipoBien, ['M', 'I'], true)),
                'nullable',
                'integer',
                'exists:empresa,id',
            ],
            'codigo_inventario' => [
                'nullable',
                'integer',
                'min:1',
                Rule::unique('bien_uso', 'codigo_inventario')
                    ->where(static fn ($q) => $empresaId
                        ? $q->where('empresa_id', (int) $empresaId)
                        : $q->whereNull('empresa_id'))
                    ->ignore($id),
            ],
            'uid' => [
                Rule::requiredIf($tipoBien === 'M'),
                'nullable',
                'max:20',
                Rule::unique('bien_uso', 'uid')->ignore($id),
            ],
            'hostname' => [
                Rule::requiredIf($tipoBien === 'P'),
                'nullable',
                'max:255',
            ],
            'ip' => 'nullable|max:45',
            'modelo' => 'nullable|max:255',
            'vendor' => 'nullable|max:255',
            'tema' => 'nullable|max:255',
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
            'empresa_id' => 'empresa',
            'codigo_inventario' => 'código de inventario',
            'uid' => 'UID',
            'numero_serie' => 'número de serie',
            'centrocosto_id' => 'centro de costo',
            'tipo_bien' => 'tipo de bien',
            'vendor' => 'fabricante / vendor',
            'tema' => 'tema / juego',
        ];
    }
}
