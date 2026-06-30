<style>
    #requisicion-banner-enviando-arbol.requisicion-enviando-arbol-overlay {
        position: fixed;
        top: 0;
        right: 0;
        bottom: 0;
        left: 0;
        z-index: 2000;
        display: none;
        align-items: center;
        justify-content: center;
        padding: 1rem;
        background: rgba(0, 0, 0, 0.2);
    }
    #requisicion-banner-enviando-arbol.requisicion-enviando-arbol-overlay.is-visible {
        display: flex;
    }
    .requisicion-enviando-arbol-banner {
        max-width: 32rem;
        width: 100%;
        border: 2px solid #28a745;
        text-align: center;
    }
    .requisicion-enviando-arbol-spinner-wrap {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 3rem;
        height: 3rem;
        margin: 0 auto 0.75rem;
    }
    .requisicion-enviando-arbol-spinner-wrap .spinner-border {
        width: 2.5rem;
        height: 2.5rem;
    }
</style>
