<style>
    #rendicion-estacionamiento-app .gastro-totales-panel { font-size: 0.95rem; }
    #rendicion-estacionamiento-app .gastro-totales-monto { font-size: 1.1rem; line-height: 1.3; }
    #rendicion-estacionamiento-app .gastro-totales-leyenda { font-size: 0.9rem !important; }
    #rendicion-estacionamiento-app .gastro-totales-tabla { font-size: 0.9rem; }
    #rendicion-estacionamiento-app .gastro-totales-tabla th,
    #rendicion-estacionamiento-app .gastro-totales-tabla td { padding: 0.45rem 0.6rem; vertical-align: middle; }
    #rendicion-estacionamiento-app .gastro-mozos-lista .card-header { background: #e8f4fc !important; }
    #rendicion-estacionamiento-app #tabla-movimientos tfoot .gastro-totales-monto { font-size: 1rem; }
    #rendicion-estacionamiento-app #tabla-movimientos tr.gastro-rendicion-fila-efectivo { background: #f0f9ff; }
    #rendicion-estacionamiento-app #tabla-movimientos tr.gastro-rendicion-fila-efectivo td:first-child { border-left: 3px solid #17a2b8; }
    #rendicion-estacionamiento-app #tabla-movimientos .gastro-col-esperado { width: 8.5rem; min-width: 8.5rem; }
    #rendicion-estacionamiento-app #tabla-movimientos .gastro-col-monto { width: 10.5rem; min-width: 10.5rem; }
    #rendicion-estacionamiento-app #tabla-movimientos .gastro-col-cotiz {
        width: 5.75rem;
        min-width: 5.75rem;
        max-width: 5.75rem;
        text-align: right;
        white-space: nowrap;
    }
    #rendicion-estacionamiento-app #tabla-movimientos thead .gastro-col-cotiz { padding-right: 0.5rem; }
    #rendicion-estacionamiento-app #tabla-movimientos tbody td.gastro-col-esperado,
    #rendicion-estacionamiento-app #tabla-movimientos tbody td.gastro-col-monto,
    #rendicion-estacionamiento-app #tabla-movimientos tbody td.gastro-col-cotiz { vertical-align: top; }
    #rendicion-estacionamiento-app #tabla-movimientos .gastro-celda-numero {
        display: flex;
        flex-direction: column;
        align-items: flex-end;
        width: 100%;
    }
    #rendicion-estacionamiento-app #tabla-movimientos .gastro-celda-numero .form-control-sm { width: 100%; }
    #rendicion-estacionamiento-app #tabla-movimientos .gastro-col-monto .gastro-celda-numero .form-control-sm { max-width: 9.75rem; }
    #rendicion-estacionamiento-app #tabla-movimientos .gastro-col-cotiz .gastro-celda-numero .form-control-sm,
    #rendicion-estacionamiento-app #tabla-movimientos .gastro-col-cotiz .gastro-cotiz-valor { max-width: 5.25rem; }
    #rendicion-estacionamiento-app #tabla-movimientos .gastro-col-cotiz .gastro-cotiz-valor {
        display: inline-block;
        text-align: right;
        padding: 0.25rem 0.35rem 0.25rem 0;
        font-variant-numeric: tabular-nums;
    }
    #rendicion-estacionamiento-app .gastro-rendicion-esperado-efectivo { font-weight: 600; color: #0c5460; }
    #rendicion-estacionamiento-app .gastro-rendicion-esperado-medio { color: #495057; }
    #rendicion-estacionamiento-app .gastro-campo-auto-actualizado {
        background-color: #fff3cd !important;
        transition: background-color 0.3s ease;
    }
    #rendicion-estacionamiento-app .rendicion-informe-z-tabla { font-size: 0.88rem; }
    #rendicion-estacionamiento-app .rendicion-informe-z-tabla th,
    #rendicion-estacionamiento-app .rendicion-informe-z-tabla td { padding: 0.35rem 0.5rem; }
    #rendicion-estacionamiento-app tr.rendicion-informe-z-diff { background: #fdecea; }
    #rendicion-estacionamiento-app .rendicion-numeracion-pv { font-size: 0.88rem; }
    #rendicion-estacionamiento-app .rendicion-numeracion-pv th,
    #rendicion-estacionamiento-app .rendicion-numeracion-pv td { padding: 0.35rem 0.5rem; vertical-align: middle; }
    #form-rendicion-estacionamiento #bloque-verificacion-footer { font-size: 0.95rem; }
    #form-rendicion-estacionamiento button[type="submit"]:disabled { cursor: not-allowed; opacity: 0.65; }
    #alert-errores-rendicion-estacionamiento,
    #alerta-flash-errores-rendicion { font-size: 0.95rem; }
    #alert-errores-rendicion-estacionamiento .js-contenido-errores-rendicion,
    #alerta-flash-errores-rendicion ul { white-space: pre-wrap; word-break: break-word; }
</style>
