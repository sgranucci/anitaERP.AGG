<?php

namespace App\Http\Requests;

use App\Support\Caja\PosicionFinancieraOrdenConceptoSupport;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ValidacionPosicionFinancieraOrdenConceptos extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'filas' => ['required', 'array', 'min:1'],
            'filas.*.uso' => ['required', 'string', Rule::in(array_keys(PosicionFinancieraOrdenConceptoSupport::usosHerramienta()))],
            'filas.*.orden' => ['required', 'integer', 'min:0', 'max:9999'],
            'filas.*.ids' => ['required', 'array', 'min:1'],
            'filas.*.ids.*' => ['integer', 'exists:cuentacaja,id'],
        ];
    }

    public function attributes(): array
    {
        return [
            'filas' => 'conceptos',
            'filas.*.orden' => 'orden',
            'filas.*.ids' => 'cuentas',
        ];
    }
}
