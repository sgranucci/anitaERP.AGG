<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ValidacionArticuloContaduria extends FormRequest
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
            'cuentacontableventa_id' => ['required','integer'],
            'impuesto_id' => ['numeric','required'],
            'nomenclador' => ['string','max:6','required']
        ];
    }
}
