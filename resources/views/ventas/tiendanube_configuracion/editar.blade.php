@extends("theme.$theme.layout")

@section('titulo')
Configuraci&oacute;n Tiendanube
@endsection

@section('styles')
<style>
    #form-config-tiendanube .tn-cfg-hint {
        font-size: .8rem;
        color: #6c757d;
    }
    #form-config-tiendanube .tn-cfg-kbd {
        display: inline-block;
        padding: .05rem .35rem;
        font-size: .72rem;
        font-family: inherit;
        background: #f4f6f9;
        border: 1px solid #ced4da;
        border-radius: 3px;
        color: #495057;
    }
    #form-config-tiendanube .card-outline > .card-header {
        background: #f8fafc;
    }
    #form-config-tiendanube #tabla-tn-pv-dep td,
    #form-config-tiendanube #tabla-tn-gateway td {
        vertical-align: middle;
        padding: .4rem .5rem;
    }
    #form-config-tiendanube .tn-gateway-key {
        font-family: SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
        font-size: .85rem;
    }
    #form-config-tiendanube .tm-puntoventa-campo .form-group,
    #form-config-tiendanube .tm-deposito-campo.form-group,
    #form-config-tiendanube .tm-cuentacaja-campo.form-group {
        margin-bottom: 0;
    }
</style>
@endsection

@section('scripts')
<script src="{{ asset('assets/pages/scripts/stock/depmae/consulta.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/ventas/puntoventa/consulta.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/caja/cuentacaja/consulta.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/stock/listaprecio/consulta.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/ventas/tiendanube_configuracion/editar.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/ventas/tiendanube_configuracion/editar.js')) ?: time() }}" type="text/javascript"></script>
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.form-error')
        @include('includes.mensaje')

        <div class="card card-primary">
            <div class="card-header">
                <h3 class="card-title"><i class="fa fa-cloud"></i> Configuraci&oacute;n Tiendanube</h3>
                <div class="card-tools">
                    <a href="{{ route('tiendanube_pedidos') }}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-reply-all"></i> Pedidos Tiendanube
                    </a>
                </div>
            </div>
            <form action="{{ route('actualizar_configuracion_tiendanube') }}" method="POST" id="form-config-tiendanube" class="form-horizontal" autocomplete="off">
                @csrf
                @method('PUT')
                <div class="card-body">
                    <div class="alert alert-light border mb-3 py-2">
                        <div class="d-flex flex-wrap align-items-center" style="gap:.5rem 1rem;">
                            <span class="tn-cfg-hint mb-0">
                                <i class="fa fa-keyboard-o"></i>
                                En c&oacute;digos de consulta:
                                <span class="tn-cfg-kbd">F1</span> / lupa = modal &middot;
                                <span class="tn-cfg-kbd">Enter</span> = validar y avanzar &middot;
                                <span class="tn-cfg-kbd">Enter</span> en el buscador del modal = primera fila
                            </span>
                            <span class="tn-cfg-hint mb-0">
                                Token API en <code>.env</code>:
                                <code>TIENDANUBE_ACCESS_TOKEN</code> (Ferli) y
                                <code>TIENDANUBE_BOAONDA_ACCESS_TOKEN</code> (Boaonda)
                            </span>
                        </div>
                    </div>

                    <div class="card card-outline card-secondary mb-3">
                        <div class="card-header py-2">
                            <strong><i class="fa fa-sliders"></i> Datos generales</strong>
                        </div>
                        <div class="card-body pb-2">
                            @include('includes.form-empresa-asignada', [
                                'empresa_query' => $empresa_query,
                                'empresa_id' => old('empresa_id', $config->empresa_id),
                                'col_label' => 'col-lg-3 control-label text-right pr-2',
                                'col_input' => 'col-lg-8',
                            ])

                            @include('stock.partials.campo_consulta_listaprecio', [
                                'prefix' => 'tn_lista',
                                'listaprecioId' => old('listaprecio_id', $config->listaprecio_id),
                                'codigo' => old('listaprecio_codigo', $lista->codigo ?? ''),
                                'nombre' => old('listaprecio_nombre', $lista->nombre ?? ''),
                                'col_label' => 'col-lg-3 control-label text-right pr-2',
                                'col_input' => 'col-lg-8',
                            ])

                            <div class="form-group row">
                                <label for="articulo_envio_sku" class="col-lg-3 control-label text-right pr-2">SKU env&iacute;o</label>
                                <div class="col-lg-3">
                                    <input type="text" name="articulo_envio_sku" id="articulo_envio_sku" class="form-control"
                                        value="{{ old('articulo_envio_sku', $config->articulo_envio_sku) }}" maxlength="40"
                                        placeholder="ej. FL">
                                </div>
                                <label for="articulo_descuento_sku" class="col-lg-2 control-label text-right pr-2">SKU descuento</label>
                                <div class="col-lg-3">
                                    <input type="text" name="articulo_descuento_sku" id="articulo_descuento_sku" class="form-control"
                                        value="{{ old('articulo_descuento_sku', $config->articulo_descuento_sku) }}" maxlength="40"
                                        placeholder="Vac&iacute;o = pie">
                                </div>
                            </div>
                            <div class="form-group row mb-0">
                                <label for="usocuentacaja_nombre" class="col-lg-3 control-label text-right pr-2">Uso cuentas de caja</label>
                                <div class="col-lg-4">
                                    <input type="text" name="usocuentacaja_nombre" id="usocuentacaja_nombre" class="form-control"
                                        value="{{ old('usocuentacaja_nombre', $config->usocuentacaja_nombre ?: 'TIENDA NUBE') }}" maxlength="80">
                                    <small class="form-text text-muted">Debe coincidir con el uso del ABM Cuentas de caja.</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <input type="hidden" name="puntoventa_id" id="puntoventa_id" value="{{ old('puntoventa_id', $config->puntoventa_id) }}">
                    <input type="hidden" name="deposito_id" id="deposito_id" value="{{ old('deposito_id', $config->deposito_id) }}">

                    <div class="card card-outline card-info mb-3">
                        <div class="card-header py-2 d-flex flex-wrap align-items-center justify-content-between">
                            <div>
                                <strong><i class="fa fa-store"></i> Pares punto de venta / dep&oacute;sito</strong>
                                <span class="tn-cfg-hint ml-2">Locales online al facturar. El marcado Default se usa al sincronizar.</span>
                            </div>
                        </div>
                        <div class="card-body p-2">
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered table-hover mb-2" id="tabla-tn-pv-dep">
                                    <thead style="background:#85C1E9;color:#17202A;">
                                        <tr>
                                            <th style="width:4.5rem;" class="text-center">Default</th>
                                            <th style="min-width:16rem;">Punto de venta</th>
                                            <th style="min-width:16rem;">Dep&oacute;sito</th>
                                            <th style="width:3.5rem;"></th>
                                        </tr>
                                    </thead>
                                    <tbody id="tbody-tn-pv-dep">
                                        @php
                                            $paresUi = old('par_puntoventa_id')
                                                ? collect(old('par_puntoventa_id'))->map(function ($pvId, $i) {
                                                    return (object) [
                                                        'puntoventa_id' => $pvId,
                                                        'deposito_id' => old('par_deposito_id.'.$i),
                                                        'es_default' => (int) old('par_default') === (int) $i,
                                                        'puntoventa' => \App\Models\Ventas\Puntoventa::find($pvId),
                                                        'deposito' => \App\Models\Stock\Depmae::find(old('par_deposito_id.'.$i)),
                                                    ];
                                                })
                                                : $pares;
                                            if ($paresUi->isEmpty()) {
                                                $paresUi = collect([(object) ['puntoventa_id' => '', 'deposito_id' => '', 'es_default' => true, 'puntoventa' => null, 'deposito' => null]]);
                                            }
                                        @endphp
                                        @foreach ($paresUi as $idx => $par)
                                            @include('ventas.tiendanube_configuracion.partials.fila_pv_deposito', ['idx' => $idx, 'par' => $par])
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-primary" id="tn-pv-dep-agregar">
                                <i class="fa fa-plus"></i> Agregar par
                            </button>
                        </div>
                    </div>

                    <div class="card card-outline card-info mb-0">
                        <div class="card-header py-2">
                            <strong><i class="fa fa-credit-card"></i> Gateway &rarr; cuenta de caja</strong>
                            <span class="tn-cfg-hint ml-2">Clave API TN (ej. <code>gocuotas</code>, <code>pago-nube</code>) o alias.</span>
                        </div>
                        <div class="card-body p-2">
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered table-hover mb-2" id="tabla-tn-gateway">
                                    <thead style="background:#85C1E9;color:#17202A;">
                                        <tr>
                                            <th style="width:14rem;">Gateway / clave</th>
                                            <th>Cuenta de caja</th>
                                            <th style="width:3.5rem;"></th>
                                        </tr>
                                    </thead>
                                    <tbody id="tbody-tn-gateway">
                                        @php
                                            $gwUi = old('gateway_key')
                                                ? collect(old('gateway_key'))->map(function ($key, $i) {
                                                    $cid = old('gateway_cuentacaja_id.'.$i);
                                                    return (object) [
                                                        'gateway_key' => $key,
                                                        'cuentacaja_id' => $cid,
                                                        'cuentacaja' => \App\Models\Caja\Cuentacaja::find($cid),
                                                    ];
                                                })
                                                : $gateways;
                                            if ($gwUi->isEmpty()) {
                                                $gwUi = collect([(object) ['gateway_key' => '', 'cuentacaja_id' => '', 'cuentacaja' => null]]);
                                            }
                                        @endphp
                                        @foreach ($gwUi as $idx => $gw)
                                            @include('ventas.tiendanube_configuracion.partials.fila_gateway', ['idx' => $idx, 'gw' => $gw])
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-primary" id="tn-gateway-agregar">
                                <i class="fa fa-plus"></i> Agregar gateway
                            </button>
                        </div>
                    </div>
                </div>
                <div class="card-footer">
                    <button type="submit" class="btn btn-primary">
                        <i class="fa fa-save"></i> Guardar
                    </button>
                    <a href="{{ route('tiendanube_pedidos') }}" class="btn btn-outline-secondary">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
</div>

@include('includes.stock.modalconsultadeposito')
@include('includes.ventas.modalconsultapuntoventa')
@include('includes.caja.modalconsultacuentacaja')
@include('includes.stock.modalconsultalistaprecio')

<template id="tn-template-fila-pv-dep">
    @include('ventas.tiendanube_configuracion.partials.fila_pv_deposito', [
        'idx' => '__IDX__',
        'par' => (object) ['puntoventa_id' => '', 'deposito_id' => '', 'es_default' => false, 'puntoventa' => null, 'deposito' => null],
    ])
</template>
<template id="tn-template-fila-gateway">
    @include('ventas.tiendanube_configuracion.partials.fila_gateway', [
        'idx' => '__IDX__',
        'gw' => (object) ['gateway_key' => '', 'cuentacaja_id' => '', 'cuentacaja' => null],
    ])
</template>
@endsection
