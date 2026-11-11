@extends("theme.$theme.layout")

@section('titulo')
    Conciliaci&oacute;n facturaci&oacute;n juego
@endsection

@section('contenido')
@php
    $resumen = $resultado['resumen'] ?? [];
    $porEstado = $resumen['por_estado'] ?? [];
    $tol = (float) ($resultado['tolerancia'] ?? config('facturacion_juego.conciliacion_tolerancia', 0.02));
@endphp
<div class="row">
    <div class="col-12">
        <div class="card card-info">
            <div class="card-header d-flex align-items-center flex-wrap">
                <h3 class="card-title mb-0">Conciliaci&oacute;n facturaci&oacute;n juego</h3>
                <div class="card-tools ml-auto">
                    @include('ventas.facturacion_juego.partials.help_cuentas', [
                        'helpSalas' => $helpSalas ?? [],
                        'helpModalId' => 'modal-help-conciliacion-juego',
                        'helpBtnClass' => 'btn btn-outline-light btn-sm',
                        'soloBoton' => true,
                    ])
                    <a href="{{ route('facturacion_juego', [
                        'fecha_desde' => $fechaDesde,
                        'fecha_hasta' => $fechaHasta,
                        'sala_id' => $salaId > 0 ? $salaId : null,
                        'consultar' => 1,
                    ]) }}" class="btn btn-outline-light btn-sm ml-1">
                        <i class="fa fa-fw fa-reply-all"></i> Volver a facturaci&oacute;n
                    </a>
                </div>
            </div>
            <div class="card-body">
                @include('includes.mensaje')

                <div class="alert alert-info py-2 small mb-3">
                    Compara Flash Crown, ventas emitidas, asientos ERP y mayor Anita (subdiario + ctamov)
                    por d&iacute;a, sala y rubro (mesas / slot / ruleta el.). Importes firmados (ganancia +, p&eacute;rdida −).
                    Tolerancia: {{ number_format($tol, 2, ',', '.') }}.
                </div>

                <form method="get" action="{{ route('facturacion_juego_conciliacion') }}" class="mb-3" id="form-conciliacion-juego">
                    <input type="hidden" name="consultar" value="1">
                    <div class="form-row align-items-end">
                        <div class="form-group col-md-2">
                            <label for="fecha_desde" class="control-label">Desde</label>
                            <input type="date" name="fecha_desde" id="fecha_desde" class="form-control"
                                value="{{ $fechaDesde }}" required>
                        </div>
                        <div class="form-group col-md-2">
                            <label for="fecha_hasta" class="control-label">Hasta</label>
                            <input type="date" name="fecha_hasta" id="fecha_hasta" class="form-control"
                                value="{{ $fechaHasta }}" required>
                        </div>
                        <div class="form-group col-md-5">
                            <label class="control-label d-block">Sala</label>
                            <input type="hidden" name="sala_id" id="sala_id" value="{{ (int) $salaId }}">
                            <div class="btn-group btn-group-sm flex-wrap" role="group">
                                <button type="button" class="btn {{ (int)$salaId === 0 ? 'btn-info' : 'btn-outline-info' }} js-sala-chip" data-sala="0">Todas</button>
                                @foreach ($salas as $sala)
                                    <button type="button"
                                        class="btn {{ (int)$salaId === (int)$sala->id ? 'btn-info' : 'btn-outline-info' }} js-sala-chip"
                                        data-sala="{{ $sala->id }}">{{ $sala->codigo }}</button>
                                @endforeach
                            </div>
                        </div>
                        <div class="form-group col-md-3">
                            <button type="submit" class="btn btn-info btn-sm">
                                <i class="fa fa-search"></i> Consultar
                            </button>
                        </div>
                    </div>
                </form>

                @if ($consultar && $resultado !== null)
                    <div class="d-flex flex-wrap align-items-center justify-content-between mb-3">
                        <div>
                            <span class="badge badge-secondary">Filas: {{ (int) ($resumen['total_filas'] ?? 0) }}</span>
                            <span class="badge badge-success">OK: {{ (int) ($porEstado['OK'] ?? 0) }}</span>
                            <span class="badge badge-danger">DIF: {{ (int) ($porEstado['DIF'] ?? 0) }}</span>
                            <span class="badge badge-warning">PEND: {{ (int) ($porEstado['PEND'] ?? 0) }}</span>
                            <span class="badge badge-info">SIN FLASH: {{ (int) ($porEstado['SIN_FLASH'] ?? 0) }}</span>
                            @if ((int) ($porEstado['SIN_CFG'] ?? 0) > 0)
                                <span class="badge badge-dark">SIN CFG: {{ (int) $porEstado['SIN_CFG'] }}</span>
                            @endif
                            <span class="badge badge-light border ml-1">
                                Flash {{ number_format((float) ($resumen['total_flash'] ?? 0), 2, ',', '.') }}
                                · Ventas {{ number_format((float) ($resumen['total_ventas'] ?? 0), 2, ',', '.') }}
                                · ERP {{ number_format((float) ($resumen['total_erp'] ?? 0), 2, ',', '.') }}
                                · Anita {{ number_format((float) ($resumen['total_anita'] ?? 0), 2, ',', '.') }}
                            </span>
                        </div>
                        <div>
                            @include('includes.exportar-tabla-queryparams', [
                                'ruta' => 'listar_facturacion_juego_conciliacion',
                                'queryparams' => $filtrosQuery ?? [],
                            ])
                        </div>
                    </div>

                    <div class="table-responsive">
                        @include('ventas.facturacion_juego.partials.tabla_conciliacion', [
                            'filas' => $filasPagina ?? [],
                            'modoExport' => false,
                        ])
                    </div>

                    @if ($filasPagina !== null)
                        <div class="mt-2">
                            {{ $filasPagina->links() }}
                        </div>
                    @endif
                @endif
            </div>
        </div>
    </div>
</div>

@include('includes.proceso_overlay_aviso', [
    'overlayId' => 'conciliacion-juego-overlay',
    'tituloId' => 'conciliacion-juego-titulo',
    'subtituloId' => 'conciliacion-juego-subtitulo',
    'titulo' => 'Conciliando…',
    'subtitulo' => 'Puede demorar según el período y la consulta a Anita. No cierre la página.',
])

@include('ventas.facturacion_juego.partials.help_cuentas', [
    'helpSalas' => $helpSalas ?? [],
    'helpModalId' => 'modal-help-conciliacion-juego',
    'soloModal' => true,
])
@endsection

@section('scripts')
<script>
(function () {
    var form = document.getElementById('form-conciliacion-juego');
    var overlay = document.getElementById('conciliacion-juego-overlay');
    function mostrar(titulo) {
        if (!overlay) return;
        if (titulo) document.getElementById('conciliacion-juego-titulo').textContent = titulo;
        overlay.classList.remove('d-none');
        overlay.style.display = 'flex';
        overlay.setAttribute('aria-hidden', 'false');
    }
    function ocultar() {
        if (!overlay) return;
        overlay.classList.add('d-none');
        overlay.style.display = '';
        overlay.setAttribute('aria-hidden', 'true');
    }
    document.querySelectorAll('.js-sala-chip').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.getElementById('sala_id').value = btn.getAttribute('data-sala') || '0';
            if (form) {
                mostrar('Conciliando…');
                form.submit();
            }
        });
    });
    if (form) {
        form.addEventListener('submit', function () {
            if (!form.checkValidity()) return;
            mostrar('Conciliando…');
        });
    }
    window.addEventListener('pageshow', ocultar);

    document.querySelectorAll('a[href*="listar-conciliacion"]').forEach(function (a) {
        a.addEventListener('click', function (e) {
            e.preventDefault();
            mostrar('Exportando…');
            var ctrl = new AbortController();
            var onEsc = function (ev) {
                if (ev.key === 'Escape') {
                    ctrl.abort();
                    ocultar();
                }
            };
            document.addEventListener('keydown', onEsc);
            fetch(a.href, {
                credentials: 'same-origin',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                signal: ctrl.signal
            })
                .then(function (res) {
                    if (!res.ok) throw new Error('HTTP ' + res.status);
                    var dispo = res.headers.get('Content-Disposition') || '';
                    var match = /filename="?([^";]+)"?/i.exec(dispo);
                    var filename = match ? match[1] : 'conciliacion_facturacion_juego.bin';
                    return res.blob().then(function (blob) {
                        return { blob: blob, filename: filename };
                    });
                })
                .then(function (pack) {
                    var url = URL.createObjectURL(pack.blob);
                    var link = document.createElement('a');
                    link.href = url;
                    link.download = pack.filename;
                    document.body.appendChild(link);
                    link.click();
                    link.remove();
                    URL.revokeObjectURL(url);
                    ocultar();
                })
                .catch(function (err) {
                    ocultar();
                    if (err && err.name === 'AbortError') return;
                    alert(err.message || 'No se pudo exportar');
                })
                .finally(function () {
                    document.removeEventListener('keydown', onEsc);
                });
        });
    });
})();
</script>
@endsection
