@extends("theme.$theme.layout")
@section('titulo')
    Manual — Reportes definibles de sueldos
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-10">
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Manual: listados definibles de sueldos</h3>
                <div class="card-tools">
                    <a href="{{ route('reporte_sueldos_definible') }}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-reply-all"></i> Volver
                    </a>
                </div>
            </div>
            <div class="card-body">
                <h4>Qué es</h4>
                <p>
                    Equivalente ERP de Anita <code>a-listgen</code> / <code>l-listgen</code>
                    (tablas <code>listmae</code>, <code>listcol</code>, <code>listcon</code>).
                    Cada listado define columnas de campo de empleado o sumas de conceptos
                    (importe / cantidad / valor) con signo, y se ejecuta sobre una liquidación
                    o sobre el ABM de empleados.
                </p>
                <h4>Flujo habitual</h4>
                <ol>
                    <li>Importar definiciones desde Anita (dry-run y luego con tilde Ejecutar), o crear manual / desde plantilla.</li>
                    <li>Editar columnas y conceptos.</li>
                    <li>Ejecutar eligiendo liquidación, agrupación y exportación PDF/Excel/CSV.</li>
                    <li>Opcional: publicar versión, ACL por usuario, suscripciones email.</li>
                </ol>
                <h4>Mejoras respecto de Anita</h4>
                <ul>
                    <li>Export unificado y preview paginado en pantalla.</li>
                    <li>Columnas fórmula (<code>C1+C2</code>, <code>si(C1&gt;0,C2,0)</code>, <code>entre</code>).</li>
                    <li>Comparación entre dos liquidaciones (columnas Δ).</li>
                    <li>Drill desde celda numérica a líneas del recibo.</li>
                    <li>Versiones, ACL, owner, auditoría OwenIt y suscripciones con ledger de envíos.</li>
                </ul>
                <h4>Distribución y bursting</h4>
                <p>
                    Las suscripciones pueden segmentar por centro de costo, lugar de trabajo,
                    agrupamiento o <strong>empleado</strong> (un paquete por legajo al email del legajo).
                    Bursting por manager/organigrama sigue fuera de alcance (sin organigrama Workday).
                </p>
                <h4>Paridad y certificación</h4>
                <p>
                    La pantalla de paridad permite emitir un <strong>acta de certificación</strong> por liquidación/nómina.
                    Publicar un dataset de origen Anita con nómina confidencial o alerta bloqueante de paridad
                    exige certificación vigente. También:
                    <code>php artisan sueldos:paridad-reporte-definible --reporte=ID --liquidacion=ID --ejecutar --certificar</code>
                </p>
                <h4>API RaaS-light (Sanctum)</h4>
                <p>
                    Contrato OpenAPI: <a href="{{ url('/api/v1/sueldos/reportes-definibles/openapi.json') }}" target="_blank" rel="noopener">
                    /api/v1/sueldos/reportes-definibles/openapi.json</a>.
                    Incluye datasets paginados, estado de ejecución con certificación/paridad, y webhooks HMAC
                    (<code>X-Anita-Signature</code>). No incluye OData completo ni discovery RaaS Workday.
                </p>
                <h4>Límites de ecosistema</h4>
                <p>
                    Siguen como gap de ecosistema: OData completo, BIP pixel-perfect, catálogo semántico
                    HCM+Payroll+Finance y bursting por manager/organigrama.
                </p>
                <h4>Comandos</h4>
                <pre class="bg-light p-2">php artisan sueldos:importar-reportes-definibles
php artisan sueldos:importar-reportes-definibles --ejecutar
php artisan sueldos:sembrar-plantillas-reporte-definible --ejecutar
php artisan sueldos:paridad-reporte-definible --reporte=ID --liquidacion=ID --ejecutar --certificar</pre>
            </div>
        </div>
    </div>
</div>
@endsection
