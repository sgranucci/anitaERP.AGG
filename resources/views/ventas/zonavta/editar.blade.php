@extends("theme.$theme.layout")
@section('titulo')
    Zonas de venta
@endsection

@section("scripts")
<script src="{{ asset('assets/pages/scripts/admin/crear.js') }}" type="text/javascript"></script>
@endsection

@section('contenido')
@php
    $esBierzo = \App\Support\Ventas\ZonavtaDestinoElBierzoSupport::activo();
@endphp
<div class="row">
    <div class="col-lg-12">
        @include('includes.form-error')
        @include('includes.mensaje')
        <div class="card {{ $esBierzo ? 'card-primary' : 'card-danger' }}">
            <div class="card-header">
                <h3 class="card-title">Editar zona de venta</h3>
                <div class="card-tools">
                    @if (isset($referer) && $referer)
                        <a href="#" onclick="history.back()" class="btn btn-outline-info btn-sm">
                            <i class="fa fa-fw fa-reply-all"></i> Volver atr&aacute;s
                        </a>
                    @else
                        <a href="{{ route('zonavta') }}" class="btn btn-outline-info btn-sm">
                            <i class="fa fa-fw fa-reply-all"></i> Volver al listado
                        </a>
                    @endif
                </div>
            </div>
            <form action="{{ route('actualizar_zonavta', ['id' => $data->id]) }}" id="form-general" class="form-horizontal form--label-right" method="POST" autocomplete="off">
                @csrf @method('put')
                <div class="card-body">
                    @if ($esBierzo)
                        <div class="row">
                            <div class="col-lg-6">
                                <div class="card card-outline card-primary h-100">
                                    <div class="card-header">
                                        <h3 class="card-title">Zona de venta</h3>
                                    </div>
                                    <div class="card-body">
                                        @include('ventas.zonavta.form')
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-6">
                                <div class="card card-outline card-info h-100">
                                    <div class="card-header">
                                        <h3 class="card-title">Destino certificados SENASA</h3>
                                    </div>
                                    <div class="card-body">
                                        @include('ventas.zonavta.partials.form_destino_elbierzo')
                                    </div>
                                </div>
                            </div>
                        </div>
                    @else
                        @include('ventas.zonavta.form')
                    @endif
                </div>
                <div class="card-footer">
                    <div class="row">
                        <div class="col-lg-3"></div>
                        <div class="col-lg-6">
                            @include('includes.boton-form-editar')
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
