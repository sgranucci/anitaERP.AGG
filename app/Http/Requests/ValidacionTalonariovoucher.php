<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ValidacionTalonariovoucher extends FormRequest
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
            'nombre' => 'required|max:255|unique:talonariovoucher,nombre,' . $this->route('id'),
            'origenvoucher_id' => 'required',
            'desdenumero' => 'required',
            'hastanumero' => 'required',
            'fechacierre' => [Rule::RequiredIf($this->fechainicio < $this->fechacierre)],
            //'nroinscripcion' => ['required', new RuleCliente('nroinscripcion')],
        ];
    }
}
