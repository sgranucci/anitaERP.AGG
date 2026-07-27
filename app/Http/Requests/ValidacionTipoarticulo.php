<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ValidacionTipoarticulo extends FormRequest
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
            'nombre' => 'required|max:50|unique:tipoarticulo,nombre,' . $this->route('id'),
            'abreviatura' => 'required|max:10|unique:tipoarticulo,abreviatura,' . $this->route('id'),
            'usa_control_contable_cigarrillos' => 'sometimes|boolean',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'usa_control_contable_cigarrillos' => $this->boolean('usa_control_contable_cigarrillos'),
        ]);
    }
}
