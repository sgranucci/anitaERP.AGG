@extends("theme.$theme.layout")
@section('titulo')
    Bienes de uso
@endsection

@section("scripts")
<script src="{{ asset('assets/pages/scripts/admin/editar.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/contable/cuentacontable/consulta.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/contable/centrocosto/consulta.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/compras/proveedor/consulta.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/contable/bien_uso_rubro/consulta.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/contable/bien_uso_rubro/consulta.js')) }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/contable/bien_uso_subrubro/consulta.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/contable/bien_uso_subrubro/consulta.js')) }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/contable/bien_uso/consulta.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/contable/bien_uso/consulta.js')) }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/contable/bien_uso/form.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/contable/bien_uso/form.js')) }}" type="text/javascript"></script>
@endsection

@section('contenido')
@php
    $volverListadoUrl = route('bien_uso', $filtrosQuery ?? []);
    $puedeEditar = empty($soloConsulta) && can('actualizar-bien-uso', false);
@endphp
@include('includes.tabs-activas-estilos')
<div class="row">
    <div class="col-lg-12">
        @include('includes.form-error')
        @include('includes.mensaje')
        <div class="card card-primary {{ ! empty($soloConsulta) && ! $puedeEditar ? 'pe-none' : '' }}">
            <div class="card-header">
                <h3 class="card-title">Editar bien de uso #{{ $data->id }}</h3>
                @if (empty($soloConsulta))
                <div class="card-tools">
                    <a href="{{ $volverListadoUrl }}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-fw fa-reply-all"></i> Volver al listado
                    </a>
                </div>
                @endif
            </div>
            <form action="{{ route('actualizar_bien_uso', ['id' => $data->id] + ($filtrosQuery ?? [])) }}" id="form-general" class="form-horizontal form--label-right" method="POST" autocomplete="off">
                @csrf
                @method('PUT')
                @if (! empty($soloConsulta))
                    <input type="hidden" name="origen" value="modal_consulta">
                @endif
                <div class="card-body">
                    <div class="tabs-activas">
                        <ul class="nav nav-tabs" id="tabs-bien-uso" role="tablist">
                            <li class="nav-item">
                                <a class="nav-link active" id="tab-contable-link" data-toggle="tab" href="#tab-contable" role="tab">
                                    <i class="fa fa-book"></i> Datos contables
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" id="tab-comprobante-link" data-toggle="tab" href="#tab-comprobante" role="tab">
                                    <i class="fa fa-file-invoice"></i> Comprobante
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" id="tab-inventario-link" data-toggle="tab" href="#tab-inventario" role="tab">
                                    <i class="fa fa-laptop"></i> Inventario IT
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" id="tab-asignaciones-link" data-toggle="tab" href="#tab-asignaciones" role="tab">
                                    <i class="fa fa-cubes"></i> Asignaciones
                                    @if (count($inventarioActual ?? []) > 0)
                                        <span class="badge badge-info">{{ count($inventarioActual) }}</span>
                                    @endif
                                </a>
                            </li>
                        </ul>
                    </div>
                    <div class="tab-content pt-3">
                        <div class="tab-pane fade show active" id="tab-contable" role="tabpanel">
                            @include('contable.bien_uso.partials.tab_datos_contables')
                            @if ($puedeEditar)
                                @include('includes.boton-form-editar')
                            @endif
                        </div>
                        <div class="tab-pane fade" id="tab-comprobante" role="tabpanel">
                            @include('contable.bien_uso.partials.tab_comprobante')
                            @if ($puedeEditar)
                                <button type="submit" form="form-general" class="btn botonsubmit btn-success">Actualizar</button>
                            @endif
                        </div>
                        <div class="tab-pane fade" id="tab-inventario" role="tabpanel">
                            @include('contable.bien_uso.partials.tab_inventario_it')
                            @if ($puedeEditar)
                                <button type="submit" form="form-general" class="btn botonsubmit btn-success">Actualizar</button>
                            @endif
                        </div>
                        <div class="tab-pane fade" id="tab-asignaciones" role="tabpanel">
                            @include('contable.bien_uso.partials.tab_asignaciones', [
                                'inventarioActual' => $inventarioActual ?? [],
                                'historial' => $historial ?? collect(),
                                'transferenciasPendientes' => $transferenciasPendientes ?? collect(),
                            ])
                            @if ($puedeEditar)
                                <button type="submit" form="form-general" class="btn botonsubmit btn-success">Actualizar</button>
                            @endif
                        </div>
                    </div>
                </div>
                <div class="card-footer">
                    <div class="row">
                        <div class="col-lg-3"></div>
                        <div class="col-lg-6">
                            @if (! empty($soloConsulta))
                                @include('includes.boton-form-consulta-modal')
                            @endif
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@include('includes.contable.modalconsultacuentacontable')
@include('includes.contable.modalconsultacentrocosto')
@include('includes.compras.modalconsultaproveedor')
@include('includes.contable.modalconsultabienusorubro')
@include('includes.contable.modalconsultabienusosubrubro')
@include('includes.contable.modalconsultabienuso')
@endsection
