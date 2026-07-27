<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ValidacionCliente_Premio_Uif extends FormRequest
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
            'cliente_uif_id' => 'required|integer|exists:cliente_uif,id',
            'sala_id' => 'required|integer|exists:sala,id',
            'juego_uif_id' => 'required|integer|exists:juego_uif,id',
            'moneda_id' => 'required|integer|exists:moneda,id',
            'monto' => 'required|numeric|min:0.01',
            'fechaentrega' => 'required|date',
            'formapago_id' => 'nullable|integer|exists:formapago,id',
            'fechatito' => 'nullable|date',
        ];
    }

    public function messages()
    {
        return [
            'cliente_uif_id.required' => 'Falta el cliente UIF del premio.',
            'cliente_uif_id.exists' => 'El cliente UIF no existe.',
            'sala_id.required' => 'Debe seleccionar la sala.',
            'juego_uif_id.required' => 'Debe seleccionar el juego.',
            'moneda_id.required' => 'Debe seleccionar la moneda.',
            'monto.required' => 'Debe ingresar el monto del premio.',
            'monto.min' => 'El monto del premio debe ser mayor a cero.',
            'fechaentrega.required' => 'Debe ingresar la fecha de entrega del premio.',
            'fechaentrega.date' => 'La fecha de entrega no es válida.',
        ];
    }

    protected function prepareForValidation()
    {
        $fechatito = $this->input('fechatito');
        if ($fechatito === '' || $fechatito === null) {
            $this->merge(['fechatito' => null]);
        }
    }
}
