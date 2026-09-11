@extends("theme.$theme.layout")
@section('titulo')
    Certificados ARCA
@endsection

@section('scripts')
<script>
window.certificadosArcaFilas = @json($filasJs ?? []);
window.certificadosArcaPrueba = @json(session('prueba_certificado_arca'));
</script>
<script src="{{ asset('assets/pages/scripts/ventas/certificados_arca/index.js') }}?v=20260911e"></script>
@endsection

@section('contenido')
@include('includes.proceso_overlay_aviso', [
    'overlayId' => 'cert-arca-overlay',
    'tituloId' => 'cert-arca-overlay-titulo',
    'subtituloId' => 'cert-arca-overlay-subtitulo',
    'titulo' => 'Procesando certificado…',
    'subtitulo' => 'No cierre la página.',
])
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        @include('includes.form-error')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Certificados ARCA por webservice</h3>
            </div>
            <div class="card-body">
                <p class="text-muted small mb-3">
                    <i class="fa fa-info-circle"></i>
                    El CSR se arma con el <strong>alias</strong> y el <strong>CUIT</strong> de ese webservice.
                    Cada fila es independiente: generar o instalar <strong>WSAPOC</strong> no toca padrón ni WSCDC
                    (pueden ser el certificado de la consultora, sin delegación en las empresas del cliente).
                    Al instalar, por default solo se actualiza <strong>ese</strong> webservice; opcionalmente puede copiar el mismo certificado a otros.
                    Con el ícono de archivo ZIP puede <strong>exportar</strong> el par vigente (<code>cert.crt</code> + <code>privada.key</code>) para llevarlo a otro ERP.
                    El certificado vigente no se reemplaza hasta que suba el <code>.crt</code> de ARCA.
                </p>
                <ol class="small mb-3 pl-3">
                    <li>Generar CSR y descargarlo.</li>
                    <li>En Clave Fiscal ARCA, crear/renovar el certificado con el <strong>mismo alias</strong> y pegar el CSR.</li>
                    <li>Descargar el <code>.crt</code> y usar <strong>Subir certificado</strong> en esta pantalla.</li>
                </ol>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered table-hover mb-0" id="tabla-certificados-arca">
                        <thead style="background:#85C1E9;color:#17202A;">
                            <tr>
                                <th>Servicio</th>
                                <th>Empresa</th>
                                <th>Carpeta</th>
                                <th>Alias</th>
                                <th>CUIT</th>
                                <th>Vence</th>
                                <th>Días</th>
                                <th>Renovación</th>
                                <th class="text-nowrap">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($filas as $f)
                                @php
                                    $dias = $f['dias_restantes'];
                                    $claseVence = '';
                                    if ($dias !== null && $dias < 0) {
                                        $claseVence = 'text-danger font-weight-bold';
                                    } elseif ($dias !== null && $dias <= 30) {
                                        $claseVence = 'text-warning font-weight-bold';
                                    }
                                    $empresaTxt = '—';
                                    if (! empty($f['empresa_id'])) {
                                        $empresaTxt = $f['empresa_id'];
                                        if (! empty($f['empresa_nombre'])) {
                                            $empresaTxt .= ' '.$f['empresa_nombre'];
                                        }
                                        if (! empty($f['carpeta'])) {
                                            $empresaTxt .= ' ['.$f['carpeta'].']';
                                        }
                                    }
                                @endphp
                                <tr>
                                    <td>{{ $f['etiqueta'] }}</td>
                                    <td>{{ $empresaTxt }}</td>
                                    <td><code class="small">{{ $f['ruta_corta'] ?? '—' }}</code></td>
                                    <td><code>{{ $f['alias'] ?? '—' }}</code></td>
                                    <td>{{ $f['cuit'] ?? '—' }}</td>
                                    <td class="{{ $claseVence }}">{{ $f['valid_to'] ?? '—' }}</td>
                                    <td class="{{ $claseVence }}">{{ $dias === null ? '—' : $dias }}</td>
                                    <td>
                                        @if (! empty($f['error']))
                                            <span class="text-danger">{{ $f['error'] }}</span>
                                        @elseif (! empty($f['tiene_csr']))
                                            CSR {{ $f['renovacion_stamp'] }}
                                            @if (! empty($f['tiene_crt_pendiente']))
                                                <br><small>Hay un .crt en la carpeta de renovación</small>
                                            @endif
                                        @else
                                            <span class="text-muted">Sin pedido</span>
                                        @endif
                                    </td>
                                    <td class="text-nowrap">
                                        @if ($puedeGenerar && empty($f['error']))
                                            <form method="post" action="{{ route('generar_csr_certificado_arca') }}" class="d-inline form-generar-csr-arca">
                                                @csrf
                                                <input type="hidden" name="certificado_id" value="{{ $f['id'] }}">
                                                <button type="submit" class="btn-accion-tabla tooltipsC" title="Generar CSR (alias {{ $f['alias'] }})">
                                                    <i class="fa fa-key"></i>
                                                </button>
                                            </form>
                                        @endif
                                        @if (! empty($f['tiene_csr']))
                                            <a href="{{ route('descargar_csr_certificado_arca', ['id' => $f['id']]) }}"
                                               class="btn-accion-tabla tooltipsC" title="Descargar CSR">
                                                <i class="fa fa-download"></i>
                                            </a>
                                        @endif
                                        @if ($puedeInstalar && ! empty($f['tiene_csr']))
                                            <button type="button"
                                                    class="btn-accion-tabla tooltipsC btn-subir-crt-arca"
                                                    title="Subir .crt de ARCA (valida e instala)"
                                                    data-id="{{ $f['id'] }}"
                                                    data-alias="{{ $f['alias'] }}"
                                                    data-etiqueta="{{ $f['etiqueta'] }}">
                                                <i class="fa fa-upload"></i>
                                            </button>
                                        @endif
                                        @if ($puedeInstalar && empty($f['error']))
                                            <a href="{{ route('exportar_par_certificado_arca', ['id' => $f['id']]) }}"
                                               class="btn-accion-tabla tooltipsC"
                                               title="Exportar certificado + clave privada (ZIP)">
                                                <i class="fa fa-file-archive-o"></i>
                                            </a>
                                        @endif
                                        @if (empty($f['error']))
                                            <form method="post" action="{{ route('probar_certificado_arca') }}" class="d-inline form-probar-cert-arca">
                                                @csrf
                                                <input type="hidden" name="certificado_id" value="{{ $f['id'] }}">
                                                <button type="submit" class="btn-accion-tabla tooltipsC" title="Probar conexión ARCA (WSAA + dummy)">
                                                    <i class="fa fa-plug"></i>
                                                </button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="text-center text-muted">No hay certificados ARCA configurados en este entorno.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modal-subir-crt-arca" tabindex="-1" role="dialog" aria-labelledby="modal-subir-crt-arca-titulo" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form method="post" action="{{ route('instalar_certificado_arca') }}" enctype="multipart/form-data" id="form-instalar-crt-arca">
                @csrf
                <input type="hidden" name="certificado_id" id="instalar_certificado_id" value="">
                <div class="modal-header">
                    <h5 class="modal-title" id="modal-subir-crt-arca-titulo">Subir certificado ARCA</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <p class="small text-muted mb-2">
                        Se instala en <strong id="instalar_alias_label"></strong>.
                        El archivo debe ser el <code>.crt</code> emitido para el CSR de este alias.
                        Por default <strong>solo</strong> se reemplaza el certificado de ese webservice (con backup).
                    </p>
                    <div class="form-group">
                        <label for="certificado" class="control-label">Archivo .crt</label>
                        <input type="file" name="certificado" id="certificado" class="form-control" accept=".crt,.pem,.cer,.txt,application/x-x509-ca-cert,application/pkix-cert" required>
                    </div>
                    <div class="form-group mb-0">
                        <label class="control-label d-block">También copiar a (opcional)</label>
                        <p class="small text-muted mb-2">Ninguno tildado = solo el webservice en el que está trabajando.</p>
                        <div id="replicar-extras" class="border rounded p-2" style="max-height: 220px; overflow-y: auto;"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fa fa-check"></i> Validar e instalar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="modal-prueba-cert-arca" tabindex="-1" role="dialog" aria-labelledby="modal-prueba-cert-arca-titulo" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header" id="modal-prueba-cert-arca-header">
                <h5 class="modal-title" id="modal-prueba-cert-arca-titulo">Resultado de la prueba</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p class="mb-3" id="modal-prueba-cert-arca-resumen"></p>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered mb-0">
                        <thead style="background:#85C1E9;color:#17202A;">
                            <tr>
                                <th style="width: 4rem;">Estado</th>
                                <th style="width: 12rem;">Paso</th>
                                <th>Detalle</th>
                            </tr>
                        </thead>
                        <tbody id="modal-prueba-cert-arca-pasos"></tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" data-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>
@endsection
