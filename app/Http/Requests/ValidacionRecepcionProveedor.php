<?php

namespace App\Http\Requests;

use App\Models\Compras\Ordencompra;
use App\Models\Stock\Depmae;
use App\Support\Stock\UsuarioDepositoAutorizado;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ValidacionRecepcionProveedor extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $items = $this->input('items', []);
        if (! is_array($items)) {
            return;
        }

        foreach ($items as $key => $item) {
            if (! is_array($item)) {
                continue;
            }
            if (! isset($item['moneda_id']) || $item['moneda_id'] === '' || $item['moneda_id'] === null) {
                $items[$key]['moneda_id'] = 1;
            }
            if (! isset($item['cotizacion']) || $item['cotizacion'] === '' || $item['cotizacion'] === null) {
                $items[$key]['cotizacion'] = 1;
            }
            if (! isset($item['cantidad_rechazada']) || $item['cantidad_rechazada'] === '' || $item['cantidad_rechazada'] === null) {
                $items[$key]['cantidad_rechazada'] = 0;
            }
        }

        $this->merge(['items' => $items]);
    }

    public function rules(): array
    {
        return [
            'ordencompra_id' => 'required|integer|exists:ordencompra,id',
            'fecha' => 'required|date',
            'numerofactura' => 'nullable|string|max:50',
            'observacion' => 'nullable|string|max:255',
            'deposito_id' => 'nullable|integer|exists:depmae,id',
            'tipo' => 'nullable|in:RECEPCION,DEVOLUCION',
            'recepcion_referencia_id' => 'nullable|integer|exists:recepcion_proveedor,id',
            'items' => 'required|array|min:1',
            'items.*.articulo_id' => 'required|integer|exists:articulo,id',
            'items.*.ordencompra_articulo_id' => 'nullable|integer|exists:ordencompra_articulo,id',
            'items.*.cantidad' => 'nullable|numeric|min:0',
            'items.*.cantidad_rechazada' => 'nullable|numeric|min:0',
            'items.*.motivorechazo' => 'nullable|string|max:255',
            'items.*.precio' => 'required|numeric|min:0',
            'items.*.precio_ordencompra' => 'nullable|numeric|min:0',
            'items.*.moneda_id' => 'required|integer|exists:moneda,id',
            'items.*.cotizacion' => 'nullable|numeric|min:0',
            'items.*.descuento' => 'nullable|numeric|min:0|max:100',
            'items.*.deposito_id' => 'nullable|integer|exists:depmae,id',
            'items.*.centrocosto_id' => 'nullable|integer|exists:centrocosto,id',
            'items.*.coeficienteconversion' => 'nullable|numeric|min:0.000001',
            'items.*.tipo_linea' => 'nullable|in:OC,EXTRA,SUSTITUTO',
            'items.*.cantidad_oc' => 'nullable|numeric|min:0',
            'items.*.ordencompra_articulo_sustituido_id' => 'nullable|integer|exists:ordencompra_articulo,id',
            'items.*.comentario_diferencia' => 'nullable|string|max:500',
            'items.*.comentario_precio' => 'nullable|string|max:255',
            'items.*.unidadmedida_id' => 'nullable|integer|exists:unidadmedida,id',
            'items.*.ocr_codigo_proveedor' => 'nullable|string|max:100',
            'items.*.ocr_descripcion_proveedor' => 'nullable|string|max:255',
            'items.*.ocr_codigobarra' => 'nullable|string|max:50',
            'items.*.ocr_unidad_compra' => 'nullable|string|max:30',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $items = $this->input('items', []);
            if (! is_array($items)) {
                return;
            }

            foreach ($items as $idx => $item) {
                if (! is_array($item)) {
                    continue;
                }
                $cantidad = (float) ($item['cantidad'] ?? 0);
                $rechazada = (float) ($item['cantidad_rechazada'] ?? 0);
                if ($cantidad <= 0 && $rechazada <= 0) {
                    $validator->errors()->add(
                        'items.'.$idx.'.cantidad',
                        'Línea '.($idx + 1).': indique cantidad recibida o rechazada.'
                    );
                }
                if ($rechazada > 0 && trim((string) ($item['motivorechazo'] ?? '')) === '') {
                    $validator->errors()->add(
                        'items.'.$idx.'.motivorechazo',
                        'Línea '.($idx + 1).': indique motivo de rechazo.'
                    );
                }
            }

            $empresaId = (int) $this->input('empresa_id', 0);
            if ($empresaId <= 0) {
                $ordencompraId = (int) $this->input('ordencompra_id', 0);
                if ($ordencompraId > 0) {
                    $empresaId = (int) (Ordencompra::query()->whereKey($ordencompraId)->value('empresa_id') ?? 0);
                }
            }

            $depositoCabeceraId = (int) $this->input('deposito_id', 0);
            if ($depositoCabeceraId > 0) {
                $this->validarDepositoEnRequest($validator, 'deposito_id', $depositoCabeceraId, $empresaId, 'Depósito general de entrada');
            }

            foreach ($items as $idx => $item) {
                if (! is_array($item)) {
                    continue;
                }
                $depLinea = (int) ($item['deposito_id'] ?? 0);
                if ($depLinea <= 0) {
                    continue;
                }
                if ($depositoCabeceraId > 0 && $depLinea === $depositoCabeceraId) {
                    continue;
                }
                $this->validarDepositoEnRequest(
                    $validator,
                    'items.'.$idx.'.deposito_id',
                    $depLinea,
                    $empresaId,
                    'Línea '.($idx + 1)
                );
            }
        });
    }

    private function validarDepositoEnRequest(
        Validator $validator,
        string $campo,
        int $depositoId,
        int $empresaId,
        string $contexto
    ): void {
        if ($depositoId <= 0) {
            return;
        }

        $deposito = Depmae::query()->find($depositoId);
        if ($deposito === null) {
            $validator->errors()->add($campo, "{$contexto}: depósito inválido.");

            return;
        }

        $empresaDeposito = (int) ($deposito->empresa_id ?? 0);
        if ($empresaId > 0 && $empresaDeposito > 0 && $empresaDeposito !== $empresaId) {
            $validator->errors()->add($campo, "{$contexto}: depósito no pertenece a la empresa de la orden de compra.");

            return;
        }

        if ($empresaId > 0 && ! Depmae::autorizadoParaUsuarioYEmpresa($depositoId, $empresaId)) {
            $validator->errors()->add($campo, "{$contexto}: depósito no autorizado para su empresa.");
        }

        if (! UsuarioDepositoAutorizado::depositoAutorizado($depositoId)) {
            $validator->errors()->add($campo, "{$contexto}: no tiene permiso para operar sobre este depósito.");
        }
    }
}
