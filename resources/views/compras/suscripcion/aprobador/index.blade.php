@extends("theme.$theme.layout")
@section('titulo')
    Aprobadores de suscripciones
@endsection

@section('scripts')
<script src="{{ asset('assets/pages/scripts/admin/index.js') }}" type="text/javascript"></script>
@endsection

@section('contenido')
@php
    $qs = $filtrosQuery ?? array_filter(['empresa_id' => $empresa_id ?: null]);
    $retornoListadoQuery = \App\Support\Listado\QueryRetornoListado::retornoLinksDesdeFiltrosQuery($qs);
@endphp
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')

        @if (($diagnostico['sin_gerente'] ?? []) !== [])
            <div class="alert alert-warning">
                <strong>Hay suscripciones sin destino de aprobación</strong> en esta empresa.
                <ul class="mb-0 mt-1">
                    @foreach ($diagnostico['sin_gerente'] as $cc)
                        <li>
                            {{ trim($cc['codigo'].' '.$cc['nombre']) }} — {{ $cc['suscripciones'] }} suscripción(es)
                            <a href="{{ route('crear_aprobador_suscripcion', $retornoListadoQuery) }}">cargar gerente</a>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Aprobadores de suscripciones</h3>
                <div class="card-tools d-flex flex-wrap align-items-center justify-content-end">
                    @include('includes.compras.boton-manual-suscripciones')
                    @if (can('configurar-suscripcion', false))
                        <a href="{{ route('crear_aprobador_suscripcion', $retornoListadoQuery) }}" class="btn btn-primary btn-sm ml-1">
                            <i class="fa fa-plus"></i> Nuevo registro
                        </a>
                    @endif
                    <a href="{{ route('consultar_suscripcion') }}" class="btn btn-outline-light btn-sm ml-1">← Suscripciones</a>
                </div>
            </div>

            @include('compras.suscripcion.partials.filtros_externos', [
                'rutaNombre' => 'aprobadores_suscripcion',
                'empresa_query' => $empresa_query,
                'empresa_id' => $empresa_id,
                'filtrosQuery' => $qs,
            ])

            <div class="card-body py-2 border-bottom">
                <p class="text-muted small mb-0">
                    Un gerente (usuario AnitaERP) por centro de costo. Recibe el alta y las revalidaciones por desvío de su área.
                </p>
            </div>

            <div class="card-body table-responsive p-0">
                @include('includes.exportar-tabla-queryparams', [
                    'ruta' => 'exportar_aprobadores_suscripcion',
                    'queryparams' => $qs,
                ])
                <table class="table table-striped table-bordered table-hover mb-0" id="tabla-paginada">
                    <thead style="background:#85C1E9;color:#17202A;">
                        <tr>
                            <th class="width20">ID</th>
                            <th>Empresa</th>
                            <th>Centro de costo</th>
                            <th>Gerente</th>
                            <th class="text-center">Suscripciones</th>
                            <th class="width120" data-orderable="false"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($filas as $fila)
                            <tr>
                                <td>{{ $fila['id'] }}</td>
                                <td>{{ $fila['empresa'] }}</td>
                                <td>
                                    <strong>{{ $fila['codigo'] }}</strong>
                                    {{ $fila['nombre'] }}
                                </td>
                                <td>
                                    {{ $fila['usuario_nombre'] }}
                                    <small class="text-muted d-block">{{ $fila['usuario_codigo'] }}</small>
                                </td>
                                <td class="text-center">
                                    @if ($fila['suscripciones'] > 0)
                                        <span class="badge badge-light">{{ $fila['suscripciones'] }}</span>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td class="text-nowrap">
                                    @if (can('configurar-suscripcion', false))
                                        <a href="{{ route('editar_aprobador_suscripcion', ['id' => $fila['id']] + $retornoListadoQuery) }}"
                                           class="btn-accion-tabla tooltipsC" title="Editar este registro">
                                            <i class="fa fa-edit"></i>
                                        </a>
                                        <form action="{{ route('eliminar_aprobador_suscripcion', ['id' => $fila['id']] + $retornoListadoQuery) }}"
                                              class="d-inline form-eliminar" method="POST">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn-accion-tabla eliminar tooltipsC" title="Eliminar este registro">
                                                <i class="fa fa-times-circle text-danger"></i>
                                            </button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">
                                    No hay aprobadores cargados.
                                    @if (can('configurar-suscripcion', false))
                                        <a href="{{ route('crear_aprobador_suscripcion', $retornoListadoQuery) }}">Crear el primero</a>
                                    @endif
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
