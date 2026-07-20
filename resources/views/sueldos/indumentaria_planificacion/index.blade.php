@extends("theme.$theme.layout")
@section('titulo')
    Planificación de indumentaria
@endsection

@section('contenido')
@php
    $esTodas = ($filtros['empresa_scope'] ?? 'una') === 'todas';
    $exportQuery = http_build_query($filtrosQuery ?? []);
@endphp
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Planificación / compra sugerida de indumentaria</h3>
            </div>
            <div class="card-body">
                <p class="text-muted mb-3">
                    Cruza la <strong>dotación</strong> de los empleados activos (agrupamiento &times; sexo) con lo
                    <strong>entregado</strong> (vigente para EPP con vida &uacute;til, o del a&ntilde;o para prendas anuales) y el
                    <strong>stock</strong> del dep&oacute;sito de origen, y sugiere la compra aplicando el % de pedido de cada prenda.
                </p>

                <form method="get" action="{{ route('planificacion_indumentaria') }}" class="mb-3">
                    <input type="hidden" name="consultar" value="1">
                    <div class="form-row">
                        <div class="col-lg-5">
                            @include('includes.form-empresa-asignada', [
                                'empresa_query' => $empresa_query,
                                'empresa_id' => $filtros['empresa_id'] ?? null,
                                'required' => false,
                                'permite_vacio' => true,
                                'opcion_vacia' => '— Elegir empresa —',
                                'col_label' => 'col-lg-3',
                                'col_input' => 'col-lg-9',
                            ])
                        </div>
                        <div class="col-lg-3">
                            <div class="custom-control custom-checkbox mt-2">
                                <input type="checkbox" class="custom-control-input" name="empresa_todas" id="empresa_todas" value="1" {{ $esTodas ? 'checked' : '' }}>
                                <label class="custom-control-label" for="empresa_todas">Todas mis empresas</label>
                            </div>
                        </div>
                    </div>
                    <div class="form-row mt-2">
                        <div class="form-group col-lg-4 col-sm-6 mb-2">
                            <label for="agrupamiento_id" class="small mb-1">Agrupamiento</label>
                            <select name="agrupamiento_id" id="agrupamiento_id" class="form-control form-control-sm">
                                <option value="">— Todos —</option>
                                @foreach ($agrupamientos as $a)
                                    <option value="{{ $a->id }}" {{ (int) ($filtros['agrupamiento_id'] ?? 0) === (int) $a->id ? 'selected' : '' }}>{{ $a->descripcion }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group col-lg-4 col-sm-6 mb-2">
                            <label for="prenda_id" class="small mb-1">Prenda</label>
                            <select name="prenda_id" id="prenda_id" class="form-control form-control-sm">
                                <option value="">— Todas —</option>
                                @foreach ($prendas as $p)
                                    <option value="{{ $p->id }}" {{ (int) ($filtros['prenda_id'] ?? 0) === (int) $p->id ? 'selected' : '' }}>{{ $p->codigo }} - {{ $p->descripcion }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group col-lg-2 col-sm-6 mb-2">
                            <label for="sexo" class="small mb-1">Sexo</label>
                            <select name="sexo" id="sexo" class="form-control form-control-sm">
                                <option value="">Ambos</option>
                                <option value="M" {{ ($filtros['sexo'] ?? '') === 'M' ? 'selected' : '' }}>Masculino</option>
                                <option value="F" {{ ($filtros['sexo'] ?? '') === 'F' ? 'selected' : '' }}>Femenino</option>
                            </select>
                        </div>
                        <div class="form-group col-lg-2 col-sm-6 mb-2 d-flex align-items-end flex-column">
                            <div class="custom-control custom-checkbox mb-1">
                                <input type="checkbox" class="custom-control-input" name="solo_epp" id="solo_epp" value="1" {{ !empty($filtros['solo_epp']) ? 'checked' : '' }}>
                                <label class="custom-control-label" for="solo_epp">Solo EPP</label>
                            </div>
                            <div class="custom-control custom-checkbox">
                                <input type="checkbox" class="custom-control-input" name="solo_sugerido" id="solo_sugerido" value="1" {{ !empty($filtros['solo_sugerido']) ? 'checked' : '' }}>
                                <label class="custom-control-label" for="solo_sugerido">Solo con sugerido</label>
                            </div>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="col-lg-12">
                            <button type="submit" class="btn btn-primary btn-sm"><i class="fa fa-search"></i> Consultar</button>
                            <a href="{{ route('planificacion_indumentaria') }}" class="btn btn-outline-secondary btn-sm"><i class="fa fa-eraser"></i> Limpiar</a>
                        </div>
                    </div>
                </form>

                @if ($consultado)
                    <div class="d-flex flex-wrap align-items-center mb-2">
                        <span class="badge badge-info mr-1">Prendas: {{ $totales['prendas'] ?? 0 }}</span>
                        <span class="badge badge-secondary mr-1">Pendiente: {{ number_format($totales['pendiente'] ?? 0, 2, ',', '.') }}</span>
                        <span class="badge badge-secondary mr-1">Stock: {{ number_format($totales['stock'] ?? 0, 2, ',', '.') }}</span>
                        <span class="badge badge-danger mr-3">Sugerido total: {{ $totales['sugerido'] ?? 0 }}</span>
                    </div>

                    <div class="mb-3">
                        <a href="{{ route('listar_planificacion_indumentaria', ['formato' => 'PDF']) }}{{ $exportQuery ? '?'.$exportQuery : '' }}" class="btn btn-app bg-danger"><i class="fas fa-file-pdf"></i> Pdf</a>
                        <a href="{{ route('listar_planificacion_indumentaria', ['formato' => 'EXCEL']) }}{{ $exportQuery ? '?'.$exportQuery : '' }}" class="btn btn-app bg-success"><i class="fas fa-file-excel"></i> Excel</a>
                        <a href="{{ route('listar_planificacion_indumentaria', ['formato' => 'CSV']) }}{{ $exportQuery ? '?'.$exportQuery : '' }}" class="btn btn-app bg-warning"><i class="fas fa-file-csv"></i> Csv</a>
                    </div>

                    <style>
                        #tabla-planificacion thead tr { background-color: #85C1E9; color: #17202A; }
                        #tabla-planificacion thead th { font-weight: 600; border-color: #7fb3d5; }
                        #tabla-planificacion .num { text-align: right; }
                    </style>
                    <div class="table-responsive">
                        @include('sueldos.indumentaria_planificacion.partials.tabla_datos', ['filas' => $filas, 'totales' => $totales])
                    </div>
                @else
                    <div class="alert alert-light border">Configur&aacute; los filtros y presion&aacute; <strong>Consultar</strong>.</div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
