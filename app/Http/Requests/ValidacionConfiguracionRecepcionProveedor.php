<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ValidacionConfiguracionRecepcionProveedor extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'empresa_id' => 'required|integer|exists:empresa,id',
            'activa_contabilidad' => 'nullable|boolean',
            'cuentacontable_provision_facturas_id' => 'nullable|integer|exists:cuentacontable,id',
            'cuentacontable_factura_anticipada_id' => 'nullable|integer|exists:cuentacontable,id',
            'cuentacontable_anticipo_bienes_uso_id' => 'nullable|integer|exists:cuentacontable,id',
            'cuentacontable_proveedores_intangible_id' => 'nullable|integer|exists:cuentacontable,id',
        ];
    }
}
