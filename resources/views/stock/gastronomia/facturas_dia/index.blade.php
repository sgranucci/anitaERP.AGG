@extends("theme.$theme.layout")

@section('titulo')
    Facturas gastronomía del día
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
                <h3 class="card-title">Facturas gastronomía del día</h3>
                <div class="card-tools">
                    <small class="text-muted">Emitidas desde esta PC: <strong>{{ $identificador_pc }}</strong></small>
                </div>
                <div class="d-md-flex justify-content-md-end align-items-md-end flex-wrap">
                    <form action="{{ route('gastronomia_facturas_dia') }}" method="GET" class="d-flex flex-wrap align-items-end mb-2 mb-md-0">
                        <div class="form-group mb-0 mr-2">
                            <label for="fecha_fd" class="small text-muted mb-0 d-block">Fecha</label>
                            <input type="date" id="fecha_fd" name="fecha" value="{{ $fecha }}" class="form-control form-control-sm">
                        </div>
                        <div class="btn-group mr-2">
                            <input type="text" name="busqueda" class="form-control form-control-sm" placeholder="Búsqueda …" value="{{ $busqueda ?? '' }}">
                            <button type="submit" class="btn btn-default btn-sm" title="Buscar">
                                <span class="fa fa-search"></span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            <div class="card-body table-responsive p-0">
                @include('includes.exportar-tabla-queryparams', [
                    'ruta' => 'listar_gastronomia_facturas_dia',
                    'queryparams' => array_filter([
                        'fecha' => $fecha,
                        'busqueda' => $busqueda ?? '',
                    ], fn ($v) => $v !== null && $v !== ''),
                ])
                <table class="table table-striped table-bordered table-hover mb-0" id="tabla-paginada">
                    <thead>
                        <tr>
                            <th>Venta ID</th>
                            <th>Fecha</th>
                            <th>Comprobante</th>
                            <th>Cliente</th>
                            <th>Punto de venta</th>
                            <th class="text-right">Total</th>
                            <th>Cuenta gastro.</th>
                            <th class="width40" data-orderable="false"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($registros as $r)
                            @php
                                $v = $r->venta;
                                $pvTxt = $v ? trim(($v->puntoventas->codigo ?? '').' '.($v->puntoventas->nombre ?? '')) : '';
                            @endphp
                            <tr>
                                <td>{{ $r->venta_id }}</td>
                                <td><small>@if($v?->fecha){{ \Illuminate\Support\Carbon::parse($v->fecha)->format('d/m/Y H:i') }}@else<span class="text-muted">—</span>@endif</small></td>
                                <td><small>{{ $v?->codigo ?? '—' }}</small></td>
                                <td><small>{{ $v?->clientes->nombre ?? '—' }}</small></td>
                                <td><small>{{ $pvTxt !== '' ? $pvTxt : '—' }}</small></td>
                                <td class="text-right"><small>{{ number_format((float) ($v?->total ?? 0), 2, ',', '.') }}</small></td>
                                <td><small>{{ $r->cuenta_gastronomia_id ?? '—' }}</small></td>
                                <td>
                                    @if (can('ver-factura-gastronomia', false))
                                        <a href="{{ route('gastronomia_facturas_dia_ver', ['ventaId' => $r->venta_id]) }}" class="btn-accion-tabla tooltipsC" title="Ver detalle">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    @endif
                                    @if ($v)
                                        <a href="{{ url('ventas/listaunafactura/'.$v->id) }}" target="_blank" rel="noopener" class="btn-accion-tabla tooltipsC" title="PDF comprobante">
                                            <i class="fas fa-file-pdf text-danger"></i>
                                        </a>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="text-center text-muted py-4">Sin registros para la fecha y filtros indicados.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
