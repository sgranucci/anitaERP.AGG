<?php

namespace App\Http\Requests;

use App\Support\Stock\ArticuloNofacturaSupport;
use Illuminate\Foundation\Http\FormRequest;

class ValidacionArticuloContaduria extends FormRequest
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

    protected function prepareForValidation(): void
    {
        if ($this->has('nofactura')) {
            $this->merge([
                'nofactura' => ArticuloNofacturaSupport::normalizar($this->input('nofactura')),
            ]);
        }

        $nomenclador = trim((string) $this->input('nomenclador', ''));
        if (strtoupper($nomenclador) === 'NULL') {
            $this->merge(['nomenclador' => '']);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'sku' => 'required|max:20|unique:articulo,sku,'.$this->route('id'),
            'cuentacontableventa_id' => ['required', 'integer', 'min:1'],
            'impuesto_id' => ['required', 'integer', 'min:1'],
            'nomenclador' => ['required', 'string', 'max:6', 'not_in:NULL,null,Null'],
            'nofactura' => ['required', 'in:0,1'],
        ];
    }

    public function messages()
    {
        return [
            'cuentacontableventa_id.min' => 'Seleccione una cuenta contable de venta válida.',
            'impuesto_id.min' => 'Seleccione un impuesto aplicado.',
            'nomenclador.not_in' => 'Indique un nomenclador válido.',
            'nofactura.required' => 'Indique si el artículo es facturable.',
            'nofactura.in' => 'El valor de facturable no es válido.',
        ];
    }
}
