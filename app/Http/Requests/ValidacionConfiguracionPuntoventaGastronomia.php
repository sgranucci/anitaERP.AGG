<?php

namespace App\Http\Requests;

use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\Ventas\GastronomiaCrown\GastronomiaCrownPosModos;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ValidacionConfiguracionPuntoventaGastronomia extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->input('ubicacion_id') === '' || $this->input('ubicacion_id') === '0') {
            $this->merge(['ubicacion_id' => null]);
        }
        if ($this->input('waitry_mesa_gastronomia_id') === '' || $this->input('waitry_mesa_gastronomia_id') === '0') {
            $this->merge(['waitry_mesa_gastronomia_id' => null]);
        }
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
        // caja_id no aplica a PC de gastronomía (sí a estacionamiento/bingo).
        $input = $this->all();
        unset($input['caja_id']);
        if (! EntornoEmpresaSupport::esCrown()) {
            unset(
                $input['modo_pos'],
                $input['politica_comanda'],
                $input['solo_marcha'],
                $input['habilita_hotel'],
                $input['habilita_canje_gst'],
                $input['pide_cubiertos'],
                $input['pide_mozo_manual'],
                $input['sala_id'],
                $input['listaprecio_hotel_id'],
                $input['salida_hotel_id'],
                $input['salida_comanda_b_id'],
                $input['salida_comanda_k_id'],
                $input['plano_ids'],
                $input['plano_orden'],
            );
        }
        $this->replace($input);
    }

    public function rules()
    {
        $id = $this->route('id');
        $empresaId = (int) $this->input('empresa_id');
        $esCrown = EntornoEmpresaSupport::esCrown();

        $rules = [
            'identificador_pc' => [
                'required',
                'max:100',
                Rule::unique('configuracion_puntoventa_gastronomia', 'identificador_pc')
                    ->where(fn ($q) => $q->where('empresa_id', $empresaId))
                    ->ignore($id),
            ],
            'descripcion' => 'nullable|max:255',
            'empresa_id' => 'required|exists:empresa,id',
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
            'ubicacion_id' => 'nullable|exists:ubicaciones_gastronomia,id',
            'salida_comanda_id' => 'required|exists:salida,id',
            'salida_factura_id' => 'required|exists:salida,id',
            'listaprecio_id' => 'required|exists:listaprecio,id',
            'deposito_venta_id' => [
                'required',
                Rule::exists('depmae', 'id')->where(function ($query) use ($empresaId) {
                    $query->where('empresa_id', $empresaId);
                }),
            ],
            'deposito_insumos_id' => [
                'required',
                Rule::exists('depmae', 'id')->where(function ($query) use ($empresaId) {
                    $query->where('empresa_id', $empresaId);
                }),
            ],
            'tipotransaccion_id' => 'required|exists:tipotransaccion,id',
            'tipotransaccion_nota_credito_id' => 'nullable|exists:tipotransaccion,id',
            'tipotransaccion_caja_id' => 'required|exists:tipotransaccion_caja,id',
            'waitry_habilitado' => 'required|in:0,1',
            'waitry_mesa_gastronomia_id' => [
                'nullable',
                Rule::exists('waitry_mesa_gastronomia', 'id')->where(function ($query) use ($empresaId) {
                    $query->where('empresa_id', $empresaId);
                }),
            ],
        ];

        if ($esCrown) {
            $rules['modo_pos'] = ['required', Rule::in(array_keys(GastronomiaCrownPosModos::modos()))];
            $rules['politica_comanda'] = ['required', Rule::in(array_keys(GastronomiaCrownPosModos::politicasComanda()))];
            $rules['solo_marcha'] = 'nullable|boolean';
            $rules['habilita_hotel'] = 'nullable|boolean';
            $rules['habilita_canje_gst'] = 'nullable|boolean';
            $rules['pide_cubiertos'] = 'nullable|boolean';
            $rules['pide_mozo_manual'] = 'nullable|boolean';
            $rules['sala_id'] = 'nullable|exists:sala,id';
            $rules['listaprecio_hotel_id'] = 'nullable|exists:listaprecio,id';
            $rules['salida_hotel_id'] = 'nullable|exists:salida,id';
            $rules['salida_comanda_b_id'] = 'nullable|exists:salida,id';
            $rules['salida_comanda_k_id'] = 'nullable|exists:salida,id';
            $rules['plano_ids'] = 'nullable|array';
            $rules['plano_ids.*'] = [
                Rule::exists('gastro_plano_mesa', 'id')->where(fn ($q) => $q->where('empresa_id', $empresaId)),
            ];
            $rules['plano_orden'] = 'nullable|array';
            $rules['plano_orden.*'] = 'nullable|integer|min:0|max:999';
        }

        return $rules;
    }

    public function attributes()
    {
        return [
            'identificador_pc' => 'identificador de PC',
            'puntoventa_cae_id' => 'punto de venta CAE',
            'puntoventa_caea_id' => 'punto de venta CAEA',
            'ubicacion_id' => 'ubicación',
            'salida_comanda_id' => 'salida de comandas',
            'salida_factura_id' => 'salida de facturas',
            'listaprecio_id' => 'lista de precios',
            'deposito_venta_id' => 'depósito de artículos facturados',
            'deposito_insumos_id' => 'depósito de descuento de insumos',
            'tipotransaccion_id' => 'tipo de transacción (factura)',
            'tipotransaccion_nota_credito_id' => 'tipo de transacción (nota de crédito)',
            'tipotransaccion_caja_id' => 'tipo de transacción de caja (cobranza)',
            'waitry_habilitado' => 'integración Waitry',
            'waitry_mesa_gastronomia_id' => 'mesa Waitry',
            'modo_pos' => 'modo POS',
            'politica_comanda' => 'política de comanda',
            'sala_id' => 'sala',
            'listaprecio_hotel_id' => 'lista de precios hotel',
            'salida_hotel_id' => 'salida hotel',
            'salida_comanda_b_id' => 'salida comanda B',
            'salida_comanda_k_id' => 'salida comanda K',
        ];
    }
}
