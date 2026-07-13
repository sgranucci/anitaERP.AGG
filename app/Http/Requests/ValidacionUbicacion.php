<?php

namespace App\Http\Requests;

use App\Support\Stock\InterformingSifabSupport;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ValidacionUbicacion extends FormRequest
{
    public function authorize(): bool
    {
        return InterformingSifabSupport::esInterforming();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $id = $this->route('id');

        return [
            'codigo' => [
                'required',
                'max:6',
                Rule::unique('ubicacion', 'codigo')->ignore($id),
            ],
            'nombre' => 'required|max:30',
            'zona' => 'nullable|max:6',
            'area' => 'nullable|max:6',
            'nivel' => 'nullable|max:6',
            'estado' => ['nullable', Rule::in([' ', 'I', 'A'])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'codigo' => 'código',
            'nombre' => 'descripción',
            'zona' => 'zona / pasillo',
            'area' => 'área',
            'nivel' => 'nivel',
        ];
    }
}
