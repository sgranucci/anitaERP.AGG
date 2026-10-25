<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ValidacionTesoreriaCrownCotizacion extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'sala_id' => ['required', 'integer', 'exists:sala,id'],
            'jornada' => ['required', 'date'],
            'compra' => ['required', 'array'],
            'compra.*' => ['nullable', 'numeric', 'min:0'],
            'venta' => ['required', 'array'],
            'venta.*' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function attributes(): array
    {
        return [
            'sala_id' => 'sala',
            'jornada' => 'jornada',
            'compra' => 'compra',
            'venta' => 'venta',
        ];
    }
}
