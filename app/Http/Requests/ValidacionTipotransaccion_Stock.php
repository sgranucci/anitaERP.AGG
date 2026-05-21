<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ValidacionTipotransaccion_Stock extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'nombre' => 'required|max:255|unique:tipotransaccion_stock,nombre,'.$this->route('id'),
            'abreviatura' => 'required|max:5|unique:tipotransaccion_stock,abreviatura,'.$this->route('id'),
            'operacion' => ['required', Rule::in(array_keys(\App\Traits\Stock\Tipotransaccion_StockTrait::$enumOperacion))],
        ];
    }
}
