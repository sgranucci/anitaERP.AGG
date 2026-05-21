<?php

namespace App\Http\Requests;

use App\Models\Ventas\Puntoventa;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ValidacionPuntoventa extends FormRequest
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
        $id = $this->route('id');

        return [
            'nombre' => 'required|max:255|unique:puntoventa,nombre,'.$id,
            'codigo' => [
                'required',
                'max:50',
                Rule::unique('puntoventa', 'codigo')->where(fn ($q) => $q->where('empresa_id', $this->empresa_id))->ignore($id),
            ],
            'empresa_id' => 'required',
            'webservice' => ['nullable', Rule::in(array_keys(Puntoventa::$enumWebservice))],
        ];
    }
}
