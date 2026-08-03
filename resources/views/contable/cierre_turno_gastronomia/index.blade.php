@extends("theme.$theme.layout")
@section('titulo')
    Cierres gastronom&iacute;a (Contable)
@endsection

@section('scripts')
<script src="{{ asset('assets/pages/scripts/admin/index.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/includes/listado-filtros.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/contable/cierre_turno_gastronomia/filtro.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/contable/cierre_turno_gastronomia/filtro.js')) ?: time() }}" type="text/javascript"></script>
@endsection

@php
    use App\Support\Contable\CierreTurnoGastronomiaContableListadoFiltros;
    use App\Support\Listado\QueryRetornoListado;
    $retornoListadoQuery = QueryRetornoListado::retornoLinksDesdeFiltrosQuery($filtrosQuery ?? []);
    $limpiarUrl = route(
        'cierres_turno_gastronomia_contable',
        CierreTurnoGastronomiaContableListadoFiltros::paraQueryStringEmpresa($filtros ?? [])
    );
@endphp

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Cierres de turno gastronom&iacute;a</h3>
                <div class="card-tools d-flex flex-wrap align-items-center justify-content-end">
                    <a href="{{ route('cierres_turno_gastronomia_contable_conciliacion', $retornoListadoQuery) }}"
                       class="btn btn-sm btn-outline-info mr-2 mb-1"
                       title="Conciliar cierres vs flash y mayor contable">
                        <i class="fa fa-balance-scale"></i> Conciliaci&oacute;n flash / mayor
                    </a>
                    <a href="{{ route('cierres_turno_gastronomia_contable_diario_puntoventa', $retornoListadoQuery) }}"
                       class="btn btn-sm btn-outline-primary mr-2 mb-1"
                       title="Diario por punto de venta y medios de pago">
                        <i class="fa fa-table"></i> Diario por PV / medios
                    </a>
                    @include('includes.listado.filtros_toolbar', [
                        'formId' => 'form-filtros-cierres-gastro-contable',
                        'filtroValor' => $filtros['valor'] ?? '',
                        'tieneCriterios' => CierreTurnoGastronomiaContableListadoFiltros::tieneCriteriosUsuario($filtros ?? []),
                        'limpiarUrl' => $limpiarUrl,
                        'placeholder' => 'Búsqueda rápida (referencia, PV, turno…)',
                        'toggleTarget' => '#panel-filtros-cierres-gastro-contable',
                        'toggleId' => 'btn-toggle-filtros-cierres-gastro-contable',
                        'inputId' => 'filtro_valor',
                    ])
                </div>
            </div>
            <form method="get" action="{{ route('cierres_turno_gastronomia_contable') }}" id="form-filtros-cierres-gastro-contable" class="mb-0">
                @include('contable.cierre_turno_gastronomia.partials.filtros_listado')
            </form>
            @include('contable.cierre_turno_gastronomia.partials.filtros_externos')
            <div class="card-body table-responsive p-0">
                @include('includes.exportar-tabla-queryparams', [
                    'ruta' => 'listar_cierres_turno_gastronomia_contable',
                    'queryparams' => $filtrosQuery ?? [],
                ])
                <div class="alert alert-info mx-3 mt-3 mb-2 py-2 small">
                    Solo consulta y conciliaci&oacute;n. La contabilizaci&oacute;n de gastronom&iacute;a se realiza en el proceso
                    <strong>post-cierre Waitry</strong> (Caja). Aqu&iacute; puede revisar cierres de turno y confrontarlos con
                    <code>flash_ayb</code> y el mayor contable (asientos Waitry + Anita).
                </div>
                <table class="table table-striped table-bordered table-hover mb-0" id="tabla-paginada">
                    <thead style="background:#85C1E9;color:#17202A;">
                        <tr>
                            <th>Tipo</th>
                            <th>Fecha / hora</th>
                            <th>Referencia</th>
                            <th>Empresa</th>
                            <th>PC</th>
                            <th>Punto venta</th>
                            <th>Turno</th>
                            <th>Jornada</th>
                            <th>Usuario</th>
                            <th class="text-right">Total final</th>
                            <th class="width100" data-orderable="false">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($coleccion as $f)
                            <tr>
                                <td>{{ $f->tipo_etiqueta }}</td>
                                <td>{{ $f->fecha_hora }}</td>
                                <td>{{ $f->referencia }}</td>
                                <td>{{ $f->nombreempresa }}</td>
                                <td>{{ $f->identificador_pc }}</td>
                                <td><small>{{ $f->puntoventa_etiqueta !== '' ? $f->puntoventa_etiqueta : '—' }}</small></td>
                                <td>{{ $f->turno_nombre }}</td>
                                <td>{{ $f->fecha_jornada }}</td>
                                <td>{{ $f->usuario }}</td>
                                <td class="text-right">${{ number_format((float) $f->total, 2, ',', '.') }}</td>
                                <td class="text-nowrap">
                                    @if (($f->tipo ?? '') === 'cierre')
                                        <a class="btn-accion-tabla tooltipsC text-primary"
                                           href="{{ route('cierres_turno_gastronomia_contable_comprobante_cierre', ['id' => $f->id, 'inline' => 1]) }}"
                                           target="_blank" rel="noopener"
                                           title="Ver comprobante de cierre">
                                            <i class="fas fa-file-invoice"></i>
                                        </a>
                                    @elseif (($f->tipo ?? '') === 'parcial')
                                        <a class="btn-accion-tabla tooltipsC text-primary"
                                           href="{{ route('cierres_turno_gastronomia_contable_comprobante_parcial', ['id' => $f->id, 'inline' => 1]) }}"
                                           target="_blank" rel="noopener"
                                           title="Ver comprobante parcial">
                                            <i class="fas fa-file-invoice"></i>
                                        </a>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="11" class="text-center text-muted py-4">Sin cierres para los filtros indicados.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if (method_exists($coleccion, 'links'))
                <div class="card-footer clearfix">
                    <div class="float-left">
                        @if ($coleccion->total() > 0)
                            Mostrando {{ $coleccion->firstItem() }}–{{ $coleccion->lastItem() }} de {{ $coleccion->total() }}
                        @endif
                    </div>
                    <div class="float-right">
                        {{ $coleccion->appends($filtrosQuery ?? [])->links() }}
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
