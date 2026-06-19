<div id="ticket-comentario-enviando-overlay"
     class="d-none"
     role="status"
     aria-live="assertive"
     aria-hidden="true"
     style="position: fixed; inset: 0; background: rgba(0,0,0,0.55); z-index: 2050; display: flex; align-items: flex-start; justify-content: center; padding: 1.25rem; pointer-events: all;">
    <div class="alert alert-warning shadow-lg mb-0 text-center px-4 py-3 border border-warning"
         style="max-width: 96vw; min-width: 20rem; font-size: 1rem;">
        <i class="fa fa-spinner fa-spin fa-2x text-danger mb-2 d-block" aria-hidden="true"></i>
        <strong id="ticket-comentario-enviando-titulo">{{ $titulo ?? 'Enviando comentario…' }}</strong>
        <div class="small mt-2" id="ticket-comentario-enviando-subtitulo">
            {{ $subtitulo ?? 'Por favor espere. Se está guardando el comentario y enviando la notificación por correo.' }}
        </div>
    </div>
</div>
