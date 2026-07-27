<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ValidacionRequisicionSalaReabrir extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'motivo' => 'required|string|min:5|max:500',
        ];
    }

    public function messages(): array
    {
        return [
            'motivo.required' => 'Indicá el motivo de la reapertura.',
            'motivo.min' => 'El motivo debe tener al menos 5 caracteres.',
        ];
    }
}
