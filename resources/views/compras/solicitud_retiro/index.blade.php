@extends("theme.$theme.layout")

@section('titulo')
    Solicitudes de retiro
@endsection

@section('contenido')
@php
    $colLabel = 'col-lg-3 control-label text-right pr-2';
    $colInput = 'col-lg-4';
    $pref = $preferencia ?? ['cbu_origen' => '', 'secuencia_sugerida' => 1, 'ultima_secuencia' => 0];
    $cbu = old('cbu_origen', $pref['cbu_origen'] ?? '');
    $fecha = old('fecha', date('Y-m-d'));
    $secuencia = old('secuencia', $pref['secuencia_sugerida'] ?? 1);
    $resultado = is_array($resultado ?? null) ? $resultado : null;
    $filas = $resultado['filas'] ?? [];
    $omitidas = $resultado['omitidas'] ?? [];
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
                <h3 class="card-title">Solicitudes de retiro</h3>
                <div class="card-tools">
                    <a href="{{ route('solicitud_retiro_crown', ['limpiar' => 1]) }}" class="btn btn-outline-secondary btn-sm" title="Limpiar el resultado">
                        <i class="fa fa-eraser"></i> Limpiar
                    </a>
                </div>
            </div>

            <form method="post" action="{{ route('generar_solicitud_retiro_crown') }}" id="form-solicitud-retiro" enctype="multipart/form-data" class="mb-0">
                @csrf
                <div class="card-body pb-2">
                    <p class="text-muted small mb-3">
                        Equivalente a Anita <code>p-cargasolret</code>. El CSV de BETSUR o DRO se parte en dos archivos:
                        <strong>solretiro.txt</strong> para Interbanking (bancos) y <strong>frances.csv</strong> cuando el banco empieza con PROBABLE CVU.
                    </p>

                    <div class="form-group row">
                        <label for="cbu_origen" class="{{ $colLabel }} requerido">CBU cuenta origen</label>
                        <div class="{{ $colInput }}">
                            <input type="text" name="cbu_origen" id="cbu_origen" class="form-control" maxlength="32"
                                   value="{{ $cbu }}" required autocomplete="off"
                                   placeholder="22 dígitos" title="CBU de débito de la cabecera Interbanking">
                            <small class="form-text text-muted">Va en la cabecera *U* del archivo Interbanking. Se recuerda para la próxima vez.</small>
                        </div>
                    </div>

                    <div class="form-group row">
                        <label for="fecha" class="{{ $colLabel }} requerido">Fecha del archivo</label>
                        <div class="col-lg-3">
                            <input type="date" name="fecha" id="fecha" class="form-control" value="{{ $fecha }}" required>
                        </div>
                        <label for="secuencia" class="col-lg-2 control-label text-right pr-2 requerido">Secuencia</label>
                        <div class="col-lg-2">
                            <input type="number" name="secuencia" id="secuencia" class="form-control" min="1" max="99999999"
                                   value="{{ $secuencia }}" required>
                            <small class="form-text text-muted">Última usada: {{ (int) ($pref['ultima_secuencia'] ?? 0) }}</small>
                        </div>
                    </div>

                    <div class="form-group row">
                        <label for="archivo" class="{{ $colLabel }} requerido">Archivo CSV</label>
                        <div class="{{ $colInput }}">
                            <input type="file" name="archivo" id="archivo" class="form-control" accept=".csv,.txt,text/csv,text/plain" required>
                            <small class="form-text text-muted">Columnas: operación, monto, estado, nombre, apellido, DNI, CUIT, CBU, banco.</small>
                        </div>
                    </div>
                </div>
                <div class="card-footer">
                    <button type="submit" class="btn btn-primary">
                        <i class="fa fa-cogs"></i> Generar archivos
                    </button>
                </div>
            </form>
        </div>

        @if ($resultado)
            <div class="card card-outline card-info">
                <div class="card-header">
                    <h3 class="card-title">
                        Resultado
                        <span class="text-muted small">secuencia {{ sprintf('%08d', (int) ($resultado['secuencia'] ?? 0)) }}</span>
                    </h3>
                    <div class="card-tools">
                        @if ((int) ($resultado['cantidad_interbanking'] ?? 0) > 0)
                            <a href="{{ route('descargar_solicitud_retiro_crown', ['tipo' => 'interbanking']) }}" class="btn btn-outline-primary btn-sm">
                                <i class="fa fa-download"></i> solretiro.txt
                            </a>
                        @endif
                        @if ((int) ($resultado['cantidad_frances'] ?? 0) > 0)
                            <a href="{{ route('descargar_solicitud_retiro_crown', ['tipo' => 'frances']) }}" class="btn btn-outline-primary btn-sm">
                                <i class="fa fa-download"></i> frances.csv
                            </a>
                        @endif
                        <a href="{{ route('descargar_solicitud_retiro_crown', ['tipo' => 'zip']) }}" class="btn btn-primary btn-sm">
                            <i class="fa fa-file-archive-o"></i> Zip
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-3">
                            <div class="small text-muted">Filas con monto</div>
                            <strong>{{ (int) ($resultado['cantidad_datos'] ?? 0) }}</strong>
                        </div>
                        <div class="col-md-3">
                            <div class="small text-muted">Interbanking</div>
                            <strong>{{ (int) ($resultado['cantidad_interbanking'] ?? 0) }}</strong>
                            <span class="text-muted">$ {{ number_format((float) ($resultado['total_interbanking'] ?? 0), 2, ',', '.') }}</span>
                        </div>
                        <div class="col-md-3">
                            <div class="small text-muted">Francés (CVU)</div>
                            <strong>{{ (int) ($resultado['cantidad_frances'] ?? 0) }}</strong>
                            <span class="text-muted">$ {{ number_format((float) ($resultado['total_frances'] ?? 0), 2, ',', '.') }}</span>
                        </div>
                        <div class="col-md-3">
                            <div class="small text-muted">Omitidas</div>
                            <strong>{{ count($omitidas) }}</strong>
                        </div>
                    </div>

                    @if ((int) ($resultado['cantidad_no_autorizadas'] ?? 0) > 0)
                        <div class="alert alert-warning">
                            Hay {{ (int) $resultado['cantidad_no_autorizadas'] }} fila(s) con estado distinto de Autorizado. Igual entran al archivo, como en Anita.
                        </div>
                    @endif

                    @if ($omitidas !== [])
                        <div class="alert alert-warning">
                            <strong>Omitidas</strong>
                            <ul class="mb-0">
                                @foreach ($omitidas as $omitida)
                                    <li>Línea {{ (int) ($omitida['linea'] ?? 0) }}: {{ $omitida['motivo'] ?? '' }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <div style="max-height: 28rem; overflow: auto;">
                        <table class="table table-sm table-bordered mb-0" id="tabla-solicitud-retiro">
                            <thead style="background:#85C1E9;color:#17202A;">
                                <tr>
                                    <th>Operación</th>
                                    <th>Nombre</th>
                                    <th>CUIT</th>
                                    <th>CBU</th>
                                    <th>Banco</th>
                                    <th class="text-right">Monto</th>
                                    <th>Estado</th>
                                    <th>Archivo</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($filas as $fila)
                                    <tr>
                                        <td>{{ $fila['operacion'] ?? '' }}</td>
                                        <td>{{ $fila['nombre'] ?? '' }}</td>
                                        <td>{{ $fila['cuit'] ?? '' }}</td>
                                        <td><code>{{ $fila['cbu'] ?? '' }}</code></td>
                                        <td>{{ $fila['banco'] ?? '' }}</td>
                                        <td class="text-right">{{ number_format((float) ($fila['monto'] ?? 0), 2, ',', '.') }}</td>
                                        <td>{{ $fila['estado'] ?? '' }}</td>
                                        <td>
                                            @if (($fila['destino'] ?? '') === 'frances')
                                                Francés
                                            @else
                                                Interbanking
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8" class="text-center text-muted">Sin filas.</td>
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

@include('includes.proceso_overlay_aviso', [
    'overlayId' => 'solicitud-retiro-overlay',
    'tituloId' => 'solicitud-retiro-titulo',
    'subtituloId' => 'solicitud-retiro-subtitulo',
    'titulo' => 'Generando archivos…',
    'subtitulo' => 'Leyendo el CSV. No cierre la página.',
])
@endsection

@section('scripts')
<script>
(function () {
    var form = document.getElementById('form-solicitud-retiro');
    var overlay = document.getElementById('solicitud-retiro-overlay');
    function ocultar() {
        if (!overlay) return;
        overlay.classList.add('d-none');
        overlay.style.display = '';
        overlay.setAttribute('aria-hidden', 'true');
    }
    function mostrar() {
        if (!overlay) return;
        overlay.classList.remove('d-none');
        overlay.style.display = 'flex';
        overlay.setAttribute('aria-hidden', 'false');
    }
    if (form) {
        form.addEventListener('submit', function () {
            if (!form.checkValidity()) return;
            mostrar();
        });
    }
    window.addEventListener('pageshow', ocultar);
})();
</script>
@endsection
