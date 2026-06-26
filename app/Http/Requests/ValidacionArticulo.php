<?php

namespace App\Http\Requests;

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

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
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
        ];
    }
}
