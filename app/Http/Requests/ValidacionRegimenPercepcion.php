<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ValidacionRegimenPercepcion extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('id');

        return [
            'codigo' => [
                'required',
                'string',
                'max:20',
                Rule::unique('regimen_percepcion', 'codigo')->ignore($id),
            ],
            'nombre' => ['required', 'string', 'max:80'],
            'habilitado' => ['nullable', 'boolean'],
            'tasa' => ['required', 'numeric', 'min:0', 'max:100'],
            'minimo_base' => ['required', 'numeric', 'min:0'],
            'minimo_importe' => ['required', 'numeric', 'min:0'],
            'vigencia_desde' => ['nullable', 'date'],
            'vigencia_hasta' => ['nullable', 'date', 'after_or_equal:vigencia_desde'],
            'empresa_ids' => ['nullable', 'array'],
            'empresa_ids.*' => ['nullable', 'integer'],
            'cuentacontable_ids' => ['nullable', 'array'],
            'cuentacontable_ids.*' => ['nullable', 'integer'],
            'creousuario_cuentacontable_ids' => ['nullable', 'array'],
            'creousuario_cuentacontable_ids.*' => ['nullable', 'integer'],
        ];
    }

    public function attributes(): array
    {
        return [
            'codigo' => 'código',
            'nombre' => 'nombre',
            'habilitado' => 'agente de percepción',
            'tasa' => 'alícuota',
            'minimo_base' => 'mínimo de gravado',
            'minimo_importe' => 'mínimo de percepción',
            'vigencia_desde' => 'vigencia desde',
            'vigencia_hasta' => 'vigencia hasta',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'codigo' => strtoupper(trim((string) $this->input('codigo', ''))),
            'habilitado' => $this->boolean('habilitado'),
        ]);
    }
}
