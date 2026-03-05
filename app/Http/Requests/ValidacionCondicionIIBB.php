<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Rules\Configuracion\RuleCondicionIIBB;
use App\Models\Configuracion\CondicionIIBB;

class ValidacionCondicionIIBB extends FormRequest
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
            'nombre' => 'required|max:50|unique:condicionIIBB,nombre,' . $this->route('id'),
            'formacalculo' => ['required', new RuleCondicionIIBB(CondicionIIBB::$enumFormaCalculo)],
            'estado' => ['required', new RuleCondicionIIBB(CondicionIIBB::$enumEstado)],
        ];
    }
}

