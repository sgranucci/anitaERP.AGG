<?php

namespace App\Http\Requests;

use App\Models\Compras\Concepto_Ivacompra;
use App\Models\Contable\Cuentacontable;
use App\Support\Configuracion\ParametroSistemaSupport;
use App\Support\Contable\CuentacontableArbolSupport;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            if (! in_array($def['tipo'] ?? '', ['cuentacaja', 'concepto_ivacompra', 'cuentacontable'], true)) {
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
                'cuentacontable' => [
                    'nullable',
                    'integer',
                    'min:1',
                    Rule::exists('cuentacontable', 'id'),
                    function (string $attribute, mixed $value, \Closure $fail): void {
                        if ((int) $value <= 0) {
                            return;
                        }
                        $cuenta = Cuentacontable::query()->find((int) $value);
                        if (! $cuenta) {
                            return;
                        }
                        if ((string) $cuenta->tipocuenta !== CuentacontableArbolSupport::TIPO_IMPUTABLE) {
                            $fail('La cuenta '.$cuenta->codigo.' no es imputable.');
                        }
                    },
                ],
                'concepto_ivacompra' => [
                    'nullable',
                    'integer',
                    'min:1',
                    Rule::exists('concepto_ivacompra', 'id'),
                    function (string $attribute, mixed $value, \Closure $fail): void {
                        if ((int) $value <= 0) {
                            return;
                        }
                        $concepto = Concepto_Ivacompra::query()->find((int) $value);
                        if (! $concepto) {
                            return;
                        }
                        $retiene = strtoupper(trim((string) ($concepto->retieneganancia ?? 'N'))) === 'S'
                            || strtoupper(trim((string) ($concepto->retieneIIBB ?? 'N'))) === 'S';
                        if ($retiene) {
                            $fail('El concepto '.$concepto->codigo.' retiene ganancias o ingresos brutos. Elegí uno que no retenga.');
                        }
                    },
                ],
                'boolean' => 'required|in:0,1',
                'select' => 'required|in:'.implode(',', array_keys($def['opciones'] ?? [])),
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
