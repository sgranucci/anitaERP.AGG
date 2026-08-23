@extends("theme.$theme.layout")
@section('titulo')
    Crear Ticket de Ingreso de Proveedor
@endsection

@section("scripts")
<script src="{{asset("assets/pages/scripts/admin/crear.js")}}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/compras/proveedor/consulta.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/compras/proveedor/consulta.js')) ?: time() }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/seguridad/ingreso_proveedor/form.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/seguridad/ingreso_proveedor/form.js')) ?: time() }}" type="text/javascript"></script>
@endsection

@section('contenido')
@php
    $volverListadoUrl = route('ingreso_proveedor', $filtrosQuery ?? []);
@endphp
<div class="row">
    <div class="col-lg-12">
        @include('includes.form-error')
        @include('includes.mensaje')
        <div class="card card-primary">
            <div class="card-header">
                <h3 class="card-title">Crear Ticket de Ingreso de Proveedor</h3>
                <div class="card-tools">
                    @if (!empty($soloConsulta))
                        <button type="button" class="btn btn-secondary btn-sm" onclick="window.close()">
                            <i class="fa fa-fw fa-times"></i> Cerrar solapa
                        </button>
                    @else
                        <a href="{{ $volverListadoUrl }}" class="btn btn-outline-info btn-sm">
                            <i class="fa fa-fw fa-reply-all"></i> Volver al listado
                        </a>
                    @endif
                </div>
            </div>
            <form action="{{ route('guardar_ingreso_proveedor') }}" id="form-general" class="form-horizontal form--label-right" method="POST" autocomplete="off" enctype="multipart/form-data">
                @csrf
                @if (!empty($soloConsulta))
                    <input type="hidden" name="origen" value="modal_consulta">
                    <input type="hidden" name="vista" value="consulta">
                @endif
                <div class="card-body">
                    @include('seguridad.ingreso_proveedor.form')
                </div>
                <div class="card-footer">
                    <div class="row">
                        <div class="col-lg-3"></div>
                        <div class="col-lg-6 text-center">
                            @include('includes.boton-form-crear')
                            @if (!empty($soloConsulta))
                                <button type="button" class="btn btn-secondary ml-2" onclick="window.close()">Cerrar solapa</button>
                            @endif
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@include('includes.compras.modalconsultaproveedor')
@include('seguridad.ingreso_proveedor.partials.modal_consulta_contrato')
@include('seguridad.ingreso_proveedor.partials.modal_alta_rapida_proveedor')
@endsection
