@extends("theme.$theme.layout")
@section('titulo')
    Tarjetas corporativas
@endsection

@section('scripts')
<script src="{{ asset('assets/pages/scripts/admin/index.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/includes/listado-filtros.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/compras/suscripcion/tarjeta/filtro.js') }}" type="text/javascript"></script>
@endsection

@section('contenido')
@php
    use App\Support\Compras\SuscripcionTarjetaListadoFiltros;
    use App\Support\Listado\QueryRetornoListado;

    $filtrosQuery = $filtrosQuery ?? SuscripcionTarjetaListadoFiltros::paraQueryString($filtros ?? []);
    $retornoListadoQuery = QueryRetornoListado::retornoLinksDesdeFiltrosQuery($filtrosQuery);
    $limpiarUrl = route('tarjetas_suscripcion', SuscripcionTarjetaListadoFiltros::paraQueryStringEmpresa($filtros ?? []));
@endphp
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Tarjetas corporativas</h3>
                <div class="card-tools d-flex flex-wrap align-items-center justify-content-end">
                    @include('includes.compras.boton-manual-suscripciones')
                    @include('includes.listado.filtros_toolbar', [
                        'formId' => 'form-filtros-tarjeta-suscripcion',
                        'filtroValor' => $filtros['valor'] ?? '',
                        'tieneCriterios' => SuscripcionTarjetaListadoFiltros::tieneCriteriosTexto($filtros ?? []),
                        'limpiarUrl' => $limpiarUrl,
                        'placeholder' => 'Búsqueda rápida (tolera errores de tipeo)…',
                        'toggleTarget' => '#panel-filtros-tarjeta-suscripcion',
                        'toggleId' => 'btn-toggle-filtros-tarjeta-suscripcion',
                        'inputId' => 'filtro_valor',
                        'nuevoRegistroUrl' => route('crear_tarjeta_suscripcion', $retornoListadoQuery),
                        'nuevoRegistroCan' => 'configurar-suscripcion',
                        'nuevoRegistroLabel' => 'Nueva tarjeta',
                    ])
                    <a href="{{ route('consultar_suscripcion') }}" class="btn btn-outline-light btn-sm ml-1">← Suscripciones</a>
                </div>
            </div>

            <form method="get" action="{{ route('tarjetas_suscripcion') }}" id="form-filtros-tarjeta-suscripcion" class="mb-0">
                @include('compras.suscripcion.tarjeta.partials.filtros_listado', [
                    'limpiarUrl' => $limpiarUrl,
                ])
            </form>

            @include('compras.suscripcion.partials.filtros_externos', [
                'rutaNombre' => 'tarjetas_suscripcion',
                'empresa_query' => $empresa_query,
                'empresa_id' => (int) ($filtros['empresa_id'] ?? 0),
                'filtrosQuery' => $filtrosQuery,
            ])

            <div class="card-body py-2 border-bottom">
                <p class="text-muted small mb-0">
                    Los últimos 4 dígitos cruzan el resumen del emisor. Para imputar en Ingresos y egresos,
                    la tarjeta necesita cuenta de caja y tipo de transacción de egreso.
                </p>
            </div>

            <div class="card-body table-responsive p-0">
                @include('includes.exportar-tabla-queryparams', [
                    'ruta' => 'exportar_tarjetas_suscripcion',
                    'queryparams' => $filtrosQuery,
                ])
                <table class="table table-sm table-striped table-bordered table-hover mb-0" id="tabla-paginada">
                    <thead style="background:#85C1E9;color:#17202A;">
                        <tr>
                            <th>Etiqueta</th>
                            <th>Últimos 4</th>
                            <th>Emisor</th>
                            <th>Empresa</th>
                            <th>Área / CC</th>
                            <th>Responsable</th>
                            <th>Imputación</th>
                            <th class="text-center">Suscripciones</th>
                            <th>Estado</th>
                            <th class="width80"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($tarjetas as $t)
                            <tr>
                                <td><strong>{{ $t->etiqueta }}</strong></td>
                                <td>••{{ $t->ult4 }}</td>
                                <td>{{ $t->emisor ?: '—' }}</td>
                                <td>{{ optional($t->empresas)->nombre }}</td>
                                <td>
                                    {{ $t->area ?: '—' }}
                                    @if ($t->centrocostos)
                                        <small class="text-muted d-block">{{ trim($t->centrocostos->codigo.' '.$t->centrocostos->nombre) }}</small>
                                    @endif
                                </td>
                                <td>{{ optional($t->responsables)->nombre ?: '—' }}</td>
                                <td>
                                    @if ($t->imputable())
                                        <span class="badge badge-success">Lista</span>
                                    @else
                                        <span class="badge badge-warning" title="Falta cuenta de caja o tipo de transacción">Incompleta</span>
                                    @endif
                                </td>
                                <td class="text-center">{{ $usos[$t->id] ?? 0 }}</td>
                                <td>
                                    <span class="badge badge-{{ $t->activo ? 'success' : 'secondary' }}">
                                        {{ $t->activo ? 'Activa' : 'Inactiva' }}
                                    </span>
                                </td>
                                <td class="text-nowrap">
                                    <a href="{{ route('editar_tarjeta_suscripcion', ['id' => $t->id] + $retornoListadoQuery) }}"
                                       class="btn btn-xs btn-outline-info" title="Editar">
                                        <i class="fa fa-pencil"></i>
                                    </a>
                                    <form method="post"
                                          action="{{ route('eliminar_tarjeta_suscripcion', ['id' => $t->id] + $retornoListadoQuery) }}"
                                          class="d-inline"
                                          onsubmit="return confirm('¿Eliminar la tarjeta {{ $t->etiqueta }}?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-xs btn-outline-danger" title="Eliminar">
                                            <i class="fa fa-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="10" class="text-center text-muted py-4">No hay tarjetas con esos filtros.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
{{ $tarjetas->appends($filtrosQuery)->links() }}
@endsection
