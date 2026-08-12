@php
    $canalMail = $canalMail ?? ['habilitado' => false, 'casilla' => ''];
    $casilla = (string) ($canalMail['casilla'] ?? '');
    $carpeta = (string) ($canalMail['carpeta'] ?? 'INBOX');
    $intervalo = (int) ($canalMail['intervalo_min'] ?? 5);
    $ingestaActiva = ! empty($canalMail['ingesta_activa']);
@endphp
@if (!empty($canalMail['habilitado']) && $casilla !== '')
<div class="card card-outline card-success mb-3">
    <div class="card-header py-2">
        <h3 class="card-title mb-0">
            <i class="fa fa-envelope-o"></i> Canal 2 — Enviar factura por correo
            @if (! $ingestaActiva)
                <span class="badge badge-warning ml-2">Ingesta apagada</span>
            @endif
        </h3>
    </div>
    <div class="card-body">
        @if (! $ingestaActiva)
            <div class="alert alert-warning py-2">
                La casilla ya está publicada para el proveedor, pero la lectura automática
                (<code>compras:ingestar-facturas-mail</code>) está apagada hasta configurar
                <code>PRECARGA_MAIL_PASSWORD</code> y <code>PRECARGA_MAIL_INGESTA_HABILITADA=true</code>.
            </div>
        @endif
        <p class="mb-2">
            Además del <strong>scan directo</strong> (botón «Presentar factura PDF»), el proveedor puede
            enviar el PDF a <strong>facturas@grupoagg.com</strong>. El schedule lee esa casilla cada
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
                Carpeta IMAP: <code>{{ $carpeta }}</code>
            </small>
        </div>
        <p class="font-weight-bold mb-1">Instrucciones para el proveedor</p>
        <ol class="mb-2 pl-3">
            <li>Adjuntar el <strong>PDF</strong> de la factura (un PDF por mail recomendado).</li>
            <li>En el asunto o cuerpo indicar la <strong>OC</strong> (ej. <code>OC 222102</code> o <code>Orden de compra 222102</code>). Si la OC está nítida en el PDF, también alcanza.</li>
            <li>Usar la palabra <strong>factura</strong> (o comprobante) en asunto/cuerpo ayuda al filtro de candidatos.</li>
            <li>No mezclar presupuestos, extractos ni otros PDFs en el mismo envío.</li>
            <li>En unos minutos la factura debería aparecer abajo con origen <strong>Mail</strong> (estado PENDIENTE o «Para revisar»).</li>
        </ol>
        <p class="text-muted small mb-0">
            Casilla corporativa: <code>facturas@grupoagg.com</code>. Coordinar con Sistemas la clave IMAP
            y el acceso a la carpeta configurada.
        </p>
    </div>
</div>
@elseif (can('listar-ai-decisiones', false) || can('cargar-portal-proveedores', false))
<div class="alert alert-warning">
    <strong>Canal mail apagado o sin casilla.</strong>
    Active <code>PRECARGA_MAIL_INGESTA_HABILITADA</code>, configure
    <code>PRECARGA_MAIL_USUARIO=facturas@grupoagg.com</code> y la clave IMAP
    (<code>PRECARGA_MAIL_PASSWORD</code>) para ofrecer envío por correo al proveedor.
</div>
@endif
