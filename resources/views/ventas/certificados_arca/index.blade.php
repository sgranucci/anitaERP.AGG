@extends("theme.$theme.layout")
@section('titulo')
    Certificados ARCA
@endsection

@section('scripts')
<script>
window.certificadosArcaFilas = @json($filasJs ?? []);
window.certificadosArcaPrueba = @json(session('prueba_certificado_arca'));
</script>
<script src="{{ asset('assets/pages/scripts/ventas/certificados_arca/index.js') }}?v=20260927c"></script>
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
        @if (is_array($reemplazoPendiente ?? null))
            <div class="alert alert-warning">
                <h4><i class="icon fa fa-warning"></i> Confirmar reemplazo</h4>
                <p class="mb-2">
                    El certificado de
                    <strong>{{ $reemplazoPendiente['etiqueta'] ?? 'este webservice' }}</strong>
                    no se modificó. El archivo subido no coincide con el vigente.
                </p>
                <ul class="mb-2">
                    @foreach (($reemplazoPendiente['avisos'] ?? []) as $textoAviso)
                        <li>{{ $textoAviso }}</li>
                    @endforeach
                </ul>
                <p class="mb-2">Si este es el certificado que corresponde, confirme el reemplazo. Si no, cancele.</p>
                @if ($puedeInstalar)
                    <form method="post" action="{{ route('confirmar_reemplazo_certificado_arca') }}" id="form-confirmar-reemplazo-arca" class="d-inline">
                        @csrf
                        <input type="hidden" name="token" value="{{ $reemplazoPendiente['token'] ?? '' }}">
                        <button type="submit" class="btn btn-warning">Reemplazar igual</button>
                    </form>
                    <form method="post" action="{{ route('cancelar_reemplazo_certificado_arca') }}" class="d-inline ml-1">
                        @csrf
                        <input type="hidden" name="token" value="{{ $reemplazoPendiente['token'] ?? '' }}">
                        <button type="submit" class="btn btn-outline-secondary">Cancelar</button>
                    </form>
                @endif
            </div>
        @endif
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
                    En el otro servidor, <strong>Subir certificado</strong> instala lo que elija: solo el <code>.crt</code>, solo la clave, o los dos. Si no sube la clave, queda la que ya está en el servidor.
                    El certificado vigente no se reemplaza hasta que suba el <code>.crt</code> o el par.
                </p>
                <ol class="small mb-3 pl-3">
                    <li>Generar CSR y descargarlo.</li>
                    <li>En Clave Fiscal ARCA, crear/renovar el certificado con el <strong>mismo alias</strong> y pegar el CSR.</li>
                    <li>Descargar el <code>.crt</code> y usar <strong>Subir certificado</strong>. La clave privada es opcional: si no la sube, no se reemplaza.</li>
                </ol>
                @if ($puedeInstalar)
                    <div class="card card-outline card-info mb-3">
                        <div class="card-header py-2">
                            <h3 class="card-title mb-0">
                                <a class="text-dark collapsed ws-pantalla-toggle" data-toggle="collapse" href="#webservices-pantalla-arca" role="button" aria-expanded="false" aria-controls="webservices-pantalla-arca">
                                    <i class="fa fa-chevron-right ws-ico-cerrado" aria-hidden="true"></i>
                                    <i class="fa fa-chevron-down ws-ico-abierto" aria-hidden="true"></i>
                                    Webservices de esta pantalla
                                </a>
                            </h3>
                            <style>
                                .ws-pantalla-toggle { cursor: pointer; }
                                .ws-pantalla-toggle .ws-ico-abierto { display: none; }
                                .ws-pantalla-toggle:not(.collapsed) .ws-ico-cerrado { display: none; }
                                .ws-pantalla-toggle:not(.collapsed) .ws-ico-abierto { display: inline; }
                                .ws-pantalla-toggle.collapsed::after { content: ' (mostrar)'; font-weight: normal; font-size: .85rem; color: #6c757d; }
                                .ws-pantalla-toggle:not(.collapsed)::after { content: ' (ocultar)'; font-weight: normal; font-size: .85rem; color: #6c757d; }
                            </style>
                        </div>
                        <div id="webservices-pantalla-arca" class="collapse">
                        <div class="card-body py-2">
                            <p class="small text-muted mb-2">
                                Destilde los que no usan en esta instalación: dejan de aparecer en el listado
                                y dejan de entrar en el mail de vencimiento.
                                El webservice del punto de venta sigue siendo el que tiene cargado ahí.
                            </p>
                            <form method="post" action="{{ route('guardar_webservices_certificado_arca') }}">
                                @csrf
                                @foreach ($serviciosPantalla as $ws)
                                    <div class="custom-control custom-checkbox custom-control-inline mb-1">
                                        <input type="checkbox" class="custom-control-input" id="ws-pantalla-{{ $ws['id'] }}" name="servicios[]" value="{{ $ws['id'] }}" @checked($ws['visible'])>
                                        <label class="custom-control-label" for="ws-pantalla-{{ $ws['id'] }}">{{ $ws['etiqueta'] }}</label>
                                    </div>
                                @endforeach
                                <div class="mt-2">
                                    <button type="submit" class="btn btn-primary btn-sm">Guardar</button>
                                </div>
                            </form>
                        </div>
                        </div>
                    </div>
                @endif
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
                                        @if ($puedeInstalar)
                                            <button type="button"
                                                    class="btn-accion-tabla tooltipsC btn-subir-crt-arca"
                                                    title="Subir certificado (.crt de este servidor, o ZIP de otro)"
                                                    data-id="{{ $f['id'] }}"
                                                    data-alias="{{ $f['alias'] }}"
                                                    data-etiqueta="{{ $f['etiqueta'] }}"
                                                    data-tiene-csr="{{ ! empty($f['tiene_csr']) ? '1' : '0' }}">
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
                        Por default <strong>solo</strong> se reemplaza el certificado de ese webservice (con backup).
                    </p>
                    <p class="small mb-2" id="instalar_ayuda_csr">
                        Se instala lo que suba. La clave privada es opcional: si no la elige, queda la que ya está en este servidor.
                        Si el alias o el CUIT no coinciden con el vigente, hay que confirmar el reemplazo.
                    </p>
                    <div class="form-group">
                        <label for="par_zip" class="control-label">ZIP (opcional)</label>
                        <input type="file" name="par_zip" id="par_zip" class="form-control" accept=".zip,application/zip">
                        <small class="text-muted">Puede traer solo <code>cert.crt</code>, solo <code>privada.key</code>, o los dos.</small>
                    </div>
                    <div class="form-group">
                        <label for="certificado" class="control-label">Archivo .crt (opcional)</label>
                        <input type="file" name="certificado" id="certificado" class="form-control" accept=".crt,.pem,.cer,.txt,application/x-x509-ca-cert,application/pkix-cert">
                    </div>
                    <div class="form-group">
                        <label for="clave" class="control-label">Clave privada (opcional)</label>
                        <input type="file" name="clave" id="clave" class="form-control" accept=".key,.pem,.txt">
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
