@extends("theme.$theme.layout")
@section('titulo')
    Árboles de aprobación
@endsection

@section("styles")
<link rel="stylesheet" href="{{ asset('assets/css/arbolaprobacion.css') }}?v={{ @filemtime(public_path('assets/css/arbolaprobacion.css')) ?: time() }}">
@endsection

@section("scripts")
<script src="{{asset("assets/pages/scripts/admin/crear.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/admin/usuario/consulta.js")}}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/contable/cuentacontable/consulta.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/contable/cuentacontable/consulta.js')) ?: time() }}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/configuracion/arbolaprobacion/crear.js")}}" type="text/javascript"></script>
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        <div class="anita-arbol">
            @include('includes.form-error')
            @include('includes.mensaje')

            <div class="anita-arbol-hero">
                <div class="anita-arbol-hero-row">
                    <div>
                        <p class="anita-arbol-brand">Configuración · Circuitos</p>
                        <h1 class="anita-arbol-title">Nuevo árbol de aprobación</h1>
                        <p class="anita-arbol-sub">Cabecera, niveles por centro de costo y, en requisiciones, dual-rama por cuenta contable.</p>
                    </div>
                    <div class="anita-arbol-hero-actions">
                        <a href="{{ route('consulta_arbolaprobacion') }}" class="btn btn-outline-light btn-sm">
                            <i class="fa fa-reply"></i> Volver al listado
                        </a>
                    </div>
                </div>
            </div>

            <form action="{{ route('guarda_arbolaprobacion') }}" id="form-general" class="form-horizontal form--label-right" method="POST" autocomplete="off">
                @csrf
                @include('configuracion.arbolaprobacion.form')
                <div class="anita-arbol-footer">
                    @include('includes.boton-form-crear')
                </div>
            </form>
            @include('includes.contable.modalconsultacuentacontable')
        </div>
    </div>
</div>
@endsection
