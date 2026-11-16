@extends("theme.$theme.layout")

@section('titulo')
    Mis preferencias de avisos
@endsection

@section('contenido')
    <div class="container-fluid">
        <div class="card card-primary">
            <div class="card-header">
                <h3 class="card-title mb-0">Canales globales</h3>
            </div>
            <form method="post" action="{{ route('actualizar_mis_preferencias_aviso') }}" data-enter-campos="1">
                @csrf
                @method('PUT')
                <div class="card-body">
                    @include('includes.mensaje')
                    <p class="text-muted">
                        Por defecto recibís todos los canales. Desmarcá el que no quieras a nivel global.
                        Abajo podés silenciar canales de un aviso concreto (manda sobre el global).
                    </p>
                    @foreach ($canales as $clave => $etiqueta)
                        <div class="form-check mb-2">
                            <input type="hidden" name="canal_{{ $clave }}" value="0">
                            <input type="checkbox" class="form-check-input" name="canal_{{ $clave }}" id="canal_{{ $clave }}" value="1"
                                @if(!empty($preferencias[$clave])) checked @endif>
                            <label class="form-check-label" for="canal_{{ $clave }}">{{ $etiqueta }}</label>
                        </div>
                    @endforeach

                    <hr>
                    <h5>Por tipo de aviso</h5>
                    <p class="text-muted small">Solo aparece si desmarcás algo. Vacío = hereda el global. El digest es solo global.</p>
                    @php $canalesTipo = $canales_tipo ?? $canales; @endphp
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered">
                            <thead style="background:#85C1E9;color:#17202A;">
                                <tr>
                                    <th>Aviso</th>
                                    @foreach ($canalesTipo as $etiqueta)
                                        <th class="text-center">{{ $etiqueta }}</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($tipos as $tipo)
                                    @php
                                        $mapaTipo = $preferencias_tipo[$tipo->id] ?? null;
                                    @endphp
                                    <tr>
                                        <td>
                                            <strong>{{ $tipo->nombre }}</strong>
                                            <br><span class="text-muted small">{{ $tipo->modulo }}/{{ $tipo->codigo }}</span>
                                        </td>
                                        @foreach ($canalesTipo as $clave => $etiqueta)
                                            @php
                                                $checked = $mapaTipo === null
                                                    ? true
                                                    : (!isset($mapaTipo[$clave]) || !empty($mapaTipo[$clave]));
                                            @endphp
                                            <td class="text-center">
                                                <input type="hidden" name="tipo[{{ $tipo->id }}][{{ $clave }}]" value="0">
                                                <input type="checkbox" name="tipo[{{ $tipo->id }}][{{ $clave }}]" value="1"
                                                    @if($checked) checked @endif>
                                            </td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer">
                    <button type="submit" class="btn btn-primary btn-sm">Guardar</button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="btn-activar-web-push">
                        Activar notificaciones del navegador
                    </button>
                    <a href="{{ url('mis-aprobaciones') }}" class="btn btn-outline-info btn-sm">
                        <i class="fas fa-reply-all"></i> Mis aprobaciones
                    </a>
                    <span class="text-muted small ml-2" id="web-push-msg"></span>
                </div>
            </form>
            <div class="px-3 pb-3">
                <p class="text-muted small mb-0" id="web-push-ios-hint">
                    En iPhone/iPad: primero agregá Anita a la pantalla de inicio (Compartir → Agregar a inicio) y después activá las notificaciones desde esa app.
                    En Android y PC alcanza con el botón de arriba y aceptar el permiso del navegador.
                </p>
            </div>
        </div>
    </div>
@endsection

@section('scripts')
<script>
window.AnitaWebPushUrls = {
    publicKey: @json(route('web_push_public_key')),
    suscribir: @json(route('web_push_suscribir')),
};
</script>
<script src="{{ asset('assets/js/anita-web-push.js') }}?v={{ @filemtime(public_path('assets/js/anita-web-push.js')) ?: time() }}"></script>
<script>
document.getElementById('btn-activar-web-push')?.addEventListener('click', function () {
    var msg = document.getElementById('web-push-msg');
    msg.textContent = 'Activando…';
    window.AnitaWebPush.activar().then(function () {
        msg.textContent = 'Push activado en este dispositivo.';
        try { localStorage.setItem('anita_web_push_ok', '1'); } catch (e) {}
    }).catch(function (e) {
        msg.textContent = e.message || 'No se pudo activar.';
    });
});
</script>
@endsection

