@extends("theme.$theme.layout")
@section('titulo')
Editar lista de precios proveedor
@endsection

@section('styles')
<link rel="stylesheet" href="{{ asset('assets/css/compras/listaprecio-proveedor-ui.css') }}?v={{ @filemtime(public_path('assets/css/compras/listaprecio-proveedor-ui.css')) ?: time() }}">
@endsection

@section("scripts")
<script src="{{ asset('assets/pages/scripts/admin/crear.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/stock/articulo/consulta.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/compras/proveedor/consulta.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/compras/proveedor/consulta.js')) ?: time() }}" type="text/javascript"></script>
<script>
    window.lpImportPreviewUrl = @json(route('importar_preview_listaprecio_proveedor'));
    window.lpImportExcelUrl = @json(route('importar_excel_listaprecio_proveedor', ['id' => $data->id] + ($filtrosQuery ?? [])));
</script>
<script src="{{ asset('assets/pages/scripts/compras/listaprecio_proveedor/crear.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/compras/listaprecio_proveedor/crear.js')) ?: time() }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/compras/listaprecio_proveedor/importar.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/compras/listaprecio_proveedor/importar.js')) ?: time() }}" type="text/javascript"></script>
@endsection

@section('contenido')
@php
    $volverListadoUrl = route('consultar_listaprecio_proveedor', $filtrosQuery ?? []);
    $soloConsulta = ! empty($soloConsulta);
    $puedeModificarLista = ! empty($puedeModificarLista);
    $visualizar = ! empty($visualizar);
    $ocultarVolver = ! empty($ocultarVolver);
    $cantArchivos = $data->listaprecio_proveedor_archivos?->count() ?? 0;
    $cantItems = $data->listaprecio_proveedor_articulos?->count() ?? 0;
@endphp
<div class="row lp-form" id="editar">
    <div class="col-lg-12">
        @include('includes.form-error')
        @include('includes.mensaje')
        <div class="card card-primary">
            <div class="card-header">
                <h3 class="card-title mb-0">
                    @if ($soloConsulta && ! $puedeModificarLista)
                        Consultar lista
                    @else
                        Editar lista
                    @endif
                    @if (! empty($data->nombre))
                        <span class="font-weight-normal">· {{ $data->nombre }}</span>
                    @endif
                    <small class="font-weight-normal">· ID {{ $data->id }}</small>
                </h3>
                <div class="card-tools">
                    @include('compras.listaprecio_proveedor.partials.botones_exportar', [
                        'listaId' => $data->id,
                        'variant' => 'header',
                    ])
                    @if (! $ocultarVolver)
                    <a href="{{ $volverListadoUrl }}" class="btn btn-outline-info btn-sm ml-1">
                        <i class="fa fa-fw fa-reply-all"></i> Volver al listado
                    </a>
                    @endif
                </div>
            </div>
            <form action="{{ route('actualizar_listaprecio_proveedor', ['id' => $data->id] + ($filtrosQuery ?? [])) }}" id="form-general" class="form-horizontal form--label-right" method="POST" enctype="multipart/form-data" autocomplete="off" @if($soloConsulta && ! $puedeModificarLista) onsubmit="return false;" @endif>
                @csrf @method('put')
                @if ($soloConsulta)
                    <input type="hidden" name="origen" value="modal_consulta">
                @endif
                <div class="card-body">
                    @include('includes.tabs-activas-estilos')
                    <div class="tabs-activas">
                        <ul class="nav nav-tabs" id="tabs-listaprecio-proveedor" role="tablist">
                                <li class="nav-item">
                                    <a class="nav-link active" data-toggle="tab" href="#tab-datos" role="tab">
                                        <i class="fa fa-info-circle"></i> Datos principales
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" data-toggle="tab" href="#tab-precios" role="tab">
                                        <i class="fa fa-list"></i> Precios
                                        @if ($cantItems > 0)
                                            <span class="badge badge-info">{{ $cantItems }}</span>
                                        @endif
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" data-toggle="tab" href="#tab-archivos" role="tab">
                                        <i class="fa fa-paperclip"></i> Archivos
                                        @if ($cantArchivos > 0)
                                            <span class="badge badge-info">{{ $cantArchivos }}</span>
                                        @endif
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" data-toggle="tab" href="#tab-historia" role="tab">
                                        <i class="fa fa-history"></i> Historia
                                    </a>
                                </li>
                            </ul>
                        </div>
                        <div class="tab-content pt-3">
                            <div class="tab-pane fade show active" id="tab-datos" role="tabpanel">
                                @include('compras.listaprecio_proveedor.form', ['visualizar' => $visualizar])
                            </div>
                            <div class="tab-pane fade" id="tab-precios" role="tabpanel">
                                @include('compras.listaprecio_proveedor.partials.solapa_precios', ['visualizar' => $visualizar])
                            </div>
                            <div class="tab-pane fade" id="tab-archivos" role="tabpanel">
                                @include('compras.listaprecio_proveedor.form_archivos', ['visualizar' => $visualizar])
                            </div>
                            <div class="tab-pane fade" id="tab-historia" role="tabpanel">
                                @include('compras.listaprecio_proveedor.partials.solapa_historia')
                            </div>
                        </div>
                    </div>
                <div class="card-footer">
                    <div class="row">
                        <div class="col-lg-12 text-center">
                            @if ($soloConsulta)
                                @if ($puedeModificarLista)
                                    @include('includes.boton-form-editar')
                                @endif
                                <button type="button" class="btn btn-secondary @if($puedeModificarLista) ml-2 @endif" onclick="window.close()">Cerrar solapa</button>
                            @else
                                @include('includes.boton-form-editar')
                            @endif
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@include('includes.stock.modalconsultaarticulo')
@include('includes.compras.modalconsultaproveedor')
@endsection
