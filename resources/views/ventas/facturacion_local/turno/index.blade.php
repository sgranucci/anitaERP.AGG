@extends("theme.$theme.layout")
@section('titulo')
    Cierres de turno — Facturación Local
@endsection
@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header d-flex flex-wrap align-items-center justify-content-between">
                <h3 class="card-title mb-0">Cierres de turno</h3>
                <div class="card-tools ml-auto d-flex flex-wrap align-items-center justify-content-end">
                    <a href="{{ route('facturacion_local_turno') }}" class="btn btn-outline-light btn-sm mr-1">Turnos</a>
                    <a href="{{ route('facturacion_local_pos') }}" class="btn btn-outline-light btn-sm">POS</a>
                </div>
            </div>
            <form method="get" class="p-3 mb-0">
                <div class="form-inline">
                    <select name="local_id" class="form-control mr-2">
                        <option value="0">Todos los locales</option>
                        @foreach ($locales as $loc)
                            <option value="{{ $loc->id }}"
                                @if ($localId === (int) $loc->id)
                                    selected
                                @endif
                            >
                                {{ $loc->codigo }} — {{ $loc->nombre }}
                            </option>
                        @endforeach
                    </select>
                    <button class="btn btn-primary btn-sm">Filtrar</button>
                </div>
            </form>
            <div class="card-body table-responsive p-0">
                <table class="table table-striped table-bordered table-hover" id="tabla-paginada">
                    <thead style="background:#85C1E9;color:#17202A;">
                        <tr>
                            <th>ID</th>
                            <th>Local</th>
                            <th>Turno</th>
                            <th>Estado</th>
                            <th>Apertura</th>
                            <th>Usuario</th>
                            <th>Fondo</th>
                            <th>Facturación</th>
                            <th>Cierre</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($datas as $t)
                        <tr>
                            <td>{{ $t->id }}</td>
                            <td>{{ $t->localVenta->codigo ?? '' }}</td>
                            <td>{{ $t->turnoLocal->nombre ?? '—' }}</td>
                            <td>{{ $t->estado }}</td>
                            <td>{{ optional($t->apertura_en)->format('d/m/Y H:i') }}</td>
                            <td>{{ $t->usuarioApertura->nombre ?? '' }}</td>
                            <td class="text-right">{{ number_format($t->fondo_inicial, 2, ',', '.') }}</td>
                            <td class="text-right">{{ number_format($t->monto_facturacion_turno, 2, ',', '.') }}</td>
                            <td>{{ optional($t->cierre_en)->format('d/m/Y H:i') }}</td>
                            <td class="text-nowrap">
                                <a href="{{ route('facturacion_local_turno_ver', $t->id) }}"
                                   class="btn-accion-tabla tooltipsC"
                                   title="{{ $t->estado === 'cerrado' ? 'Ver cierre' : 'Revisar medios y cerrar' }}">
                                    <i class="fa {{ $t->estado === 'cerrado' ? 'fa-eye' : 'fa-lock' }}"></i>
                                </a>
                                @if ($t->estado === 'cerrado')
                                    <a href="{{ route('facturacion_local_turno_pdf', $t->id) }}" target="_blank" rel="noopener"
                                       class="btn-accion-tabla tooltipsC" title="PDF del cierre">
                                        <i class="fas fa-file-pdf text-danger"></i>
                                    </a>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="card-footer">{{ $datas->appends(request()->only('local_id'))->links() }}</div>
        </div>
    </div>
</div>
@endsection
