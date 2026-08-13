@extends("theme.$theme.layout")
@section('titulo')
    Administración de Tickets
@endsection

@section("scripts")
<script src="{{ asset('assets/pages/scripts/ticket/administracion_ticket/crear.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/ticket/administracion_ticket/crear.js')) ?: time() }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/ticket/tarea_ticket/consulta.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/ticket/tarea_ticket/consulta.js')) ?: time() }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/ticket/tecnico_ticket/consulta.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/ticket/tecnico_ticket/consulta.js')) ?: time() }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/ticket/categoria_ticket/consulta.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/ticket/categoria_ticket/consulta.js')) ?: time() }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/ticket/subcategoria_ticket/consulta.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/ticket/subcategoria_ticket/consulta.js')) ?: time() }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/stock/articulo/consulta.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/stock/articulo/consulta.js')) ?: time() }}" type="text/javascript"></script>
@endsection

@section('contenido')
@php
    $volverListadoUrl = route('consulta_administracion_ticket', $filtrosQuery ?? []);
@endphp
<div class="row">
    <div class="col-lg-12">
        @include('includes.form-error')
        @include('includes.mensaje')
        <div class="card card-primary">
            <div class="card-header">
                <h3 class="card-title">Editar Ticket — {{ $data->areadestinos->nombre ?? '' }} N° {{ $data->id }}</h3>
                <div class="card-tools">
                    <a href="{{ $volverListadoUrl }}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-fw fa-reply-all"></i> Volver al listado
                    </a>
                </div>
            </div>
            <form action="{{ route('actualiza_administracion_ticket', ['id' => $data->id] + ($filtrosQuery ?? [])) }}" id="form-general" class="form-horizontal form--label-right" method="POST" enctype="multipart/form-data" autocomplete="off" novalidate>
                @csrf @method("put")
                @include('includes.tabs-activas-estilos')
                <div class="card-body">
                    <div class="tabs-activas">
                        @include('ticket.administracion_ticket.partials.tabs_nav')
                    </div>
                    <div class="tab-content pt-3">
                        <div class="tab-pane fade show active" id="tab-datos" role="tabpanel">
                            @include('ticket.administracion_ticket.form')
                        </div>
                        <div class="tab-pane fade" id="tab-articulos" role="tabpanel">
                            @include('ticket.administracion_ticket.form2')
                        </div>
                        <div class="tab-pane fade" id="tab-historia" role="tabpanel">
                            @include('ticket.administracion_ticket.form3')
                        </div>
                        <div class="tab-pane fade" id="tab-archivos" role="tabpanel">
                            @include('ticket.administracion_ticket.form4')
                        </div>
                    </div>
                </div>
                <div class="card-footer">
                    <div class="row">
                        <div class="col-lg-3"></div>
                        <div class="col-lg-6">
                            <button type="submit"
                                    form="form-general"
                                    id="btn-actualizar-administracion-ticket"
                                    class="btn btn-success">
                                Actualizar
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@include('includes.ticket.modalconsultacategoria')
@include('includes.ticket.modalconsultatarea_ticket')
@include('includes.ticket.modalconsultatecnico_ticket')
@include('includes.ticket.modalconsultasubcategoria')
@include('includes.stock.modalconsultaarticulo')
@endsection
