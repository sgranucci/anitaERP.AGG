<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ValidacionMarketplace extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'codigo' => (int) preg_replace('/\D+/', '', (string) $this->input('codigo', '')),
            'nombre' => trim((string) $this->input('nombre', '')),
            'activo' => $this->boolean('activo'),
        ]);
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
                'integer',
                'min:1',
                'max:4294967295',
                Rule::unique('marketplace', 'codigo')->ignore($id),
            ],
            'nombre' => 'required|string|max:60',
            'activo' => 'boolean',
        ];
    }

    /**
     * @return array{codigo:int,nombre:string,activo:bool}
     */
    public function datos(): array
    {
        $validado = $this->validated();

        return [
            'codigo' => (int) $validado['codigo'],
            'nombre' => (string) $validado['nombre'],
            'activo' => (bool) ($validado['activo'] ?? false),
        ];
    }
}
