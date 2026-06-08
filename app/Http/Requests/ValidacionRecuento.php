<?php

namespace App\Http\Requests;

use App\Models\Stock\Depmae;
use App\Support\Stock\UsuarioDepositoAutorizado;
use Illuminate\Foundation\Http\FormRequest;

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

            $articuloIds = array_values(array_filter(array_map(
                static fn ($id): int => (int) $id,
                $this->input('articulo_ids', [])
            )));
            $conteos = array_count_values($articuloIds);
            foreach ($conteos as $articuloId => $cantidad) {
                if ($cantidad <= 1 || $articuloId <= 0) {
                    continue;
                }
                $sku = \App\Models\Stock\Articulo::query()->where('id', $articuloId)->value('sku');
                $ref = $sku ?: ('ID '.$articuloId);
                $validator->errors()->add(
                    'articulo_ids',
                    "El artículo «{$ref}» está repetido en el recuento. Cada artículo debe figurar en una sola línea."
                );
            }
        });
    }
}
