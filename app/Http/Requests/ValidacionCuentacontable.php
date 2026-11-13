<?php

namespace App\Http\Requests;

use App\Support\Contable\CuentacontableArbolSupport;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ValidacionCuentacontable extends FormRequest
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
    protected function prepareForValidation(): void
    {
        $codigo = CuentacontableArbolSupport::normalizarCodigo((string) $this->input('codigo', ''));
        if ($codigo !== '000000000') {
            $this->merge(['codigo' => $codigo]);
        }
    }

    public function rules()
    {
        $id = $this->route('id');
        $empresaId = (int) $this->input('empresa_id');

        return [
            'empresa_id' => 'required|integer',
            'rubrocontable_id' => 'required|integer',
            // El nombre puede repetirse en la misma empresa (título + imputable del mismo rubro).
            // La unicidad de negocio es el código Anita.
            'nombre' => ['required', 'max:100'],
            'codigo' => [
                'required',
                'max:50',
                Rule::unique('cuentacontable', 'codigo')
                    ->where(fn ($q) => $q->where('empresa_id', $empresaId))
                    ->ignore($id),
            ],
            'tipocuenta' => 'required|min:1|max:2,',
            'nivel' => 'required|min:1|max:9,',
            'monetaria' => 'required|min:1|max:2,',
            'manejaccosto' => 'required|min:1|max:2,',
        ];
    }

    public function messages()
    {
        return [
            'codigo.unique' => 'El código ya está en uso para esta empresa.',
        ];
    }
}
