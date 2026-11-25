@extends("theme.$theme.layout")
@section('titulo')
    Grupos liquidación final
@endsection

@section("scripts")
<script src="{{asset("assets/pages/scripts/admin/index.js")}}" type="text/javascript"></script>
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Grupos para liquidación final</h3>
                <div class="card-tools">
                    @if (can('crear-grupo-liq-final-sueldos', false))
                        <a href="{{ route('crear_grupo_liq_final_sueldos') }}" class="btn btn-outline-info btn-sm">
                            <i class="fa fa-fw fa-plus"></i> Nueva regla
                        </a>
                    @endif
                </div>
            </div>
            <div class="card-body border-bottom py-2">
                <p class="text-muted small mb-2">
                    Al liquidar tipo <strong>final</strong>, el motor une automáticamente el grupo según
                    empresa + sindicato del legajo + motivo de egreso (si no hay fila del motivo, usa la de
                    &laquo;cualquier motivo&raquo;).
                </p>
                <form method="get" action="{{ route('consultar_grupo_liq_final_sueldos') }}" class="form-inline flex-wrap">
                    <label class="mr-1">Empresa</label>
                    <select name="empresa_id" class="form-control form-control-sm mr-2 mb-1" onchange="this.form.submit()">
                        <option value="0">Todas</option>
                        @foreach ($empresas as $e)
                            <option value="{{ $e->id }}" {{ (int)$empresaId === (int)$e->id ? 'selected' : '' }}>{{ $e->nombre }}</option>
                        @endforeach
                    </select>
                    <label class="mr-1">Sindicato</label>
                    <select name="sindicato_id" class="form-control form-control-sm mr-2 mb-1" onchange="this.form.submit()">
                        <option value="0">Todos</option>
                        @foreach ($sindicatos as $s)
                            <option value="{{ $s->id }}" {{ (int)$sindicatoId === (int)$s->id ? 'selected' : '' }}>
                                {{ $s->codigo }} — {{ $s->descripcion }}
                            </option>
                        @endforeach
                    </select>
                    <button type="submit" class="btn btn-sm btn-outline-secondary mb-1">Filtrar</button>
                </form>
            </div>
            @php
                $qs = ['origen' => 'modal_consulta', 'vista' => 'consulta'];
                $verEmpresa = can('editar-empresas', false) || can('listar-empresas', false);
                $verSindicato = can('editar-sindicato-sueldos', false) || can('listar-sindicato-sueldos', false);
                $verMotivo = can('editar-motivoegreso-sueldos', false) || can('listar-motivoegreso-sueldos', false);
                $verGrupo = can('editar-grupo-concepto-sueldos', false) || can('listar-grupo-concepto-sueldos', false);
            @endphp
            <div class="card-body table-responsive p-0">
                <table class="table table-striped table-bordered table-hover mb-0">
                    <thead style="background-color:#85C1E9;color:#17202A;">
                        <tr>
                            <th>Empresa</th>
                            <th>Sindicato</th>
                            <th>Motivo de egreso</th>
                            <th>Grupo de conceptos</th>
                            <th>Activo</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($datas as $data)
                            @php
                                $empresaNombre = optional($data->empresa)->nombre;
                                $sindicatoTxt = trim((optional($data->sindicato)->codigo ?? '').' — '.(optional($data->sindicato)->descripcion ?? ''), ' —');
                                $motivoTxt = $data->motivoegreso_id
                                    ? trim((optional($data->motivoegreso)->codigo ?? '').' — '.(optional($data->motivoegreso)->descripcion ?? ''), ' —')
                                    : null;
                                $grupoTxt = trim((optional($data->grupoConcepto)->codigo ?? '').' — '.(optional($data->grupoConcepto)->descripcion ?? ''), ' —');
                            @endphp
                            <tr>
                                <td>
                                    @if ($verEmpresa && $data->empresa_id && $empresaNombre)
                                        <a href="{{ route('editar_empresa', array_merge(['id' => $data->empresa_id], $qs)) }}"
                                           class="text-primary" target="_blank" rel="noopener">{{ $empresaNombre }}</a>
                                    @else
                                        {{ $empresaNombre }}
                                    @endif
                                </td>
                                <td>
                                    @if ($verSindicato && $data->sindicato_id && $sindicatoTxt !== '')
                                        <a href="{{ route('editar_sindicato_sueldos', array_merge(['id' => $data->sindicato_id], $qs)) }}"
                                           class="text-primary" target="_blank" rel="noopener">{{ $sindicatoTxt }}</a>
                                    @else
                                        {{ $sindicatoTxt }}
                                    @endif
                                </td>
                                <td>
                                    @if ($motivoTxt === null)
                                        <em class="text-muted">Cualquier motivo (fallback)</em>
                                    @elseif ($verMotivo && $data->motivoegreso_id)
                                        <a href="{{ route('editar_motivoegreso_sueldos', array_merge(['id' => $data->motivoegreso_id], $qs)) }}"
                                           class="text-primary" target="_blank" rel="noopener">{{ $motivoTxt }}</a>
                                    @else
                                        {{ $motivoTxt }}
                                    @endif
                                </td>
                                <td>
                                    @if ($verGrupo && $data->grupo_concepto_id && $grupoTxt !== '')
                                        <a href="{{ route('editar_grupo_concepto_sueldos', array_merge(['id' => $data->grupo_concepto_id], $qs)) }}"
                                           class="text-primary" target="_blank" rel="noopener">{{ $grupoTxt }}</a>
                                    @else
                                        {{ $grupoTxt }}
                                    @endif
                                </td>
                                <td>{{ $data->activo ? 'Sí' : 'No' }}</td>
                                <td class="text-nowrap">
                                    @if (can('editar-grupo-liq-final-sueldos', false))
                                        <a href="{{ route('editar_grupo_liq_final_sueldos', $data->id) }}" class="btn-accion-tabla tooltipsC" title="Editar">
                                            <i class="fa fa-edit"></i>
                                        </a>
                                    @endif
                                    @if (can('borrar-grupo-liq-final-sueldos', false))
                                        <form action="{{ route('eliminar_grupo_liq_final_sueldos', $data->id) }}" method="POST" class="d-inline form-eliminar">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn-accion-tabla eliminar tooltipsC" title="Eliminar">
                                                <i class="fa fa-times-circle text-danger"></i>
                                            </button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">
                                    Sin reglas. Cargá por ejemplo ALEARA → grupo 15 para la empresa correspondiente.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($datas->hasPages())
                <div class="card-footer">{{ $datas->links() }}</div>
            @endif
        </div>
    </div>
</div>
@endsection
