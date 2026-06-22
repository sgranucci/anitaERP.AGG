<?php

namespace App\Http\Requests;

use App\Models\Stock\Depmae;
use App\Models\Stock\Tipotransaccion_Stock;
use App\Support\Stock\TransferenciaBienUsoSupport;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
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
            'deposito_id' => [
                Rule::requiredIf(fn () => ! $this->validarComoTransferenciaNueva()),
                'nullable',
                'integer',
                'exists:depmae,id',
            ],
            'deposito_salida_id' => 'nullable|integer|exists:depmae,id',
            'deposito_entrada_id' => 'nullable|integer|exists:depmae,id',
            'bien_uso_destino_id' => 'nullable|integer|exists:bien_uso,id',
            'bien_uso_origen_id' => 'nullable|integer|exists:bien_uso,id',
            'centrocosto_destino_id' => 'nullable|integer|exists:centrocosto,id',
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

            $tipoId = (int) ($this->input('tipotransaccion_stock_id') ?: $this->input('tipotransaccion_id'));
            $tipo = $tipoId > 0 ? Tipotransaccion_Stock::query()->find($tipoId) : null;

            if ($tipo && (bool) $tipo->maneja_contabilidad && ! $this->validarComoTransferenciaNueva()) {
                $ccDestino = (int) $this->input('centrocosto_destino_id', 0);
                if ($ccDestino <= 0) {
                    $validator->errors()->add(
                        'centrocosto_destino_id',
                        'Debe indicar centro de costo destino para movimientos con contabilidad.'
                    );
                }
            }

            if (! $this->validarComoTransferenciaNueva() || $tipo === null) {
                return;
            }

            $salidaId = (int) ($this->input('deposito_salida_id') ?: $this->input('deposito_id'));
            $entradaId = (int) $this->input('deposito_entrada_id', 0);
            $bienUsoDestId = (int) $this->input('bien_uso_destino_id', 0);
            $bienUsoOrigId = (int) $this->input('bien_uso_origen_id', 0);
            $destinoBien = TransferenciaBienUsoSupport::tipoDestinoBienUso($tipo);
            $origenBien = TransferenciaBienUsoSupport::tipoOrigenBienUso($tipo);

            if ($origenBien) {
                if ($bienUsoOrigId <= 0) {
                    $validator->errors()->add('bien_uso_origen_id', 'Debe seleccionar el bien de uso de origen.');
                }
                if ($salidaId > 0) {
                    $validator->errors()->add('deposito_salida_id', 'Este tipo de transferencia no usa depósito de origen.');
                }
                if ($entradaId <= 0) {
                    $validator->errors()->add('deposito_entrada_id', 'Debe indicar depósito destino.');
                }
            } elseif ($destinoBien) {
                if ($salidaId <= 0) {
                    $validator->errors()->add('deposito_salida_id', 'Debe indicar depósito origen.');
                }
                if ($bienUsoDestId <= 0) {
                    $validator->errors()->add('bien_uso_destino_id', 'Debe seleccionar el bien de uso destino.');
                }
                if ($entradaId > 0) {
                    $validator->errors()->add('deposito_entrada_id', 'Este tipo de transferencia no usa depósito destino.');
                }
            } else {
                if ($salidaId <= 0) {
                    $validator->errors()->add('deposito_salida_id', 'Debe indicar depósito origen.');
                }
                if ($entradaId <= 0) {
                    $validator->errors()->add('deposito_entrada_id', 'Debe indicar depósito destino.');
                }
                if ($salidaId > 0 && $entradaId > 0 && $salidaId === $entradaId) {
                    $validator->errors()->add('deposito_entrada_id', 'El depósito destino debe ser distinto al de origen.');
                }
            }

            if ($empresaId <= 0) {
                return;
            }

            if ($salidaId > 0 && ! Depmae::autorizadoParaUsuarioYEmpresa($salidaId, $empresaId)) {
                $validator->errors()->add('deposito_salida_id', 'El depósito origen no pertenece a la empresa seleccionada o no está autorizado para su usuario.');
            }

            if (! $destinoBien && $entradaId > 0 && ! Depmae::autorizadoParaUsuarioYEmpresa($entradaId, $empresaId)) {
                $validator->errors()->add('deposito_entrada_id', 'El depósito destino no pertenece a la empresa seleccionada o no está autorizado para su usuario.');
            }

            $articulos = $this->input('articulos_id', []);
            $cantidades = $this->input('cantidades', []);
            $tieneLinea = false;
            foreach ($articulos as $i => $articuloId) {
                if ((int) $articuloId > 0 && abs((float) ($cantidades[$i] ?? 0)) > 0) {
                    $tieneLinea = true;
                    break;
                }
            }
            if (! $tieneLinea) {
                $validator->errors()->add('articulos_id', 'Debe indicar al menos un artículo con cantidad para la transferencia.');
            }
        });
    }

    private function validarComoTransferenciaNueva(): bool
    {
        if ($this->isMethod('put') || $this->isMethod('patch')) {
            return false;
        }

        $tipoId = (int) ($this->input('tipotransaccion_stock_id') ?: $this->input('tipotransaccion_id'));
        if ($tipoId <= 0) {
            return false;
        }

        $tipo = Tipotransaccion_Stock::query()->find($tipoId);

        return $tipo !== null && $tipo->operacion === 'T';
    }
}
