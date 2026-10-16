@extends("theme.$theme.layout")
@section('titulo', 'Diferencia de cambio en facturas')

@section('contenido')
@include('includes.proceso_overlay_aviso', [
    'overlayId' => 'difcambio-overlay',
    'tituloId' => 'difcambio-overlay-titulo',
    'subtituloId' => 'difcambio-overlay-subtitulo',
    'titulo' => 'Exportando…',
    'subtitulo' => 'Puede demorar. No cierre la página.',
])
<div class="container-fluid">
    <div class="card card-info">
        <div class="card-header">
            <h3 class="card-title">Diferencia de cambio en facturas de proveedor</h3>
        </div>
        <div class="card-body">
            <form method="get" action="{{ route('diferencia_cambio_factura') }}" id="form-difcambio" class="form-horizontal">
                <input type="hidden" name="consultar" value="1">
                <div class="form-group row">
                    <label class="col-lg-2 control-label text-right pr-2" for="empresa_id">Empresa</label>
                    <div class="col-lg-4">
                        @if ($empresa_query->count() <= 1)
                            <input type="hidden" name="empresa_id" value="{{ $empresa_query->first()->id ?? '' }}">
                            <input type="text" class="form-control" readonly value="{{ $empresa_query->first()->nombre ?? 'Todas mis empresas' }}">
                        @else
                            <select name="empresa_id" id="empresa_id" class="form-control">
                                <option value="">Todas mis empresas</option>
                                @foreach ($empresa_query as $empresa)
                                    <option value="{{ $empresa->id }}" @selected((int) ($filtros['empresa_id'] ?? 0) === (int) $empresa->id)>{{ $empresa->nombre }}</option>
                                @endforeach
                            </select>
                        @endif
                    </div>
                    <label class="col-lg-2 control-label text-right pr-2" for="estado">Estado</label>
                    <div class="col-lg-4">
                        <select name="estado" id="estado" class="form-control">
                            <option value="">Todos</option>
                            <option value="pendiente_aprobacion" @selected(($filtros['estado'] ?? '') === 'pendiente_aprobacion')>Pendiente de aprobación</option>
                            <option value="contabilizado" @selected(($filtros['estado'] ?? '') === 'contabilizado')>Contabilizado</option>
                            <option value="rechazada" @selected(($filtros['estado'] ?? '') === 'rechazada')>Rechazada</option>
                        </select>
                    </div>
                </div>
                <div class="form-group row">
                    <label class="col-lg-2 control-label text-right pr-2" for="fecha_desde">Fecha factura desde</label>
                    <div class="col-lg-4">
                        <input type="date" name="fecha_desde" id="fecha_desde" class="form-control" value="{{ $filtros['fecha_desde'] ?? '' }}">
                    </div>
                    <label class="col-lg-2 control-label text-right pr-2" for="fecha_hasta">Hasta</label>
                    <div class="col-lg-4">
                        <input type="date" name="fecha_hasta" id="fecha_hasta" class="form-control" value="{{ $filtros['fecha_hasta'] ?? '' }}">
                    </div>
                </div>
                <button type="submit" class="btn btn-primary">Consultar</button>
            </form>

            @if ($consulto && $filas)
                <div class="d-flex justify-content-between align-items-center mt-3 mb-2" id="difcambio-exportar">
                    <div>{{ $filas->total() }} registros</div>
                    @include('includes.exportar-tabla-queryparams', [
                        'ruta' => 'listar_diferencia_cambio_factura',
                        'queryparams' => $filtrosQuery ?? [],
                        'variant' => 'compact',
                    ])
                </div>
                <table class="table table-sm table-bordered" id="tabla-diferencia-cambio-factura">
                    <thead style="background:#85C1E9;color:#17202A;">
                        <tr>
                            <th>Factura</th>
                            <th>Proveedor</th>
                            <th>OC</th>
                            <th>Recepción</th>
                            <th class="text-right">TC recepción</th>
                            <th class="text-right">TC factura</th>
                            <th class="text-right">Nominal</th>
                            <th class="text-right">Pesos</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($filas as $fila)
                            @php
                                $cp = $fila->comprobantes;
                                $consulta = ['origen' => 'modal_consulta', 'vista' => 'consulta'];
                            @endphp
                            <tr>
                                <td>
                                    @if ($cp)
                                        <a class="text-primary" target="_blank" rel="noopener" href="{{ route('editar_comprobante_proveedor', ['id' => $cp->id] + $consulta) }}">
                                            {{ $cp->letra }} {{ $cp->sucursal }}-{{ $cp->numerocomprobante }}
                                        </a>
                                    @endif
                                </td>
                                <td>{{ optional($cp?->proveedores)->nombre }}</td>
                                <td>
                                    @if ($cp && (int) ($cp->ordencompra_id ?? 0) > 0)
                                        <a class="text-primary" target="_blank" rel="noopener" href="{{ route('editar_ordencompra', ['id' => $cp->ordencompra_id] + $consulta) }}">
                                            {{ optional($cp->ordencompras)->numeroordencompra }}
                                        </a>
                                    @endif
                                </td>
                                <td>
                                    <a class="text-primary" target="_blank" rel="noopener" href="{{ route('editar_recepcion_proveedor', ['id' => $fila->recepcion_proveedor_id] + $consulta) }}">
                                        #{{ $fila->recepcion_proveedor_id }}
                                    </a>
                                </td>
                                <td class="text-right">{{ number_format((float) $fila->tc_recepcion, 4, ',', '.') }}</td>
                                <td class="text-right">{{ number_format((float) $fila->tc_factura, 4, ',', '.') }}</td>
                                <td class="text-right">{{ number_format((float) $fila->nominal_me, 2, ',', '.') }}</td>
                                <td class="text-right">{{ number_format((float) $fila->importe_pesos, 2, ',', '.') }}</td>
                                <td>{{ $fila->estado }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="9">No hay ajustes para el filtro.</td></tr>
                        @endforelse
                    </tbody>
                </table>
                <div>{{ $filas->appends($filtrosQuery ?? [])->links() }}</div>
            @endif
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    var overlay = document.getElementById('difcambio-overlay');
    function mostrar() {
        if (!overlay) return;
        overlay.classList.remove('d-none');
        overlay.style.display = 'flex';
    }
    function ocultar() {
        if (!overlay) return;
        overlay.classList.add('d-none');
        overlay.style.display = '';
    }
    window.addEventListener('pageshow', ocultar);
    document.querySelectorAll('#difcambio-exportar a').forEach(function (a) {
        a.addEventListener('click', function (e) {
            e.preventDefault();
            mostrar();
            fetch(a.href, { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function (res) {
                    if (!res.ok) throw new Error('No se pudo exportar');
                    var nombre = 'diferencia_cambio_factura';
                    var disp = res.headers.get('Content-Disposition') || '';
                    var m = /filename=\"?([^\";]+)\"?/i.exec(disp);
                    if (m) nombre = m[1];
                    return res.blob().then(function (blob) { return { blob: blob, nombre: nombre }; });
                })
                .then(function (pack) {
                    var url = URL.createObjectURL(pack.blob);
                    var link = document.createElement('a');
                    link.href = url;
                    link.download = pack.nombre;
                    document.body.appendChild(link);
                    link.click();
                    link.remove();
                    URL.revokeObjectURL(url);
                    ocultar();
                })
                .catch(function () { ocultar(); });
        });
    });
});
</script>
@endsection
