<?php

namespace App\Http\Requests;

use App\Support\Contable\CuentaAutomaticaClaves;
use Illuminate\Foundation\Http\FormRequest;
class ValidacionContabilidadCuentaAutomatica extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'empresa_id' => 'required|integer|exists:empresa,id',
            'cuentas' => 'nullable|array',
            'cuentas.*' => 'nullable|integer|exists:cuentacontable,id',
        ];
    }

    protected function prepareForValidation(): void
    {
        $cuentas = $this->input('cuentas', []);
        if (! is_array($cuentas)) {
            return;
        }

        $normalizado = [];
        foreach ($cuentas as $clave => $valor) {
            if (! is_string($clave) || ! in_array($clave, CuentaAutomaticaClaves::todasLasClaves(), true)) {
                continue;
            }
            if ($valor === null || $valor === '') {
                $normalizado[$clave] = null;
                continue;
            }
            $normalizado[$clave] = (int) $valor;
        }

        $this->merge(['cuentas' => $normalizado]);
    }
}
