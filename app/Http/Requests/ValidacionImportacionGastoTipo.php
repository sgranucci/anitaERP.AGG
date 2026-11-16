<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Support\Compras\ImportacionGastoTratamiento;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ValidacionImportacionGastoTipo extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $id = (int) $this->route('id');

        return [
            'codigo' => [
                'required',
                'string',
                'max:30',
                Rule::unique('importacion_gasto_tipo', 'codigo')->ignore($id),
            ],
            'nombre' => 'required|string|max:120',
            'tratamiento' => ['required', Rule::in(array_keys(ImportacionGastoTratamiento::ETIQUETAS))],
            'base_prorrateo' => ['required', Rule::in(array_keys(ImportacionGastoTratamiento::BASES))],
            'cuentacontable_id' => 'nullable|integer|exists:cuentacontable,id',
            'activo' => 'nullable|boolean',
            'orden' => 'nullable|integer|min:0|max:9999',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'activo' => $this->boolean('activo', true),
        ]);
    }
}
