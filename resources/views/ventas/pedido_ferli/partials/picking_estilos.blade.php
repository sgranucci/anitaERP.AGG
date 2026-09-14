<style>
    #itemspedido-table thead th {
        background: #85C1E9;
        color: #17202A;
        font-weight: 600;
        vertical-align: middle;
        white-space: nowrap;
    }
    #itemspedido-table.table-sm td,
    #itemspedido-table.table-sm th {
        padding: 0.35rem 0.4rem;
        vertical-align: middle;
    }
    .picking-cell {
        min-width: 168px;
        max-width: 200px;
        background: #f8fafc;
    }
    .picking-box {
        display: flex;
        flex-direction: column;
        gap: 2px;
    }
    .picking-box .picking-lote,
    .picking-box .picking-deposito {
        width: 100%;
        font-size: 0.8rem;
    }
    .picking-box .picking-lote-grupo {
        width: 100%;
    }
    .picking-box .picking-lote-grupo .picking-lote {
        width: auto;
    }
    .picking-box .consulta-lotes-stock-picking {
        padding: 0.15rem 0.4rem;
    }
    .picking-box .guarda-picking {
        font-size: 0.75rem;
        padding: 0.15rem 0.4rem;
    }
    .picking-box .badge {
        font-size: 0.7rem;
        font-weight: 600;
    }
    tr.item-pedido.picking-row-preparado {
        background-color: #fef9e7 !important;
    }
    tr.item-pedido.picking-row-facturado {
        background-color: #eafaf1 !important;
    }
</style>
