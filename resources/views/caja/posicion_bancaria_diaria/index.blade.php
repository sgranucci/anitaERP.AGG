@extends("theme.$theme.layout")
@section('titulo')
    Posición bancaria diaria
@endsection

@section('scripts')
<script>
(function () {
    var overlay = document.getElementById('posbanc-overlay');
    function mostrar(titulo) {
        if (!overlay) return;
        var t = document.getElementById('posbanc-overlay-titulo');
        if (t && titulo) t.textContent = titulo;
        overlay.classList.remove('d-none');
        overlay.style.display = 'flex';
    }
    function ocultar() {
        if (!overlay) return;
        overlay.classList.add('d-none');
        overlay.style.display = '';
    }
    var formConsulta = document.getElementById('form-posbanc-consulta');
    if (formConsulta) {
        formConsulta.addEventListener('submit', function () {
            mostrar('Consultando saldos Interbanking…');
        });
    }
    var formExport = document.getElementById('form-posbanc-export');
    if (formExport) {
        formExport.addEventListener('submit', function () {
            mostrar('Generando Excel de posición…');
            window.addEventListener('focus', ocultar, { once: true });
        });
    }
    window.addEventListener('pageshow', ocultar);
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') ocultar();
    });
})();
</script>
@endsection

@section('contenido')
@include('includes.proceso_overlay_aviso', [
    'overlayId' => 'posbanc-overlay',
    'tituloId' => 'posbanc-overlay-titulo',
    'titulo' => 'Procesando…',
    'subtitulo' => 'Espere. Pulse Esc para cerrar este aviso.',
])

<div class="row">
    <div class="col-12">
        <div class="card card-primary card-outline">
            <div class="card-header">
                <h3 class="card-title">Posición bancaria diaria</h3>
            </div>
            <div class="card-body">
                <p class="text-muted mb-3">
                    Genera el Excel de posición: hoja <strong>Saldos</strong> desde Interbanking (ERP),
                    cheques del portfolio, <strong>Disponible HOY</strong> y hojas de proyección
                    (Macro / BMA / BAPRO / Bi Bank / BIND + Resumen descubierto).
                    TRF, RRHH e impuestos se completan a mano en el Excel. No consulta Anita online.
                </p>

                @if (session('errores'))
                    <div class="alert alert-danger">{{ session('errores') }}</div>
                @endif
                @if (session('mensaje'))
                    <div class="alert alert-warning">{{ session('mensaje') }}</div>
                @endif

                <form id="form-posbanc-consulta" method="get" action="{{ route('posicion_bancaria_diaria') }}" class="form-inline flex-wrap align-items-end mb-3">
                    <input type="hidden" name="consultar" value="1">
                    <div class="form-group mr-3 mb-2">
                        <label for="fecha" class="mr-2">Fecha</label>
                        <input type="date" class="form-control" id="fecha" name="fecha" value="{{ $fecha }}" required>
                    </div>
                    <div class="form-group mr-3 mb-2">
                        <label for="cotizacion_usd" class="mr-2">USD</label>
                        <input type="number" step="0.0001" min="0" class="form-control" id="cotizacion_usd" name="cotizacion_usd"
                               value="{{ $cotizacion_usd }}" style="width:110px" placeholder="venta">
                    </div>
                    <div class="form-group mr-3 mb-2">
                        <label for="cotizacion_eur" class="mr-2">EUR</label>
                        <input type="number" step="0.0001" min="0" class="form-control" id="cotizacion_eur" name="cotizacion_eur"
                               value="{{ $cotizacion_eur }}" style="width:110px" placeholder="venta">
                    </div>
                    <button type="submit" class="btn btn-primary mb-2 mr-2">
                        <i class="fas fa-search"></i> Consultar saldos
                    </button>
                </form>

                <form id="form-posbanc-export" method="get" action="{{ route('posicion_bancaria_diaria_exportar') }}" class="mb-4">
                    <input type="hidden" name="fecha" value="{{ $fecha }}">
                    <input type="hidden" name="cotizacion_usd" value="{{ $cotizacion_usd }}">
                    <input type="hidden" name="cotizacion_eur" value="{{ $cotizacion_eur }}">
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-file-excel"></i> Generar y descargar Excel
                    </button>
                </form>

                @if ($consultado && $preview)
                    <div class="mb-2">
                        <strong>Saldos Interbanking usados:</strong> {{ $preview['fecha'] }}
                        · {{ count($preview['por_codigo']) }} códigos mapeados
                        · {{ count($preview['sin_mapear']) }} sin mapear
                    </div>

                    @if (count($preview['por_codigo']) > 0)
                        <div class="table-responsive mb-4">
                            <table class="table table-sm table-bordered table-striped">
                                <thead>
                                <tr>
                                    <th>Código Saldos</th>
                                    <th class="text-right">Saldo</th>
                                </tr>
                                </thead>
                                <tbody>
                                @foreach ($preview['por_codigo'] as $codigo => $importe)
                                    <tr>
                                        <td><code>{{ $codigo }}</code></td>
                                        <td class="text-right">{{ number_format($importe, 2, ',', '.') }}</td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="alert alert-warning">No hay saldos IB mapeables para esa fecha.</div>
                    @endif

                    @if (count($preview['sin_mapear']) > 0)
                        <details class="mb-3">
                            <summary>Cuentas IB sin mapear ({{ count($preview['sin_mapear']) }})</summary>
                            <div class="table-responsive mt-2">
                                <table class="table table-sm table-bordered">
                                    <thead>
                                    <tr>
                                        <th>Emp</th><th>Banco</th><th>Cuenta</th><th>Mon</th><th>Tipo</th><th class="text-right">Saldo</th><th>Label</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    @foreach ($preview['sin_mapear'] as $row)
                                        <tr>
                                            <td>{{ $row['empresa_id'] }}</td>
                                            <td>{{ $row['bank_number'] }}</td>
                                            <td>{{ $row['account_number'] }}</td>
                                            <td>{{ $row['currency'] }}</td>
                                            <td>{{ $row['account_type'] }}</td>
                                            <td class="text-right">{{ number_format($row['day_balance'], 2, ',', '.') }}</td>
                                            <td>{{ $row['account_label'] }}</td>
                                        </tr>
                                    @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </details>
                    @endif
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
