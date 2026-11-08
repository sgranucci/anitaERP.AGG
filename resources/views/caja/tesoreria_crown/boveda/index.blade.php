@extends("theme.$theme.layout")

@section('titulo')
    Bóveda
@endsection

@section('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/tesoreria-crown-jornada.css') }}?v={{ filemtime(public_path('assets/css/tesoreria-crown-jornada.css')) }}">
    <link rel="stylesheet" href="{{ asset('assets/css/listado-workbench.css') }}?v={{ filemtime(public_path('assets/css/listado-workbench.css')) }}">
@endsection

@section('contenido')
@include('includes.mensaje')
@include('includes.proceso_overlay_aviso', [
    'overlayId' => 'tesoreria-crown-overlay',
    'tituloId' => 'tesoreria-crown-overlay-titulo',
    'subtituloId' => 'tesoreria-crown-overlay-subtitulo',
    'titulo' => 'Exportando…',
    'subtitulo' => 'Generando el archivo.',
])
@if (session('emitir_boveda') && can('imprimir-tesoreria-crown-boveda', false))
    <div class="alert alert-info">
        <a href="{{ route('pdf_tesoreria_crown_boveda', (int) session('emitir_boveda')) }}" target="_blank" rel="noopener" id="emitir-boveda-link">Emitir comprobante</a>
    </div>
@endif
<div class="row">
    <div class="col-lg-12">
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">
                    Bóveda
                    @if ($jornada)
                        · {{ $jornada->fecha->format('d/m/Y') }}
                    @endif
                    @if ($lugar)
                        — {{ $lugar->codigo }} {{ $lugar->nombre }}
                    @endif
                </h3>
                <div class="card-tools">
                    @if ($jornada && $lugar && can('listar-tesoreria-crown-boveda-arqueo', false))
                        <a href="{{ route('tesoreria_crown_boveda_arqueo', ['sala_id' => $sala->id ?? null, 'jornada_id' => $jornada->id, 'deposito_id' => $lugar->id]) }}" class="btn btn-sm tc-tool tc-tool-arqueo">
                            <i class="fa fa-balance-scale"></i> Arqueo de turno
                        </a>
                    @endif
                    @if ($jornada && $lugar && $jornada->estaAbierta() && can('crear-tesoreria-crown-boveda', false))
                        <a href="{{ route('nueva_tesoreria_crown_boveda', ['jornada_id' => $jornada->id, 'deposito_id' => $lugar->id]) }}" class="btn btn-primary btn-sm tc-tool tc-tool-nuevo">
                            <i class="fa fa-plus-circle"></i> Nuevo movimiento
                        </a>
                    @endif
                    @if ($jornada)
                        <a href="{{ route('ver_tesoreria_crown_jornada', $jornada->id) }}?panel=boveda" class="btn btn-outline-info btn-sm">
                            <i class="fa fa-desktop"></i> Escritorio
                        </a>
                    @endif
                </div>
            </div>
            @include('caja.tesoreria_crown.partials.sala_chips', [
                'ruta' => 'tesoreria_crown_boveda',
                'exportRuta' => ($jornada && $lugar) ? 'listar_tesoreria_crown_boveda' : null,
                'exportQueryparams' => array_merge($filtrosQuery ?? [], [
                    'columnas' => implode(',', $columnasVisibles ?? []),
                ]),
            ])
            @include('caja.tesoreria_crown.partials.jornada_selector', [
                'ruta' => 'tesoreria_crown_boveda',
                'queryExtra' => array_filter([
                    'deposito_id' => $lugar->id ?? null,
                ]),
            ])
            <div class="card-body">
                @if (! $jornada)
                    <p>
                        @if (trim((string) ($fechaPedida ?? '')) !== '')
                            No hay jornada el {{ \Illuminate\Support\Carbon::parse($fechaPedida)->format('d/m/Y') }} en esta sala.
                        @else
                            Esta sala no tiene jornada. Abrila para cargar movimientos.
                        @endif
                    </p>
                @elseif ($lugares->isEmpty())
                    <div class="alert alert-warning mb-0">
                        Esta sala no tiene un depósito de bóveda, pre-depósito o caja chica.
                        <a class="text-primary" href="{{ route('tesoreria_crown_deposito', ['sala_id' => $sala->id]) }}">Cargalo en Depósitos</a>
                        con el tipo Bóveda.
                    </div>
                @else
                    @if ($lugares->count() > 1)
                        <div class="mb-3">
                            @foreach ($lugares as $opcion)
                                <a class="btn btn-sm {{ $lugar && (int) $lugar->id === (int) $opcion->id ? 'btn-info' : 'btn-outline-info' }} mr-1 mb-1"
                                   href="{{ route('tesoreria_crown_boveda', ['sala_id' => $sala->id, 'jornada_id' => $jornada->id, 'deposito_id' => $opcion->id]) }}">
                                    {{ $opcion->tipoDeposito->nombre ?? '' }} {{ $opcion->codigo }}
                                </a>
                            @endforeach
                        </div>
                    @endif
                    <div class="d-flex flex-wrap align-items-center mb-2">
                        <button type="button" class="btn btn-sm lw-cortes-toggle collapsed mr-1 mb-1" data-toggle="collapse" data-target="#tc-herramienta-arqueo" aria-expanded="false" aria-controls="tc-herramienta-arqueo">
                            <i class="fa fa-chevron-down lw-cortes-ico lw-cortes-ico-abierto" aria-hidden="true"></i>
                            <i class="fa fa-chevron-right lw-cortes-ico lw-cortes-ico-cerrado" aria-hidden="true"></i>
                            Arqueo
                        </button>
                        <button type="button" class="btn btn-sm lw-cortes-toggle collapsed mr-1 mb-1" data-toggle="collapse" data-target="#tc-herramienta-inicio" aria-expanded="false" aria-controls="tc-herramienta-inicio">
                            <i class="fa fa-chevron-down lw-cortes-ico lw-cortes-ico-abierto" aria-hidden="true"></i>
                            <i class="fa fa-chevron-right lw-cortes-ico lw-cortes-ico-cerrado" aria-hidden="true"></i>
                            Inicio de bóveda
                        </button>
                    </div>
                    <div class="collapse mb-3" id="tc-herramienta-arqueo" data-url="{{ route('composicion_billete_tesoreria_crown_boveda', ['jornada_id' => $jornada->id, 'deposito_id' => $lugar->id], false) }}">
                        <p class="text-muted small mb-2">Saldo confirmado de {{ $lugar->nombre }} hasta esta jornada. Dólares y euros quedan en su moneda.</p>
                        @include('caja.tesoreria_crown.boveda.partials.saldo_grupos', [
                            'grupos' => $saldo,
                            'vacio' => 'Todavía no hay cantidades confirmadas en este lugar.',
                            'detalleBillete' => true,
                        ])
                    </div>
                    <div class="collapse mb-3" id="tc-herramienta-inicio">
                        @if ($apertura)
                            <p class="small mb-2">
                                <a class="text-primary" href="{{ route('editar_tesoreria_crown_boveda', $apertura->id) }}">
                                    Apertura {{ \App\Support\Caja\TesoreriaCrownNumeradorSupport::visible($apertura->numero) }}
                                </a>
                                <span class="text-muted">· saldo con el que arranca esta jornada.</span>
                            </p>
                            @include('caja.tesoreria_crown.boveda.partials.saldo_grupos', ['grupos' => $inicio, 'vacio' => 'La apertura no tiene cantidades cargadas.'])
                        @else
                            <p class="text-muted small mb-1">Esta jornada no tiene apertura cargada.</p>
                            @if ($jornada->estaAbierta() && can('crear-tesoreria-crown-boveda', false))
                                <a class="text-primary small" href="{{ route('nueva_tesoreria_crown_boveda', ['jornada_id' => $jornada->id, 'deposito_id' => $lugar->id, 'tipo_abrev' => 'APER']) }}">Cargar apertura</a>
                            @endif
                        @endif
                    </div>
                    <div class="lw-workbench mb-2" data-form="#form-filtros-tesoreria-crown-boveda">
                        <form method="get" action="{{ route('tesoreria_crown_boveda') }}" id="form-filtros-tesoreria-crown-boveda" class="mb-0">
                            <input type="hidden" name="sala_id" value="{{ $sala->id }}">
                            <input type="hidden" name="jornada_id" value="{{ $jornada->id }}">
                            <input type="hidden" name="deposito_id" value="{{ $lugar->id }}">
                            <input type="hidden" name="vista_id" value="{{ $vistaActiva->id ?? '' }}">
                            <input type="hidden" name="filtro_valor" id="filtro_valor" value="{{ $filtros['valor'] ?? '' }}">
                            <input type="hidden" name="filtro_busqueda_rapida" id="filtro_busqueda_rapida" value="">
                            <input type="hidden" name="columnas" id="lw_columnas_csv" value="{{ implode(',', $columnasVisibles ?? []) }}">
                            @include('caja.tesoreria_crown.partials.workbench_barra', [
                                'rutaListado' => 'tesoreria_crown_boveda',
                                'baseUrl' => route('tesoreria_crown_boveda', ['sala_id' => $sala->id, 'jornada_id' => $jornada->id, 'deposito_id' => $lugar->id]),
                                'limpiarParams' => ['sala_id' => $sala->id, 'jornada_id' => $jornada->id, 'deposito_id' => $lugar->id, 'vista_estandar' => 1],
                            ])
                            @include('caja.tesoreria_crown.partials.workbench_qbe')
                        </form>
                    </div>
                    @php
                        $puedeRevertirGrilla = $jornada->estaAbierta() && can('editar-tesoreria-crown-boveda', false);
                        $puedeImprimirGrilla = can('imprimir-tesoreria-crown-boveda', false);
                    @endphp
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered table-hover mb-0" id="tabla-paginada">
                            <thead style="background:#85C1E9;color:#17202A;">
                                <tr>
                                    @foreach ($columnasVisibles as $keyColumna)
                                        @include('caja.tesoreria_crown.partials.workbench_th', ['key' => $keyColumna, 'rutaListado' => 'tesoreria_crown_boveda'])
                                    @endforeach
                                    <th class="text-left" style="width:120px;">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($movimientos as $mov)
                                    <tr class="{{ $mov->estado === 'N' ? 'text-muted' : '' }}">
                                        @foreach ($columnasVisibles as $keyColumna)
                                            <td>
                                                @include('caja.tesoreria_crown.boveda.partials.workbench_celda', ['key' => $keyColumna])
                                            </td>
                                        @endforeach
                                        <td class="text-left text-nowrap">
                                            <a href="{{ route('editar_tesoreria_crown_boveda', $mov->id) }}" class="btn-accion-tabla tooltipsC" title="{{ $mov->esBorrador() ? 'Editar' : 'Consultar' }}">
                                                <i class="fa fa-edit"></i>
                                            </a>
                                            @if ($puedeImprimirGrilla)
                                                <a href="{{ route('pdf_tesoreria_crown_boveda', $mov->id) }}" class="btn-accion-tabla tooltipsC" title="Imprimir" target="_blank" rel="noopener">
                                                    <i class="fa fa-print"></i>
                                                </a>
                                            @endif
                                            @if ($puedeRevertirGrilla && $mov->puedeRevertir())
                                                <button type="button" class="btn-accion-tabla tooltipsC js-revertir-boveda" title="Revertir"
                                                        data-url="{{ route('revertir_tesoreria_crown_boveda', $mov->id) }}"
                                                        data-numero="{{ \App\Support\Caja\TesoreriaCrownNumeradorSupport::visible($mov->numero) }}">
                                                    <i class="fa fa-undo text-warning"></i>
                                                </button>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="{{ count($columnasVisibles) + 1 }}" class="text-center text-muted py-3">
                                            {{ $tieneCriterios ? 'No hay movimientos para esta búsqueda.' : 'No hay movimientos en este lugar.' }}
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                            @if ($movimientos->isNotEmpty())
                                <tfoot>
                                    <tr style="background:#EAF2F8;color:#17202A;font-weight:bold;">
                                        @foreach ($columnasVisibles as $keyColumna)
                                            <td class="{{ $keyColumna === 'valor' ? 'text-right' : '' }}">
                                                @if ($keyColumna === 'estado')
                                                    Totales
                                                @elseif ($keyColumna === 'valor')
                                                    @foreach ($totalesMonedas ?? [] as $codigo => $monto)
                                                        @if (abs((float) $monto) >= 0.0001)
                                                            <div class="{{ (float) $monto < 0 ? 'text-danger' : '' }}">
                                                                {{ number_format((float) $monto, 2, ',', '.') }} {{ $codigo }}
                                                            </div>
                                                        @endif
                                                    @endforeach
                                                @endif
                                            </td>
                                        @endforeach
                                        <td></td>
                                    </tr>
                                </tfoot>
                            @endif
                        </table>
                    </div>
                    @if ($puedeRevertirGrilla)
                        <div class="modal fade" id="modal-revertir-boveda" tabindex="-1" role="dialog" aria-hidden="true">
                            <div class="modal-dialog" role="document">
                                <form method="POST" id="form-revertir-boveda" action="" autocomplete="off" data-enter-campos="1" data-enter-boton="1">
                                    @csrf
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title">Revertir <span id="revertir-boveda-numero"></span></h5>
                                            <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button>
                                        </div>
                                        <div class="modal-body">
                                            <p class="mb-2">Se graba otro movimiento, con el número siguiente y las cantidades al revés. El original queda.</p>
                                            <div class="form-group">
                                                <label class="control-label text-right pr-2 d-block" for="revertir-boveda-motivo">Motivo</label>
                                                <textarea name="motivo" id="revertir-boveda-motivo" class="form-control" rows="2" maxlength="500" required></textarea>
                                            </div>
                                            <div class="form-group">
                                                <label class="control-label text-right pr-2 d-block" for="revertir-boveda-usuario">Usuario</label>
                                                <input type="text" name="firma_usuario" id="revertir-boveda-usuario" class="form-control" autocomplete="off" required>
                                            </div>
                                            <div class="form-group mb-0">
                                                <label class="control-label text-right pr-2 d-block" for="revertir-boveda-clave">Clave</label>
                                                <input type="password" name="firma_password" id="revertir-boveda-clave" class="form-control" autocomplete="new-password" required>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-outline-secondary btn-sm" data-dismiss="modal">Cancelar</button>
                                            <button type="submit" class="btn btn-outline-danger btn-sm"><i class="fa fa-undo"></i> Revertir</button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    @endif
                    @include('caja.tesoreria_crown.partials.workbench_modales', [
                        'rutaGuardarColumnas' => 'guardar_columnas_tesoreria_crown_boveda',
                        'rutaGuardarVista' => 'guardar_vista_tesoreria_crown_boveda',
                        'rutaEliminarVista' => 'eliminar_vista_tesoreria_crown_boveda',
                        'rutaPreview' => 'preview_tesoreria_crown_boveda',
                        'rutaGuardarEtiquetas' => 'guardar_etiquetas_tesoreria_crown_boveda',
                    ])
                @endif
            </div>
        </div>
    </div>
</div>
@if ($jornada && $lugar)
    <div class="modal fade" id="modal-arqueo-billete" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-xl" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="arqueo-billete-titulo">Operaciones</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body p-0">
                    <p class="text-muted small px-3 pt-3 mb-2" id="arqueo-billete-hasta"></p>
                    <div class="table-responsive" style="max-height:65vh;">
                        <table class="table table-sm table-bordered mb-0">
                            <thead style="background:#85C1E9;color:#17202A;position:sticky;top:0;">
                                <tr>
                                    <th>Fecha</th>
                                    <th>Número</th>
                                    <th>Tipo</th>
                                    <th>Efecto</th>
                                    <th class="text-right">Cantidad</th>
                                    <th class="text-right">Saldo</th>
                                    <th>Comentario</th>
                                </tr>
                            </thead>
                            <tbody id="arqueo-billete-filas"></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endif
@endsection

@section('scripts')
    @if (session('emitir_boveda') && can('imprimir-tesoreria-crown-boveda', false))
        <script>
            window.open(@json(route('pdf_tesoreria_crown_boveda', (int) session('emitir_boveda'))), 'bovedaEmitir');
        </script>
    @endif
    <script src="{{ asset('assets/pages/scripts/listado/workbench-qbe-grupos.js') }}?v={{ filemtime(public_path('assets/pages/scripts/listado/workbench-qbe-grupos.js')) }}"></script>
    <script src="{{ asset('assets/pages/scripts/listado/workbench-orden.js') }}?v={{ filemtime(public_path('assets/pages/scripts/listado/workbench-orden.js')) }}"></script>
    <script src="{{ asset('assets/pages/scripts/listado/workbench-agrupar.js') }}?v={{ filemtime(public_path('assets/pages/scripts/listado/workbench-agrupar.js')) }}"></script>
    <script src="{{ asset('assets/pages/scripts/listado/workbench-disenador-preview.js') }}?v={{ filemtime(public_path('assets/pages/scripts/listado/workbench-disenador-preview.js')) }}"></script>
    <script src="{{ asset('assets/pages/scripts/listado/workbench-vista-guardar.js') }}?v={{ filemtime(public_path('assets/pages/scripts/listado/workbench-vista-guardar.js')) }}"></script>
    <script src="{{ asset('assets/pages/scripts/caja/tesoreria_crown/workbench.js') }}?v={{ filemtime(public_path('assets/pages/scripts/caja/tesoreria_crown/workbench.js')) }}"></script>
    <script src="{{ asset('assets/pages/scripts/caja/tesoreria_crown/listado.js') }}?v={{ filemtime(public_path('assets/pages/scripts/caja/tesoreria_crown/listado.js')) }}"></script>
    <script>
        document.querySelectorAll('.js-revertir-boveda').forEach(function (boton) {
            boton.addEventListener('click', function () {
                var form = document.getElementById('form-revertir-boveda');
                if (!form || !window.jQuery) {
                    return;
                }
                form.action = boton.getAttribute('data-url') || '';
                form.querySelector('[name="motivo"]').value = '';
                form.querySelector('[name="firma_usuario"]').value = '';
                form.querySelector('[name="firma_password"]').value = '';
                document.getElementById('revertir-boveda-numero').textContent = boton.getAttribute('data-numero') || '';
                window.jQuery('#modal-revertir-boveda').modal('show');
            });
        });
        (function () {
            var panel = document.getElementById('tc-herramienta-arqueo');
            var modal = document.getElementById('modal-arqueo-billete');
            if (!panel || !modal || !window.jQuery) {
                return;
            }
            function texto(numero) {
                return Number(numero).toLocaleString('es-AR', { maximumFractionDigits: 2 });
            }
            function escapar(valor) {
                return String(valor == null ? '' : valor).replace(/[&<>"']/g, function (c) {
                    return {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[c];
                });
            }
            function filaHtml(fila) {
                var cantidad = Number(fila.cantidad);
                var clase = cantidad < 0 ? 'text-danger' : '';
                return '<tr>'
                    + '<td>' + escapar(fila.fecha) + '</td>'
                    + '<td><a class="text-primary" target="_blank" rel="noopener" href="' + escapar(fila.url) + '">' + escapar(fila.numero) + '</a></td>'
                    + '<td>' + escapar(fila.tipo) + '</td>'
                    + '<td>' + escapar(fila.efecto) + '</td>'
                    + '<td class="text-right ' + clase + '">' + texto(cantidad) + '</td>'
                    + '<td class="text-right">' + texto(fila.saldo) + '</td>'
                    + '<td>' + escapar(fila.comentario) + '</td>'
                    + '</tr>';
            }
            document.querySelectorAll('.js-arqueo-billete').forEach(function (boton) {
                boton.addEventListener('click', function () {
                    var cuerpo = document.getElementById('arqueo-billete-filas');
                    var titulo = document.getElementById('arqueo-billete-titulo');
                    var hasta = document.getElementById('arqueo-billete-hasta');
                    cuerpo.innerHTML = '<tr><td colspan="7" class="text-muted">Leyendo…</td></tr>';
                    titulo.textContent = 'Operaciones';
                    hasta.textContent = '';
                    window.jQuery(modal).modal('show');
                    var url = new URL(panel.getAttribute('data-url'), window.location.origin);
                    url.searchParams.set('valor_id', boton.getAttribute('data-valor') || '');
                    window.fetch(url, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
                        .then(function (respuesta) {
                            if (!respuesta.ok) {
                                throw new Error('respuesta');
                            }
                            return respuesta.json();
                        })
                        .then(function (datos) {
                            titulo.textContent = datos.titulo || 'Operaciones';
                            hasta.textContent = 'Hasta el ' + (datos.hasta || '') + '. Entra suma y sale resta. La última fila es el stock del arqueo: ' + texto(datos.saldo) + '.';
                            if (!datos.filas || !datos.filas.length) {
                                cuerpo.innerHTML = '<tr><td colspan="7" class="text-muted">Este billete no tiene movimientos que muevan el saldo.</td></tr>';
                                return;
                            }
                            cuerpo.innerHTML = datos.filas.map(filaHtml).join('');
                        })
                        .catch(function () {
                            cuerpo.innerHTML = '<tr><td colspan="7" class="text-danger">No se pudo leer el detalle.</td></tr>';
                        });
                });
            });
        })();
        if (window.jQuery) {
            window.jQuery('#modal-revertir-boveda').on('shown.bs.modal', function () {
                var campo = document.getElementById('revertir-boveda-motivo');
                if (campo) {
                    campo.focus();
                }
            });
        }
    </script>
@endsection
