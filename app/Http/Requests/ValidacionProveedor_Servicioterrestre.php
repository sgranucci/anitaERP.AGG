<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ValidacionProveedor_Servicioterrestre extends FormRequest
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
            'servicioterrestre_id' => [
                                        'required',
                                        Rule::unique('proveedor_servicioterrestre')->ignore($this->id)->where(function ($query) {
                                            return $query->where('proveedor_id', $this->get('proveedor_id'));
                                        }),
                                    ]
        ];
    }
}
