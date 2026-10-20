@extends("theme.$theme.layout")
@section('titulo')
    Cruce visitas por OC
@endsection

@section('contenido')
@php
    $k = $resultado['kpis'] ?? [];
    $colLabel = 'col-lg-2 control-label text-right pr-2';
    $colInput = 'col-lg-4';
@endphp
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Cruce de visitas por orden de compra</h3>
            </div>
            <div class="card-body">
                <p class="text-muted mb-3">
                    Cruza cada visita (ticket de ingreso) con su OC y el circuito de compras:
                    requisición, remitos (COM), factura y orden de pago.
                </p>
                <form method="get" action="{{ route('reporte_visitas_oc_cruce') }}" class="mb-4">
                    @include('includes.form-empresa-asignada', [
                        'empresa_query' => $empresa_query,
                        'empresa_id' => $filtros['empresa_id'] ?? null,
                        'required' => false,
                        'col_label' => $colLabel,
                        'col_input' => $colInput,
                    ])
                    <div class="form-group row">
                        <label for="fecha_desde" class="{{ $colLabel }}">Desde</label>
                        <div class="{{ $colInput }}">
                            <input type="date" name="fecha_desde" id="fecha_desde" class="form-control"
                                value="{{ $filtros['fecha_desde'] ?? '' }}">
                        </div>
                        <label for="fecha_hasta" class="{{ $colLabel }}">Hasta</label>
                        <div class="{{ $colInput }}">
                            <input type="date" name="fecha_hasta" id="fecha_hasta" class="form-control"
                                value="{{ $filtros['fecha_hasta'] ?? '' }}">
                        </div>
                    </div>
                    <div class="form-group row">
                        <label for="estado" class="{{ $colLabel }}">Estado</label>
                        <div class="{{ $colInput }}">
                            <select name="estado" id="estado" class="form-control">
                                <option value="">Todos</option>
                                @foreach ($estados ?? [] as $cod => $meta)
                                    <option value="{{ $cod }}" @selected(($filtros['estado'] ?? '') === $cod)>{{ $meta['label'] ?? $cod }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="{{ $colLabel }}"></div>
                        <div class="{{ $colInput }}">
                            <input type="hidden" name="solo_con_oc" value="0">
                            <div class="form-check mt-2">
                                <input type="checkbox" class="form-check-input" name="solo_con_oc" id="solo_con_oc" value="1"
                                    @if ($filtros['solo_con_oc'] ?? true) checked @endif>
                                <label class="form-check-label" for="solo_con_oc">Solo visitas con OC</label>
                            </div>
                        </div>
                    </div>
                    <div class="form-group row mb-0">
                        <div class="{{ $colLabel }}"></div>
                        <div class="col-lg-10">
                            <input type="hidden" name="consultar" value="1">
                            <button type="submit" class="btn btn-primary btn-sm"><i class="fa fa-search"></i> Consultar</button>
                        </div>
                    </div>
                </form>

                @if ($consultado && $resultado)
                    <div class="row mb-3">
                        <div class="col-md-2 col-sm-4 mb-2"><div class="small text-muted">Visitas</div><div class="h5 mb-0">{{ $k['visitas'] ?? 0 }}</div></div>
                        <div class="col-md-2 col-sm-4 mb-2"><div class="small text-muted">Con OC</div><div class="h5 mb-0">{{ $k['con_oc'] ?? 0 }}</div></div>
                        <div class="col-md-2 col-sm-4 mb-2"><div class="small text-muted">Con RQ</div><div class="h5 mb-0">{{ $k['con_requisicion'] ?? 0 }}</div></div>
                        <div class="col-md-2 col-sm-4 mb-2"><div class="small text-muted">Con factura</div><div class="h5 mb-0">{{ $k['con_factura'] ?? 0 }}</div></div>
                        <div class="col-md-2 col-sm-4 mb-2"><div class="small text-muted">Con OP</div><div class="h5 mb-0">{{ $k['con_op'] ?? 0 }}</div></div>
                        <div class="col-md-2 col-sm-4 mb-2"><div class="small text-muted">Sin OC</div><div class="h5 mb-0">{{ $k['sin_oc'] ?? 0 }}</div></div>
                    </div>

                    @include('includes.exportar-tabla-queryparams', [
                        'ruta' => 'listar_reporte_visitas_oc_cruce',
                        'queryparams' => $filtrosQuery ?? [],
                    ])

                    <div class="table-responsive">
                        <table id="tabla-paginada" class="table table-bordered table-hover table-sm mb-0" style="font-size: 0.8rem;">
                            @include('seguridad.visitas_oc_cruce_reporte.partials.tabla_datos', [
                                'filas' => $filasPaginadas ?? [],
                                'en_pantalla' => true,
                                'puede_ver_ticket' => $puede_ver_ticket ?? false,
                                'puede_ver_oc' => $puede_ver_oc ?? false,
                                'puede_ver_proveedor' => $puede_ver_proveedor ?? false,
                                'puede_ver_empresa' => $puede_ver_empresa ?? false,
                                'puede_ver_requisicion' => $puede_ver_requisicion ?? false,
                                'puede_ver_factura' => $puede_ver_factura ?? false,
                                'puede_ver_op' => $puede_ver_op ?? false,
                                'puede_ver_com' => $puede_ver_com ?? false,
                                'solo_thead_tbody' => true,
                            ])
                        </table>
                    </div>
                    @if ($filasPaginadas instanceof \Illuminate\Pagination\LengthAwarePaginator)
                        <div class="mt-2">{{ $filasPaginadas->withQueryString()->links() }}</div>
                    @endif
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
