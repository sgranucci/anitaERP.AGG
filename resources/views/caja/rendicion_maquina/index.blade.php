@extends("theme.$theme.layout")
@section('titulo')
    Rendiciones de m&aacute;quinas
@endsection

@section("scripts")
<style>
    #tabla-paginada thead th { background: #85C1E9; color: #17202A; }
    .rendmaq-badge-turno {
        display: inline-block;
        min-width: 1.75rem;
        text-align: center;
        font-weight: 600;
    }
    #tabla-paginada th.rendmaq-col-acciones,
    #tabla-paginada td.rendmaq-col-acciones {
        width: 7.5rem;
        min-width: 7.5rem;
        white-space: nowrap;
        text-align: center;
        vertical-align: middle;
    }
    #tabla-paginada td.rendmaq-col-acciones .btn-accion-tabla,
    #tabla-paginada td.rendmaq-col-acciones .form-eliminar {
        display: inline-block;
        vertical-align: middle;
        margin: 0 1px;
    }
</style>
<script src="{{ asset('assets/pages/scripts/admin/index.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/includes/listado-filtros.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/caja/rendicion_maquina/filtro.js') }}" type="text/javascript"></script>
@endsection

@php
    use App\Models\Caja\RendicionMaquina;
    use App\Support\Caja\RendicionMaquinaListadoFiltros;
@endphp

@section('contenido')
@php
    $retornoListadoQuery = \App\Support\Listado\QueryRetornoListado::retornoLinksDesdeFiltrosQuery($filtrosQuery ?? []);
    $limpiarUrl = route('rendicion_maquina', RendicionMaquinaListadoFiltros::paraQueryStringEmpresa($filtros ?? []));
@endphp
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Rendiciones de m&aacute;quinas</h3>
                <div class="card-tools d-flex flex-wrap align-items-center justify-content-end">
                    @include('includes.listado.filtros_toolbar', [
                        'formId' => 'form-filtros-rendicion-maquina',
                        'filtroValor' => $filtros['valor'] ?? '',
                        'tieneCriterios' => RendicionMaquinaListadoFiltros::tieneCriteriosTexto($filtros ?? []),
                        'limpiarUrl' => $limpiarUrl,
                        'placeholder' => 'B&uacute;squeda r&aacute;pida (tolera errores de tipeo)&hellip;',
                        'toggleTarget' => '#panel-filtros-rendicion-maquina',
                        'toggleId' => 'btn-toggle-filtros-rendicion-maquina',
                        'inputId' => 'filtro_valor',
                        'nuevoRegistroUrl' => route('crear_rendicion_maquina', $retornoListadoQuery),
                        'nuevoRegistroCan' => 'crear-rendicion-maquina',
                        'nuevoRegistroLabel' => 'Nueva',
                    ])
                    @if (can('crear-rendicion-maquina', false))
                        <a href="{{ route('crear_rendicion_maquina', ['turno' => 'C'] + $retornoListadoQuery) }}"
                           class="btn btn-outline-warning btn-sm ml-1"
                           title="Cargar turno C (cierre de jornada del d&iacute;a anterior)">
                            <i class="fa fa-moon-o"></i> Cerrar jornada (C)
                        </a>
                    @endif
                </div>
            </div>
            <form method="get" action="{{ route('rendicion_maquina') }}" id="form-filtros-rendicion-maquina" class="mb-0">
                @include('caja.rendicion_maquina.partials.filtros_listado', [
                    'limpiarUrl' => $limpiarUrl,
                ])
            </form>
            @include('caja.rendicion_maquina.partials.filtros_externos')
            <div class="card-body table-responsive p-0">
                @include('includes.exportar-tabla-queryparams', [
                    'ruta' => 'lista_rendicion_maquina',
                    'queryparams' => $filtrosQuery ?? [],
                ])
                <table class="table table-striped table-bordered table-hover mb-0" id="tabla-paginada">
                    <thead>
                        <tr>
                            <th class="width20">ID</th>
                            <th>C&oacute;digo</th>
                            <th>Fecha</th>
                            <th>Turno</th>
                            <th>Empresa</th>
                            <th class="text-right">Resultado</th>
                            <th class="text-right">Transferencia</th>
                            <th class="width120">Estado</th>
                            <th class="rendmaq-col-acciones" data-orderable="false">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($datas as $data)
                        @php
                            $estado = (string) ($data->estado ?? '');
                            $badgeEstado = match ($estado) {
                                RendicionMaquina::ESTADO_CONFIRMADA => 'badge-success',
                                RendicionMaquina::ESTADO_ANULADA => 'badge-danger',
                                default => 'badge-secondary',
                            };
                            $turno = (string) ($data->turno ?? '');
                            $badgeTurno = match ($turno) {
                                'C' => 'badge-warning',
                                'N' => 'badge-dark',
                                'T' => 'badge-info',
                                default => 'badge-primary',
                            };
                        @endphp
                        <tr>
                            <td>{{ $data->id }}</td>
                            <td>{{ $data->codigo }}</td>
                            <td>{{ optional($data->fecha)->format('d/m/Y') }}</td>
                            <td>
                                <span class="badge {{ $badgeTurno }} rendmaq-badge-turno" title="{{ $data->turno_label }}">{{ $turno }}</span>
                                <span class="text-muted small ml-1">{{ $data->turno_label }}</span>
                            </td>
                            <td>{{ $data->empresa->nombre ?? '' }}</td>
                            <td class="text-right font-weight-bold">{{ number_format((float) $data->resultado_turno, 2, ',', '.') }}</td>
                            <td class="text-right">{{ number_format((float) $data->transferencia, 2, ',', '.') }}</td>
                            <td><span class="badge {{ $badgeEstado }}">{{ $data->estado_label }}</span></td>
                            <td class="rendmaq-col-acciones">
                                @if (can('imprimir-rendicion-maquina', false))
                                    <a href="{{ route('imprimir_rendicion_maquina', ['id' => $data->id, 'inline' => 1]) }}"
                                       target="_blank" rel="noopener"
                                       class="btn-accion-tabla tooltipsC" title="Imprimir comprobante PDF">
                                        <i class="fa fa-print text-primary"></i>
                                    </a>
                                @endif
                                @if (can('editar-rendicion-maquina', false))
                                    <a href="{{ route('editar_rendicion_maquina', ['id' => $data->id] + $retornoListadoQuery) }}" class="btn-accion-tabla tooltipsC" title="Editar este registro">
                                        <i class="fa fa-edit"></i>
                                    </a>
                                @endif
                                @if (can('borrar-rendicion-maquina', false))
                                <form action="{{ route('eliminar_rendicion_maquina', ['id' => $data->id]) }}" class="d-inline form-eliminar" method="POST">
                                    @csrf @method("delete")
                                    <button type="submit" class="btn-accion-tabla eliminar tooltipsC" title="Eliminar este registro">
                                        <i class="fa fa-times-circle text-danger"></i>
                                    </button>
                                </form>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="9" class="text-center text-muted py-4">
                                No hay rendiciones con los filtros actuales.
                                @if (can('crear-rendicion-maquina', false))
                                    <a href="{{ route('crear_rendicion_maquina', $retornoListadoQuery) }}">Crear la primera</a>
                                @endif
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if (method_exists($datas, 'links'))
                <div class="card-footer clearfix">
                    {{ $datas->appends($filtrosQuery ?? [])->links() }}
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
