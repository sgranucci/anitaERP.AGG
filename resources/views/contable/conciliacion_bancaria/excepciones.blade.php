@extends("theme.$theme.layout")
@section('titulo')
    Worklist excepciones conciliación
@endsection

@section('scripts')
<meta name="csrf-token" content="{{ csrf_token() }}">
<script>
    window.conciliacionBancariaCuentacajaPorCodigoUrl = @json(route('conciliacion_bancaria_api_cuentacaja_por_codigo', ['codigo' => '__CODIGO__']));
</script>
<script src="{{ asset('assets/pages/scripts/contable/conciliacion_bancaria/filtro.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/contable/conciliacion_bancaria/filtro.js')) ?: time() }}" type="text/javascript"></script>
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Worklist de excepciones</h3>
                <div class="card-tools">
                    <a href="{{ route('conciliacion_bancaria_aging') }}" class="btn btn-outline-light btn-sm">Aging</a>
                    <a href="{{ route('conciliacion_bancaria') }}" class="btn btn-outline-light btn-sm">Conciliación</a>
                </div>
            </div>
            <form method="get" action="{{ route('conciliacion_bancaria_excepciones') }}" class="mb-0" data-enter-campos="1">
                <input type="hidden" name="consultar" value="1">
                <div class="card-body">
                    @include('includes.form-empresa-asignada', [
                        'empresa_query' => $empresa_query,
                        'empresa_id' => $empresa_id ?: null,
                        'col_label' => 'col-lg-2',
                        'col_input' => 'col-lg-4',
                    ])
                    <div class="form-group row">
                        <label class="col-lg-2 control-label text-right pr-2 requerido">Cuenta de caja</label>
                        <input type="hidden" name="cuentacaja_id" id="cuentacaja_id" value="{{ $cuentacaja_id ?: '' }}">
                        <div class="col-lg-2">
                            <input type="text" class="form-control" id="codigo_cuentacaja" autocomplete="off"
                                value="{{ $cuenta->codigo ?? '' }}" placeholder="Código">
                        </div>
                        <div class="col-lg-1">
                            <button type="button" class="btn-accion-tabla consultacuentacaja tooltipsC" title="Consultar">
                                <i class="fa fa-search text-primary"></i>
                            </button>
                        </div>
                        <div class="col-lg-5">
                            <input type="text" class="form-control" id="nombre_cuentacaja" readonly
                                value="{{ $cuenta->nombre ?? '' }}">
                        </div>
                    </div>
                    <div class="form-group row">
                        <label class="col-lg-2 control-label text-right pr-2">Período</label>
                        <div class="col-lg-2">
                            <select name="mes" class="form-control">
                                @for ($m = 1; $m <= 12; $m++)
                                    <option value="{{ $m }}" @selected($mes === $m)>{{ str_pad($m, 2, '0', STR_PAD_LEFT) }}</option>
                                @endfor
                            </select>
                        </div>
                        <div class="col-lg-2">
                            <input type="number" name="anio" class="form-control" value="{{ $anio }}">
                        </div>
                        <div class="col-lg-2">
                            <select name="estado" class="form-control">
                                <option value="abierta" @selected($estado === 'abierta')>Abiertas</option>
                                <option value="resuelta" @selected($estado === 'resuelta')>Resueltas</option>
                                <option value="descartada" @selected($estado === 'descartada')>Descartadas</option>
                                <option value="todos" @selected($estado === 'todos')>Todos</option>
                            </select>
                        </div>
                        <div class="col-lg-2">
                            <button type="submit" class="btn btn-primary">Consultar</button>
                        </div>
                    </div>

                    @if ($consultar && $sync)
                        <p class="text-muted small">Sync aging → worklist: +{{ $sync['creadas'] }} · upd {{ $sync['actualizadas'] }} · abiertas {{ $sync['total'] }}</p>
                    @endif

                    @if ($consultar)
                        <table class="table table-sm table-bordered" id="tabla-paginada">
                            <thead style="background:#85C1E9;color:#17202A;">
                                <tr>
                                    <th>Lado</th>
                                    <th>Fecha</th>
                                    <th class="text-right">Días</th>
                                    <th>Bucket</th>
                                    <th>Detalle</th>
                                    <th class="text-right">Importe</th>
                                    <th>Estado</th>
                                    <th>Asignado</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($filas as $exc)
                                    <tr>
                                        <td>{{ $exc->lado === 'B' ? 'Banco' : 'Contable' }}</td>
                                        <td>{{ $exc->fecha?->format('d/m/Y') }}</td>
                                        <td class="text-right">{{ $exc->dias }}</td>
                                        <td>{{ $exc->bucket }}</td>
                                        <td>{{ $exc->detalle }}</td>
                                        <td class="text-right">{{ number_format((float) $exc->importe, 2, ',', '.') }}</td>
                                        <td>{{ $exc->estado }}</td>
                                        <td>{{ $exc->asignado->nombre ?? '—' }}</td>
                                        <td class="text-nowrap">
                                            @if (in_array($exc->estado, ['abierta', 'en_curso'], true))
                                                <form method="post" action="{{ route('conciliacion_bancaria_excepciones_asignar') }}" class="d-inline">
                                                    @csrf
                                                    <input type="hidden" name="id" value="{{ $exc->id }}">
                                                    <select name="usuario_id" class="form-control form-control-sm d-inline-block" style="width:140px" onchange="this.form.submit()">
                                                        <option value="0">Sin asignar</option>
                                                        @foreach ($usuarios as $u)
                                                            <option value="{{ $u->id }}" @selected((int) $exc->asignado_usuario_id === (int) $u->id)>{{ $u->nombre }}</option>
                                                        @endforeach
                                                    </select>
                                                </form>
                                                <form method="post" action="{{ route('conciliacion_bancaria_excepciones_resolver') }}" class="d-inline">
                                                    @csrf
                                                    <input type="hidden" name="id" value="{{ $exc->id }}">
                                                    <input type="hidden" name="resolucion" value="manual">
                                                    <button type="submit" class="btn btn-sm btn-outline-success" title="Marcar resuelta">OK</button>
                                                </form>
                                                <form method="post" action="{{ route('conciliacion_bancaria_excepciones_resolver') }}" class="d-inline">
                                                    @csrf
                                                    <input type="hidden" name="id" value="{{ $exc->id }}">
                                                    <input type="hidden" name="resolucion" value="descartar">
                                                    <button type="submit" class="btn btn-sm btn-outline-secondary" title="Descartar">X</button>
                                                </form>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="9" class="text-muted">Sin excepciones para el filtro.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                        @if ($filas instanceof \Illuminate\Pagination\LengthAwarePaginator)
                            <div class="mt-2">{{ $filas->links() }}</div>
                        @endif
                    @endif
                </div>
            </form>
        </div>
    </div>
</div>
@include('includes.caja.modalconsultacuentacaja')
@endsection
