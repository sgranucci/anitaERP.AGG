<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ValidacionMotivoDevolucion extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $orden = $this->input('orden');
        if ($orden === '' || $orden === null) {
            $this->merge(['orden' => 0]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $id = (int) $this->route('id');

        return [
            'codigo' => [
                'required',
                'string',
                'max:40',
                Rule::unique('motivo_devolucion', 'codigo')->ignore($id),
            ],
            'nombre' => 'required|string|max:120',
            'vuelve_stock' => 'nullable|boolean',
            'activo' => 'nullable|boolean',
            'orden' => 'nullable|integer|min:0|max:9999',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function datos(): array
    {
        $data = $this->validated();

        return [
            'codigo' => trim((string) $data['codigo']),
            'nombre' => trim((string) $data['nombre']),
            'vuelve_stock' => $this->boolean('vuelve_stock'),
            'activo' => $this->boolean('activo'),
            'orden' => (int) ($data['orden'] ?? 0),
        ];
    }
}
