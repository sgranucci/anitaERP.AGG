@extends("theme.$theme.layout")
@section('titulo')
Seguimiento de legajos
@endsection

@section('scripts')
<script src="{{ asset('assets/pages/scripts/includes/listado-filtros.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/compras/legajo_seguimiento/seguimiento.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/compras/legajo_seguimiento/seguimiento.js')) ?: time() }}" type="text/javascript"></script>
@endsection

@section('contenido')
@php
    use App\Support\Compras\OrdencompraLegajoBandejaFiltros;
    use App\Support\Compras\OrdencompraListadoFiltros;
    $limpiarUrl = route('consultar_seguimiento_legajo_compra', OrdencompraListadoFiltros::paraQueryStringEmpresa($filtros ?? []));
@endphp

<div class="modal fade" id="modalSeguimientoFicha" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Ficha del legajo</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body">
                <div id="seguimientoFichaResumen" class="mb-3"></div>
                <h6 class="text-muted">Historia de sector</h6>
                <div class="table-responsive">
                    <table class="table table-sm table-striped mb-0" id="tablaSeguimientoHistoria">
                        <thead><tr><th>Fecha</th><th>Sector</th><th>Observación</th><th>Usuario</th></tr></thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-secondary">
            <div class="card-header">
                <h3 class="card-title"><i class="fa fa-search"></i> Seguimiento de legajos</h3>
                <div class="card-tools d-flex flex-wrap align-items-center justify-content-end">
                    @if (can('listar-legajo-compra', false))
                        <a href="{{ route('consultar_legajo_compra') }}" class="btn btn-outline-light btn-sm mr-1">
                            <i class="fa fa-folder-open"></i> Bandeja
                        </a>
                    @endif
                    @include('includes.listado.filtros_toolbar', [
                        'formId' => 'form-filtros-seguimiento-legajo',
                        'filtroValor' => $filtros['valor'] ?? '',
                        'tieneCriterios' => OrdencompraListadoFiltros::tieneCriteriosTexto($filtros ?? [])
                            || !empty($filtros['nro_oc'])
                            || !empty($filtros['nro_factura'])
                            || !empty($filtros['nro_com'])
                            || !empty($filtros['nro_op']),
                        'limpiarUrl' => $limpiarUrl,
                        'placeholder' => 'Nº OC, proveedor, factura, COM u OP…',
                        'toggleTarget' => '#panel-filtros-ordencompra',
                        'toggleId' => 'btn-toggle-filtros-seguimiento',
                        'inputId' => 'filtro_valor',
                    ])
                </div>
            </div>
            <form method="get" action="{{ route('consultar_seguimiento_legajo_compra') }}" id="form-filtros-seguimiento-legajo" class="mb-0">
                @include('compras.ordencompra.partials.filtros_listado', ['limpiarUrl' => $limpiarUrl])
                <div class="card-body py-2 border-bottom bg-light">
                    <div class="form-row align-items-end">
                        <div class="form-group col-md-2 col-sm-6 mb-2">
                            <label class="small mb-1" for="nro_oc">Nº OC</label>
                            <input type="text" name="nro_oc" id="nro_oc" class="form-control form-control-sm"
                                   value="{{ $filtros['nro_oc'] ?? '' }}" placeholder="Orden de compra" autocomplete="off">
                        </div>
                        <div class="form-group col-md-2 col-sm-6 mb-2">
                            <label class="small mb-1" for="nro_factura">Nº factura</label>
                            <input type="text" name="nro_factura" id="nro_factura" class="form-control form-control-sm"
                                   value="{{ $filtros['nro_factura'] ?? '' }}" placeholder="Número o dígitos" autocomplete="off">
                        </div>
                        <div class="form-group col-md-2 col-sm-6 mb-2">
                            <label class="small mb-1" for="nro_com">Nº COM</label>
                            <input type="text" name="nro_com" id="nro_com" class="form-control form-control-sm"
                                   value="{{ $filtros['nro_com'] ?? '' }}" placeholder="Número o ID" autocomplete="off">
                        </div>
                        <div class="form-group col-md-3 col-sm-6 mb-2">
                            <label class="small mb-1" for="nro_op">Nº orden de pago</label>
                            <input type="text" name="nro_op" id="nro_op" class="form-control form-control-sm"
                                   value="{{ $filtros['nro_op'] ?? '' }}" placeholder="Cuando el legajo está pago" autocomplete="off">
                        </div>
                        <div class="form-group col-md-3 col-sm-6 mb-2">
                            <button type="submit" class="btn btn-primary btn-sm">
                                <i class="fa fa-search"></i> Buscar
                            </button>
                            <a href="{{ $limpiarUrl }}" class="btn btn-outline-secondary btn-sm">Limpiar</a>
                        </div>
                    </div>
                </div>
            </form>
            @include('compras.ordencompra.partials.filtros_externos', [
                'rutaIndex' => 'consultar_seguimiento_legajo_compra',
                'filtros' => $filtros,
                'filtrosQuery' => $filtrosQuery ?? [],
                'empresa_query' => $empresa_query ?? collect(),
            ])
            <div class="card-body py-2">
                <p class="text-muted small mb-2">
                    Consulta global de solo lectura: ubicá un legajo por OC, factura, COM u OP aunque no esté en tu bandeja.
                    Para trabajar el día a día usá la <strong>Bandeja</strong>.
                </p>
            </div>
            <div class="card-body table-responsive p-0">
                <table class="table table-striped table-bordered table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Id</th>
                            <th>OC</th>
                            <th>Fecha</th>
                            <th>Empresa</th>
                            <th>Proveedor</th>
                            <th>Sector</th>
                            <th>Días</th>
                            <th>Facturas</th>
                            <th>Paquete</th>
                            <th>Consulta</th>
                        </tr>
                    </thead>
                    <tbody>
                        @if (empty($busco))
                            <tr>
                                <td colspan="10" class="text-center text-muted py-4">
                                    Ingresá un Nº de OC, factura, COM u OP (o un texto de búsqueda) para consultar.
                                </td>
                            </tr>
                        @else
                            @forelse ($filas as $row)
                                <tr>
                                    <td>{{ $row['id'] }}</td>
                                    <td>
                                        <a href="{{ $row['url_oc'] }}" target="_blank" rel="noopener">{{ $row['numero'] }}</a>
                                        @if (!empty($row['es_anticipada']))
                                            <span class="badge badge-warning" title="Legajo anticipado"><i class="fa fa-clock-o"></i> Anticipado</span>
                                        @endif
                                        @if (!empty($row['es_gastronomia']))
                                            <span class="badge badge-info">Gastro</span>
                                        @endif
                                        @if (!empty($row['tiene_nota']))
                                            <span class="badge badge-warning" title="{{ $row['nota_legajo'] }}"><i class="fa fa-sticky-note"></i></span>
                                        @endif
                                    </td>
                                    <td>{{ $row['fecha'] }}</td>
                                    <td><small>{{ $row['empresa'] }}</small></td>
                                    <td><small>{{ $row['proveedor'] }}</small></td>
                                    <td><strong>{{ $row['sector'] }}</strong></td>
                                    <td>{{ $row['dias'] }}</td>
                                    <td class="small">
                                        @php
                                            $nCargadasSeg = (int) ($row['facturas_cargadas_count'] ?? 0);
                                            $hayFacPendienteSeg = ! empty($row['facturas_legajo']);
                                        @endphp
                                        @if ($hayFacPendienteSeg)
                                            @foreach ($row['facturas_legajo'] as $facLeg)
                                                <div @class(['mt-1 pt-1 border-top' => !$loop->first])>
                                                    @if (!empty($facLeg['url_pdf']))
                                                        <a href="{{ $facLeg['url_pdf'] }}" class="text-primary" target="_blank" rel="noopener"
                                                           title="Abrir PDF en pantalla completa">{{ $facLeg['numero'] ?? '' }}</a>
                                                    @else
                                                        <span>{{ $facLeg['numero'] ?? '' }}</span>
                                                    @endif
                                                    @if (!empty($facLeg['estado']))
                                                        @php
                                                            $esAnitaSeg = ($facLeg['estado'] ?? '') === 'en_anita';
                                                        @endphp
                                                        <span class="badge {{ $esAnitaSeg ? 'badge-success' : 'badge-secondary' }}">
                                                            {{ $esAnitaSeg ? 'en Anita' : 'pendiente' }}
                                                        </span>
                                                    @endif
                                                    @if (!empty($facLeg['origen']))
                                                        <small class="d-block text-muted">{{ $facLeg['origen'] }}</small>
                                                    @endif
                                                </div>
                                            @endforeach
                                        @endif
                                        @if ($nCargadasSeg > 0)
                                            <div @class(['mt-1 pt-1 border-top' => $hayFacPendienteSeg])>
                                                <a href="{{ $row['url_oc'] }}" class="text-primary" target="_blank" rel="noopener"
                                                   title="Consultar el legajo completo (OC)">
                                                    {{ $nCargadasSeg }} ya en CxP
                                                </a>
                                            </div>
                                        @elseif (! $hayFacPendienteSeg)
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if (!empty($row['paquete_ok']))
                                            <span class="badge badge-success">OK</span>
                                        @else
                                            @if (!empty($row['tiene_factura']))
                                                <span class="badge badge-secondary">FC</span>
                                            @endif
                                            @if (!empty($row['tiene_com']))
                                                <span class="badge badge-secondary">COM</span>
                                            @endif
                                            @if (!empty($row['tiene_comprobante']))
                                                <span class="badge badge-info" title="Todas las facturas y NC del legajo están en CxP">cargada</span>
                                            @endif
                                            @if (!empty($row['tiene_comprobante_parcial']))
                                                <span class="badge badge-warning" title="Hay comprobantes en CxP, pero quedan documentos pendientes">parcial</span>
                                            @endif
                                            @if (!empty($row['tiene_pago']))
                                                <span class="badge badge-success">{{ $row['etiqueta_pago'] ?: 'OP' }}</span>
                                            @endif
                                            @if (empty($row['tiene_factura']) && empty($row['tiene_com']))
                                                <span class="text-muted">—</span>
                                            @endif
                                        @endif
                                    </td>
                                    <td class="text-nowrap">
                                        <a href="{{ route('solo_consulta_ordencompra', ['id' => $row['id']]) }}"
                                           class="btn btn-xs btn-outline-secondary" target="_blank" rel="noopener" title="Consulta OC">
                                            <i class="fa fa-eye"></i>
                                        </a>
                                        @if (!empty($row['url_factura']))
                                            <a href="{{ $row['url_factura'] }}" class="btn btn-xs btn-outline-danger"
                                               target="_blank" rel="noopener noreferrer" title="Ver factura">
                                                <i class="fa fa-file-pdf-o"></i>
                                            </a>
                                        @endif
                                        @if (!empty($row['tiene_com']) && !empty($row['url_com']))
                                            <a href="{{ $row['url_com'] }}" class="btn btn-xs btn-outline-dark"
                                               target="_blank" rel="noopener noreferrer" title="Ver COM">
                                                <i class="fa fa-cubes"></i>
                                            </a>
                                        @endif
                                        <button type="button" class="btn btn-xs btn-outline-info js-seguimiento-ficha"
                                                data-url="{{ route('ficha_seguimiento_legajo_compra', ['id' => $row['id']]) }}"
                                                data-numero="{{ $row['numero'] }}"
                                                title="Historia y detalle">
                                            <i class="fa fa-history"></i>
                                        </button>
                                        @if (!empty($puede_ver_comprobante) && !empty($row['url_comprobante']))
                                            <a href="{{ $row['url_comprobante'] }}" class="btn btn-xs btn-outline-info"
                                               target="_blank" rel="noopener" title="Comprobante cargado">
                                                <i class="fa fa-check-square-o"></i>
                                            </a>
                                        @endif
                                        @if (!empty($puede_ver_pago) && !empty($row['url_pago']))
                                            <a href="{{ $row['url_pago'] }}" class="btn btn-xs btn-outline-success"
                                               target="_blank" rel="noopener" title="Orden de pago">
                                                <i class="fa fa-money"></i>
                                            </a>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="10" class="text-center text-muted py-4">
                                        No se encontraron legajos con ese criterio.
                                    </td>
                                </tr>
                            @endforelse
                        @endif
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
