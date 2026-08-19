@extends("theme.$theme.layout")
@section('titulo')
    Rendiciones estacionamiento
@endsection

@section("scripts")
<script src="{{ asset('assets/pages/scripts/admin/index.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/includes/listado-filtros.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/caja/rendicion_estacionamiento/filtro.js') }}" type="text/javascript"></script>
<script>
    function eliminarRendicionEstacionamiento(event) {
        if (!confirm('¿Eliminar esta rendición de estacionamiento?')) {
            event.preventDefault();
        }
    }
</script>
@endsection

<?php
use App\Support\Caja\EstacionamientoJornadaComprobantePermiso;
use App\Support\Caja\RendicionEstacionamientoCajaListadoFiltros;
use App\Support\Caja\RendicionEstacionamientoPdfPermiso;
?>

@section('contenido')
@php
    $retornoListadoQuery = \App\Support\Listado\QueryRetornoListado::retornoLinksDesdeFiltrosQuery($filtrosQuery ?? []);
    $limpiarUrl = route('rendicionestacionamiento', RendicionEstacionamientoCajaListadoFiltros::paraQueryStringEmpresa($filtros ?? []));
@endphp
<div class="row">
    <div class="col-lg-12">
        @include('caja.rendicionestacionamiento.partials.flash_mensajes')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Rendiciones estacionamiento</h3>
                <div class="card-tools d-flex flex-wrap align-items-center justify-content-end">
                    @include('includes.listado.filtros_toolbar', [
                        'formId' => 'form-filtros-rendicion-estacionamiento',
                        'filtroValor' => $filtros['valor'] ?? '',
                        'tieneCriterios' => RendicionEstacionamientoCajaListadoFiltros::tieneCriteriosUsuario($filtros ?? []),
                        'limpiarUrl' => $limpiarUrl,
                        'placeholder' => 'Búsqueda rápida (ticket, ID, empresa…)',
                        'toggleTarget' => '#panel-filtros-rendicion-estacionamiento',
                        'toggleId' => 'btn-toggle-filtros-rendicion-estacionamiento',
                        'inputId' => 'filtro_valor',
                        'nuevoRegistroUrl' => route('crear_rendicionestacionamiento', $retornoListadoQuery),
                        'nuevoRegistroCan' => 'crear-rendicion-estacionamiento-caja',
                    ])
                </div>
            </div>
            <form method="get" action="{{ route('rendicionestacionamiento') }}" id="form-filtros-rendicion-estacionamiento" class="mb-0">
                @include('caja.rendicionestacionamiento.partials.filtros_listado')
            </form>
            @include('caja.rendicionestacionamiento.partials.filtros_externos')
            <div class="card-body table-responsive p-0">
                @include('includes.exportar-tabla-queryparams', [
                    'ruta' => 'listar_rendicionestacionamiento',
                    'queryparams' => $filtrosQuery ?? [],
                ])
                <table class="table table-striped table-bordered table-hover" id="tabla-paginada">
                    <thead>
                        <tr>
                            <th class="width20">ID</th>
                            <th>Tipo</th>
                            <th>Ticket</th>
                            <th>Fecha rendición</th>
                            <th>Empresa</th>
                            <th>Caja</th>
                            <th>Punto venta</th>
                            <th>Origen</th>
                            <th>Jornada</th>
                            <th class="text-right" title="Total ventas del turno/jornada">Ventas</th>
                            <th class="text-right" title="Total invitaciones">Invit.</th>
                            <th class="text-right">Cobrado</th>
                            <th class="width80" data-orderable="false"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rendiciones as $row)
                        @php
                            $pvCae = $row->puntoventaCae;
                            $pvCaea = $row->puntoventaCaea;
                            $etiquetaPv = '';
                            if ($pvCae) {
                                $etiquetaPv = trim(($pvCae->codigo ?? '').' — '.($pvCae->nombre ?? ''));
                            }
                            if ($pvCaea && (int) $pvCaea->id !== (int) ($pvCae?->id ?? 0)) {
                                $etiquetaPv .= ($etiquetaPv !== '' ? ' / ' : '').'CAEA';
                            }
                        @endphp
                        <tr>
                            <td>{{ $row->id }}</td>
                            <td>
                                @if ($row->esRendicionJornada())
                                    <span class="badge badge-info">Jornada</span>
                                @else
                                    <span class="badge badge-secondary">Turno</span>
                                @endif
                            </td>
                            <td>{{ $row->codigo }}</td>
                            <td>{{ $row->fecharendicion?->format('d/m/Y H:i') }}</td>
                            <td>{{ $row->empresa?->nombre }}</td>
                            <td>{{ $row->caja?->nombre }}</td>
                            <td><small>{{ $etiquetaPv !== '' ? $etiquetaPv : '—' }}</small></td>
                            <td>
                                @if ($row->esRendicionJornada())
                                    Jornada #{{ $row->jornada_estacionamiento_id }}
                                @else
                                    #{{ $row->turno_operativo_estacionamiento_id }}
                                    @if ($row->turnoOperativo?->turno?->nombre)
                                        — {{ $row->turnoOperativo->turno->nombre }}
                                    @endif
                                @endif
                            </td>
                            <td>{{ $row->jornada?->fecha_jornada?->format('d/m/Y') ?? $row->turnoOperativo?->jornada?->fecha_jornada?->format('d/m/Y') }}</td>
                            <td class="text-right text-nowrap">${{ number_format((float) $row->totalfactura, 2, ',', '.') }}</td>
                            <td class="text-right text-nowrap">
                                @if ((float) $row->totalinvitacion > 0.009)
                                    ${{ number_format((float) $row->totalinvitacion, 2, ',', '.') }}
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td class="text-right text-nowrap">${{ number_format((float) $row->totalcobrado, 2, ',', '.') }}</td>
                            <td class="text-nowrap">
                                @if (
                                    can('editar-rendicion-estacionamiento-caja', false)
                                    && \App\Support\Caja\RendicionEstacionamientoCajaPermiso::puedeActualizarPorFecha($row)
                                    && \App\Support\Caja\RendicionEstacionamientoCajaPermiso::puedeModificarRendicionTurno($row)
                                )
                                <a href="{{ route('editar_rendicionestacionamiento', ['id' => $row->id] + $retornoListadoQuery) }}" class="btn-accion-tabla tooltipsC" title="Editar este registro">
                                    <i class="fa fa-edit"></i>
                                </a>
                                @endif
                                @if (RendicionEstacionamientoPdfPermiso::puedeVerPdfRendicion())
                                <a href="{{ route('imprimir_rendicion_estacionamiento', ['id' => $row->id, 'inline' => 1]) }}" class="btn-accion-tabla tooltipsC" title="Ver PDF rendición" target="_blank" rel="noopener">
                                    <i class="fa fa-print"></i>
                                </a>
                                @endif
                                @if ($row->turno_operativo_estacionamiento_id && can('ver-comprobante-cierre-turno-estacionamiento', false))
                                <a href="{{ route('estacionamiento_cierre_turno_comprobante_cierre', ['id' => $row->turno_operativo_estacionamiento_id, 'inline' => 1]) }}"
                                   class="btn-accion-tabla tooltipsC" title="Ver comprobante cierre turno" target="_blank">
                                    <i class="fa fa-file-pdf-o text-danger"></i>
                                </a>
                                @elseif (
                                    $row->esRendicionJornada()
                                    && $row->jornada_estacionamiento_id
                                    && EstacionamientoJornadaComprobantePermiso::puedeVerComprobanteTotalesZ()
                                )
                                <a href="{{ route('estacionamiento_jornada_comprobante_totales_z', [
                                    'jornadaId' => $row->jornada_estacionamiento_id,
                                    'inline' => 1,
                                ]) }}"
                                   class="btn-accion-tabla tooltipsC" title="Reporte Totales Z jornada" target="_blank" rel="noopener">
                                    <i class="fa fa-file-pdf-o text-danger"></i>
                                </a>
                                @endif
                                @if (
                                    can('borrar-rendicion-estacionamiento-caja', false)
                                    && \App\Support\Caja\RendicionEstacionamientoCajaPermiso::puedeModificarRendicionTurno($row)
                                )
                                <form action="{{ route('eliminar_rendicionestacionamiento', ['id' => $row->id] + $retornoListadoQuery) }}" class="d-inline form-eliminar" method="POST">
                                    @csrf @method('delete')
                                    <button type="submit" onclick="eliminarRendicionEstacionamiento(event)" class="btn-accion-tabla eliminar tooltipsC" title="Eliminar este registro">
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
                {{ $rendiciones->appends($filtrosQuery ?? [])->links() }}
            </div>
            @endif
        </div>
    </div>
</div>
@endsection
