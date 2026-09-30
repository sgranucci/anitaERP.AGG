@extends("theme.$theme.layout")
@section('titulo')
    Editar marketplace
@endsection
@section('scripts')
<script src="{{ asset('assets/pages/scripts/admin/crear.js') }}" type="text/javascript"></script>
@endsection
@section('contenido')
@php
    $soloConsulta = ! empty($soloConsulta);
    $puedeActualizar = ! empty($puedeActualizar);
@endphp
<div class="row">
    <div class="col-lg-12">
        @include('includes.form-error')
        @include('includes.mensaje')
        <div class="card card-primary">
            <div class="card-header d-flex flex-wrap align-items-center justify-content-between">
                <h3 class="card-title mb-0">
                    @if ($soloConsulta && ! $puedeActualizar)
                        Consultar marketplace
                    @else
                        Editar marketplace
                    @endif
                </h3>
                <div class="card-tools ml-auto">
                    @if (! $soloConsulta)
                        <a href="{{ route('facturacion_local_marketplaces') }}" class="btn btn-outline-info btn-sm">
                            <i class="fa fa-reply-all"></i> Volver al listado
                        </a>
                    @endif
                </div>
            </div>
            <form action="{{ route('actualizar_marketplace', $data->id) }}" method="POST" id="form-general" class="form-horizontal" autocomplete="off" @if($soloConsulta && ! $puedeActualizar) onsubmit="return false;" @endif>
                @csrf
                @method('PUT')
                @if ($soloConsulta)
                    <input type="hidden" name="origen" value="modal_consulta">
                @endif
                <div class="card-body @if($soloConsulta && ! $puedeActualizar) pe-none @endif">
                    @include('ventas.facturacion_local.marketplace.partials.form')
                </div>
                <div class="card-footer text-center">
                    @if ($puedeActualizar)
                        <button type="submit" class="btn btn-primary">Actualizar</button>
                    @endif
                    @if ($soloConsulta)
                        <button type="button" class="btn btn-secondary @if($puedeActualizar) ml-2 @endif" onclick="window.close()">Cerrar solapa</button>
                    @endif
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
