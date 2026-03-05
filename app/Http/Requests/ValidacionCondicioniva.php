<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Rules\Configuracion\RuleCondicioniva;
use App\Models\Configuracion\Condicioniva;

class ValidacionCondicioniva extends FormRequest
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
            'nombre' => 'required|max:50|unique:condicioniva,nombre,' . $this->route('id'),
            'letra' => ['required', new RuleCondicioniva(Condicioniva::$enumLetra)],
            'coniva' => ['required', new RuleCondicioniva(Condicioniva::$enumIva)],
            'coniibb' => ['required', new RuleCondicioniva(Condicioniva::$enumIibb)],
        ];
    }
}

