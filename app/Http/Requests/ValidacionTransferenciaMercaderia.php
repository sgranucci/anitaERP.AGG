<?php

namespace App\Http\Requests;

use App\Models\Stock\Depmae;
use App\Models\Stock\Tipotransaccion_Stock;
use App\Support\Stock\TransferenciaBienUsoSupport;
use App\Support\Stock\TransferenciaMercaderiaAprobacionSupport;
use App\Support\Stock\TransferenciaMercaderiaIntercompanySupport;
use App\Support\Stock\TransferenciaMercaderiaLineaContableSupport;
use App\Support\Stock\UsuarioTipotransaccionStockAutorizado;
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
            'deposito_salida_id' => 'nullable|integer|exists:depmae,id',
            'deposito_entrada_id' => 'nullable|integer|exists:depmae,id',
            'bien_uso_destino_id' => 'nullable|integer|exists:bien_uso,id',
            'bien_uso_origen_id' => 'nullable|integer|exists:bien_uso,id',
            'tipotransaccion_stock_id' => 'required_without:tipotransaccion_id|integer|exists:tipotransaccion_stock,id',
            'tipotransaccion_id' => 'required_without:tipotransaccion_stock_id|integer',
            'lineas' => 'required|array|min:1',
            'lineas.*.articulo_id' => 'required|integer|exists:articulo,id',
            'lineas.*.cantidad' => 'required|numeric|gt:0',
            'usuario_destino_id' => 'nullable|integer|exists:usuario,id',
            'centrocosto_destino_id' => 'nullable|integer|exists:centrocosto,id',
            'enviar_aviso' => 'nullable|boolean',
            'observacion' => 'nullable|string|max:2000',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $empresaId = (int) $this->input('empresa_id', 0);
            $salidaId = (int) $this->input('deposito_salida_id', 0);
            $entradaId = (int) $this->input('deposito_entrada_id', 0);
            $bienUsoDestId = (int) $this->input('bien_uso_destino_id', 0);
            $bienUsoOrigId = (int) $this->input('bien_uso_origen_id', 0);
            $tipoId = (int) ($this->input('tipotransaccion_stock_id') ?: $this->input('tipotransaccion_id'));

            $tipo = $tipoId > 0 ? Tipotransaccion_Stock::query()->find($tipoId) : null;
            $destinoBien = TransferenciaBienUsoSupport::tipoDestinoBienUso($tipo);
            $origenBien = TransferenciaBienUsoSupport::tipoOrigenBienUso($tipo);

            if ($tipoId > 0 && ! UsuarioTipotransaccionStockAutorizado::tipotransaccionAutorizada($tipoId)) {
                $validator->errors()->add(
                    'tipotransaccion_stock_id',
                    'El tipo de transacción seleccionado no está autorizado para su usuario.'
                );
            }

            if ($origenBien) {
                if ($bienUsoOrigId <= 0) {
                    $validator->errors()->add('bien_uso_origen_id', 'Debe seleccionar el bien de uso de origen.');
                }
                if ($salidaId > 0) {
                    $validator->errors()->add('deposito_salida_id', 'Este tipo de transferencia no usa depósito de salida.');
                }
                if ($entradaId <= 0) {
                    $validator->errors()->add('deposito_entrada_id', 'Debe indicar depósito de entrada.');
                }
                if ($bienUsoDestId > 0) {
                    $validator->errors()->add('bien_uso_destino_id', 'Este tipo no admite bien de uso como destino.');
                }
            } elseif ($destinoBien) {
                if ($salidaId <= 0) {
                    $validator->errors()->add('deposito_salida_id', 'Debe indicar depósito de salida.');
                }
                if ($bienUsoDestId <= 0) {
                    $validator->errors()->add('bien_uso_destino_id', 'Debe seleccionar el bien de uso destino.');
                }
                if ($entradaId > 0) {
                    $validator->errors()->add('deposito_entrada_id', 'Este tipo de transferencia no usa depósito de entrada.');
                }
            } else {
                if ($salidaId <= 0) {
                    $validator->errors()->add('deposito_salida_id', 'Debe indicar depósito de salida.');
                }
                if ($entradaId <= 0) {
                    $validator->errors()->add('deposito_entrada_id', 'Debe indicar depósito de entrada.');
                }
                if ($salidaId > 0 && $entradaId > 0 && $salidaId === $entradaId) {
                    $validator->errors()->add('deposito_entrada_id', 'El depósito de entrada debe ser distinto al de salida.');
                }
            }

            if ($empresaId <= 0) {
                return;
            }

            if ($salidaId > 0 && ! TransferenciaMercaderiaIntercompanySupport::depositoSalidaAutorizado($salidaId, $empresaId)) {
                $validator->errors()->add('deposito_salida_id', 'El depósito de salida no pertenece a la empresa seleccionada o no está autorizado para su usuario.');
            }

            if (! $destinoBien && $entradaId > 0 && ! TransferenciaMercaderiaIntercompanySupport::depositoEntradaAutorizado($entradaId, $empresaId)) {
                $validator->errors()->add(
                    'deposito_entrada_id',
                    TransferenciaMercaderiaIntercompanySupport::puedeUsar()
                        ? 'El depósito de entrada no existe o no es válido.'
                        : 'El depósito de entrada no pertenece a la empresa seleccionada.'
                );
            }

            if ($tipo && TransferenciaMercaderiaAprobacionSupport::manejaContabilidad($tipo)) {
                $ccDestino = (int) $this->input('centrocosto_destino_id', 0);
                if ($ccDestino <= 0) {
                    $validator->errors()->add('centrocosto_destino_id', 'Debe indicar centro de costo destino para transferencias con contabilidad.');
                }

                $origenBien = TransferenciaBienUsoSupport::tipoOrigenBienUso($tipo);
                if ($origenBien) {
                    $validator->errors()->add(
                        'tipotransaccion_stock_id',
                        'Las transferencias contables (TRCONT) requieren depósito de salida (no bien de uso como origen).'
                    );
                } elseif ($salidaId > 0) {
                    $articuloIds = [];
                    foreach ($this->input('lineas', []) as $linea) {
                        $articuloId = (int) ($linea['articulo_id'] ?? 0);
                        if ($articuloId > 0 && (float) ($linea['cantidad'] ?? 0) > 0) {
                            $articuloIds[] = $articuloId;
                        }
                    }

                    if ($articuloIds !== []) {
                        try {
                            TransferenciaMercaderiaLineaContableSupport::assertLineasValidasParaTrcont(
                                $articuloIds,
                                $salidaId,
                                $empresaId
                            );
                        } catch (\Throwable $e) {
                            $validator->errors()->add('lineas', $e->getMessage());
                        }
                    }
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'lineas.min' => 'Debe transferir al menos un artículo.',
        ];
    }
}
