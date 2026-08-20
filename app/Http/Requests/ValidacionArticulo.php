<?php

namespace App\Http\Requests;

use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Foundation\Http\FormRequest;

class ValidacionArticulo extends FormRequest
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
        if (! config('articulo.filtro_empresa')) {
            $this->request->remove('empresa_id');
        } else {
            $eid = $this->input('empresa_id');
            if ($eid === '' || $eid === null) {
                $this->merge(['empresa_id' => null]);
            }
        }

        $this->merge([
            'maneja_stock_color_talle' => $this->boolean('maneja_stock_color_talle'),
        ]);

        if (EntornoEmpresaSupport::esElBierzo()) {
            $sid = $this->input('codigosenasa_id');
            $this->merge([
                'codigosenasa_id' => ($sid === '' || $sid === null) ? null : (int) $sid,
            ]);
        } else {
            $this->request->remove('codigosenasa_id');
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $rules = [
            'sku' => 'required|max:20|unique:articulo,sku,' . $this->route('id'),
            'descripcion' => 'required|max:100|',
            'codigobarra' => 'nullable|max:50',
            'categoria_id' => 'required|numeric',
            'unidadmedida_id' => 'required|numeric',
            'usoarticulo_id' => 'required|numeric',
            'tipoproducto_id' => 'nullable|numeric|exists:tipoproducto,id',
            'capacidad_id' => 'nullable|numeric|exists:capacidad,id',
            'color_id' => 'nullable|numeric|exists:color,id',
            'tipoliquidofreno_id' => 'nullable|numeric|exists:tipoliquidofreno,id',
            'subrubro' => 'nullable|max:50',
            'lineamaterial' => 'nullable|max:50',
            'grupoproducto' => 'nullable|max:50',
            'codigo_interno_sifab' => 'nullable|integer',
            'rubro_sifab' => 'nullable|max:20',
            'clasematerial' => 'nullable|max:20',
            'gestioncompra' => 'nullable|max:20',
            'maneja_stock_color_talle' => 'nullable|boolean',
        ];

        if (config('articulo.filtro_empresa')) {
            $rules['empresa_id'] = ['nullable', 'integer', 'exists:empresa,id'];
        }

        if (EntornoEmpresaSupport::esElBierzo()) {
            $rules['codigosenasa_id'] = ['nullable', 'integer', 'exists:codigosenasa,id'];
        }

        return $rules;
    }
}
