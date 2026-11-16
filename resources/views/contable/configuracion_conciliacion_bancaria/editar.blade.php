@extends("theme.$theme.layout")
@section('titulo')
    Configuración conciliación bancaria
@endsection

@section('scripts')
<meta name="csrf-token" content="{{ csrf_token() }}">
<script>
    window.conciliacionBancariaConfigUrls = {
        resolverConcepto: @json(route('resolver_concepto_ivacompra')),
        consultaConcepto: @json(url('compras/concepto_ivacompra/consulta')),
        leerCuentaPorCodigo: @json(url('contable/cuentacontable/leercuentacontableporcodigo')),
    };
</script>
<script src="{{ asset('assets/pages/scripts/contable/cuentacontable/consulta.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/contable/cuentacontable/consulta.js')) ?: time() }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/contable/configuracion_conciliacion_bancaria/form.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/contable/configuracion_conciliacion_bancaria/form.js')) ?: time() }}" type="text/javascript"></script>
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <form method="POST" action="{{ route('actualizar_configuracion_conciliacion_bancaria') }}" class="form-horizontal" data-enter-campos="1">
            @csrf
            @method('PUT')

            <div class="card card-primary">
                <div class="card-header">
                    <h3 class="card-title">Configuración conciliación bancaria</h3>
                    <div class="card-tools">
                        <a href="{{ route('conciliacion_bancaria') }}" class="btn btn-outline-light btn-sm">
                            <i class="fa fa-reply-all"></i> Ir a conciliación
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <p class="text-muted small mb-3">
                        Estos valores mandan sobre el <code>.env</code> / config PHP. Guardar aplica reglas y todos los mapas de codificación.
                    </p>

                    <ul class="nav nav-tabs" id="tabs-config-conc" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link active" id="tab-reglas-link" data-toggle="tab" href="#tab-reglas" role="tab">Reglas</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="tab-pcc-link" data-toggle="tab" href="#tab-pcc" role="tab">P.C.C.</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="tab-concepto-link" data-toggle="tab" href="#tab-concepto" role="tab">Conceptos IB</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="tab-cod-op-link" data-toggle="tab" href="#tab-cod-op" role="tab">Cód. operación</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="tab-patron-link" data-toggle="tab" href="#tab-patron" role="tab">Patrones gasto</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="tab-tipo-comp-link" data-toggle="tab" href="#tab-tipo-comp" role="tab">Tipo comp.</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="tab-doc-gasto-link" data-toggle="tab" href="#tab-doc-gasto" role="tab">Doc. gastos</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="tab-residual-link" data-toggle="tab" href="#tab-residual" role="tab">Residuales</a>
                        </li>
                    </ul>

                    <div class="tab-content border border-top-0 p-3">
                        <div class="tab-pane fade show active" id="tab-reglas" role="tabpanel">
                            @foreach ($reglas as $regla)
                                <div class="form-group row">
                                    <label class="col-lg-4 control-label text-right pr-2" for="regla_{{ $regla['clave'] }}">
                                        {{ $regla['etiqueta'] }}
                                    </label>
                                    <div class="col-lg-4">
                                        @if ($regla['tipo'] === 'boolean')
                                            <div class="custom-control custom-switch mt-2">
                                                <input type="hidden" name="reglas[{{ $regla['clave'] }}]" value="0">
                                                <input type="checkbox"
                                                    class="custom-control-input"
                                                    id="regla_{{ $regla['clave'] }}"
                                                    name="reglas[{{ $regla['clave'] }}]"
                                                    value="1"
                                                    @checked(in_array($regla['valor'], ['1', 'true', 'on'], true))>
                                                <label class="custom-control-label" for="regla_{{ $regla['clave'] }}">Activo</label>
                                            </div>
                                        @elseif ($regla['tipo'] === 'entero')
                                            <input type="number" step="1" min="0" class="form-control"
                                                id="regla_{{ $regla['clave'] }}"
                                                name="reglas[{{ $regla['clave'] }}]"
                                                value="{{ $regla['valor'] }}">
                                        @elseif ($regla['tipo'] === 'texto')
                                            <input type="text" class="form-control"
                                                id="regla_{{ $regla['clave'] }}"
                                                name="reglas[{{ $regla['clave'] }}]"
                                                value="{{ $regla['valor'] }}"
                                                maxlength="32" autocomplete="off">
                                        @else
                                            <input type="number" step="0.01" min="0" class="form-control"
                                                id="regla_{{ $regla['clave'] }}"
                                                name="reglas[{{ $regla['clave'] }}]"
                                                value="{{ $regla['valor'] }}">
                                        @endif
                                        <small class="form-text text-muted">{{ $regla['ayuda'] }}</small>
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <div class="tab-pane fade" id="tab-pcc" role="tabpanel">
                            @include('contable.configuracion_conciliacion_bancaria.partials.tabla_pcc')
                        </div>
                        <div class="tab-pane fade" id="tab-concepto" role="tabpanel">
                            @include('contable.configuracion_conciliacion_bancaria.partials.tabla_concepto')
                        </div>
                        <div class="tab-pane fade" id="tab-cod-op" role="tabpanel">
                            @include('contable.configuracion_conciliacion_bancaria.partials.tabla_cod_op')
                        </div>
                        <div class="tab-pane fade" id="tab-patron" role="tabpanel">
                            @include('contable.configuracion_conciliacion_bancaria.partials.tabla_patron_gasto')
                        </div>
                        <div class="tab-pane fade" id="tab-tipo-comp" role="tabpanel">
                            @include('contable.configuracion_conciliacion_bancaria.partials.tabla_tipo_comp')
                        </div>
                        <div class="tab-pane fade" id="tab-doc-gasto" role="tabpanel">
                            @include('contable.configuracion_conciliacion_bancaria.partials.tabla_doc_gasto')
                        </div>
                        <div class="tab-pane fade" id="tab-residual" role="tabpanel">
                            @include('contable.configuracion_conciliacion_bancaria.partials.tabla_residual')
                        </div>
                    </div>
                </div>
                <div class="card-footer">
                    <button type="submit" class="btn btn-primary">
                        <i class="fa fa-save"></i> Guardar
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<template id="template-renglon-pcc">
    @include('contable.configuracion_conciliacion_bancaria.partials.fila_pcc_vacia', ['i' => 999])
</template>
<template id="template-renglon-concepto">
    @include('contable.configuracion_conciliacion_bancaria.partials.fila_concepto_vacia', ['i' => 999])
</template>
<template id="template-renglon-cod-op">
    @include('contable.configuracion_conciliacion_bancaria.partials.fila_cod_op_vacia', ['i' => 999])
</template>
<template id="template-renglon-patron-gasto">
    @include('contable.configuracion_conciliacion_bancaria.partials.fila_patron_gasto_vacia', ['i' => 999])
</template>
<template id="template-renglon-tipo-comp">
    @include('contable.configuracion_conciliacion_bancaria.partials.fila_tipo_comp_vacia', ['i' => 999])
</template>
<template id="template-renglon-doc-gasto">
    @include('contable.configuracion_conciliacion_bancaria.partials.fila_doc_gasto_vacia', ['i' => 999])
</template>
<template id="template-renglon-residual">
    @include('contable.configuracion_conciliacion_bancaria.partials.fila_residual_vacia', ['i' => 999])
</template>

{{-- Bridge oculto para reutilizar el modal de cuenta contable --}}
<div class="d-none" aria-hidden="true">
    <div class="tm-cuentacontable-campo" id="bridge-cuenta-doc-gasto" data-cuentacontable-campo="1">
        <input type="hidden" class="cuentacontable_id" value="">
        <input type="text" class="codigocuentacontable" value="">
        <input type="text" class="nombrecuentacontable" value="" readonly>
        <button type="button" class="consultacuentacontable" id="bridge-btn-consulta-cuenta"></button>
    </div>
</div>
@include('includes.contable.modalconsultacuentacontable')
@include('includes.compras.modalconsultaconcepto_ivacompra')
@endsection
