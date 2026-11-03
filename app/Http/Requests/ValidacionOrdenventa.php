<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Rules\Ventas\RuleCliente;
use App\Models\Ventas\Ordenventa;

class ValidacionOrdenventa extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    protected function prepareForValidation()
    {
        $this->merge([
            'subtotal' => $this->normalizaImporte($this->input('subtotal', $this->input('monto', 0))),
            'percepcion' => $this->normalizaImporte($this->input('percepcion', 0)),
            'iva' => $this->normalizaImporte($this->input('iva', 0)),
        ]);
    }

    public function rules()
    {
        return [
            'localidad_id' => ['integer', 'nullable'],
            'provincia_id' => ['integer', 'nullable'],
            'pais_id' => ['integer', 'nullable'],
            'condicionventa_id' => ['integer', 'nullable'],
            'nroinscripcion' => [new RuleCliente('nroinscripcion')],
            'subtotal' => 'required|numeric',
            'percepcion' => 'nullable|numeric',
            'iva' => 'nullable|numeric',
        ];
    }

    private function normalizaImporte($valor): string
    {
        $texto = trim((string) ($valor ?? '0'));
        $texto = str_replace(' ', '', $texto);
        if ($texto === '') {
            return '0';
        }
        if (str_contains($texto, ',') && str_contains($texto, '.')) {
            $texto = str_replace('.', '', $texto);
            $texto = str_replace(',', '.', $texto);
        } else {
            $texto = str_replace(',', '.', $texto);
        }

        return $texto;
    }
}
