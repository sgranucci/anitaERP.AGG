{{-- Mismos estilos base que ventas/gastronomia/cierres_turno/comprobante.blade.php --}}
<style>
    body { font-family: DejaVu Sans, Helvetica, Arial, sans-serif; font-size: 9px; color: #222; margin: 12px 16px; }
    h1 { font-size: 16px; margin: 0 0 4px 0; color: #1a1a1a; }
    h2 { font-size: 11px; margin: 12px 0 6px 0; border-bottom: 1px solid #333; padding-bottom: 3px; }
    table { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
    th, td { border: 1px solid #666; padding: 4px 6px; vertical-align: top; }
    th { background: #d4e6f1; font-weight: bold; text-align: left; }
    .cabecera-doc td { border: none !important; vertical-align: middle; }
    .cabecera-doc { width: 100%; margin-bottom: 12px; border-bottom: 2px solid #333; padding-bottom: 8px; }
    .logo { max-height: 56px; max-width: 200px; }
    .subtitulo { font-size: 10px; color: #444; margin-bottom: 8px; }
    .lbl { background: #f0f0f0; font-weight: bold; width: 28%; }
    .num { text-align: right; white-space: nowrap; }
    .muted { color: #555; font-size: 8px; }
    .bloque-obs { white-space: pre-wrap; min-height: 24px; }
    .total-grande { font-size: 12px; font-weight: bold; background: #e8f4fc; }
    .barra-acciones {
        margin-bottom: 16px;
        padding: 10px;
        background: #f4f4f4;
        border: 1px solid #ccc;
    }
    .barra-acciones button,
    .barra-acciones a.btn-link {
        margin-right: 8px;
        padding: 6px 12px;
        cursor: pointer;
        text-decoration: none;
        color: #007bff;
    }
    @media print {
        .no-print { display: none !important; }
        body { margin: 8px; }
    }
</style>
