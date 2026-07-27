<?php

namespace App\Http\Requests;

use App\Models\Compras\Ordencompra;
use App\Models\Stock\Articulo;
use App\Models\Stock\Depmae;
use App\Support\Stock\RecepcionProveedorAccionLineaOc;
use App\Support\Stock\RecepcionProveedorArticuloExtraSupport;
use App\Support\Stock\RecepcionProveedorCentrocostoLineaSupport;
use App\Support\Stock\RecepcionProveedorDepositoSupport;
use App\Support\Stock\RecepcionProveedorImpuestoInternoSupport;
use App\Support\Stock\RecepcionProveedorIntercompanySupport;
use App\Support\Stock\UsuarioDepositoAutorizado;
use App\Support\Stock\MovimientoStockColorTalleExclusividadSupport;
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
        $depositoCabecera = $this->input('deposito_id');
        if ($depositoCabecera === '' || $depositoCabecera === null || (int) $depositoCabecera <= 0) {
            $this->merge(['deposito_id' => null]);
        }

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
            if (! isset($item['deposito_id']) || $item['deposito_id'] === '' || (int) $item['deposito_id'] <= 0) {
                $items[$key]['deposito_id'] = null;
            }
        }

        $this->merge(['items' => $items]);
    }

    public function rules(): array
    {
        return [
            'ordencompra_id' => 'required|integer|exists:ordencompra,id',
            'fecha' => 'required|date|before_or_equal:today',
            'numerofactura' => 'nullable|string|max:50',
            'observacion' => 'nullable|string|max:255',
        'impuesto_interno' => 'nullable|numeric|min:0',
            'deposito_id' => 'nullable|integer|exists:depmae,id',
            'tipo' => 'nullable|in:RECEPCION,DEVOLUCION',
            'recepcion_referencia_id' => 'nullable|integer|exists:recepcion_proveedor,id',
            'origen_carga' => 'nullable|string|max:20',
            'ai_decision_id' => 'nullable|integer|min:1',
            'ai_sugerencia_hash' => 'nullable|string|size:64',
            'items' => 'required|array|min:1',
            'items.*.articulo_id' => 'required|integer|exists:articulo,id',
            'items.*.ordencompra_articulo_id' => 'nullable|integer|exists:ordencompra_articulo,id',
            'items.*.cantidad' => 'nullable|numeric|min:0',
            'items.*.cantidad_recepcionada_origen' => 'nullable|numeric|min:0',
            'items.*.cantidad_rechazada' => 'nullable|numeric|min:0',
            'items.*.motivorechazo' => 'nullable|string|max:255',
            'items.*.precio' => 'required|numeric|min:0',
            'items.*.precio_solicitado' => 'nullable|numeric|min:0',
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
            'items.*.accion_linea_oc' => 'nullable|in:PENDIENTE,RECIBIR,CERRAR',
            'items.*.fl_cerrar_linea_oc' => 'nullable|boolean',
            'items.*.unidadmedida_id' => 'nullable|integer|exists:unidadmedida,id',
            'items.*.ocr_codigo_proveedor' => 'nullable|string|max:100',
            'items.*.ocr_descripcion_proveedor' => 'nullable|string|max:255',
            'items.*.ocr_codigobarra' => 'nullable|string|max:50',
            'items.*.ocr_unidad_compra' => 'nullable|string|max:30',
            'items.*.color_id' => 'nullable|integer|exists:color,id',
            'items.*.talle_id' => 'nullable|integer|exists:talle,id',
        ];
    }

    public function messages(): array
    {
        return [
            'fecha.before_or_equal' => 'La fecha de recepción no puede ser posterior a hoy.',
            'deposito_id.exists' => 'El depósito general de entrada seleccionado no existe.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $items = $this->input('items', []);
            if (! is_array($items)) {
                return;
            }

            $esDevolucion = $this->input('tipo') === 'DEVOLUCION';

            if ($esDevolucion) {
                $lineasConCantidad = 0;
                foreach ($items as $item) {
                    if (! is_array($item)) {
                        continue;
                    }
                    if ((float) ($item['cantidad'] ?? 0) > 0.000001) {
                        $lineasConCantidad++;
                    }
                }
                if ($lineasConCantidad === 0) {
                    $validator->errors()->add('items', 'Indique al menos una línea con cantidad a devolver.');
                }
            } else {
                $tieneAccion = false;
                foreach ($items as $item) {
                    if (is_array($item) && ! RecepcionProveedorAccionLineaOc::esPendiente($item)) {
                        $tieneAccion = true;
                        break;
                    }
                }
                if (! $tieneAccion) {
                    $validator->errors()->add('items', 'Indique al menos una línea a recibir, rechazar o cerrar en la OC.');
                }
            }

            foreach ($items as $idx => $item) {
                if (! is_array($item)) {
                    continue;
                }

                if ($esDevolucion) {
                    continue;
                }

                if (RecepcionProveedorAccionLineaOc::requiereDefinicionEnGuardado($item)) {
                    $validator->errors()->add(
                        'items.'.$idx.'.cantidad',
                        'Línea '.($idx + 1).': sin cantidad. Indique pendiente o cierre de línea OC.'
                    );
                    continue;
                }

                $accion = RecepcionProveedorAccionLineaOc::resolver($item);
                if ($accion === RecepcionProveedorAccionLineaOc::PENDIENTE) {
                    continue;
                }

                $cantidad = (float) ($item['cantidad'] ?? 0);
                $rechazada = (float) ($item['cantidad_rechazada'] ?? 0);
                if ($accion === RecepcionProveedorAccionLineaOc::RECIBIR
                    && $cantidad <= 0 && $rechazada <= 0) {
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

                if (RecepcionProveedorArticuloExtraSupport::itemEsExtraActivo($item)
                    && ! RecepcionProveedorArticuloExtraSupport::puedeAgregar()) {
                    $validator->errors()->add(
                        'items.'.$idx.'.tipo_linea',
                        'Línea '.($idx + 1).': no tiene permiso para agregar artículos extra fuera de la orden de compra.'
                    );
                }
            }

            $empresaId = (int) $this->input('empresa_id', 0);
            $ordencompra = null;
            if ($empresaId <= 0) {
                $ordencompraId = (int) $this->input('ordencompra_id', 0);
                if ($ordencompraId > 0) {
                    $ordencompra = Ordencompra::query()
                        ->with('ordencompra_articulos')
                        ->find($ordencompraId);
                    $empresaId = (int) ($ordencompra->empresa_id ?? 0);
                }
            } elseif ((int) $this->input('ordencompra_id', 0) > 0) {
                $ordencompra = Ordencompra::query()
                    ->with('ordencompra_articulos')
                    ->find((int) $this->input('ordencompra_id'));
            }

            if ($ordencompra !== null) {
                try {
                    RecepcionProveedorCentrocostoLineaSupport::assertOcRecepcionable($ordencompra);
                } catch (\RuntimeException $e) {
                    $validator->errors()->add('ordencompra_id', $e->getMessage());
                }

                foreach ($items as $idx => $item) {
                    if (! is_array($item)) {
                        continue;
                    }
                    if (RecepcionProveedorAccionLineaOc::resolver($item) === RecepcionProveedorAccionLineaOc::PENDIENTE) {
                        continue;
                    }

                    $ccLinea = RecepcionProveedorCentrocostoLineaSupport::resolverDesdeOcYItem($ordencompra, $item);
                    if ($ccLinea === null) {
                        $validator->errors()->add(
                            'items.'.$idx.'.centrocosto_id',
                            'Línea '.($idx + 1).': falta centro de costo destino.'
                        );
                    }
                }
            }

            $depositoCabeceraId = (int) $this->input('deposito_id', 0);
            if ($depositoCabeceraId > 0) {
                $this->validarDepositoEnRequest($validator, 'deposito_id', $depositoCabeceraId, $empresaId, 'Depósito general de entrada');
            } else {
                $this->validarDepositoCabeceraCuandoLineasSinDeposito(
                    $validator,
                    $items,
                    $empresaId
                );
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

            if ($this->input('tipo') !== 'DEVOLUCION'
                && RecepcionProveedorImpuestoInternoSupport::itemsRequierenImpuestoInterno($items)) {
                if ($this->input('impuesto_interno') === null || $this->input('impuesto_interno') === '') {
                    $validator->errors()->add(
                        'impuesto_interno',
                        'Indique el impuesto interno de la factura (hay líneas con cigarrillos recibidos).'
                    );
                }
            }

            $articulosId = [];
            $coloresId = [];
            $tallesId = [];
            foreach ($items as $item) {
                if (! is_array($item)) {
                    continue;
                }
                $articuloId = (int) ($item['articulo_id'] ?? 0);
                if ($articuloId <= 0) {
                    continue;
                }
                $articulosId[] = $articuloId;
                $coloresId[] = (int) ($item['color_id'] ?? 0) ?: null;
                $tallesId[] = (int) ($item['talle_id'] ?? 0) ?: null;
            }
            try {
                MovimientoStockColorTalleExclusividadSupport::validarLineas(
                    $articulosId,
                    $coloresId,
                    $tallesId
                );
            } catch (\InvalidArgumentException $e) {
                $validator->errors()->add('items', $e->getMessage());
            }
        });
    }

    /**
     * @param  list<mixed>  $items
     */
    private function validarDepositoCabeceraCuandoLineasSinDeposito(
        Validator $validator,
        array $items,
        int $empresaId
    ): void {
        $articuloIds = [];
        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }
            if (RecepcionProveedorAccionLineaOc::resolver($item) === RecepcionProveedorAccionLineaOc::PENDIENTE) {
                continue;
            }
            if ((int) ($item['deposito_id'] ?? 0) > 0) {
                continue;
            }
            $articuloId = (int) ($item['articulo_id'] ?? 0);
            if ($articuloId > 0) {
                $articuloIds[] = $articuloId;
            }
        }

        if ($articuloIds === []) {
            return;
        }

        $depositosArticulo = Articulo::query()
            ->whereIn('id', array_values(array_unique($articuloIds)))
            ->pluck('depositoentrega_id', 'id');

        $lineasSinDep = [];
        foreach ($items as $idx => $item) {
            if (! is_array($item)) {
                continue;
            }
            if (RecepcionProveedorAccionLineaOc::resolver($item) === RecepcionProveedorAccionLineaOc::PENDIENTE) {
                continue;
            }
            if ((int) ($item['deposito_id'] ?? 0) > 0) {
                continue;
            }

            $articuloId = (int) ($item['articulo_id'] ?? 0);
            $depArt = (int) ($depositosArticulo[$articuloId] ?? 0);
            if ($depArt > 0 && RecepcionProveedorDepositoSupport::depositoEntregaVisible($depArt, $empresaId) !== null) {
                continue;
            }

            $lineasSinDep[] = $idx + 1;
        }

        if ($lineasSinDep === []) {
            return;
        }

        $detalleLineas = count($lineasSinDep) === 1
            ? 'la línea '.$lineasSinDep[0]
            : 'las líneas '.implode(', ', $lineasSinDep);

        $validator->errors()->add(
            'deposito_id',
            'Indique depósito general de entrada: '.$detalleLineas.' no tiene depósito configurado en el artículo.'
        );
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

        $intercompany = RecepcionProveedorIntercompanySupport::puedeUsar();

        $empresaDeposito = (int) ($deposito->empresa_id ?? 0);
        if (! $intercompany && $empresaId > 0 && $empresaDeposito > 0 && $empresaDeposito !== $empresaId) {
            $validator->errors()->add($campo, "{$contexto}: depósito no pertenece a la empresa de la orden de compra.");

            return;
        }

        if (! $intercompany && $empresaId > 0 && ! Depmae::autorizadoParaUsuarioYEmpresa($depositoId, $empresaId)) {
            $validator->errors()->add($campo, "{$contexto}: depósito no autorizado para su empresa.");
        }

        if (! UsuarioDepositoAutorizado::depositoAutorizado($depositoId)) {
            $validator->errors()->add($campo, "{$contexto}: no tiene permiso para operar sobre este depósito.");
        }
    }
}
