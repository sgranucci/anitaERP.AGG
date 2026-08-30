<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Rules\Ventas\RuleCliente;
use App\Rules\Ventas\RuleClienteDocumentoUnico;
use App\Models\Ventas\Cliente;
use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\Configuracion\LocalidadProvinciaSupport;

class ValidacionCliente extends FormRequest
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

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $excluirClienteId = $this->route('id') ? (int) $this->route('id') : null;
        $reglaDocumentoUnico = new RuleClienteDocumentoUnico($excluirClienteId);

        if (config('app.empresa') == 'Calzados Ferli')
            return [
                'nombre' => 'required|max:255|',
                'domicilio' => 'required|max:255|',
                'descuento' => 'numeric|nullable|max:100',
                'localidad_id' => ['integer', 'nullable'],
                'provincia_id' => 'required',
                'provincia_iibb_id' => ['nullable', 'integer', 'exists:provincia,id'],
                'pais_id' => 'required',
                'zonavta_id' => ['integer', 'nullable'],
                'subzonavta_id' => ['integer', 'nullable'],
                'vendedor_id' => ['integer', 'nullable'],
                'cobrador_id' => ['integer', 'nullable'],
                'condicioniva_id' => ['integer', 'nullable'],
                'condicionventa_id' => ['integer', 'nullable'],
                'listaprecio_id' => ['integer', 'nullable'],
                'cuentacontable_id' => 'required',
                'numerodocumento' => ['required', new RuleCliente('numerodocumento'), $reglaDocumentoUnico],
                'retieneiva' => ['required', new RuleCliente('retieneiva')],
                'condicioniibb_id' => 'required',
                'vaweb' => ['required', new RuleCliente('vaweb')],
            ];
        else {
            $reglas = [
                'nombre' => 'required|max:255|',
                'domicilio' => 'required|max:255|',
                'descuento' => 'numeric|nullable|max:100',
                'localidad_id' => ['integer', 'nullable'],
                'provincia_id' => 'required',
                'provincia_iibb_id' => ['nullable', 'integer', 'exists:provincia,id'],
                'pais_id' => 'required',
                'zonavta_id' => ['integer', 'nullable'],
                'subzonavta_id' => ['integer', 'nullable'],
                'vendedor_id' => ['integer', 'nullable'],
                'cobrador_id' => ['integer', 'nullable'],
                'condicioniva_id' => ['integer', 'nullable'],
                'condicionventa_id' => ['integer', 'nullable'],
                'listaprecio_id' => ['integer', 'nullable'],
                'cuentacontable_id' => 'required',
                'numerodocumento' => ['required', new RuleCliente('numerodocumento'), $reglaDocumentoUnico],
                'retieneiva' => ['required', new RuleCliente('retieneiva')],
                'condicioniibb_id' => 'required',
            ];

            if (EntornoEmpresaSupport::esElBierzo()) {
                $reglas['emitecertificado'] = 'nullable|in:S,N,Emite Certificado,No Emite Certificado';
            }

            return $reglas;
        }
    }

    protected function prepareForValidation(): void
    {
        $localidadId = LocalidadProvinciaSupport::idConFallback(
            $this->input('localidad_id'),
            $this->input('localidad_id_previa')
        );
        if ($localidadId !== null) {
            $this->merge(['localidad_id' => $localidadId]);
        }

        $provinciaIibb = $this->input('provincia_iibb_id');
        if ($provinciaIibb === '' || $provinciaIibb === null || (int) $provinciaIibb <= 0) {
            $this->merge(['provincia_iibb_id' => null]);
        }
    }
}
