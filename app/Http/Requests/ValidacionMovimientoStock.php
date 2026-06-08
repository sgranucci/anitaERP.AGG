<?php

namespace App\Http\Requests;

use App\Models\Stock\Depmae;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ValidacionMovimientoStock extends FormRequest
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
            'tipotransaccion_stock_id' => 'required_without:tipotransaccion_id',
            'tipotransaccion_id' => 'required_without:tipotransaccion_stock_id',
            'fecha' => 'required',
            'empresa_id' => 'required|integer|exists:empresa,id',
            'deposito_id' => 'required|integer|exists:depmae,id',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $empresaId = (int) $this->input('empresa_id', 0);
            $depositoId = (int) $this->input('deposito_id', 0);

            if ($depositoId > 0 && $empresaId > 0 && ! Depmae::autorizadoParaUsuarioYEmpresa($depositoId, $empresaId)) {
                $validator->errors()->add('deposito_id', 'El depósito no pertenece a la empresa seleccionada o no está autorizado para su usuario.');
            }
        });
    }
}
