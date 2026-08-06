@extends("theme.$theme.layout")
@section('titulo')
    Usuarios de vianda
@endsection

@section("scripts")
<script src="{{asset("assets/pages/scripts/admin/editar.js")}}" type="text/javascript"></script>
@endsection

@section('contenido')
@php
    $volverListadoUrl = route('consultar_vianda_usuario_gastronomia', $filtrosQuery ?? []);
    $modoConsulta = request()->input('vista') === 'consulta' || ! empty($soloConsulta);
    $soloLecturaConsulta = $modoConsulta && empty($puedeActualizarUsuario);
@endphp
<div class="row">
    <div class="col-lg-12">
        @include('includes.form-error')
        @include('includes.mensaje')
        <div class="card card-primary">
            <div class="card-header">
                <h3 class="card-title">
                    @if ($modoConsulta && $soloLecturaConsulta)
                        Consultar usuario de vianda
                    @else
                        Editar usuario de vianda
                    @endif
                </h3>
                <div class="card-tools">
                    @if (empty($ocultarVolver))
                        <a href="{{ $volverListadoUrl }}" class="btn btn-outline-info btn-sm">
                            <i class="fa fa-fw fa-reply-all"></i> Volver al listado
                        </a>
                    @endif
                </div>
            </div>
            <form action="{{ route('actualizar_vianda_usuario_gastronomia', ['id' => $data->id] + ($filtrosQuery ?? [])) }}" id="form-general" class="form-horizontal form--label-right" method="POST" autocomplete="off" @if($soloLecturaConsulta) onsubmit="return false;" @endif>
                @csrf
                @method('PUT')
                @if ($modoConsulta)
                    <input type="hidden" name="origen" value="modal_consulta">
                    <input type="hidden" name="vista" value="consulta">
                @endif
                <div class="card-body @if($soloLecturaConsulta) pe-none @endif" @if($soloLecturaConsulta) style="opacity:.92" @endif>
                    @include('ventas.vianda_usuario.form', ['soloConsulta' => $modoConsulta])
                </div>
                <div class="card-footer">
                    <div class="row">
                        <div class="col-lg-3"></div>
                        <div class="col-lg-6 text-center">
                            @if (! $modoConsulta)
                                @include('includes.boton-form-editar')
                            @else
                                @if (! empty($puedeActualizarUsuario))
                                    @include('includes.boton-form-editar')
                                @endif
                                <button type="button" class="btn btn-secondary @if(!empty($puedeActualizarUsuario)) ml-2 @endif" onclick="window.close()">Cerrar solapa</button>
                            @endif
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
