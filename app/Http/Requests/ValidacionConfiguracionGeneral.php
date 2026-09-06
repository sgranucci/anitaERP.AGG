<?php

namespace App\Http\Requests;

use App\Support\Configuracion\ParametroSistemaSupport;
use Illuminate\Foundation\Http\FormRequest;

class ValidacionConfiguracionGeneral extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $parametros = $this->input('parametros', []);
        if (! is_array($parametros)) {
            return;
        }
        foreach (ParametroSistemaSupport::definiciones() as $clave => $def) {
            if (($def['tipo'] ?? '') !== 'cuentacaja') {
                continue;
            }
            if (! array_key_exists($clave, $parametros)) {
                continue;
            }
            $valor = $parametros[$clave];
            if ($valor === '' || $valor === null || (int) $valor <= 0) {
                $parametros[$clave] = null;
            }
        }
        $this->merge(['parametros' => $parametros]);
    }

    /** @return array<string, string> */
    public function rules(): array
    {
        $rules = [];
        foreach (ParametroSistemaSupport::definiciones() as $clave => $def) {
            $rules['parametros.'.$clave] = match ($def['tipo']) {
                'entero' => 'required|integer|min:0',
                'cuentacaja' => 'nullable|integer|min:1|exists:cuentacaja,id',
                'boolean' => 'required|in:0,1',
                default => 'required|numeric|min:0',
            };
        }

        return $rules;
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        $attrs = [];
        foreach (ParametroSistemaSupport::definiciones() as $clave => $def) {
            $attrs['parametros.'.$clave] = $def['etiqueta'];
        }

        return $attrs;
    }
}
