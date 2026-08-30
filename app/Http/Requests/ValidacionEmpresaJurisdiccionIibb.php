<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ValidacionEmpresaJurisdiccionIibb extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, string> */
    public function rules(): array
    {
        return [
            'agentes' => 'nullable|array',
            'agentes.*' => 'array',
            'agentes.*.*' => 'array',
            'agentes.*.*.percepcion' => 'nullable',
            'agentes.*.*.retencion' => 'nullable',
        ];
    }
}
