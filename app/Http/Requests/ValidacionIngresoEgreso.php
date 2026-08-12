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
        });
    }
}
