<style>
    .mv-columnas-principales { align-items: stretch; }
    .mv-columnas-principales > [class*="col-"] {
        display: flex;
        flex-direction: column;
    }
    .mv-card-articulos, .mv-card-cobranza {
        flex: 1 1 auto;
        display: flex;
        flex-direction: column;
        min-height: 0;
    }
    .mv-card-articulos .card-body,
    .mv-card-cobranza .card-body {
        flex: 1 1 auto;
        display: flex;
        flex-direction: column;
        min-height: 0;
    }
    .mv-panel-articulos {
        flex: 1 1 auto;
        max-height: 52vh;
        overflow-y: auto;
        min-height: 0;
    }
    #panel-cobranza-compacta {
        border: 1px solid #dee2e6;
        border-radius: 0.25rem;
        background: #f8f9fa;
        padding: 0.5rem;
    }
    #panel-cobranza-compacta .mv-cobranza-scroll {
        flex: 1 1 auto;
        overflow-y: auto;
        min-height: 140px;
    }
    #mv-cuenta-table th, #mv-cuenta-table td { vertical-align: middle; }
    #mv-cuenta-table .mv-cc-cuenta-wrap {
        display: flex;
        align-items: center;
        gap: 4px;
        flex-wrap: nowrap;
        min-width: 0;
    }
    #mv-cuenta-table .mv-cc-codigo { width: 72px; flex: 0 0 72px; }
    #mv-cuenta-table .mv-cc-nombre { flex: 1 1 auto; min-width: 0; }
    #mv-cuenta-table .mv-cc-monto { width: 110px; }
    #mv-medios-rapidos {
        display: inline-flex;
        flex-wrap: wrap;
        gap: 0.35rem;
        align-items: flex-start;
    }
    #mv-medios-rapidos .mv-medio-rapido {
        display: inline-flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        min-width: 72px;
        max-width: 110px;
        padding: 0.35rem 0.4rem 0.25rem;
        font-size: 0.68rem;
        line-height: 1.15;
        text-align: center;
        white-space: normal;
        word-break: break-word;
    }
    #mv-medios-rapidos .mv-medio-rapido i,
    #mv-medios-rapidos .mv-medio-rapido .gastro-icon-mercadopago {
        font-size: 1.15rem;
        margin-bottom: 0.15rem;
    }
    .gastro-icon-mercadopago {
        display: inline-block;
        width: 1.15rem;
        height: 1.15rem;
        background: url('{{ asset('assets/pages/img/ventas/gastronomia/mercadopago.svg') }}') center/contain no-repeat;
    }
    #mv-cuenta-table .consultacuentacaja i,
    #mv-cuenta-table .consultacuentacaja .gastro-icon-mercadopago {
        font-size: 1rem;
    }
    #mv-cuenta-table .consultacuentacaja .gastro-icon-mercadopago {
        width: 1rem;
        height: 1rem;
    }
    .mv-totales-resumen { font-size: 1rem; }
    .mv-totales-resumen .mv-total-diff { color: #dc3545; font-weight: normal; }
    #tabla-articulos-rendicion input.cantidad-vendida {
        text-align: right;
    }
</style>
