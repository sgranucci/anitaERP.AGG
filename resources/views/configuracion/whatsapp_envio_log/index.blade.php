@extends("theme.$theme.layout")
@section('titulo')
Auditoría WhatsApp
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title mb-0"><i class="fa fa-list-alt"></i> Envíos WhatsApp</h3>
                <div class="card-tools">
                    <a href="{{ route('consultar_empresa_whatsapp') }}" class="btn btn-outline-light btn-sm">Cuentas WA</a>
                </div>
            </div>
            <div class="card-body">
                <form method="get" class="form-inline mb-3" data-enter-campos="1">
                    <label class="mr-2">Estado</label>
                    <select name="estado" class="form-control form-control-sm mr-2">
                        <option value="">Todos</option>
                        @foreach (['ok','error','omitido'] as $st)
                            <option value="{{ $st }}" @if($estado===$st) selected @endif>{{ $st }}</option>
                        @endforeach
                    </select>
                    <label class="mr-2">Empresa ID</label>
                    <input type="number" name="empresa_id" class="form-control form-control-sm mr-2" value="{{ $empresa_id ?: '' }}" min="1">
                    <button type="submit" class="btn btn-sm btn-primary">Filtrar</button>
                </form>

                <table id="tabla-paginada" class="table table-sm table-bordered">
                    <thead style="background:#85C1E9;color:#17202A;">
                        <tr>
                            <th>ID</th>
                            <th>Cuando</th>
                            <th>Empresa</th>
                            <th>Usuario</th>
                            <th>Celular</th>
                            <th>Aviso</th>
                            <th>Estado</th>
                            <th>Error</th>
                            <th>Driver</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($filas as $fila)
                            <tr>
                                <td>{{ $fila->id }}</td>
                                <td>{{ optional($fila->enviado_en)->format('d/m/Y H:i') }}</td>
                                <td>{{ $fila->empresas->nombre ?? ($fila->empresa_id ?: '—') }}</td>
                                <td>{{ $fila->usuarios->nombre ?? ($fila->usuario_id ?: '—') }}</td>
                                <td><code>{{ $fila->celular ?: '—' }}</code></td>
                                <td class="small">{{ $fila->modulo_aviso ?: '—' }} @if($fila->entity_id)#{{ $fila->entity_id }}@endif</td>
                                <td>
                                    @if ($fila->estado === 'ok')
                                        <span class="badge badge-success">ok</span>
                                    @elseif ($fila->estado === 'omitido')
                                        <span class="badge badge-secondary">omitido</span>
                                    @else
                                        <span class="badge badge-danger">{{ $fila->estado }}</span>
                                    @endif
                                </td>
                                <td class="small">{{ $fila->error ?: '—' }}</td>
                                <td><code>{{ $fila->driver }}</code></td>
                            </tr>
                        @empty
                            <tr><td colspan="9" class="text-muted">Sin envíos registrados.</td></tr>
                        @endforelse
                    </tbody>
                </table>
                {{ $filas->links() }}
            </div>
        </div>
    </div>
</div>
@endsection
