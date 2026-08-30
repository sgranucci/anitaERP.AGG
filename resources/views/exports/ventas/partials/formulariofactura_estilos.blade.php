<style type="text/css">
    @page { margin: 8mm 8mm 8mm 8mm; }
    body { margin: 0; padding: 0; }
    table.table { width: 100%; border-collapse: collapse; }
    table.table td, table.table th { vertical-align: top; }
    table.table-sm td, table.table-sm th { padding: 3px 4px; }
    table.table-bordered, table.table-bordered td, table.table-bordered th { border: 1px solid #dee2e6; }
    table.table-striped tbody tr:nth-of-type(odd) { background-color: #f2f2f2; }
    table.borderless td, table.borderless th { border: 0 !important; }
    .page {
        background: white;
        box-sizing: border-box;
    }
    .factura-pagina {
        page-break-inside: auto;
    }
    .salto-pagina {
        page-break-before: always;
    }
    .text-center { text-align: center; }
    .text-right { text-align: right; }
    .factura-cabecera td { font-size: 13px; padding: 2px 4px; }
    .factura-empresa-nombre { font-size: 16px; }
    .factura-empresa-datos { margin: 2px 0 0 0; font-size: 12px; line-height: 1.2; }
    .factura-cabecera-comprobante { text-align: right; font-size: 16px; }
    .factura-cabecera-comprobante p { margin: 2px 0 0 0; font-size: 12px; }
    .factura-cabecera-letra { text-align: center; width: 80px; }
    .factura-cabecera-letra-remito { width: 170px; }
    .factura-codigo-tipo { font-size: 11px; font-weight: bold; }
    .factura-bloque-cliente-admin td { font-size: 13px; padding: 2px 4px; }
    .factura-bloque-cliente-admin p { margin: 2px 0 0 0; line-height: 1.2; }
    .factura-cliente-der { text-align: right; }
    .factura-continua { font-size: 11px; text-align: right; margin: 4px 0 0 0; }
    .factura-pie-bloque { page-break-inside: auto; page-break-before: avoid; }
    table.tabla-totales-importes { page-break-inside: avoid; page-break-before: avoid; }
    .factura-pie-cai, .factura-pie-cae { font-size: 13px; text-align: right; white-space: nowrap; }
    .factura-valor-asegurado {
        font-size: 13px;
        text-align: right;
        margin: 8px 0 4px 0;
        font-weight: bold;
    }
    .factura-pie-leyendas { font-size: 9px; }
    .factura-leyenda { font-size: 11px; margin: 4px 0 0 0; }
    table.tabla-items-factura {
        border-collapse: collapse;
        width: 100%;
        font-size: 12px;
        margin: 4px 0 0 0;
    }
    table.tabla-items-factura thead { display: table-row-group; }
    table.tabla-items-factura tr:nth-child(even) { background-color: #f2f2f2; }
    table.tabla-items-factura tr.tabla-items-head td {
        font-weight: bold;
        background-color: #e8e8e8;
    }
    table.tabla-items-factura tr.fila-totales-items td {
        background-color: #e9ecef;
        border: 1px solid #dee2e6;
    }
    .factura-totales-wrap {
        width: 100%;
        text-align: right;
        margin-top: 4px;
    }
    table.tabla-totales-importes {
        width: auto !important;
        max-width: 48%;
        margin-left: auto;
        border-collapse: collapse;
        font-size: 16px;
    }
    table.tabla-totales-importes td,
    table.tabla-totales-importes th {
        padding: 3px 8px;
        vertical-align: middle;
    }
    table.tabla-totales-importes tr.fila-total-final td {
        background-color: #e9ecef;
        border: 1px solid #dee2e6;
    }
    .factura-letra-caja {
        border: 1px solid #000;
        width: 42px;
        height: 42px;
        text-align: center;
        vertical-align: middle;
        font-size: 22px;
        font-weight: bold;
        line-height: 42px;
        margin: 0 auto 2px auto;
    }
    .factura-pie-fiscal td {
        vertical-align: top;
        padding: 4px 6px;
    }
    .factura-pie-qr {
        width: 100px;
        vertical-align: bottom;
        padding-right: 8px;
    }
    table.factura-bloque-cliente-admin thead th,
    table.factura-remito-caja-admin th {
        border: none !important;
    }
    table.factura-bloque-cliente-admin thead th {
        padding: 3px 4px;
        vertical-align: top;
    }
    table.factura-bloque-cliente-admin p,
    table.factura-bloque-cliente-admin strong {
        margin: 0;
        padding: 0;
        line-height: 1.25;
    }
    table.factura-cabecera-admin tr.factura-cabecera-admin-linea th {
        border: none !important;
        border-bottom: 1px solid #000 !important;
        padding: 0;
        height: 0;
        line-height: 0;
        font-size: 0;
    }
    table.factura-linea-cliente-admin {
        width: 100%;
        border-collapse: collapse;
        margin: 0;
    }
    table.factura-linea-cliente-admin tr.factura-linea-cliente-admin-fila th {
        border: none !important;
        border-bottom: 1px solid #000 !important;
        padding: 0;
        height: 0;
        line-height: 0;
        font-size: 0;
    }
    table.factura-remito-caja-admin {
        width: 100%;
        border-collapse: collapse;
        margin: 0;
        border-top: 1px solid #000;
        border-bottom: 1px solid #000;
    }
    table.factura-remito-caja-admin th {
        padding: 3px 4px;
        font-weight: normal;
        font-size: 16px;
        line-height: 1.25;
    }
    table.tabla-items-factura.factura-items-debajo-remito {
        margin-top: 6px !important;
    }
    table.tabla-items-factura.factura-items-debajo-cliente {
        margin-top: 5px !important;
    }
    .factura-cuerpo-sin-gap {
        margin: 0;
        padding: 0;
    }
    .factura-cuerpo-sin-gap table.table {
        margin-bottom: 0 !important;
    }
    table.factura-cabecera-admin thead th {
        border: none !important;
    }
    .factura-remito-no-valido {
        font-size: 13px;
        font-weight: bold;
        color: #1a1a1a;
        margin: 8px 0 0 0;
        line-height: 1.25;
        text-align: center;
    }
    table.factura-remito-pie-grid {
        width: 100%;
        border-collapse: collapse;
        margin-top: 4px;
    }
    table.factura-remito-pie-grid > tbody > tr > td {
        vertical-align: top;
        padding: 0;
        border: none;
    }
    .factura-remito-pie-izq { width: 52%; padding-right: 10px !important; }
    .factura-remito-pie-der { width: 48%; }
</style>
