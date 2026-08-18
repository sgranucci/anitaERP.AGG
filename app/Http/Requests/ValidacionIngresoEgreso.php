<?php

namespace App\Http\Requests;

use App\Support\Caja\IngresoEgresoSolicitudpagoSupport;
use App\Support\Caja\IngresoEgresoTransferenciaSupport;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use InvalidArgumentException;

class ValidacionIngresoEgreso extends FormRequest
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
            'empresa_id' => 'required',
            'tipotransaccion_caja_id' => 'required',
        ];
    }

    protected function prepareForValidation(): void
    {
        $data = $this->all();
        try {
            IngresoEgresoSolicitudpagoSupport::aplicarPagoDesdeSolicitud($data);
        } catch (InvalidArgumentException $e) {
            return;
        }
        $merge = [];
        if (isset($data['tipotransaccion_caja_id'])) {
            $merge['tipotransaccion_caja_id'] = $data['tipotransaccion_caja_id'];
        }
        if (isset($data['proveedor_id'])) {
            $merge['proveedor_id'] = $data['proveedor_id'];
        }
        if (isset($data['solicitudpago_id'])) {
            $merge['solicitudpago_id'] = $data['solicitudpago_id'];
        }
        if ($merge !== []) {
            $this->merge($merge);
        }
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            try {
                IngresoEgresoTransferenciaSupport::assertBalanceado($this->all());
            } catch (InvalidArgumentException $e) {
                $validator->errors()->add('tipotransaccion_caja_id', $e->getMessage());
            }

            try {
                IngresoEgresoSolicitudpagoSupport::assertMontoCoincideConSolicitud($this->all());
            } catch (InvalidArgumentException $e) {
                $validator->errors()->add('solicitudpago_id', $e->getMessage());
            }

            try {
                $tmp = $this->all();
                IngresoEgresoSolicitudpagoSupport::aplicarPagoDesdeSolicitud($tmp);
            } catch (InvalidArgumentException $e) {
                $validator->errors()->add('tipotransaccion_caja_id', $e->getMessage());
            }
        });
    }
}
