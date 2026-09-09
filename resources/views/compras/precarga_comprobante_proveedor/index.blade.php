@extends("theme.$theme.layout")
@section('titulo')
    Precarga de Comprobantes de Proveedores
@endsection

@section("scripts")
<script src="{{asset("assets/pages/scripts/admin/index.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/includes/listado-filtros.js")}}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/compras/precarga_comprobante_proveedor/filtro.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/includes/erp-workspace-panel.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/includes/erp-workspace-panel.js')) ?: time() }}" type="text/javascript"></script>
@if (!empty($pdfIaHabilitado) && can('crear-precarga-proveedores', false))
<script src="{{ asset('assets/pages/scripts/compras/precarga_comprobante_proveedor/pdf_ia.js') }}" type="text/javascript"></script>
@endif
@endsection

<?php use App\Helpers\biblioteca;
use App\Support\Compras\PrecargaComprobanteOrigenEntrada;
use App\Support\Compras\PrecargaComprobanteProveedorListadoFiltros;
use App\Support\Listado\QueryRetornoListado; ?>

@section('contenido')
@php
    $retornoListadoQuery = QueryRetornoListado::retornoLinksDesdeFiltrosQuery($filtrosQuery ?? []);
    $limpiarUrl = route('precarga_comprobante_proveedor', PrecargaComprobanteProveedorListadoFiltros::paraQueryStringEstado($filtros ?? []));
@endphp
<div class="row erp-ws-host">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Precarga de Comprobantes de Proveedores</h3>
                <div class="card-tools d-flex flex-wrap align-items-center justify-content-end">
                    <a href="{{ route('comprobante_proveedor_opciones_carga') }}" class="btn btn-outline-success btn-sm mr-1">
                        <i class="fa fa-file-text-o"></i> Cargar factura
                    </a>
                    @if (can('listar-precarga-proveedores', false))
                    <a href="{{ route('precarga_comprobante_recepcion_error') }}" class="btn btn-outline-danger btn-sm mr-1" title="Errores de la API / PDF+IA">
                        <i class="fa fa-exclamation-triangle"></i> Errores recepción
                    </a>
                    @endif
                    @if (!empty($pdfIaHabilitado) && can('crear-precarga-proveedores', false))
                    <button type="button" class="btn btn-info btn-sm mr-1" data-toggle="modal" data-target="#modal-precarga-pdf-ia">
                        <i class="fa fa-magic"></i> PDF (IA)
                    </button>
                    @endif
                    @if (can('crear-precarga-proveedores', false))
                    <a href="{{ route('crear_precarga_comprobante_proveedor', $retornoListadoQuery) }}" class="btn btn-outline-secondary btn-sm mr-1">
                        <i class="fa fa-fw fa-plus-circle"></i> Precarga manual
                    </a>
                    @endif
                    @include('includes.listado.filtros_toolbar', [
                        'formId' => 'form-filtros-precarga-comprobante-proveedor',
                        'filtroValor' => $filtros['valor'] ?? '',
                        'tieneCriterios' => PrecargaComprobanteProveedorListadoFiltros::tieneCriteriosAplicados($filtros ?? []),
                        'limpiarUrl' => $limpiarUrl,
                        'placeholder' => 'Búsqueda rápida (tolera errores de tipeo)…',
                        'toggleTarget' => '#panel-filtros-precarga-comprobante-proveedor',
                        'toggleId' => 'btn-toggle-filtros-precarga-comprobante-proveedor',
                        'inputId' => 'filtro_valor',
                    ])
                </div>
            </div>
            <form method="get" action="{{ route('precarga_comprobante_proveedor') }}" id="form-filtros-precarga-comprobante-proveedor" class="mb-0">
                @include('compras.precarga_comprobante_proveedor.partials.filtros_listado', [
                    'limpiarUrl' => $limpiarUrl,
                ])
            </form>
            @include('compras.precarga_comprobante_proveedor.partials.filtros_externos')
            <div class="px-3 pt-2 pb-0">
                <div class="alert alert-light border mb-2 py-2 small text-body" role="note">
                    <i class="fa fa-info-circle text-info"></i>
                    {{ PrecargaComprobanteOrigenEntrada::leyendaSinImportes() }}
                </div>
            </div>
            <div class="card-body table-responsive p-0">
                @include('includes.exportar-tabla-queryparams', [
                    'ruta' => 'lista_precarga_comprobante_proveedor',
                    'queryparams' => $filtrosQuery ?? [],
                ])
                <table class="table table-striped table-bordered table-hover" id="tabla-paginada">
                    <thead>
                        <tr>
                            <th class="width20">ID</th>
                            <th>Empresa</th>
                            <th>Proveedor</th>
                            <th>Tipo de comprobante</th>
                            <th>N&uacute;mero</th>
                            <th>Fecha</th>
                            <th>Fecha Email</th>
                            <th>N&uacute;mero de OC</th>
                            <th>Total</th>
                            <th>Estado</th>
                            <th>Origen</th>
                            <th class="text-nowrap" style="width:180px" data-orderable="false"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($datas as $data)
                        <tr data-ws-id="precarga-{{ $data->id }}">
                            <td>{{$data->id}}</td>
                            <td>{{$data->nombreempresa ?? ($data->empresas->nombre ?? '')}}</td>
                            <td>{{$data->nombreproveedor ?? ($data->proveedores->nombre ?? '')}}</td>
                            <td>
                                @php
                                    $abrevTipo = trim((string) ($data->abreviaturatipotransaccion_compra
                                        ?? ($data->tipotransaccion_compras->abreviatura ?? '')));
                                    $nombreTipo = trim((string) ($data->nombretipotransaccion_compra
                                        ?? ($data->tipotransaccion_compras->nombre ?? '')));
                                @endphp
                                @if ($abrevTipo !== '' && $nombreTipo !== '')
                                    <strong>{{ $abrevTipo }}</strong> — {{ $nombreTipo }}
                                @elseif ($abrevTipo !== '')
                                    <strong>{{ $abrevTipo }}</strong>
                                @else
                                    {{ $nombreTipo }}
                                @endif
                            </td>
                            <td>{{$data->letra}}{{$data->sucursal}}-{{$data->numerocomprobante}}</td>
                            <td>{{ filled($data->fechafactura) ? \Carbon\Carbon::parse($data->fechafactura)->format('d/m/Y') : '' }}</td>
                            <td>{{ filled($data->fecharecepcionemail) ? \Carbon\Carbon::parse($data->fecharecepcionemail)->format('d/m/Y') : '' }}</td>
                            <td>{{$data->numeroordencompra ?? ''}}</td>
                            @php
                                $origenPrecarga = $data->origen_entrada ?? null;
                                $totalPrecarga = (float) ($data->total ?? 0);
                                $avisoSinImportes = PrecargaComprobanteOrigenEntrada::avisoFilaSinImportes($origenPrecarga);
                                $mostrarAvisoSinImportes = $avisoSinImportes !== null && abs($totalPrecarga) < 0.005;
                            @endphp
                            <td class="text-right">
                                {{ number_format($totalPrecarga, 2, ',', '.') }}
                                @if ($mostrarAvisoSinImportes)
                                    <br><small class="text-muted" title="{{ PrecargaComprobanteOrigenEntrada::leyendaSinImportes() }}">sin importes (PDF sin OCR)</small>
                                @endif
                            </td>
                            <td>
                                @if (($data->estado ?? '') === \App\Support\Compras\PrecargaComprobanteEstados::CARGADA_ANITA)
                                    <span class="badge badge-info" title="Factura ya existente en Anita; no se genera comprobante ERP">
                                        {{ \App\Support\Compras\PrecargaComprobanteEstados::etiquetaRegistro($data->estado) }}
                                    </span>
                                    @if (!empty($data->anita_nro_interno))
                                        <br><small class="text-muted">Anita #{{ $data->anita_nro_interno }}</small>
                                    @endif
                                @else
                                    {{ \App\Support\Compras\PrecargaComprobanteEstados::etiquetaRegistro((string) ($data->estado ?? '')) }}
                                @endif
                                @if (filled($data->marca_error ?? null))
                                    <br><span class="badge badge-danger" title="{{ $data->aviso_error ?? '' }}">
                                        {{ \App\Support\Compras\ComprobanteProveedorCotizacionIngresoSupport::etiquetaMarca($data->marca_error) ?: $data->marca_error }}
                                    </span>
                                @endif
                                @if (!empty($data->comprobante_proveedor_id))
                                    @php
                                        $cpEstado = $data->comprobante_proveedor_estado ?? null;
                                    @endphp
                                    @if ($cpEstado === \App\Support\Compras\ComprobanteProveedorEstados::CONTABILIZADO)
                                        <br><span class="badge badge-success" title="Comprobante contabilizado — los importes están en el CP, no en la precarga">CP #{{ $data->comprobante_proveedor_id }}</span>
                                    @else
                                        <br><span class="badge badge-warning" title="Borrador de comprobante (aún no contabilizado)">Borrador CP #{{ $data->comprobante_proveedor_id }}</span>
                                    @endif
                                @endif
                            </td>
                            <td>
                                <small>{{ PrecargaComprobanteOrigenEntrada::etiqueta($origenPrecarga) }}</small>
                                @if ($avisoSinImportes !== null)
                                    <br><small class="text-muted">{{ $avisoSinImportes }}</small>
                                @endif
                            </td>
                            <td class="text-nowrap">
                                @if (filled($data->rutaalmacenamiento) && puedeVerPrecargaFacturaPdf())
                                <a href="{{ urlAppCarpeta('compras/precarga_comprobante_proveedor/'.$data->id.'/factura-pdf?inline=1') }}"
                                   class="btn-accion-tabla tooltipsC"
                                   title="Ver PDF escaneado"
                                   target="_blank"
                                   rel="noopener noreferrer">
                                    <i class="fa fa-file-pdf-o text-danger"></i>
                                </a>
                                @endif
                                @if (!empty($data->comprobante_proveedor_id) && (can('editar-comprobante-proveedor', false) || can('listar-comprobante-proveedor', false)))
                                <a href="{{ route('editar_comprobante_proveedor', [
                                        'id' => $data->comprobante_proveedor_id,
                                        'origen' => \App\Support\Compras\ComprobanteProveedorRetornoLegajoSupport::ORIGEN_PRECARGA,
                                    ]) }}"
                                   class="btn-accion-tabla tooltipsC text-primary"
                                   title="Abrir comprobante #{{ $data->comprobante_proveedor_id }}">
                                    <i class="fa fa-external-link"></i>
                                </a>
                                @elseif (can('crear-comprobante-proveedor', false)
                                    && \App\Support\Compras\PrecargaComprobanteEstados::puedeGenerarComprobante((string) ($data->estado ?? '')))
                                <a href="{{ route('crear_comprobante_proveedor', [
                                        'precarga_id' => $data->id,
                                        'origen' => \App\Support\Compras\ComprobanteProveedorRetornoLegajoSupport::ORIGEN_PRECARGA,
                                    ]) }}"
                                   class="btn-accion-tabla tooltipsC text-success"
                                   title="Abrir alta de comprobante desde esta precarga (no graba hasta Guardar)">
                                    <i class="fa fa-file-text-o"></i>
                                </a>
                                @endif
                                @if (\App\Support\Compras\PrecargaComprobanteEstados::puedeMarcarCargadaAnita((string) ($data->estado ?? ''))
                                    && empty($data->comprobante_proveedor_id))
                                    @include('compras.precarga_comprobante_proveedor.partials.boton_marcar_cargada_anita', [
                                        'precargaId' => $data->id,
                                        'retornoListadoQuery' => $retornoListadoQuery,
                                    ])
                                @endif
                       			@if (can('editar-precarga-proveedores', false))
                                	<a href="{{route('editar_precarga_comprobante_proveedor', ['id' => $data->id] + $retornoListadoQuery)}}" class="btn-accion-tabla tooltipsC" title="Editar este registro">
                                    <i class="fa fa-edit"></i>
                                	</a>
								@endif
                       			@if (can('borrar-precarga-proveedores', false))
                                <form action="{{route('eliminar_precarga_comprobante_proveedor', ['id' => $data->id] + $retornoListadoQuery)}}" class="d-inline form-eliminar" method="POST">
                                    @csrf @method("delete")
                                    <button type="submit" class="btn-accion-tabla eliminar tooltipsC" title="Eliminar este registro">
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
        </div>
    </div>
</div>
{{ $datas->appends($filtrosQuery ?? [])->links() }}
@if (!empty($pdfIaHabilitado) && can('crear-precarga-proveedores', false))
    @include('compras.precarga_comprobante_proveedor.partials.modal_pdf_ia', [
        'pdfIaOverlayId' => 'precarga-pdf-ia-proceso-overlay',
    ])
    @include('includes.proceso_overlay_aviso', [
        'overlayId' => 'precarga-pdf-ia-proceso-overlay',
        'tituloId' => 'precarga-pdf-ia-proceso-titulo',
        'subtituloId' => 'precarga-pdf-ia-proceso-subtitulo',
        'titulo' => 'Analizando factura…',
        'subtitulo' => 'El OCR y la validación pueden demorar. No cierre la página.',
    ])
@endif
@endsection
