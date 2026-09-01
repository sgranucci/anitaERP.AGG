<?php

namespace App\Http\Requests;

use App\Models\Stock\Articulo;
use App\Models\Stock\Depmae;
use App\Support\Stock\ArticuloStockColorTalleSupport;
use App\Support\Stock\MovimientoStockColorTalleExclusividadSupport;
use App\Support\Stock\RecuentoItemsRequestSupport;
use App\Support\Stock\UsuarioDepositoAutorizado;
use Illuminate\Foundation\Http\FormRequest;
use InvalidArgumentException;

class ValidacionRecuento extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $raw = $this->input('items_json');
        if ($raw !== null && $raw !== '') {
            $arrays = RecuentoItemsRequestSupport::arraysDesdeItemsJson($raw);
            if ($arrays !== null) {
                $this->merge($arrays);
            }
        }

        $this->normalizarCantidadesContadas();
    }

    private function normalizarCantidadesContadas(): void
    {
        $cantidades = $this->input('cantidades_contadas');
        if (! is_array($cantidades)) {
            return;
        }

        foreach ($cantidades as $i => $cantidad) {
            $cantidades[$i] = RecuentoItemsRequestSupport::normalizarCantidadContada($cantidad);
        }

        $this->merge(['cantidades_contadas' => $cantidades]);
    }

    public function rules(): array
    {
        return [
            'fecha' => 'required|date',
            'deposito_id' => 'required|integer|exists:depmae,id',
            'comentario' => 'nullable|string|max:5000',
            'articulo_ids' => 'nullable|array',
            'articulo_ids.*' => 'nullable|integer|exists:articulo,id',
            'recuento_item_ids' => 'nullable|array',
            'recuento_item_ids.*' => 'nullable|integer',
            'colores_id' => 'nullable|array',
            'colores_id.*' => 'nullable|integer',
            'talles_id' => 'nullable|array',
            'talles_id.*' => 'nullable|integer',
            'detalle_articulos' => 'nullable|array',
            'detalle_articulos.*' => 'nullable|string|max:500',
            'cantidades_contadas' => 'nullable|array',
            'cantidades_contadas.*' => 'nullable|numeric|min:0',
            'saldos_sistema' => 'nullable|array',
            'saldos_sistema.*' => 'nullable|numeric',
            'unidadmedida_ids' => 'nullable|array',
            'unidadmedida_ids.*' => 'nullable|integer|exists:unidadmedida,id',
            'nombrearchivos' => 'nullable|array',
            'nombrearchivos.*' => 'nullable|file|max:10240',
            'nombresanteriores' => 'nullable|array',
            'nombresanteriores.*' => 'nullable|string|max:255',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $depositoId = (int) $this->input('deposito_id', 0);
            if ($depositoId <= 0) {
                return;
            }

            $deposito = Depmae::query()->find($depositoId);
            if (! $deposito) {
                $validator->errors()->add('deposito_id', 'Depósito inválido.');

                return;
            }

            if (! Depmae::autorizadoParaUsuarioYEmpresa($depositoId, (int) $deposito->empresa_id)) {
                $validator->errors()->add('deposito_id', 'Depósito no autorizado para su empresa.');
            }

            if (! UsuarioDepositoAutorizado::depositoAutorizado($depositoId)) {
                $validator->errors()->add('deposito_id', 'No tiene permiso para operar sobre este depósito.');
            }

            if ($this->filled('items_json') && RecuentoItemsRequestSupport::arraysDesdeItemsJson($this->input('items_json')) === null) {
                $validator->errors()->add('items_json', 'No se pudieron leer las líneas del recuento. Vuelva a guardar.');

                return;
            }

            if (! $this->filled('items_json') && RecuentoItemsRequestSupport::postTruncado($this->all())) {
                $validator->errors()->add(
                    'articulo_ids',
                    'El recuento tiene demasiadas líneas para el envío clásico del formulario y se cortó el último ítem. Recargue la página y vuelva a guardar.'
                );

                return;
            }

            $articuloIds = $this->input('articulo_ids', []);
            $coloresId = $this->input('colores_id', []);
            $tallesId = $this->input('talles_id', []);

            $clavesVistas = [];
            foreach ($articuloIds as $i => $articuloIdRaw) {
                $articuloId = (int) $articuloIdRaw;
                if ($articuloId <= 0) {
                    continue;
                }
                $colorRaw = isset($coloresId[$i]) ? (int) $coloresId[$i] : 0;
                $talleRaw = isset($tallesId[$i]) ? (int) $tallesId[$i] : 0;
                [$colorKey, $talleKey] = ArticuloStockColorTalleSupport::claveSaldo(
                    $colorRaw > 0 ? $colorRaw : null,
                    $talleRaw > 0 ? $talleRaw : null
                );
                $clave = $articuloId.'|'.$colorKey.'|'.$talleKey;
                if (isset($clavesVistas[$clave])) {
                    $sku = Articulo::query()->where('id', $articuloId)->value('sku');
                    $ref = $sku ?: ('ID '.$articuloId);
                    $validator->errors()->add(
                        'articulo_ids',
                        "La variante del artículo «{$ref}» (color/talle) está repetida. Cada combinación debe figurar en una sola línea."
                    );
                    break;
                }
                $clavesVistas[$clave] = true;
            }

            try {
                MovimientoStockColorTalleExclusividadSupport::validarLineas(
                    is_array($articuloIds) ? $articuloIds : [],
                    is_array($coloresId) ? $coloresId : [],
                    is_array($tallesId) ? $tallesId : []
                );
            } catch (InvalidArgumentException $e) {
                $validator->errors()->add('articulo_ids', $e->getMessage());
            }
        });
    }

    public function attributes(): array
    {
        $attrs = [];
        $detalles = $this->input('detalle_articulos', []);
        $cantidades = $this->input('cantidades_contadas', []);
        $indices = array_unique(array_merge(
            array_keys(is_array($detalles) ? $detalles : []),
            array_keys(is_array($cantidades) ? $cantidades : [])
        ));
        foreach ($indices as $i) {
            $detalle = trim((string) (is_array($detalles) ? ($detalles[$i] ?? '') : ''));
            $ref = $detalle !== '' ? $detalle : ('línea '.(((int) $i) + 1));
            $attrs['cantidades_contadas.'.$i] = 'cantidad contada ('.$ref.')';
        }

        return $attrs;
    }

    public function messages(): array
    {
        return [
            'cantidades_contadas.*.min' => 'La :attribute no puede ser negativa. Indique 0 si no había existencias.',
            'cantidades_contadas.*.numeric' => 'La :attribute debe ser un número.',
        ];
    }
}
