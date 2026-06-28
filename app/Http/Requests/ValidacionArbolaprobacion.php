<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ValidacionArbolaprobacion extends FormRequest
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
            'nombre' => 'required|max:255|unique:arbolaprobacion,nombre,' . $this->route('id'),
            'empresa_id' => 'required',
            'estado' => 'required',
            'documento_estado_al_aprobar.*' => 'nullable|string|max:50',
            'oc_disparar_arbol_al_alta' => 'nullable|in:S,N',
            'oc_sector_cambio_centrocosto_id' => 'nullable|integer|exists:centrocosto,id',
            'oc_sector_disparo_aprobacion_id' => 'nullable|integer|exists:sector_legajocompra,id',
            'oc_sector_destino_aprobacion_id' => 'nullable|integer|exists:sector_legajocompra,id',
        ];
    }
}
