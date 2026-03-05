<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ValidacionLote extends FormRequest
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
            'numerodespacho' => 'required|max:255|unique:lote,numerodespacho,' . $this->route('id'),
            'pais_id' => 'required|integer',
            'fechaingreso' => 'required',
        ];
    }
}
