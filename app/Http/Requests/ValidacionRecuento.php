<?php

namespace App\Http\Requests;

use App\Models\Stock\Articulo;
use App\Models\Stock\Depmae;
use App\Support\Stock\ArticuloStockColorTalleSupport;
use App\Support\Stock\MovimientoStockColorTalleExclusividadSupport;
use App\Support\Stock\UsuarioDepositoAutorizado;
use Illuminate\Foundation\Http\FormRequest;
use InvalidArgumentException;

class ValidacionRecuento extends FormRequest
{
    public function authorize(): bool
    {
        return true;
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
}
