<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ValidacionPercepcionNoCategorizado extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'habilitado' => ['nullable', 'boolean'],
            'tasa' => ['required', 'numeric', 'min:0', 'max:100'],
            'minimo' => ['required', 'numeric', 'min:0'],
        ];
    }

    public function attributes(): array
    {
        return [
            'habilitado' => 'agente de percepción no categorizado',
            'tasa' => 'alícuota RG 2126',
            'minimo' => 'mínimo de percepción',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'habilitado' => $this->boolean('habilitado'),
        ]);
    }
}
