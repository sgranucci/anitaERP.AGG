<style>
    #requisicion-sala-banner-grabando.requisicion-sala-grabando-overlay {
        position: fixed;
        top: 0;
        right: 0;
        bottom: 0;
        left: 0;
        z-index: 2050;
        display: none;
        align-items: center;
        justify-content: center;
        padding: 1rem;
        background: rgba(0, 0, 0, 0.2);
    }
    #requisicion-sala-banner-grabando.requisicion-sala-grabando-overlay.is-visible {
        display: flex;
    }
    .requisicion-sala-grabando-banner {
        max-width: 32rem;
        width: 100%;
        border: 2px solid #ffc107;
        text-align: center;
    }
    .requisicion-sala-grabando-spinner-wrap {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 3rem;
        height: 3rem;
        margin: 0 auto 0.75rem;
    }
    .requisicion-sala-grabando-spinner-wrap .spinner-border {
        width: 2.5rem;
        height: 2.5rem;
    }
</style>
