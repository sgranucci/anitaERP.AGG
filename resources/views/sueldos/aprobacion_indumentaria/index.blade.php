@extends("theme.$theme.layout")
@section('titulo')
    Aprobación de indumentaria
@endsection

@section('contenido')
@php
    $puedeEditar = can('editar-aprobacion-indumentaria', false);
    $porScope = $niveles->groupBy(fn ($n) => $n->agrupamiento_id ? 'AGR:'.$n->agrupamiento_id : 'TODOS');
@endphp
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Árbol de aprobación de solicitudes de indumentaria</h3>
            </div>
            <div class="card-body">
                <p class="text-muted">
                    Defin&iacute; los niveles de aprobadores por empresa (y opcionalmente por agrupamiento).
                    Una solicitud pasa por los niveles en orden; en cada nivel, <strong>cualquiera</strong> de los
                    usuarios listados puede aprobar o rechazar. <strong>Sin niveles configurados, la aprobaci&oacute;n
                    queda deshabilitada y la solicitud se aprueba autom&aacute;ticamente.</strong>
                    Si hay niveles por agrupamiento, tienen prioridad sobre los de la empresa.
                </p>

                <form method="get" action="{{ route('aprobacion_indumentaria') }}" class="form-inline mb-3">
                    <label class="mr-2">Empresa</label>
                    <select name="empresa_id" class="form-control form-control-sm mr-2" onchange="this.form.submit()">
                        @foreach ($empresas as $e)
                            <option value="{{ $e->id }}" {{ (int) $empresaId === (int) $e->id ? 'selected' : '' }}>{{ $e->nombre }}</option>
                        @endforeach
                    </select>
                </form>

                @if ($puedeEditar)
                    <form method="post" action="{{ route('guardar_aprobacion_indumentaria') }}" class="border rounded p-2 mb-3 bg-light">
                        @csrf
                        <input type="hidden" name="empresa_id" value="{{ $empresaId }}">
                        <div class="form-row align-items-end">
                            <div class="form-group col-md-4 mb-1">
                                <label class="small mb-1">Agrupamiento (opcional)</label>
                                <select name="agrupamiento_id" class="form-control form-control-sm">
                                    <option value="">Todos (empresa)</option>
                                    @foreach ($agrupamientos as $a)
                                        <option value="{{ $a->id }}">{{ $a->descripcion }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group col-md-2 mb-1">
                                <label class="small mb-1">Nivel</label>
                                <input type="number" name="nivel" class="form-control form-control-sm" min="1" max="20" value="1" required>
                            </div>
                            <div class="form-group col-md-4 mb-1">
                                <label class="small mb-1">Aprobador</label>
                                <select name="usuario_id" class="form-control form-control-sm" required>
                                    <option value="">— Usuario —</option>
                                    @foreach ($usuarios as $u)
                                        <option value="{{ $u->id }}">{{ $u->nombre }} ({{ $u->usuario }})</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group col-md-2 mb-1">
                                <button type="submit" class="btn btn-primary btn-sm btn-block"><i class="fa fa-plus"></i> Agregar</button>
                            </div>
                        </div>
                    </form>
                @endif

                @forelse ($porScope as $scope => $filas)
                    <h6 class="mt-3">
                        @if ($scope === 'TODOS')
                            <span class="badge badge-secondary">Toda la empresa</span>
                        @else
                            <span class="badge badge-info">Agrupamiento: {{ $agrupNombres[(int) str_replace('AGR:', '', $scope)] ?? $scope }}</span>
                        @endif
                    </h6>
                    <table class="table table-sm table-bordered">
                        <thead style="background:#85C1E9;color:#17202A">
                            <tr><th style="width:15%">Nivel</th><th>Aprobador</th><th style="width:12%"></th></tr>
                        </thead>
                        <tbody>
                            @foreach ($filas->sortBy([['nivel','asc'],['orden','asc']]) as $n)
                                <tr>
                                    <td>{{ $n->nivel }}</td>
                                    <td>{{ optional($n->usuario)->nombre }} <small class="text-muted">({{ optional($n->usuario)->usuario }})</small></td>
                                    <td>
                                        @if ($puedeEditar)
                                            <form method="post" action="{{ route('eliminar_aprobacion_indumentaria', $n->id) }}" onsubmit="return confirm('¿Quitar este aprobador?');">
                                                @csrf @method('DELETE')
                                                <button class="btn btn-xs btn-danger"><i class="fa fa-times"></i></button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @empty
                    <div class="alert alert-warning">Sin niveles configurados para esta empresa: las solicitudes se aprobar&aacute;n autom&aacute;ticamente.</div>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection
