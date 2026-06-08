<?php

namespace App\Http\Requests;

use App\Models\Stock\Depmae;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ValidacionTransferenciaMercaderia extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'empresa_id' => 'required|integer|exists:empresa,id',
            'deposito_salida_id' => 'required|integer|exists:depmae,id',
            'deposito_entrada_id' => 'required|integer|exists:depmae,id|different:deposito_salida_id',
            'tipotransaccion_stock_id' => 'required_without:tipotransaccion_id|integer|exists:tipotransaccion_stock,id',
            'tipotransaccion_id' => 'required_without:tipotransaccion_stock_id|integer',
            'lineas' => 'required|array|min:1',
            'lineas.*.articulo_id' => 'required|integer|exists:articulo,id',
            'lineas.*.cantidad' => 'required|numeric|gt:0',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $empresaId = (int) $this->input('empresa_id', 0);
            $salidaId = (int) $this->input('deposito_salida_id', 0);
            $entradaId = (int) $this->input('deposito_entrada_id', 0);

            if ($empresaId <= 0) {
                return;
            }

            if ($salidaId > 0 && ! Depmae::autorizadoParaUsuarioYEmpresa($salidaId, $empresaId)) {
                $validator->errors()->add('deposito_salida_id', 'El depósito de salida no pertenece a la empresa seleccionada o no está autorizado para su usuario.');
            }

            if ($entradaId > 0 && ! Depmae::autorizadoParaUsuarioYEmpresa($entradaId, $empresaId)) {
                $validator->errors()->add('deposito_entrada_id', 'El depósito de entrada no pertenece a la empresa seleccionada o no está autorizado para su usuario.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'deposito_entrada_id.different' => 'El depósito de entrada debe ser distinto al de salida.',
            'lineas.min' => 'Debe transferir al menos un artículo.',
        ];
    }
}
