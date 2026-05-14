<?php

namespace App\Http\Requests;

use App\Models\Compras\Tiposervicio_Proveedor;
use App\Rules\Compras\RuleProveedor;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ValidacionProveedor extends FormRequest
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
        $nroInscripcionRules = ['required', new RuleProveedor('nroinscripcion')];
        if ($this->tipoServicioProveedorControlaUnicidadCuit()) {
            $nroInscripcionRules[] = Rule::unique('proveedor', 'nroinscripcion')
                ->ignore($this->route('id'))
                ->whereNull('deleted_at');
        }

        return [
            'nombre' => 'required|max:255|',
            'domicilio' => 'required|max:255|',
            'localidad_id' => ['integer', 'nullable'],
            'provincia_id' => 'required',
            'pais_id' => 'required',
            'condicioniva_id' => ['integer', 'nullable'],
            'condicionpago_id' => ['integer', 'nullable'],
            'cuentacontable_id' => 'required',
            'cuentacontableme_id' => 'required',
            'nroinscripcion' => $nroInscripcionRules,
            'retieneiva' => ['required', new RuleProveedor('retieneiva')],
            'nroIIBB' => 'sometimes|max:100|',
        ];
    }

    /**
     * Si el tipo de servicio del proveedor está configurado como NO CONTROLA, no se aplica unicidad de CUIT.
     */
    private function tipoServicioProveedorControlaUnicidadCuit(): bool
    {
        $tipoId = $this->input('tiposervicio_proveedor_id');
        if ($tipoId === null || $tipoId === '') {
            return true;
        }

        $tipo = Tiposervicio_Proveedor::query()->find((int) $tipoId);
        if ($tipo === null) {
            return true;
        }

        return $tipo->controla_unicidad_cuit !== Tiposervicio_Proveedor::UNICIDAD_CUIT_NO_CONTROLA;
    }
}
