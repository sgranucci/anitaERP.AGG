@extends("theme.$theme.layout")
@section('titulo')
Historial del Programador
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title"><i class="fa fa-history"></i> Run history global</h3>
                <div class="card-tools">
                    <a href="{{ route('consultar_modulo_aviso', ['solo_programables' => 1]) }}" class="btn btn-outline-light btn-sm">
                        <i class="fa fa-reply-all"></i> Volver al Programador
                    </a>
                </div>
            </div>
            <div class="card-body">
                <form method="get" action="{{ route('historial_modulo_aviso_programado') }}" class="form-inline mb-3" data-enter-campos="1">
                    <label class="mr-2">Clave</label>
                    <input type="text" name="clave" class="form-control form-control-sm mr-2" value="{{ $filtro_clave }}" placeholder="caja.cheques_aging">
                    <label class="mr-2">Estado</label>
                    <select name="estado" class="form-control form-control-sm mr-2">
                        <option value="">Todos</option>
                        <option value="ok" @if ($filtro_estado === 'ok') selected @endif>OK</option>
                        <option value="error" @if ($filtro_estado === 'error') selected @endif>Error</option>
                    </select>
                    <button type="submit" class="btn btn-primary btn-sm">Filtrar</button>
                </form>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered table-striped mb-0" id="tabla-paginada">
                        <thead style="background:#85C1E9;color:#17202A;">
                            <tr>
                                <th>ID</th>
                                <th>Inicio</th>
                                <th>Fin</th>
                                <th>Clave</th>
                                <th>Origen</th>
                                <th>Exit</th>
                                <th>Comando</th>
                                <th>Salida / error</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($logs as $log)
                                <tr>
                                    <td>{{ $log->id }}</td>
                                    <td class="text-nowrap">{{ optional($log->inicio_en)->format('d/m/Y H:i:s') }}</td>
                                    <td class="text-nowrap">{{ optional($log->fin_en)->format('d/m/Y H:i:s') ?? '—' }}</td>
                                    <td><code class="small">{{ $log->clave }}</code></td>
                                    <td>{{ $log->origen }}</td>
                                    <td>
                                        @if ($log->exit_code === 0)
                                            <span class="badge badge-success">0</span>
                                        @else
                                            <span class="badge badge-danger">{{ $log->exit_code }}</span>
                                        @endif
                                    </td>
                                    <td class="small">{{ $log->comando }}</td>
                                    <td class="small">
                                        @if ($log->error)
                                            <span class="text-danger">{{ \Illuminate\Support\Str::limit($log->error, 240) }}</span>
                                        @else
                                            {{ \Illuminate\Support\Str::limit($log->salida ?? '—', 240) }}
                                        @endif
                                    </td>
                                    <td class="text-right text-nowrap">
                                        @if ($log->modulo_aviso_tipo_id)
                                            <a href="{{ route('editar_modulo_aviso', $log->modulo_aviso_tipo_id) }}?tab=ejecucion" class="btn-accion-tabla" title="Configurar">
                                                <i class="fa fa-edit"></i>
                                            </a>
                                            <button type="button"
                                                class="btn-accion-tabla js-reintentar-aviso"
                                                title="Reintentar con impacto"
                                                data-tipo-id="{{ $log->modulo_aviso_tipo_id }}"
                                                data-impacto-url="{{ route('impacto_json_modulo_aviso_programado', $log->modulo_aviso_tipo_id) }}">
                                                <i class="fa fa-redo text-warning"></i>
                                            </button>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="text-muted">Sin corridas registradas.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="mt-3">
                    {{ $logs->links() }}
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modal-reintento-aviso" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Reintentar con impacto</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body">
                <div id="reintento-loading" class="text-muted">Cargando impacto…</div>
                <div id="reintento-contenido" class="d-none">
                    <p class="mb-1"><strong id="reintento-nombre"></strong> <code id="reintento-clave" class="ml-1"></code></p>
                    <p class="small text-muted mb-2" id="reintento-meta"></p>
                    <div id="reintento-advertencias" class="alert alert-warning d-none py-2"></div>
                    <p class="small mb-2">Destinatarios catálogo: <strong id="reintento-dest-count">0</strong></p>
                    <div class="table-responsive mb-2" style="max-height:12rem;overflow:auto;">
                        <table class="table table-sm table-bordered mb-0">
                            <thead style="background:#85C1E9;color:#17202A;">
                                <tr><th>Email</th><th>Usuario</th><th>Empresa</th><th>CC</th></tr>
                            </thead>
                            <tbody id="reintento-dest-tbody"></tbody>
                        </table>
                    </div>
                    <p class="small mb-0">Próximos 14 días: <span id="reintento-simular"></span></p>
                </div>
                <div id="reintento-error" class="alert alert-danger d-none mb-0"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cerrar</button>
                <form id="reintento-form" method="POST" action="#" class="d-inline">
                    @csrf
                    <input type="hidden" name="volver" value="historial">
                    <button type="submit" class="btn btn-warning btn-sm" id="reintento-submit" disabled>
                        <i class="fa fa-bolt"></i> Ejecutar ahora
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
(function () {
    var $modal = $('#modal-reintento-aviso');
    var token = $('meta[name="csrf-token"]').attr('content')
        || $('#reintento-form input[name=_token]').val();

    $(document).on('click', '.js-reintentar-aviso', function () {
        var url = $(this).data('impacto-url');
        $('#reintento-loading').removeClass('d-none');
        $('#reintento-contenido, #reintento-error').addClass('d-none');
        $('#reintento-submit').prop('disabled', true);
        $('#reintento-advertencias').addClass('d-none').empty();
        $modal.modal('show');

        $.getJSON(url)
            .done(function (data) {
                $('#reintento-loading').addClass('d-none');
                if (!data.ok) {
                    $('#reintento-error').removeClass('d-none').text(data.error || 'No se pudo cargar el impacto');
                    return;
                }
                var i = data.impacto;
                $('#reintento-nombre').text(i.nombre || '');
                $('#reintento-clave').text(i.clave || '');
                $('#reintento-meta').text(
                    'Acción: ' + (i.accion || '') +
                    (i.comando ? ' · ' + i.comando : '') +
                    ' · canales: ' + ((i.canales || []).join(', ')) +
                    (i.proxima ? ' · próxima: ' + i.proxima : '')
                );
                $('#reintento-dest-count').text(i.destinatarios_count || 0);
                var $tb = $('#reintento-dest-tbody').empty();
                (i.destinatarios || []).forEach(function (d) {
                    $tb.append(
                        '<tr><td>' + (d.email || '—') + '</td><td>' + (d.usuario || '—') +
                        '</td><td>' + (d.empresa_id || '—') + '</td><td>' + (d.centrocosto_id || '—') + '</td></tr>'
                    );
                });
                if (!(i.destinatarios || []).length) {
                    $tb.append('<tr><td colspan="4" class="text-muted">Sin destinatarios activos en catálogo</td></tr>');
                }
                var sim = (i.simular_14d || []).map(function (f) {
                    var p = String(f).split('-');
                    return p.length === 3 ? (p[2] + '/' + p[1]) : f;
                });
                $('#reintento-simular').html(sim.length
                    ? sim.map(function (s) { return '<span class="badge badge-info mr-1">' + s + '</span>'; }).join('')
                    : '<span class="text-muted">ninguna</span>');
                if ((i.advertencias || []).length) {
                    var html = '<ul class="mb-0 pl-3">';
                    i.advertencias.forEach(function (a) { html += '<li>' + a + '</li>'; });
                    html += '</ul>';
                    $('#reintento-advertencias').html(html).removeClass('d-none');
                }
                $('#reintento-form').attr('action', data.ejecutar_url);
                $('#reintento-submit').prop('disabled', !data.puede_ejecutar);
                if (!data.puede_ejecutar) {
                    $('#reintento-error').removeClass('d-none').text('Sin permiso de ejecución.');
                }
                $('#reintento-contenido').removeClass('d-none');
            })
            .fail(function () {
                $('#reintento-loading').addClass('d-none');
                $('#reintento-error').removeClass('d-none').text('Error al consultar el impacto.');
            });
    });

    $('#reintento-form').on('submit', function () {
        return confirm('¿Ejecutar este aviso ahora?');
    });
})();
</script>
@endsection
