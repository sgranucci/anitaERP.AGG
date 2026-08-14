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
            'cuentas_multiples' => 'nullable|array',
            'cuentas_multiples.*' => 'nullable|array',
            'cuentas_multiples.*.*' => 'nullable|integer|exists:cuentacontable,id',
        ];
    }

    protected function prepareForValidation(): void
    {
        $cuentas = $this->input('cuentas', []);
        $normalizado = [];
        if (is_array($cuentas)) {
            foreach ($cuentas as $clave => $valor) {
                if (! is_string($clave) || ! in_array($clave, CuentaAutomaticaClaves::todasLasClaves(), true)) {
                    continue;
                }
                if (CuentaAutomaticaClaves::esMultiple($clave)) {
                    continue;
                }
                if ($valor === null || $valor === '') {
                    $normalizado[$clave] = null;
                    continue;
                }
                $normalizado[$clave] = (int) $valor;
            }
        }

        $multiples = $this->input('cuentas_multiples', []);
        $multiNormalizado = [];
        if (is_array($multiples)) {
            foreach ($multiples as $clave => $valores) {
                if (! is_string($clave) || ! CuentaAutomaticaClaves::esMultiple($clave)) {
                    continue;
                }
                if (! is_array($valores)) {
                    $multiNormalizado[$clave] = [];
                    continue;
                }
                $ids = [];
                foreach ($valores as $valor) {
                    if ($valor === null || $valor === '') {
                        continue;
                    }
                    $id = (int) $valor;
                    if ($id > 0) {
                        $ids[$id] = $id;
                    }
                }
                $multiNormalizado[$clave] = array_values($ids);
            }
        }

        foreach (CuentaAutomaticaClaves::clavesMultiples() as $clave) {
            if (! array_key_exists($clave, $multiNormalizado)) {
                $multiNormalizado[$clave] = [];
            }
        }

        $this->merge([
            'cuentas' => $normalizado,
            'cuentas_multiples' => $multiNormalizado,
        ]);
    }
}
