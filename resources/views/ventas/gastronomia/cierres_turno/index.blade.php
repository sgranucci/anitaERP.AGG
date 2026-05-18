@extends("theme.$theme.layout")
@section('titulo')
    Cierres de turno gastronomía
@endsection

@section("scripts")
<script src="{{asset("assets/pages/scripts/admin/index.js")}}" type="text/javascript"></script>
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Cierres de turno gastronomía</h3>
                <div class="card-tools">
                    <a href="{{ route('gastronomia_habilitacion_turno') }}" class="btn btn-outline-secondary btn-sm">
                        <i class="fa fa-key"></i> Habilitación de turno
                    </a>
                </div>
            </div>
            <div class="card-body">
                <form action="{{ route('gastronomia_cierres_turno') }}" method="GET" class="form-row align-items-end mb-3">
                    <div class="form-group col-md-2">
                        <label class="small">Empresa</label>
                        <select name="empresa_id" class="form-control form-control-sm">
                            <option value="">Todas</option>
                            @foreach ($empresa_query as $emp)
                                <option value="{{ $emp->id }}" @selected((int) ($filtros['empresa_id'] ?? 0) === (int) $emp->id)>{{ $emp->nombre }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group col-md-2">
                        <label class="small">PC</label>
                        <input type="text" name="identificador_pc" class="form-control form-control-sm" value="{{ $filtros['identificador_pc'] ?? $identificador_pc_default }}" placeholder="Terminal"/>
                    </div>
                    <div class="form-group col-md-2">
                        <label class="small">Desde</label>
                        <input type="date" name="fecha_desde" class="form-control form-control-sm" value="{{ $filtros['fecha_desde'] ?? '' }}"/>
                    </div>
                    <div class="form-group col-md-2">
                        <label class="small">Hasta</label>
                        <input type="date" name="fecha_hasta" class="form-control form-control-sm" value="{{ $filtros['fecha_hasta'] ?? '' }}"/>
                    </div>
                    <div class="form-group col-md-2">
                        <label class="small">Tipo</label>
                        <select name="tipo" class="form-control form-control-sm">
                            <option value="" @selected(($filtros['tipo'] ?? '') === '')>Todos</option>
                            <option value="parcial" @selected(($filtros['tipo'] ?? '') === 'parcial')>Cierre parcial</option>
                            <option value="cierre" @selected(($filtros['tipo'] ?? '') === 'cierre')>Cierre definitivo</option>
                        </select>
                    </div>
                    <div class="form-group col-md-2">
                        <button type="submit" class="btn btn-primary btn-sm"><i class="fa fa-search"></i> Buscar</button>
                    </div>
                </form>

                <div class="mb-2">
                    @include('includes.exportar-tabla-queryparams', [
                        'ruta' => 'listar_gastronomia_cierres_turno',
                        'queryparams' => $filtros ?? [],
                    ])
                </div>

                <div class="table-responsive">
                    <table class="table table-striped table-bordered table-hover" id="tabla-data">
                        <thead>
                            <tr>
                                <th>Tipo</th>
                                <th>Fecha / hora</th>
                                <th>Referencia</th>
                                <th>Empresa</th>
                                <th>PC</th>
                                <th>Turno</th>
                                <th>Jornada</th>
                                <th>Usuario</th>
                                <th class="text-right">Total</th>
                                <th class="width80" data-orderable="false">PDF</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($filas as $f)
                            <tr>
                                <td>{{ $f->tipo_etiqueta }}</td>
                                <td>{{ $f->fecha_hora }}</td>
                                <td>{{ $f->referencia }}</td>
                                <td>{{ $f->nombreempresa }}</td>
                                <td>{{ $f->identificador_pc }}</td>
                                <td>{{ $f->turno_nombre }}</td>
                                <td>{{ $f->fecha_jornada }}</td>
                                <td>{{ $f->usuario }}</td>
                                <td class="text-right">${{ number_format((float) $f->total, 2, ',', '.') }}</td>
                                <td>
                                    @if ($f->tipo === 'parcial')
                                        <a href="{{ route('gastronomia_cierre_turno_comprobante_parcial', ['id' => $f->id]) }}" target="_blank" class="btn-accion-tabla tooltipsC" title="Comprobante PDF">
                                            <i class="fa fa-file-pdf-o text-danger"></i>
                                        </a>
                                    @else
                                        <a href="{{ route('gastronomia_cierre_turno_comprobante_cierre', ['id' => $f->id]) }}" target="_blank" class="btn-accion-tabla tooltipsC" title="Comprobante PDF">
                                            <i class="fa fa-file-pdf-o text-danger"></i>
                                        </a>
                                    @endif
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
