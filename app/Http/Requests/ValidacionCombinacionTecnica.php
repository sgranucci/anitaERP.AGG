<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ValidacionCombinacionTecnica extends FormRequest
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
            'plvista_id' => ['sometimes', 'integer', 'nullable'],
            'plarmado_id' => ['sometimes', 'integer', 'nullable'],
            'fondo_id' => ['sometimes','integer', 'nullable'],
            'colorfondo_id' => ['sometimes', 'integer', 'nullable'],
            'horma_id' => ['sometimes', 'integer', 'nullable'],
            'serigrafia_id' => ['sometimes', 'integer', 'nullable'],
        ];
    }
}

