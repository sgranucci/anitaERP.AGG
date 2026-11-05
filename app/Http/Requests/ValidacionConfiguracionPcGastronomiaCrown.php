<?php

namespace App\Http\Requests;

use App\Support\Ventas\GastronomiaCrown\GastronomiaCrownPosModos;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ValidacionConfiguracionPcGastronomiaCrown extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        foreach ([
            'sala_id',
            'listaprecio_hotel_id',
            'salida_hotel_id',
            'salida_comanda_b_id',
            'salida_comanda_k_id',
        ] as $nullableId) {
            if ($this->input($nullableId) === '' || $this->input($nullableId) === '0') {
                $this->merge([$nullableId => null]);
            }
        }
        foreach (['solo_marcha', 'habilita_hotel', 'habilita_canje_gst', 'pide_cubiertos', 'pide_mozo_manual'] as $flag) {
            if ($this->has($flag)) {
                $this->merge([$flag => $this->boolean($flag) ? 1 : 0]);
            }
        }
    }

    public function rules(): array
    {
        $id = $this->route('id');
        $empresaId = (int) $this->input('empresa_id');

        return [
            'identificador_pc' => [
                'required',
                'max:100',
                Rule::unique('configuracion_puntoventa_gastronomia', 'identificador_pc')
                    ->where(fn ($q) => $q->where('empresa_id', $empresaId))
                    ->ignore($id),
            ],
            'descripcion' => 'nullable|max:255',
            'empresa_id' => 'required|exists:empresa,id',
            'modo_pos' => ['required', Rule::in(array_keys(GastronomiaCrownPosModos::modos()))],
            'politica_comanda' => ['required', Rule::in(array_keys(GastronomiaCrownPosModos::politicasComanda()))],
            'solo_marcha' => 'nullable|boolean',
            'habilita_hotel' => 'nullable|boolean',
            'habilita_canje_gst' => 'nullable|boolean',
            'pide_cubiertos' => 'nullable|boolean',
            'pide_mozo_manual' => 'nullable|boolean',
            'sala_id' => 'nullable|exists:sala,id',
            'listaprecio_id' => 'required|exists:listaprecio,id',
            'listaprecio_hotel_id' => 'nullable|exists:listaprecio,id',
            'salida_comanda_id' => 'required|exists:salida,id',
            'salida_factura_id' => 'required|exists:salida,id',
            'salida_hotel_id' => 'nullable|exists:salida,id',
            'salida_comanda_b_id' => 'nullable|exists:salida,id',
            'salida_comanda_k_id' => 'nullable|exists:salida,id',
            'puntoventa_cae_id' => [
                'required',
                Rule::exists('puntoventa', 'id')->where(function ($query) use ($empresaId) {
                    $query->where('empresa_id', $empresaId)
                        ->where('estado', 'A')
                        ->whereIn('modofacturacion', ['C', 'E'])
                        ->whereNull('deleted_at');
                }),
            ],
            'puntoventa_caea_id' => [
                'required',
                Rule::exists('puntoventa', 'id')->where(function ($query) use ($empresaId) {
                    $query->where('empresa_id', $empresaId)
                        ->where('estado', 'A')
                        ->where('modofacturacion', 'A')
                        ->whereNull('deleted_at');
                }),
            ],
            'tipotransaccion_id' => 'required|exists:tipotransaccion,id',
            'tipotransaccion_nota_credito_id' => 'required|exists:tipotransaccion,id',
            'plano_ids' => 'nullable|array',
            'plano_ids.*' => [
                Rule::exists('gastro_plano_mesa', 'id')->where(fn ($q) => $q->where('empresa_id', $empresaId)),
            ],
            'plano_orden' => 'nullable|array',
            'plano_orden.*' => 'nullable|integer|min:0|max:999',
        ];
    }

    public function attributes(): array
    {
        return [
            'identificador_pc' => 'identificador de PC',
            'modo_pos' => 'modo de uso',
            'politica_comanda' => 'política de comanda',
            'sala_id' => 'sala',
            'listaprecio_id' => 'lista de precios',
            'listaprecio_hotel_id' => 'lista de precios hotel',
            'salida_comanda_id' => 'salida de comandas',
            'salida_factura_id' => 'salida de facturas',
            'salida_hotel_id' => 'salida cargo hotel',
            'salida_comanda_b_id' => 'salida comanda B',
            'salida_comanda_k_id' => 'salida comanda K',
            'puntoventa_cae_id' => 'punto de venta electrónico',
            'puntoventa_caea_id' => 'punto de venta CAEA',
            'tipotransaccion_id' => 'tipo de factura',
            'tipotransaccion_nota_credito_id' => 'tipo de nota de crédito',
        ];
    }
}
