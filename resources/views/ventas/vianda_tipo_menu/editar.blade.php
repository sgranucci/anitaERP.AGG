@extends("theme.$theme.layout")
@section('titulo')
    Tipos de men&uacute; de vianda
@endsection

@section("scripts")
<script src="{{asset("assets/pages/scripts/admin/editar.js")}}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/stock/articulo/consulta.js') }}"></script>
<script src="{{ asset('assets/pages/scripts/ventas/vianda_tipo_menu/form.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/ventas/vianda_tipo_menu/form.js')) }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/ventas/vianda_tipo_menu/replicar.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/ventas/vianda_tipo_menu/replicar.js')) }}" type="text/javascript"></script>
@endsection

@section('contenido')
@php
    $puedeReplicar = ! empty($puede_replicar_vianda_tipo_menu)
        && ($empresa_query_replicar ?? collect())->count() > 1;
@endphp
<div class="row">
    <div class="col-lg-12">
        @include('includes.form-error')
        @include('includes.mensaje')
        <div class="card card-primary">
            <div class="card-header">
                <h3 class="card-title">Editar tipo de men&uacute; de vianda</h3>
                <div class="card-tools d-flex flex-wrap align-items-center justify-content-end" style="gap: 6px;">
                    @if ($puedeReplicar)
                        <button type="button"
                                class="btn btn-outline-light btn-sm btn-replicar-vianda-tipo-menu"
                                title="Replicar men&uacute; a otras empresas"
                                data-id="{{ $data->id }}"
                                data-nombre="{{ $data->nombre }}"
                                data-empresa-id="{{ (int) $data->empresa_id }}"
                                data-empresa-nombre="{{ optional($data->empresa)->nombre }}"
                                data-url="{{ route('replicar_vianda_tipo_menu_gastronomia', ['id' => $data->id]) }}">
                            <i class="fa fa-copy"></i> Replicar a otras empresas
                        </button>
                    @endif
                    <a href="{{ route('consultar_vianda_tipo_menu_gastronomia') }}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-fw fa-reply-all"></i> Volver al listado
                    </a>
                </div>
            </div>
            <form action="{{ route('actualizar_vianda_tipo_menu_gastronomia', ['id' => $data->id]) }}" id="form-general" class="form-horizontal form--label-right" method="POST" autocomplete="off">
                @csrf
                @method('PUT')
                <div class="card-body">
                    @include('ventas.vianda_tipo_menu.form')
                </div>
                <div class="card-footer">
                    <div class="row">
                        <div class="col-lg-3"></div>
                        <div class="col-lg-6 d-flex flex-wrap align-items-center" style="gap: 8px;">
                            @include('includes.boton-form-editar')
                            @if ($puedeReplicar)
                                <button type="button"
                                        class="btn btn-outline-primary btn-replicar-vianda-tipo-menu"
                                        title="Replicar men&uacute; a otras empresas"
                                        data-id="{{ $data->id }}"
                                        data-nombre="{{ $data->nombre }}"
                                        data-empresa-id="{{ (int) $data->empresa_id }}"
                                        data-empresa-nombre="{{ optional($data->empresa)->nombre }}"
                                        data-url="{{ route('replicar_vianda_tipo_menu_gastronomia', ['id' => $data->id]) }}">
                                    <i class="fa fa-copy"></i> Replicar a otras empresas
                                </button>
                            @endif
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

@include('ventas.vianda_tipo_menu.partials.modal_replicar')
@endsection
