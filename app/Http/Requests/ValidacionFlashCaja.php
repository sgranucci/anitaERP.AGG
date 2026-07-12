<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ValidacionFlashCaja extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('id');

        return [
            'empresa_id' => ['required', 'integer', 'min:1'],
            'fecha' => [
                'required',
                'date',
                Rule::unique('flash_caja', 'fecha')
                    ->where(fn ($q) => $q->where('empresa_id', (int) $this->input('empresa_id')))
                    ->ignore($id),
            ],
            'att' => ['nullable', 'integer', 'min:0'],
            'comentario' => ['nullable', 'string', 'max:30'],
            'cotizacion' => ['nullable', 'numeric', 'min:0'],
            'pos_online' => ['nullable', 'integer', 'min:0'],
            'show' => ['nullable', 'numeric'],
            'recalcular' => ['nullable', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'empresa_id' => 'empresa',
            'fecha' => 'fecha',
            'att' => 'asistencia',
            'comentario' => 'comentario',
        ];
    }
}
