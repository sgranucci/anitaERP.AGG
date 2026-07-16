@extends("theme.$theme.layout")
@section('titulo')
    Informe de canjes / tickets
@endsection

@section('contenido')
@php
    use App\Support\Configuracion\EmpresaLogoArchivo;
@endphp
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Informe de Datos de Ventas / Canjes</h3>
            </div>
            <div class="card-body">
                <form method="get" action="{{ route('informe_ticket_canje_caja') }}" id="form-informe-ticket-canje" class="mb-0">
                    <input type="hidden" name="consultar" value="1">
                    <div class="form-row align-items-end">
                        <div class="form-group col-md-3">
                            <label for="empresa_id">Empresa</label>
                            @include('includes.form-empresa-asignada-control', [
                                'empresa_query' => $empresa_query,
                                'empresa_id' => $filtros['empresa_id'] ?? null,
                                'solo_lectura' => false,
                                'required' => true,
                            ])
                        </div>
                        <div class="form-group col-md-2">
                            <label for="fecha_desde">Fecha desde</label>
                            <input type="date" class="form-control" name="fecha_desde" id="fecha_desde"
                                   value="{{ $filtros['fecha_desde'] ?? '' }}" required>
                        </div>
                        <div class="form-group col-md-2">
                            <label for="fecha_hasta">Fecha hasta</label>
                            <input type="date" class="form-control" name="fecha_hasta" id="fecha_hasta"
                                   value="{{ $filtros['fecha_hasta'] ?? '' }}" required>
                        </div>
                        <div class="form-group col-md-2">
                            <label for="estado">Estado</label>
                            <select name="estado" id="estado" class="form-control">
                                <option value="" @selected(($filtros['estado'] ?? '') === '')>Todos</option>
                                <option value="P" @selected(($filtros['estado'] ?? '') === 'P')>Pendiente</option>
                                <option value="C" @selected(($filtros['estado'] ?? '') === 'C')>Canjeado</option>
                                <option value="V" @selected(($filtros['estado'] ?? '') === 'V')>VIP</option>
                            </select>
                        </div>
                        <div class="form-group col-md-3">
                            <button type="submit" class="btn btn-primary">
                                <i class="fa fa-search"></i> Consultar
                            </button>
                            <a href="{{ route('informe_ticket_canje_caja') }}" class="btn btn-outline-secondary">
                                Limpiar
                            </a>
                        </div>
                    </div>
                </form>
            </div>

            @if ($consultado ?? false)
            <div class="card-body p-0 border-top">
                @php
                    $tot = $totales ?? [];
                    $logos = EmpresaLogoArchivo::logosCabeceraDesdeColeccion($filas);
                @endphp
                <div class="d-flex flex-wrap align-items-center justify-content-between px-3 py-2 border-bottom bg-light">
                    <div class="mb-1 mb-md-0">
                        @include('includes.exportar-tabla-queryparams', [
                            'ruta' => 'listar_informe_ticket_canje_caja',
                            'queryparams' => $filtrosQuery ?? [],
                        ])
                    </div>
                    <div class="small text-md-right">
                        <span class="text-muted">{{ $subtitulo ?? '' }}</span>
                        · <strong>{{ (int) ($tot['cantidad'] ?? 0) }}</strong> tickets
                        · Venta <strong>${{ number_format((float) ($tot['monto_venta'] ?? 0), 2, ',', '.') }}</strong>
                        · Ticket <strong>${{ number_format((float) ($tot['monto_ticket'] ?? 0), 2, ',', '.') }}</strong>
                    </div>
                </div>
                @if (! empty($logos))
                <div class="px-3 pt-2">
                    @foreach ($logos as $logo)
                        <img src="{{ is_array($logo) ? ($logo['uri'] ?? '') : $logo }}" alt="logo" style="height:40px;margin-right:8px;">
                    @endforeach
                </div>
                @endif
                <div class="table-responsive">
                    @include('caja.canjes.informe.partials.tabla_datos', [
                        'filas' => $filas,
                        'es_export' => false,
                    ])
                </div>
            </div>
            @if (method_exists($filas, 'links'))
            <div class="card-footer clearfix">
                <div class="float-left">
                    @if ($filas->total() > 0)
                        Mostrando {{ $filas->firstItem() }}–{{ $filas->lastItem() }} de {{ $filas->total() }}
                    @endif
                </div>
                <div class="float-right">
                    {{ $filas->appends($filtrosQuery ?? [])->links() }}
                </div>
            </div>
            @endif
            @endif
        </div>
    </div>
</div>
@endsection
