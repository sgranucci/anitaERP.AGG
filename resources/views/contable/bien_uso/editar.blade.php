@extends("theme.$theme.layout")
@section('titulo')
    Bienes de uso
@endsection

@section("scripts")
<script src="{{asset("assets/pages/scripts/admin/editar.js")}}" type="text/javascript"></script>
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.form-error')
        @include('includes.mensaje')
        <div class="card card-primary">
            <div class="card-header">
                <h3 class="card-title">Editar bien de uso #{{ $data->id }}</h3>
                @if(empty($soloConsulta))
                <div class="card-tools">
                    <a href="{{ route('bien_uso') }}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-fw fa-reply-all"></i> Volver al listado
                    </a>
                </div>
                @endif
            </div>
            <form action="{{ route('actualizar_bien_uso', ['id' => $data->id]) }}" id="form-general" class="form-horizontal form--label-right" method="POST" autocomplete="off">
                @csrf
                @method('PUT')
                @if(!empty($soloConsulta))
                    <input type="hidden" name="origen" value="modal_consulta">
                @endif
                <div class="card-body">
                    <ul class="nav nav-tabs" id="tabs-bien-uso" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link active" id="tab-datos-link" data-toggle="tab" href="#tab-datos" role="tab">
                                <i class="fa fa-info-circle"></i> Datos generales
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
                    <div class="tab-content pt-3">
                        <div class="tab-pane fade show active" id="tab-datos" role="tabpanel">
                            @include('contable.bien_uso.form')
                        </div>
                        <div class="tab-pane fade" id="tab-asignaciones" role="tabpanel">
                            @include('contable.bien_uso.partials.tab_asignaciones', [
                                'inventarioActual' => $inventarioActual ?? [],
                                'historial' => $historial ?? collect(),
                                'transferenciasPendientes' => $transferenciasPendientes ?? collect(),
                            ])
                        </div>
                    </div>
                </div>
                <div class="card-footer">
                    <div class="row">
                        <div class="col-lg-3"></div>
                        <div class="col-lg-6">
                            @if(!empty($soloConsulta))
                                @include('includes.boton-form-consulta-modal')
                            @else
                                @include('includes.boton-form-editar')
                            @endif
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
