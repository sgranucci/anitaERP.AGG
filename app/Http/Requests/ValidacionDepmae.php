<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ValidacionDepmae extends FormRequest
{
    public const CODIGO_REGEX = '/^[A-Za-z0-9._ -]+$/';

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $codigo = trim((string) $this->input('codigo', ''));

        if ($codigo !== '') {
            $this->merge(['codigo' => $codigo]);
        }
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
            'codigo' => [
                'required',
                'max:10',
                'regex:'.self::CODIGO_REGEX,
                Rule::unique('depmae', 'codigo')->ignore($id)->where(function ($query) {
                    return $query->where('empresa_id', $this->get('empresa_id'));
                }),
            ],
            'nombre' => 'required|max:50|unique:depmae,nombre,'.$id,
            'tipodeposito' => 'required',
            'empresa_id' => 'required|integer|exists:empresa,id',
        ];
    }

    public function attributes(): array
    {
        return [
            'codigo' => 'código',
            'nombre' => 'descripción',
        ];
    }

    public function messages(): array
    {
        return [
            'codigo.regex' => 'El código admite letras, números, espacios, punto, guión y guión bajo (máx. 10 caracteres).',
            'codigo.max' => 'El código no puede superar 10 caracteres.',
        ];
    }
}
