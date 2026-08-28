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

    /** @return array<string, string> */
    public function rules(): array
    {
        $rules = [];
        foreach (ParametroSistemaSupport::definiciones() as $clave => $def) {
            $rules['parametros.'.$clave] = $def['tipo'] === 'entero'
                ? 'required|integer|min:0'
                : 'required|numeric|min:0';
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
