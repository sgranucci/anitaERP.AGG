@extends("theme.$theme.layout")
@section('titulo')
    Árboles de aprobación
@endsection

@section("styles")
<link rel="stylesheet" href="{{ asset('assets/css/arbolaprobacion.css') }}?v={{ @filemtime(public_path('assets/css/arbolaprobacion.css')) ?: time() }}">
@endsection

@section("scripts")
<script src="{{asset("assets/pages/scripts/admin/index.js")}}" type="text/javascript"></script>
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        <div class="anita-arbol anita-arbol-index">
            @include('includes.mensaje')

            <div class="anita-arbol-hero">
                <div class="anita-arbol-hero-row">
                    <div>
                        <p class="anita-arbol-brand">Configuración · Circuitos</p>
                        <h1 class="anita-arbol-title">Árboles de aprobación</h1>
                        <p class="anita-arbol-sub">Circuitos por documento, centro de costo y dual-rama por cuenta en requisiciones.</p>
                    </div>
                    <div class="anita-arbol-hero-actions">
                        @if (can('crea-arbol-de-aprobacion', false))
                            <a href="{{ route('crea_arbolaprobacion') }}" class="btn btn-light btn-sm">
                                <i class="fa fa-plus"></i> Nuevo árbol
                            </a>
                        @endif
                    </div>
                </div>
            </div>

            <div class="anita-arbol-panel p-0 overflow-hidden">
                @include('configuracion.arbolaprobacion.partials.filtros_externos')
                <div class="table-responsive">
                    <table class="table table-hover table-striped mb-0" id="tabla-data">
                        <thead>
                            <tr>
                                <th class="width20">ID</th>
                                <th>Nombre</th>
                                <th>Empresa</th>
                                <th>Tipo</th>
                                <th>Estado</th>
                                <th>Niveles</th>
                                <th class="width80" data-orderable="false"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($datas as $data)
                            <tr>
                                <td>{{ $data->id }}</td>
                                <td><strong>{{ $data->nombre }}</strong></td>
                                <td>{{ optional($data->empresas)->nombre }}</td>
                                <td>
                                    <span class="anita-arbol-chip anita-arbol-chip-navy">{{ $data->tipoarbol }}</span>
                                </td>
                                <td>
                                    <span class="anita-arbol-chip {{ in_array((string) $data->estado, ['Activo', 'ACTIVO'], true) ? 'anita-arbol-chip-ok' : 'anita-arbol-chip-warn' }}">
                                        {{ $data->estado }}
                                    </span>
                                </td>
                                <td>
                                    <ul class="anita-arbol-nivel-list">
                                        @foreach($data->arbolaprobacion_niveles as $nivel)
                                            <li class="anita-arbol-nivel-item">
                                                <span class="anita-arbol-chip anita-arbol-chip-teal">N{{ $nivel->nivel }}</span>
                                                @if(!empty($nivel->rama))
                                                    <span class="anita-arbol-chip {{ $nivel->rama === 'B' ? 'anita-arbol-chip-warn' : 'anita-arbol-chip-ok' }}">Rama {{ $nivel->rama }}</span>
                                                @endif
                                                <span>CC {{ optional($nivel->centrocosto_ids)->codigo }}</span>
                                                <span class="text-muted">·</span>
                                                <span>{{ optional($nivel->usuarios)->nombre ?: 'auto' }}</span>
                                                @if(filled($nivel->documento_estado_al_aprobar))
                                                    <span class="text-muted">→ {{ $nivel->documento_estado_al_aprobar }}</span>
                                                @endif
                                            </li>
                                        @endforeach
                                    </ul>
                                </td>
                                <td>
                                    @if (can('edita-arbol-de-aprobacion', false))
                                        <a href="{{ route('edita_arbolaprobacion', ['id' => $data->id]) }}" class="btn-accion-tabla tooltipsC" title="Editar">
                                            <i class="fa fa-edit"></i>
                                        </a>
                                    @endif
                                    @if (can('borra-arbol-de-aprobacion', false))
                                        <form action="{{ route('elimina_arbolaprobacion', ['id' => $data->id]) }}" class="d-inline form-eliminar" method="POST">
                                            @csrf @method("delete")
                                            <button type="submit" class="btn-accion-tabla eliminar tooltipsC" title="Eliminar">
                                                <i class="fa fa-times-circle text-danger"></i>
                                            </button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
