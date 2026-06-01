@extends("theme.$theme.layout")
@section('titulo')
    Rendiciones gastronomía
@endsection

@section("scripts")
<script src="{{ asset('assets/pages/scripts/admin/index.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/includes/listado-filtros.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/caja/rendicion_gastronomia/filtro.js') }}" type="text/javascript"></script>
<script>
    function eliminarRendicionGastronomia(event) {
        if (!confirm('¿Eliminar esta rendición de gastronomía?')) {
            event.preventDefault();
        }
    }
</script>
@endsection

<?php
use App\Support\Caja\RendicionGastronomiaCajaListadoFiltros;
use App\Support\Caja\RendicionGastronomiaPdfPermiso;
use App\Support\Ventas\GastronomiaJornadaComprobantePermiso;
?>

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Rendiciones gastronomía</h3>
                <div class="card-tools d-flex flex-wrap align-items-center justify-content-end">
                    @include('includes.listado.filtros_toolbar', [
                        'formId' => 'form-filtros-rendicion-gastronomia',
                        'filtroValor' => $filtros['valor'] ?? '',
                        'tieneCriterios' => RendicionGastronomiaCajaListadoFiltros::tieneCriteriosUsuario($filtros ?? []),
                        'limpiarUrl' => route('rendiciongastronomia'),
                        'placeholder' => 'Búsqueda rápida (ticket, ID, empresa…)',
                        'toggleTarget' => '#panel-filtros-rendicion-gastronomia',
                        'toggleId' => 'btn-toggle-filtros-rendicion-gastronomia',
                        'inputId' => 'filtro_valor',
                        'nuevoRegistroUrl' => route('crear_rendiciongastronomia'),
                        'nuevoRegistroCan' => 'crear-rendicion-gastronomia-caja',
                    ])
                </div>
            </div>
            <form method="get" action="{{ route('rendiciongastronomia') }}" id="form-filtros-rendicion-gastronomia" class="mb-0">
                @include('caja.rendiciongastronomia.partials.filtros_listado')
            </form>
            <div class="card-body table-responsive p-0">
                @include('includes.exportar-tabla-queryparams', [
                    'ruta' => 'listar_rendiciongastronomia',
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
                                    Jornada #{{ $row->jornada_gastronomia_id }}
                                    @if ((int) ($row->waitry_order_id_hasta ?? 0) > 0)
                                        <br><small class="text-muted">Waitry ≤ {{ $row->waitry_order_id_hasta }}</small>
                                    @endif
                                @else
                                    #{{ $row->turno_operativo_gastronomia_id }}
                                    @if ($row->turnoOperativo?->turno?->nombre)
                                        — {{ $row->turnoOperativo->turno->nombre }}
                                    @endif
                                @endif
                            </td>
                            <td>{{ $row->jornada?->fecha_jornada?->format('d/m/Y') ?? $row->turnoOperativo?->jornada?->fecha_jornada?->format('d/m/Y') }}</td>
                            <td class="text-right">${{ number_format((float) $row->totalcobrado, 2, ',', '.') }}</td>
                            <td>
                                @if (
                                    can('editar-rendicion-gastronomia-caja', false)
                                    && \App\Support\Caja\RendicionGastronomiaCajaPermiso::puedeActualizarPorFecha($row)
                                    && \App\Support\Caja\RendicionGastronomiaCajaPermiso::puedeModificarRendicionTurno($row)
                                )
                                <a href="{{ route('editar_rendiciongastronomia', ['id' => $row->id]) }}" class="btn-accion-tabla tooltipsC" title="Editar este registro">
                                    <i class="fa fa-edit"></i>
                                </a>
                                @endif
                                @if (RendicionGastronomiaPdfPermiso::puedeVerPdfRendicion())
                                <a href="{{ route('imprimir_rendicion_gastronomia', ['id' => $row->id, 'inline' => 1]) }}" class="btn-accion-tabla tooltipsC" title="Ver PDF rendición" target="_blank" rel="noopener">
                                    <i class="fa fa-print"></i>
                                </a>
                                @endif
                                @if ($row->turno_operativo_gastronomia_id && can('ver-comprobante-cierre-turno-gastronomia', false))
                                <a href="{{ route('gastronomia_cierre_turno_comprobante_cierre', ['id' => $row->turno_operativo_gastronomia_id, 'inline' => 1]) }}"
                                   class="btn-accion-tabla tooltipsC" title="Ver comprobante cierre turno" target="_blank">
                                    <i class="fa fa-file-pdf-o text-danger"></i>
                                </a>
                                @elseif (
                                    $row->esRendicionJornada()
                                    && $row->jornada_gastronomia_id
                                    && GastronomiaJornadaComprobantePermiso::puedeVerComprobanteCierreTotem()
                                    && ($row->cierreTotemJornada || $row->jornada?->cierreTotem)
                                )
                                @php
                                    $cierreTotem = $row->cierreTotemJornada ?? $row->jornada?->cierreTotem;
                                    $cierreTotemId = (int) ($cierreTotem?->id ?? 0);
                                @endphp
                                <a href="{{ route('gastronomia_jornada_comprobante_cierre_totem', [
                                    'jornadaId' => $row->jornada_gastronomia_id,
                                    'cierre_totem_id' => $cierreTotemId > 0 ? $cierreTotemId : null,
                                    'inline' => 1,
                                ]) }}"
                                   class="btn-accion-tabla tooltipsC" title="Comprobante cierre tótem / Z" target="_blank" rel="noopener">
                                    <i class="fa fa-file-pdf-o text-danger"></i>
                                </a>
                                @endif
                                @if (
                                    can('borrar-rendicion-gastronomia-caja', false)
                                    && \App\Support\Caja\RendicionGastronomiaCajaPermiso::puedeModificarRendicionTurno($row)
                                )
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
                {{ $rendiciones->appends($filtrosQuery ?? [])->links() }}
            </div>
            @endif
        </div>
    </div>
</div>
@endsection
