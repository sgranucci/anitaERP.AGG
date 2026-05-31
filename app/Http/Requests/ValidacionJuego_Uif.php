<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ValidacionJuego_Uif  extends FormRequest
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
        $this->merge([
            'riesgo' => $this->filled('riesgo') ? $this->riesgo : null,
            'puntaje' => $this->filled('puntaje') ? $this->puntaje : null,
        ]);
    }

    public function rules()
    {
        return [
            'nombre' => 'required|max:255|unique:juego_uif,nombre,' . $this->route('id'),
            'riesgo' => 'nullable|max:50',
            'puntaje' => 'nullable|integer|min:0',
        ];
    }
}
