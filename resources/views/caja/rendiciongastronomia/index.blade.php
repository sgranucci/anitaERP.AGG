@extends("theme.$theme.layout")
@section('titulo')
    Rendiciones gastronomía
@endsection

@section("scripts")
<script src="{{ asset('assets/pages/scripts/admin/index.js') }}" type="text/javascript"></script>
<script>
    function eliminarRendicionGastronomia(event) {
        if (!confirm('¿Eliminar esta rendición de gastronomía?')) {
            event.preventDefault();
        }
    }
</script>
@endsection

<?php use App\Helpers\biblioteca ?>

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Rendiciones gastronomía</h3>
                <div class="d-md-flex justify-content-md-end flex-wrap align-items-end">
                    <form action="{{ route('rendiciongastronomia') }}" method="GET" class="d-flex flex-wrap align-items-end mb-2 mb-md-0">
                        <div class="form-group mb-0 mr-2">
                            <label for="fecha_desde_rg" class="small text-muted mb-0 d-block">Desde (rendición o jornada)</label>
                            <input type="date" id="fecha_desde_rg" name="fecha_desde" class="form-control form-control-sm"
                                   value="{{ $filtros['fecha_desde'] ?? '' }}">
                        </div>
                        <div class="form-group mb-0 mr-2">
                            <label for="fecha_hasta_rg" class="small text-muted mb-0 d-block">Hasta (rendición o jornada)</label>
                            <input type="date" id="fecha_hasta_rg" name="fecha_hasta" class="form-control form-control-sm"
                                   value="{{ $filtros['fecha_hasta'] ?? '' }}">
                        </div>
                        <div class="form-group mb-0 mr-2">
                            <label for="busqueda_rg" class="small text-muted mb-0 d-block">Búsqueda</label>
                            <input type="text" id="busqueda_rg" name="busqueda" class="form-control form-control-sm"
                                   placeholder="Ticket, ID, turno…" value="{{ $filtros['busqueda'] ?? '' }}" style="min-width:160px;">
                        </div>
                        <div class="form-group mb-0">
                            <label class="small text-muted mb-0 d-block">&nbsp;</label>
                            <button type="submit" class="btn btn-default btn-sm">
                                <span class="fa fa-search"></span>
                            </button>
                        </div>
                    </form>
                    @if (can('crear-rendicion-gastronomia-caja', false))
                    <div class="form-group mb-0 ml-md-2 mb-2 mb-md-0">
                        <label class="small text-muted mb-0 d-block">&nbsp;</label>
                        <a href="{{ route('crear_rendiciongastronomia') }}" class="btn btn-outline-secondary btn-sm">
                            <i class="fa fa-fw fa-plus-circle"></i> Nuevo registro
                        </a>
                    </div>
                    @endif
                </div>
            </div>
            <div class="card-body table-responsive p-0">
                @include('includes.exportar-tabla-queryparams', [
                    'ruta' => 'listar_rendiciongastronomia',
                    'queryparams' => $filtros ?? [],
                ])
                <table class="table table-striped table-bordered table-hover" id="tabla-paginada">
                    <thead>
                        <tr>
                            <th class="width20">ID</th>
                            <th>Ticket</th>
                            <th>Fecha rendición</th>
                            <th>Empresa</th>
                            <th>Caja</th>
                            <th>Turno op.</th>
                            <th>Jornada</th>
                            <th class="text-right">Cobrado</th>
                            <th class="width80" data-orderable="false"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rendiciones as $row)
                        <tr>
                            <td>{{ $row->id }}</td>
                            <td>{{ $row->codigo }}</td>
                            <td>{{ $row->fecharendicion?->format('d/m/Y H:i') }}</td>
                            <td>{{ $row->empresa?->nombre }}</td>
                            <td>{{ $row->caja?->nombre }}</td>
                            <td>
                                #{{ $row->turno_operativo_gastronomia_id }}
                                @if ($row->turnoOperativo?->turno?->nombre)
                                    — {{ $row->turnoOperativo->turno->nombre }}
                                @endif
                            </td>
                            <td>{{ $row->turnoOperativo?->jornada?->fecha_jornada?->format('d/m/Y') }}</td>
                            <td class="text-right">${{ number_format((float) $row->totalcobrado, 2, ',', '.') }}</td>
                            <td>
                                @if (
                                    can('editar-rendicion-gastronomia-caja', false)
                                    && \App\Support\Caja\RendicionGastronomiaCajaPermiso::puedeActualizarPorFecha($row)
                                )
                                <a href="{{ route('editar_rendiciongastronomia', ['id' => $row->id]) }}" class="btn-accion-tabla tooltipsC" title="Editar este registro">
                                    <i class="fa fa-edit"></i>
                                </a>
                                @endif
                                @if (can('listar-rendicion-gastronomia-caja', false) || can('editar-rendicion-gastronomia-caja', false))
                                <a href="{{ route('imprimir_rendicion_gastronomia', ['id' => $row->id, 'inline' => 1]) }}" class="btn-accion-tabla tooltipsC" title="Ver PDF rendición" target="_blank" rel="noopener">
                                    <i class="fa fa-print"></i>
                                </a>
                                @endif
                                @if ($row->turno_operativo_gastronomia_id)
                                <a href="{{ route('gastronomia_cierre_turno_comprobante_cierre', ['id' => $row->turno_operativo_gastronomia_id, 'inline' => 1]) }}"
                                   class="btn-accion-tabla tooltipsC" title="Ver comprobante cierre turno" target="_blank">
                                    <i class="fa fa-file-pdf-o text-danger"></i>
                                </a>
                                @endif
                                @if (can('borrar-rendicion-gastronomia-caja', false))
                                <form action="{{ route('eliminar_rendiciongastronomia', ['id' => $row->id]) }}" class="d-inline form-eliminar" method="POST">
                                    @csrf @method('delete')
                                    <button type="submit" onclick="eliminarRendicionGastronomia(event)" class="btn-accion-tabla eliminar tooltipsC" title="Eliminar este registro">
                                        <i class="fa fa-times-circle text-danger"></i>
                                    </button>
                                </form>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if (method_exists($rendiciones, 'links'))
            <div class="card-footer">
                {{ $rendiciones->appends(array_filter($filtros ?? [], fn ($v) => $v !== null && $v !== ''))->links() }}
            </div>
            @endif
        </div>
    </div>
</div>
@endsection
