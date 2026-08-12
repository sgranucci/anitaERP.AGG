<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ValidacionConcepto_Ivacompra extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        $id = $this->route('id');

        return [
            'nombre' => [
                'required',
                'max:255',
                Rule::unique('concepto_ivacompra', 'nombre')->ignore($id),
            ],
            'nombre_ia' => 'required|max:255',
            'codigo' => [
                'required',
                'max:10',
                Rule::unique('concepto_ivacompra', 'codigo')->ignore($id),
            ],
            'formula' => 'nullable|max:255',
            'columna_ivacompra_id' => 'nullable|integer|exists:columna_ivacompra,id',
            'tipoconcepto' => 'required|string|size:1',
            'retieneganancia' => 'required|in:S,N',
            'retieneIIBB' => 'required|in:S,N',
            'provincia_id' => 'nullable|integer|exists:provincia,id',
            'impuesto_id' => 'nullable|integer|exists:impuesto,id',
            'empresa_ids' => 'nullable|array',
            'empresa_ids.*' => 'nullable|integer|exists:empresa,id',
            'cuentacontabledebe_ids' => 'nullable|array',
            'cuentacontabledebe_ids.*' => 'nullable|integer|exists:cuentacontable,id',
            'cuentacontablehaber_ids' => 'nullable|array',
            'cuentacontablehaber_ids.*' => 'nullable|integer|exists:cuentacontable,id',
            'condicioniva_ids' => 'nullable|array',
            'condicioniva_ids.*' => 'nullable|integer|exists:condicioniva,id',
        ];
    }

    public function messages()
    {
        return [
            'nombre.unique' => 'Ya existe un concepto con ese nombre.',
            'codigo.unique' => 'Ya existe un concepto con ese código Anita.',
        ];
    }
}
