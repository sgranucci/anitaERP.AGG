@extends("theme.$theme.layout")

@section('titulo')
    Archivo pagos Francés
@endsection

@section('contenido')
@php
    $colLabel = 'col-lg-3 control-label text-right pr-2';
    $colInput = 'col-lg-3';
    $filas = $resultado['filas'] ?? [];
    $omitidas = $resultado['omitidas'] ?? [];
    $errores = $resultado['errores'] ?? [];
@endphp

<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        @include('includes.form-error')
        @if (session('mensaje_error'))
            <div class="alert alert-danger">{{ session('mensaje_error') }}</div>
        @endif

        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Archivo de pagos Banco Francés</h3>
                <div class="card-tools">
                    <a href="{{ route('frances_archivo_pago') }}" class="btn btn-outline-secondary btn-sm" title="Limpiar">
                        <i class="fa fa-eraser"></i> Limpiar
                    </a>
                </div>
            </div>

            <form method="get" action="{{ route('frances_archivo_pago') }}" id="form-frances-archivo-pago" class="mb-0">
                <input type="hidden" name="consultar" value="1">
                <div class="card-body pb-2">
                    <p class="text-muted small mb-3">
                        Toma las órdenes de pago del período pagadas con la cuenta de caja indicada
                        y arma <strong>frances.csv</strong>: nombre, CBU, importe, FAC y CUIT.
                        La cuenta que propone es la cuenta corriente del Banco Francés.
                        No incluye las que Anita ya marcó como transferidas.
                    </p>

                    @include('includes.form-empresa-asignada', [
                        'empresa_query' => $empresa_query,
                        'empresa_id' => $filtros['empresa_id'] ?? null,
                        'col_label' => 'col-lg-3 text-right pr-2',
                        'col_input' => 'col-lg-4',
                        'required' => true,
                    ])

                    <div class="form-group row">
                        <label for="fecha_desde" class="{{ $colLabel }} requerido">Desde fecha</label>
                        <div class="{{ $colInput }}">
                            <input type="date" name="fecha_desde" id="fecha_desde" class="form-control" required
                                   value="{{ $filtros['fecha_desde'] ?? '' }}">
                        </div>
                        <label for="fecha_hasta" class="{{ $colLabel }} requerido">Hasta fecha</label>
                        <div class="{{ $colInput }}">
                            <input type="date" name="fecha_hasta" id="fecha_hasta" class="form-control" required
                                   value="{{ $filtros['fecha_hasta'] ?? '' }}">
                        </div>
                    </div>

                    @include('caja.partials.campo_consulta_cuentacaja', [
                        'prefix' => 'frances',
                        'layout' => 'form_row',
                        'label' => 'Cuenta de caja',
                        'inputName' => 'cuentacaja_id',
                        'inputId' => 'frances_cuentacaja_id',
                        'cuentacajaId' => (int) ($filtros['cuentacaja_id'] ?? 0) > 0 ? (int) $filtros['cuentacaja_id'] : '',
                        'codigo' => $cuenta->codigo ?? '',
                        'nombre' => $cuenta->nombre ?? '',
                        'col_label' => 'col-lg-3 control-label text-right pr-2',
                        'col_input' => 'col-lg-6',
                        'required' => true,
                        'ayuda' => 'Código, Enter valida. F1 o la lupa abren la consulta.',
                    ])
                </div>
                <div class="card-footer">
                    <button type="submit" class="btn btn-primary">
                        <i class="fa fa-search"></i> Consultar
                    </button>
                    @if ($consultado && count($filas) > 0)
                        <a href="{{ route('descargar_frances_archivo_pago', $filtrosQuery) }}" class="btn btn-outline-primary" id="btn-descargar-frances">
                            <i class="fa fa-download"></i> frances.csv
                        </a>
                    @endif
                </div>
            </form>
        </div>

        @if ($consultado)
            <div class="card card-outline card-info">
                <div class="card-header">
                    <h3 class="card-title">
                        {{ (int) ($resultado['cantidad'] ?? 0) }} pago(s)
                        · $ {{ number_format((float) ($resultado['total'] ?? 0), 2, ',', '.') }}
                    </h3>
                </div>
                <div class="card-body">
                    @if ($errores !== [])
                        <div class="alert alert-warning">
                            @foreach ($errores as $error)
                                <div>{{ $error }}</div>
                            @endforeach
                        </div>
                    @endif
                    @if ($omitidas !== [])
                        <div class="alert alert-warning">
                            <strong>Omitidas</strong>
                            <ul class="mb-0">
                                @foreach ($omitidas as $omitida)
                                    <li>{{ $omitida['orden_pago'] ?? '' }}: {{ $omitida['motivo'] ?? '' }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                    <div style="max-height: 28rem; overflow: auto;">
                        <table class="table table-sm table-bordered mb-0" id="tabla-paginada">
                            <thead style="background:#85C1E9;color:#17202A;">
                                <tr>
                                    <th>Tipo</th>
                                    <th>Orden de pago</th>
                                    <th>Proveedor</th>
                                    <th>Fecha</th>
                                    <th class="text-right">Importe</th>
                                    <th>CBU</th>
                                    <th>CUIT</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($filas as $fila)
                                    <tr>
                                        <td>{{ $fila['tipo'] ?? '' }}</td>
                                        <td>{{ $fila['orden_pago'] ?? '' }}</td>
                                        <td>{{ $fila['proveedor_nombre'] ?? '' }}</td>
                                        <td>
                                            @if (! empty($fila['fecha']))
                                                {{ date('d/m/Y', strtotime((string) $fila['fecha'])) }}
                                            @endif
                                        </td>
                                        <td class="text-right">{{ number_format((float) ($fila['importe'] ?? 0), 2, ',', '.') }}</td>
                                        <td><code>{{ $fila['cbu'] ?? '' }}</code></td>
                                        <td>{{ $fila['cuit'] ?? '' }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="text-center text-muted">Sin pagos para ese filtro.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endif
    </div>
</div>

@include('includes.caja.modalconsultacuentacaja')
@include('includes.proceso_overlay_aviso', [
    'overlayId' => 'frances-archivo-overlay',
    'tituloId' => 'frances-archivo-titulo',
    'subtituloId' => 'frances-archivo-subtitulo',
    'titulo' => 'Leyendo pagos en Anita…',
    'subtitulo' => 'Puede demorar según el período. No cierre la página.',
])
@endsection

@section('scripts')
<script src="{{ asset('assets/pages/scripts/caja/cuentacaja/consulta.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/caja/cuentacaja/consulta.js')) ?: time() }}"></script>
<script src="{{ asset('assets/pages/scripts/caja/frances/archivo_pago.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/caja/frances/archivo_pago.js')) ?: time() }}"></script>
<script>
(function () {
    var form = document.getElementById('form-frances-archivo-pago');
    var overlay = document.getElementById('frances-archivo-overlay');
    var titulo = document.getElementById('frances-archivo-titulo');
    function ocultar() {
        if (!overlay) return;
        overlay.classList.add('d-none');
        overlay.style.display = '';
        overlay.setAttribute('aria-hidden', 'true');
    }
    function mostrar(texto) {
        if (!overlay) return;
        if (texto && titulo) titulo.textContent = texto;
        overlay.classList.remove('d-none');
        overlay.style.display = 'flex';
        overlay.setAttribute('aria-hidden', 'false');
    }
    if (form) {
        form.addEventListener('submit', function () {
            if (!form.checkValidity()) return;
            mostrar('Leyendo pagos en Anita…');
        });
    }
    var descarga = document.getElementById('btn-descargar-frances');
    if (descarga) {
        descarga.addEventListener('click', function (e) {
            e.preventDefault();
            mostrar('Armando frances.csv…');
            fetch(descarga.href, { credentials: 'same-origin' })
                .then(function (r) {
                    var tipo = r.headers.get('Content-Type') || '';
                    if (!r.ok || tipo.indexOf('text/csv') === -1) {
                        window.location = descarga.href;
                        return null;
                    }
                    var nombre = 'frances.csv';
                    var m = /filename="([^"]+)"/.exec(r.headers.get('Content-Disposition') || '');
                    if (m) nombre = m[1];
                    return r.blob().then(function (blob) { return { blob: blob, nombre: nombre }; });
                })
                .then(function (pack) {
                    if (!pack) return;
                    var url = URL.createObjectURL(pack.blob);
                    var a = document.createElement('a');
                    a.href = url;
                    a.download = pack.nombre;
                    document.body.appendChild(a);
                    a.click();
                    a.remove();
                    URL.revokeObjectURL(url);
                    ocultar();
                })
                .catch(function () {
                    ocultar();
                    window.location = descarga.href;
                });
        });
    }
    window.addEventListener('pageshow', ocultar);
})();
</script>
@endsection
