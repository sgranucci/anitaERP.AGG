<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ValidacionUsuario extends FormRequest
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

    protected function prepareForValidation(): void
    {
        $sector = $this->input('sector_legajocompra_id');
        if ($sector === '' || $sector === null) {
            $this->merge(['sector_legajocompra_id' => null]);
        }

        $vendedor = $this->input('vendedor_id');
        if ($vendedor === '' || $vendedor === null || (int) $vendedor === 0) {
            $this->merge(['vendedor_id' => null]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        if ($this->route('id')) {
            return [
                'usuario' => 'required|max:50|unique:usuario,usuario,'.$this->route('id'),
                'nombre' => 'required|max:50',
                'email' => 'required|email|max:100',
                'password' => 'nullable|min:5',
                're_password' => 'nullable|required_with:password|min:5|same:password',
                'rol_id' => 'required|array',
                'sector_legajocompra_id' => 'nullable|integer|exists:sector_legajocompra,id',
            ];
        } else {
            return [
                'usuario' => 'required|max:50|unique:usuario,usuario,'.$this->route('id'),
                'nombre' => 'required|max:50',
                'email' => 'required|email|max:100',
                'password' => 'required|min:5',
                're_password' => 'required|min:5|same:password',
                'rol_id' => 'required|array',
                'sector_legajocompra_id' => 'nullable|integer|exists:sector_legajocompra,id',
            ];
        }
    }
}
