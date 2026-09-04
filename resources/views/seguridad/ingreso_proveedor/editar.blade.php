@extends("theme.$theme.layout")
@section('titulo')
    Editar Ticket de Ingreso de Proveedor
@endsection

@section("scripts")
<script src="{{asset("assets/pages/scripts/admin/crear.js")}}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/compras/proveedor/consulta.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/compras/proveedor/consulta.js')) ?: time() }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/seguridad/ingreso_proveedor/form.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/seguridad/ingreso_proveedor/form.js')) ?: time() }}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/seguridad/ingreso_proveedor/autorizar.js")}}" type="text/javascript"></script>
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
                <h3 class="card-title">Editar Ticket de Ingreso de Proveedor</h3>
                <div class="card-tools">
                    @include('includes.ayuda.boton-guia', [
                        'slug' => 'ingreso-proveedores',
                        'titulo' => 'Guía: carga de tickets de ingreso de proveedores',
                        'clase' => 'btn btn-light btn-sm mr-1',
                    ])
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
            <div class="card-body pb-0">
                @include('seguridad.ingreso_proveedor.partials.acciones_seguridad', ['ticket' => $data])
            </div>
            @php
                $puedeActualizar = $puedeActualizar ?? can('actualizar-ingreso-proveedor', false);
                $soloLectura = !empty($soloConsulta) && ! $puedeActualizar;
            @endphp
            <form action="{{ route('actualizar_ingreso_proveedor', $data->id) }}" id="form-general" class="form-horizontal form--label-right" method="POST" autocomplete="off" enctype="multipart/form-data">
                @csrf
                @method('PUT')
                @if (!empty($soloConsulta))
                    <input type="hidden" name="origen" value="modal_consulta">
                    <input type="hidden" name="vista" value="consulta">
                @endif
                <div class="card-body{{ $soloLectura ? ' pe-none' : '' }}">
                    @include('seguridad.ingreso_proveedor.form')
                </div>
                <div class="card-footer">
                    <div class="row">
                        <div class="col-lg-3"></div>
                        <div class="col-lg-6 text-center">
                            @if ($puedeActualizar)
                                <button type="submit" class="btn botonsubmit btn-success">Actualizar</button>
                            @endif
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
@include('seguridad.ingreso_proveedor.partials.modal_rechazo')
@endsection
