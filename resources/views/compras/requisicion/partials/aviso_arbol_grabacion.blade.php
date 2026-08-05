{{-- Aviso árbol de aprobación: banner central + franja compacta en el formulario --}}
<style>
    #requisicion-aviso-arbol-overlay.requisicion-aviso-arbol-overlay {
        position: fixed;
        inset: 0;
        z-index: 2050;
        display: none;
        align-items: center;
        justify-content: center;
        padding: 1.25rem;
        background: rgba(0, 0, 0, 0.45);
    }
    #requisicion-aviso-arbol-overlay.requisicion-aviso-arbol-overlay.is-visible {
        display: flex;
    }
    .requisicion-aviso-arbol-overlay-card {
        max-width: 34rem;
        width: 100%;
        border-width: 2px;
        text-align: center;
    }
    .requisicion-aviso-arbol-overlay-card.is-danger {
        border-color: #dc3545;
        background: #fff5f5;
    }
    .requisicion-aviso-arbol-overlay-card.is-warning {
        border-color: #ffc107;
        background: #fffbeb;
    }
    .requisicion-aviso-arbol-overlay-card.is-loading {
        border-color: #6c757d;
        background: #f8f9fa;
    }
    .requisicion-aviso-arbol-icon-wrap {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 3.25rem;
        height: 3.25rem;
        margin: 0 auto 0.85rem;
        border-radius: 50%;
    }
    .requisicion-aviso-arbol-overlay-card.is-danger .requisicion-aviso-arbol-icon-wrap {
        background: rgba(220, 53, 69, 0.12);
        color: #c82333;
    }
    .requisicion-aviso-arbol-overlay-card.is-warning .requisicion-aviso-arbol-icon-wrap {
        background: rgba(255, 193, 7, 0.2);
        color: #856404;
    }
    .requisicion-aviso-arbol-overlay-card.is-loading .requisicion-aviso-arbol-icon-wrap {
        background: rgba(108, 117, 125, 0.12);
        color: #495057;
    }
    .requisicion-aviso-arbol-strip {
        border-width: 1px 1px 1px 4px;
        border-style: solid;
        border-radius: 0.35rem;
    }
    .requisicion-aviso-arbol-strip.is-danger {
        border-color: #f5c6cb;
        border-left-color: #dc3545;
        background: #fff5f5;
        color: #721c24;
    }
    .requisicion-aviso-arbol-strip.is-warning {
        border-color: #ffeeba;
        border-left-color: #ffc107;
        background: #fffbeb;
        color: #856404;
    }
    .requisicion-aviso-arbol-strip.is-loading {
        border-color: #dee2e6;
        border-left-color: #6c757d;
        background: #f8f9fa;
        color: #495057;
    }
</style>

{{-- Franja compacta bajo el header del formulario (queda visible tras cerrar el overlay) --}}
<div id="requisicion-aviso-arbol-grabacion"
     class="requisicion-aviso-arbol-strip d-none mb-3 px-3 py-2"
     role="alert"
     aria-live="polite">
    <div class="d-flex align-items-start">
        <span id="requisicion-aviso-arbol-spinner" class="fa fa-spinner fa-spin mr-2 mt-1" style="display:none;" aria-hidden="true"></span>
        <span class="fa fa-sitemap mr-2 mt-1 requisicion-aviso-arbol-strip-icon" aria-hidden="true"></span>
        <div class="flex-grow-1">
            <strong class="d-block mb-1 requisicion-aviso-arbol-strip-titulo">Árbol de aprobación</strong>
            <span class="texto"></span>
        </div>
        <button type="button"
                class="btn btn-sm btn-link text-muted p-0 ml-2 requisicion-aviso-arbol-strip-reabrir"
                title="Ver aviso completo"
                aria-label="Ver aviso completo">
            <i class="fa fa-expand" aria-hidden="true"></i>
        </button>
    </div>
</div>

{{-- Banner central (overlay) --}}
<div id="requisicion-aviso-arbol-overlay"
     class="requisicion-aviso-arbol-overlay"
     role="alertdialog"
     aria-modal="true"
     aria-labelledby="requisicion-aviso-arbol-overlay-titulo"
     aria-describedby="requisicion-aviso-arbol-overlay-texto"
     aria-hidden="true">
    <div class="alert shadow-lg mb-0 px-4 py-4 requisicion-aviso-arbol-overlay-card is-warning">
        <div class="requisicion-aviso-arbol-icon-wrap" aria-hidden="true">
            <i class="fa fa-sitemap fa-lg requisicion-aviso-arbol-overlay-icon"></i>
            <span class="fa fa-spinner fa-spin fa-lg requisicion-aviso-arbol-overlay-spinner d-none"></span>
        </div>
        <strong id="requisicion-aviso-arbol-overlay-titulo" class="d-block mb-2 h5 text-dark">
            Árbol de aprobación
        </strong>
        <p id="requisicion-aviso-arbol-overlay-texto" class="mb-3 text-dark texto"></p>
        <button type="button"
                id="requisicion-aviso-arbol-overlay-cerrar"
                class="btn btn-outline-secondary btn-sm px-4">
            Entendido
        </button>
    </div>
</div>
