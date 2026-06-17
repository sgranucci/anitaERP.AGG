<p>Adjunto reporte Excel de conciliación <strong>Rebisco (RSA)</strong>, jornada <strong>{{ $informe['fecha_jornada'] ?? '' }}</strong>.</p>

<ul>
    <li>Facturas: {{ $informe['total_facturas'] ?? 0 }}</li>
    <li>Total ERP: ${{ number_format((float) ($informe['total_erp'] ?? 0), 2, ',', '.') }}</li>
    <li>Total Anita: ${{ number_format((float) ($informe['total_anita'] ?? 0), 2, ',', '.') }}</li>
    <li>Total cobrado (medios): ${{ number_format((float) ($informe['total_cobrado'] ?? 0), 2, ',', '.') }}</li>
</ul>

<p>Hojas del Excel:</p>
<ol>
    <li><strong>Resumen</strong> — cruce ERP vs Anita por PV</li>
    <li><strong>Medios por PV</strong> — detalle de cobranzas por punto de venta</li>
    <li><strong>Medios global</strong> — totales por medio de pago</li>
</ol>

<p>Generado: {{ now()->format('d/m/Y H:i') }}</p>
