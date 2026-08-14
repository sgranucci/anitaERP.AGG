@php
    $r = $resumen;
@endphp
<div id="fallos-empleado-panel" data-empleado="{{ $empleado->id }}"
     data-url="{{ route('fallos_empleado_sueldos', ['empleado' => $empleado->id]) }}">
    <div class="d-flex flex-wrap align-items-center justify-content-between mb-3">
        <h5 class="mb-0"><i class="fa fa-balance-scale"></i> Fallos / faltantes</h5>
        <div class="small text-muted">
            Tabla aplicada:
            <strong>{{ $r['fallo_tipo'] !== '' ? $r['fallo_tipo'] : 'sin agrupamiento con fallo' }}</strong>
        </div>
    </div>

    <form class="form-row align-items-end mb-3" id="form-fallos-empleado-filtro">
        <div class="form-group col-md-3 mb-2">
            <label class="small mb-0">Desde</label>
            <input type="date" name="fecha_desde" class="form-control form-control-sm" value="{{ $r['fecha_desde'] }}">
        </div>
        <div class="form-group col-md-3 mb-2">
            <label class="small mb-0">Hasta</label>
            <input type="date" name="fecha_hasta" class="form-control form-control-sm" value="{{ $r['fecha_hasta'] }}">
        </div>
        <div class="form-group col-md-3 mb-2">
            <button type="submit" class="btn btn-outline-primary btn-sm"><i class="fa fa-search"></i> Actualizar</button>
            @if ($puedeVerReporte ?? false)
                <a class="btn btn-outline-secondary btn-sm"
                   href="{{ route('fallo_reporte_sueldos', [
                        'consultar' => 1,
                        'empresa_id' => $empleado->empresa_id,
                        'fecha_desde' => $r['fecha_desde'],
                        'fecha_hasta' => $r['fecha_hasta'],
                        'legajo_desde' => $empleado->legajo,
                        'legajo_hasta' => $empleado->legajo,
                   ]) }}" target="_blank" rel="noopener">
                    Abrir reporte
                </a>
            @endif
            @if ($puedeVerProceso ?? false)
                <a class="btn btn-outline-secondary btn-sm" href="{{ route('consultar_dtofallo_sueldos') }}" target="_blank" rel="noopener">
                    Proceso dto.
                </a>
            @endif
        </div>
    </form>

    <div class="row mb-3">
        <div class="col-md-4">
            <div class="border rounded p-2">
                <div class="small text-muted">Debe (faltantes / ingresos)</div>
                <div class="h5 mb-0">$ {{ number_format($r['total_debe'], 2, ',', '.') }}</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="border rounded p-2">
                <div class="small text-muted">Haber (descuentos / sanciones)</div>
                <div class="h5 mb-0">$ {{ number_format($r['total_haber'], 2, ',', '.') }}</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="border rounded p-2">
                <div class="small text-muted">Saldo</div>
                <div class="h5 mb-0">$ {{ number_format($r['total_saldo'], 2, ',', '.') }}</div>
            </div>
        </div>
    </div>

    <div class="table-responsive mb-4">
        <table class="table table-sm table-bordered table-striped mb-0">
            <thead style="background:#85C1E9;color:#17202A;">
                <tr>
                    <th>Fecha</th>
                    <th>Concepto</th>
                    <th class="text-right">Debe</th>
                    <th class="text-right">Haber</th>
                    <th>Observaci&oacute;n</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($r['filas'] as $f)
                    <tr>
                        <td>{{ $f['fecha_fmt'] ?? '' }}</td>
                        <td>{{ $f['concepto'] ?? '' }}</td>
                        <td class="text-right">{{ ((float)($f['debe'] ?? 0)) > 0 ? number_format((float)$f['debe'], 2, ',', '.') : '' }}</td>
                        <td class="text-right">{{ ((float)($f['haber'] ?? 0)) > 0 ? number_format((float)$f['haber'], 2, ',', '.') : '' }}</td>
                        <td>{{ $f['observacion'] ?? '' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-muted py-3">Sin movimientos en el rango.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <h6 class="mb-2">Ledger dtofallo (cuotas / sanciones del proceso)</h6>
    <div class="table-responsive">
        <table class="table table-sm table-bordered mb-0">
            <thead style="background:#85C1E9;color:#17202A;">
                <tr>
                    <th>Fecha</th>
                    <th>Per&iacute;odo</th>
                    <th>Tipo</th>
                    <th class="text-right">Importe</th>
                    <th>Cierre</th>
                    <th>Observaci&oacute;n</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($r['movimientos_ledger'] as $m)
                    <tr>
                        <td>{{ optional($m->fecha)->format('d/m/Y') }}</td>
                        <td>{{ $m->periodo }}</td>
                        <td>{{ $m->tipoLabel() }}</td>
                        <td class="text-right">{{ number_format((float)$m->importe, 2, ',', '.') }}</td>
                        <td>{{ optional($m->cierre)->nro_cierre }}</td>
                        <td>{{ $m->observacion }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted py-3">Sin cuotas generadas.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<script>
(function ($) {
    var $panel = $('#fallos-empleado-panel');
    if (!$panel.length) { return; }
    $panel.off('submit.fallosEmp').on('submit.fallosEmp', '#form-fallos-empleado-filtro', function (e) {
        e.preventDefault();
        var url = $panel.data('url');
        var data = $(this).serialize();
        var $host = $('#host-fallos');
        $host.html('<div class="text-center text-muted py-4"><i class="fa fa-spinner fa-spin"></i> Actualizando…</div>');
        $.getJSON(url, data).done(function (resp) {
            $host.html(resp.html || '');
        }).fail(function () {
            $host.html('<div class="alert alert-danger">No se pudo cargar la solapa de fallos.</div>');
        });
    });
})(jQuery);
</script>
