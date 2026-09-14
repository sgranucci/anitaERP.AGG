<?php

namespace App\Http\Requests;

use App\Support\Configuracion\PercepcionIibbPrioridadAlicuotaSupport;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ValidacionProvincia extends FormRequest
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
    protected function prepareForValidation(): void
    {
        $tope = $this->input('tope_alicuota_percepcion');
        if ($tope === '' || $tope === null) {
            $this->merge(['tope_alicuota_percepcion' => null]);
        }

        $prioridad = $this->input('prioridad_alicuota_percepcion');
        if ($prioridad === null || $prioridad === '') {
            $this->merge([
                'prioridad_alicuota_percepcion' => PercepcionIibbPrioridadAlicuotaSupport::PADRON_DESCARTE,
            ]);
        }
    }

    public function rules()
    {
        return [
            'nombre' => 'required|max:255|unique:provincia,nombre,' . $this->route('id'),
            'abreviatura' => 'sometimes|max:10' ,
            'jurisdiccion' => 'sometimes|max:50' ,
            'codigo' => 'sometimes|max:50' ,
            'pais_id' => 'required|integer',
            'tope_alicuota_percepcion' => 'nullable|numeric|min:0|max:100',
            'prioridad_alicuota_percepcion' => [
                'required',
                Rule::in(PercepcionIibbPrioridadAlicuotaSupport::valores()),
            ],
        ];
    }
}
