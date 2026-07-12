@extends("theme.$theme.layout")
@section('titulo')
    Consulta rendiciones bingo
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Consulta rendiciones</h3>
                <div class="card-tools">
                    <a href="{{ route('bingo_habilitacion_turno') }}" class="btn btn-outline-secondary btn-sm">Habilitación</a>
                </div>
            </div>
            <div class="card-body">
                <form method="get" class="form-inline mb-3 flex-wrap">
                    @include('includes.listado.filtro_empresa_asignada_inline', [
                        'empresa_query' => $empresa_query,
                        'empresa_id' => $empresa_id,
                        'required' => false,
                        'permite_todas' => true,
                    ])
                    <label class="ml-2 mr-1">Desde</label>
                    <input type="date" name="fecha_desde" class="form-control form-control-sm mr-2" value="{{ $fecha_desde }}">
                    <label class="mr-1">Hasta</label>
                    <input type="date" name="fecha_hasta" class="form-control form-control-sm mr-2" value="{{ $fecha_hasta }}">
                    <label class="mr-1">PC</label>
                    <input type="text" name="identificador_pc" class="form-control form-control-sm mr-2" value="{{ $identificador_pc }}">
                    <button type="submit" class="btn btn-primary btn-sm">Consultar</button>
                </form>

                <div class="table-responsive">
                    <table class="table table-striped table-bordered">
                        <thead style="background:#85C1E9;color:#17202A;">
                            <tr>
                                <th>Nº cierre</th>
                                <th>Cierre</th>
                                <th>Jornada</th>
                                <th>Turno</th>
                                <th>PC</th>
                                <th>Usuario</th>
                                <th class="text-right">Rendición</th>
                                <th class="text-center">Estado</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($datas as $t)
                                <tr>
                                    <td>{{ $t->numero_cierre }}</td>
                                    <td>{{ optional($t->cierre_en)->format('d/m/Y H:i') }}</td>
                                    <td>{{ optional($t->jornada?->fecha_jornada)->format('d/m/Y') }}</td>
                                    <td>{{ $t->turno?->nombre }}</td>
                                    <td><code>{{ $t->identificador_pc }}</code></td>
                                    <td>{{ $t->usuarioHabilitado?->nombre }}</td>
                                    <td class="text-right">${{ number_format((float) ($t->deposito ?? $t->monto_rendicion_turno ?? 0), 2, ',', '.') }}</td>
                                    <td class="text-center">
                                        @if ($t->rendicion_presentada)
                                            <span class="badge badge-success">Presentada</span>
                                        @else
                                            <span class="badge badge-warning">Pendiente</span>
                                        @endif
                                    </td>
                                    <td class="text-nowrap">
                                        @if (! $t->rendicion_presentada && can('crear-rendicion-bingo-caja', false))
                                            <a href="{{ route('bingo_rendicion_editar', ['turno' => $t->id]) }}"
                                               class="btn-accion-tabla tooltipsC" title="Editar rendición">
                                                <i class="fa fa-edit text-primary"></i>
                                            </a>
                                        @endif
                                        @if (can('ver-comprobante-cierre-turno-bingo', false))
                                            <a href="{{ route('bingo_cierre_turno_comprobante_cierre', ['id' => $t->id]) }}"
                                               class="btn-accion-tabla" target="_blank" rel="noopener" title="PDF cierre">
                                                <i class="fa fa-file-pdf-o text-danger"></i>
                                            </a>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="9" class="text-center text-muted">Sin cierres en el período.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                {{ $datas->links() }}
            </div>
        </div>
    </div>
</div>
@endsection
