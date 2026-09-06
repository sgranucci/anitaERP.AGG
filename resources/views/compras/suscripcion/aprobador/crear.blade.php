@extends("theme.$theme.layout")
@section('titulo')
    {{ $nivel ? 'Editar aprobador' : 'Nuevo aprobador' }}
@endsection

@section('scripts')
<script src="{{ asset('assets/pages/scripts/admin/crear.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/contable/centrocosto/consulta.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/admin/usuario/consulta.js') }}" type="text/javascript"></script>
<script>
(function () {
    // El gerente puede ser de cualquier empresa asignada al usuario.
    window._consultaUsuarioOmitirFiltroEmpresaFijo = true;
    window._consultaUsuarioOmitirFiltroEmpresa = true;

    document.addEventListener('DOMContentLoaded', function () {
        if (typeof activa_eventos_consultacentrocosto === 'function') {
            activa_eventos_consultacentrocosto();
        }
        if (typeof activa_eventos_consultausuario === 'function') {
            activa_eventos_consultausuario();
        }
    });
})();
</script>
@endsection

@section('contenido')
@php
    $volverQs = $filtrosQuery ?? array_filter(['empresa_id' => $empresa_id ?: null]);
    $accion = $nivel
        ? route('actualizar_aprobador_suscripcion', ['id' => $nivel->id] + $volverQs)
        : route('guardar_aprobador_suscripcion', $volverQs);
@endphp
<div class="row">
    <div class="col-lg-12">
        @include('includes.form-error')
        @include('includes.mensaje')
        <div class="card card-{{ $nivel ? 'primary' : 'info' }}">
            <div class="card-header">
                <h3 class="card-title">{{ $nivel ? 'Editar aprobador' : 'Nuevo aprobador' }}</h3>
                <div class="card-tools">
                    <a href="{{ route('aprobadores_suscripcion', $volverQs) }}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-fw fa-reply-all"></i> Volver al listado
                    </a>
                </div>
            </div>
            <form action="{{ $accion }}" id="form-general" class="form-horizontal form--label-right" method="POST" autocomplete="off">
                @csrf
                @if ($nivel)
                    @method('PUT')
                @endif
                <div class="card-body">
                    <p class="text-muted small">
                        El gerente autorizado recibe todas las suscripciones de ese centro de costo
                        y las revalidaciones por desvío. Es un usuario del sistema AnitaERP.
                    </p>
                    @include('compras.suscripcion.aprobador.form')
                </div>
                <div class="card-footer">
                    <div class="row">
                        <div class="col-lg-3"></div>
                        <div class="col-lg-6">
                            @include('includes.boton-form-crear')
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

@include('includes.contable.modalconsultacentrocosto')
@include('includes.admin.modalconsultausuario')
@endsection
