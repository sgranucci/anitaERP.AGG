@php
    $canalMail = $canalMail ?? ['habilitado' => false, 'casilla' => ''];
    $casilla = (string) ($canalMail['casilla'] ?? '');
    $carpeta = (string) ($canalMail['carpeta'] ?? 'anitaERP Facturas');
    $intervalo = (int) ($canalMail['intervalo_min'] ?? 5);
@endphp
@if (!empty($canalMail['habilitado']) && $casilla !== '')
<div class="card card-outline card-success mb-3">
    <div class="card-header py-2">
        <h3 class="card-title mb-0">
            <i class="fa fa-envelope-o"></i> Canal 2 — Enviar factura por correo
        </h3>
    </div>
    <div class="card-body">
        <p class="mb-2">
            Además del <strong>scan directo</strong> (botón «Presentar factura PDF»), el proveedor puede
            enviar el PDF a la casilla del agente Document AI. El schedule lee esa casilla cada
            {{ $intervalo }} minutos, aplica el mismo pipeline PDF+IA (OC, conceptos por centro de costo)
            y deja la precarga en esta grilla con origen <em>Mail</em>.
        </p>
        <div class="alert alert-light border mb-3">
            <div class="d-flex flex-wrap align-items-center">
                <div class="mr-3 mb-1">
                    <span class="text-muted small d-block">Casilla del agente</span>
                    <a class="h5 mb-0 text-primary" href="mailto:{{ $casilla }}?subject=Factura%20OC%20">{{ $casilla }}</a>
                </div>
                <div class="mb-1">
                    <a class="btn btn-outline-success btn-sm"
                       href="mailto:{{ $casilla }}?subject=Factura%20OC%20&body=Adjuntar%20PDF%20de%20la%20factura.%0AIndicar%20OC%20de%206%20digitos%20en%20el%20asunto%20(ej.%20OC%20222102).">
                        <i class="fa fa-paper-plane"></i> Redactar mail
                    </a>
                </div>
            </div>
            <small class="text-muted d-block mt-2">
                Bandeja/label IMAP: <code>{{ $carpeta }}</code>
                (en Gmail: mover o etiquetar el mail a ese label si no cae solo).
            </small>
        </div>
        <p class="font-weight-bold mb-1">Instrucciones para el proveedor</p>
        <ol class="mb-2 pl-3">
            <li>Adjuntar el <strong>PDF</strong> de la factura (un PDF por mail recomendado).</li>
            <li>En el asunto o cuerpo indicar la <strong>OC</strong> (ej. <code>OC 222102</code> o <code>Orden de compra 222102</code>). Si la OC está nítida en el PDF, también alcanza.</li>
            <li>Usar la palabra <strong>factura</strong> (o comprobante) en asunto/cuerpo ayuda al filtro de la casilla provisoria.</li>
            <li>No mezclar presupuestos, extractos ni otros PDFs en el mismo envío.</li>
            <li>En unos minutos la factura debería aparecer abajo con origen <strong>Mail</strong> (estado PENDIENTE o «Para revisar»).</li>
        </ol>
        <p class="text-muted small mb-0">
            Tip operativo (casilla provisoria): si el mail llega al Inbox y no al label
            <code>{{ $carpeta }}</code>, muévalo/etiquétalo ahí o cree una regla Gmail.
            El agente no procesa el Inbox personal completo a propósito.
        </p>
    </div>
</div>
@elseif (can('listar-ai-decisiones', false) || can('cargar-portal-proveedores', false))
<div class="alert alert-warning">
    <strong>Canal mail apagado o sin casilla.</strong>
    Active <code>PRECARGA_MAIL_INGESTA_HABILITADA</code> y configure
    <code>PRECARGA_MAIL_USUARIO</code> para ofrecer envío por correo al proveedor.
</div>
@endif
