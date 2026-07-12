@include('caja.rendiciongastronomia.partials.estilos_comprobante_pdf')
<style>
    .bingo-recaudacion-origen {
        margin: 10px 0 12px 0;
        padding: 10px 12px;
        background: #e8f4fc;
        border: 2px solid #1a5276;
        text-align: center;
    }
    .bingo-recaudacion-origen .etiqueta {
        font-size: 9px;
        color: #1a5276;
        display: block;
        margin-bottom: 4px;
    }
    .bingo-recaudacion-origen .valor {
        font-size: 16px;
        font-weight: bold;
        color: #17202A;
    }
    .tabla-cuenta thead th { background: #85C1E9; color: #17202A; }
    .fila-origen td { background: #f4f9fd; font-weight: bold; }
    .fila-saldo-final td { background: #d5e8d4; font-weight: bold; }
</style>
