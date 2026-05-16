<?php

/** Genera capturas SVG estilo UI del ERP para el manual. */
declare(strict_types=1);

$dir = dirname(__DIR__, 2) . '/public/docs/manual-compras/img';
if (! is_dir($dir)) {
    mkdir($dir, 0755, true);
}

function uiFrame(string $title, string $body, string $accent = '#3c8dbc'): string
{
    return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 920 520" width="920" height="520">
  <defs>
    <linearGradient id="hdr" x1="0" y1="0" x2="1" y2="0"><stop offset="0%" stop-color="#343a40"/><stop offset="100%" stop-color="#454d55"/></linearGradient>
    <filter id="sh" x="-2%" y="-2%" width="104%" height="104%"><feDropShadow dx="0" dy="4" stdDeviation="8" flood-opacity=".12"/></filter>
  </defs>
  <rect width="920" height="520" rx="12" fill="#f4f6f9" filter="url(#sh)"/>
  <rect x="0" y="0" width="920" height="48" rx="12" fill="url(#hdr)"/>
  <rect x="0" y="36" width="920" height="12" fill="url(#hdr)"/>
  <text x="24" y="30" fill="#fff" font-family="Segoe UI, system-ui, sans-serif" font-size="15" font-weight="600">Anita ERP</text>
  <text x="120" y="30" fill="#adb5bd" font-family="Segoe UI, system-ui, sans-serif" font-size="13">/ Compras / {$title}</text>
  <rect x="16" y="64" width="200" height="440" rx="8" fill="#fff" stroke="#dee2e6"/>
  <rect x="16" y="64" width="200" height="36" rx="8" fill="{$accent}" opacity=".15"/>
  <text x="28" y="88" fill="{$accent}" font-family="Segoe UI, system-ui, sans-serif" font-size="12" font-weight="600">Menú Compras</text>
  <rect x="232" y="64" width="672" height="440" rx="8" fill="#fff" stroke="#dee2e6"/>
  {$body}
</svg>
SVG;
}

$capturas = [
    'login.svg' => uiFrame('Login', '
  <rect x="280" y="120" width="360" height="280" rx="10" fill="#fff" stroke="#dee2e6"/>
  <text x="460" y="165" text-anchor="middle" fill="#1f4e79" font-family="Segoe UI,sans-serif" font-size="22" font-weight="700">Anita ERP</text>
  <text x="460" y="192" text-anchor="middle" fill="#6c757d" font-family="Segoe UI,sans-serif" font-size="13">Inicio de Sesión</text>
  <rect x="310" y="215" width="300" height="36" rx="4" fill="#f8f9fa" stroke="#ced4da"/>
  <text x="322" y="238" fill="#868e96" font-family="Segoe UI,sans-serif" font-size="12">Usuario</text>
  <rect x="310" y="262" width="300" height="36" rx="4" fill="#f8f9fa" stroke="#ced4da"/>
  <text x="322" y="285" fill="#868e96" font-family="Segoe UI,sans-serif" font-size="12">Contraseña</text>
  <rect x="506" y="318" width="104" height="34" rx="4" fill="#007bff"/>
  <text x="558" y="340" text-anchor="middle" fill="#fff" font-family="Segoe UI,sans-serif" font-size="13" font-weight="600">Login</text>
', '#007bff'),

    'proveedor-listado.svg' => uiFrame('Proveedores', '
  <rect x="248" y="80" width="640" height="40" rx="6" fill="#17a2b8" opacity=".12"/>
  <text x="268" y="106" fill="#17a2b8" font-family="Segoe UI,sans-serif" font-size="16" font-weight="600">Proveedores</text>
  <rect x="790" y="88" width="88" height="28" rx="4" fill="#6c757d"/>
  <text x="834" y="106" text-anchor="middle" fill="#fff" font-family="Segoe UI,sans-serif" font-size="11">+ Nuevo</text>
  <rect x="248" y="132" width="640" height="28" fill="#e9ecef"/>
  <text x="260" y="150" fill="#495057" font-family="Segoe UI,sans-serif" font-size="11" font-weight="600">Código</text>
  <text x="340" y="150" fill="#495057" font-family="Segoe UI,sans-serif" font-size="11" font-weight="600">Nombre</text>
  <text x="520" y="150" fill="#495057" font-family="Segoe UI,sans-serif" font-size="11" font-weight="600">CUIT</text>
  <text x="640" y="150" fill="#495057" font-family="Segoe UI,sans-serif" font-size="11" font-weight="600">Estado</text>
  <rect x="248" y="160" width="640" height="32" fill="#fff" stroke="#f1f3f5"/>
  <text x="260" y="180" fill="#212529" font-family="Segoe UI,sans-serif" font-size="11">P-1042</text>
  <text x="340" y="180" fill="#212529" font-family="Segoe UI,sans-serif" font-size="11">Proveedor Demo S.A.</text>
  <text x="520" y="180" fill="#212529" font-family="Segoe UI,sans-serif" font-size="11">30-71234567-8</text>
  <rect x="640" y="168" width="56" height="18" rx="9" fill="#28a745" opacity=".2"/>
  <text x="668" y="181" text-anchor="middle" fill="#28a745" font-family="Segoe UI,sans-serif" font-size="10">Activo</text>
', '#17a2b8'),

    'requisicion-listado.svg' => uiFrame('Requisiciones', '
  <rect x="248" y="80" width="640" height="40" rx="6" fill="#17a2b8" opacity=".12"/>
  <text x="268" y="106" fill="#17a2b8" font-family="Segoe UI,sans-serif" font-size="16" font-weight="600">Requisiciones</text>
  <rect x="248" y="132" width="640" height="28" fill="#e9ecef"/>
  <text x="260" y="150" fill="#495057" font-family="Segoe UI,sans-serif" font-size="11" font-weight="600">Número</text>
  <text x="340" y="150" fill="#495057" font-family="Segoe UI,sans-serif" font-size="11" font-weight="600">Solicitante</text>
  <text x="500" y="150" fill="#495057" font-family="Segoe UI,sans-serif" font-size="11" font-weight="600">Estado</text>
  <text x="620" y="150" fill="#495057" font-family="Segoe UI,sans-serif" font-size="11" font-weight="600">Total</text>
  <rect x="248" y="160" width="640" height="32" fill="#fff"/>
  <text x="260" y="180" fill="#212529" font-family="Segoe UI,sans-serif" font-size="11">REQ-2026-0142</text>
  <text x="340" y="180" fill="#212529" font-family="Segoe UI,sans-serif" font-size="11">García, Ana</text>
  <rect x="500" y="168" width="72" height="18" rx="9" fill="#ffc107" opacity=".35"/>
  <text x="536" y="181" text-anchor="middle" fill="#856404" font-family="Segoe UI,sans-serif" font-size="10">EN COMPRAS</text>
  <text x="620" y="180" fill="#212529" font-family="Segoe UI,sans-serif" font-size="11">$ 1.245.800,00</text>
', '#17a2b8'),

    'ordencompra-listado.svg' => uiFrame('Órdenes de compra', '
  <rect x="248" y="80" width="640" height="40" rx="6" fill="#17a2b8" opacity=".12"/>
  <text x="268" y="106" fill="#17a2b8" font-family="Segoe UI,sans-serif" font-size="16" font-weight="600">Órdenes de compra</text>
  <rect x="248" y="132" width="640" height="28" fill="#e9ecef"/>
  <text x="260" y="150" fill="#495057" font-family="Segoe UI,sans-serif" font-size="11" font-weight="600">N° OC</text>
  <text x="360" y="150" fill="#495057" font-family="Segoe UI,sans-serif" font-size="11" font-weight="600">Proveedor</text>
  <text x="540" y="150" fill="#495057" font-family="Segoe UI,sans-serif" font-size="11" font-weight="600">Estado</text>
  <rect x="248" y="160" width="640" height="32" fill="#fff"/>
  <text x="260" y="180" fill="#212529" font-family="Segoe UI,sans-serif" font-size="11">OC-2026-0089</text>
  <text x="360" y="180" fill="#212529" font-family="Segoe UI,sans-serif" font-size="11">Proveedor Demo S.A.</text>
  <rect x="540" y="168" width="64" height="18" rx="9" fill="#28a745" opacity=".25"/>
  <text x="572" y="181" text-anchor="middle" fill="#155724" font-family="Segoe UI,sans-serif" font-size="10">APROBADA</text>
', '#17a2b8'),

    'listaprecio-proveedor.svg' => uiFrame('Listas de precio', '
  <rect x="248" y="80" width="640" height="40" rx="6" fill="#17a2b8" opacity=".12"/>
  <text x="268" y="106" fill="#17a2b8" font-family="Segoe UI,sans-serif" font-size="16" font-weight="600">Listas de precio proveedor</text>
  <rect x="248" y="132" width="640" height="28" fill="#e9ecef"/>
  <text x="260" y="150" fill="#495057" font-family="Segoe UI,sans-serif" font-size="11" font-weight="600">Proveedor</text>
  <text x="420" y="150" fill="#495057" font-family="Segoe UI,sans-serif" font-size="11" font-weight="600">Nombre lista</text>
  <text x="600" y="150" fill="#495057" font-family="Segoe UI,sans-serif" font-size="11" font-weight="600">Estado</text>
  <rect x="248" y="160" width="640" height="32" fill="#fff"/>
  <text x="260" y="180" fill="#212529" font-family="Segoe UI,sans-serif" font-size="11">Proveedor Demo S.A.</text>
  <text x="420" y="180" fill="#212529" font-family="Segoe UI,sans-serif" font-size="11">Lista Mayo 2026</text>
  <rect x="600" y="168" width="52" height="18" rx="9" fill="#28a745" opacity=".25"/>
  <text x="626" y="181" text-anchor="middle" fill="#155724" font-family="Segoe UI,sans-serif" font-size="10">ACTIVA</text>
', '#17a2b8'),

    'presupuestos-tab.svg' => uiFrame('Requisición / Presupuestos', '
  <rect x="248" y="76" width="88" height="28" rx="4" fill="#f8f9fa" stroke="#dee2e6"/>
  <text x="292" y="94" text-anchor="middle" fill="#6c757d" font-family="Segoe UI,sans-serif" font-size="11">Cabecera</text>
  <rect x="340" y="76" width="88" height="28" rx="4" fill="#f8f9fa" stroke="#dee2e6"/>
  <text x="384" y="94" text-anchor="middle" fill="#6c757d" font-family="Segoe UI,sans-serif" font-size="11">Líneas</text>
  <rect x="432" y="76" width="100" height="28" rx="4" fill="#007bff"/>
  <text x="482" y="94" text-anchor="middle" fill="#fff" font-family="Segoe UI,sans-serif" font-size="11" font-weight="600">Presupuestos</text>
  <rect x="248" y="116" width="640" height="200" rx="6" fill="#f8f9fa" stroke="#dee2e6"/>
  <text x="268" y="142" fill="#495057" font-family="Segoe UI,sans-serif" font-size="12" font-weight="600">Cotizaciones del proveedor</text>
  <rect x="268" y="158" width="600" height="24" fill="#e9ecef"/>
  <text x="280" y="174" fill="#495057" font-family="Segoe UI,sans-serif" font-size="10">Proveedor</text>
  <text x="420" y="174" fill="#495057" font-family="Segoe UI,sans-serif" font-size="10">Fecha</text>
  <text x="520" y="174" fill="#495057" font-family="Segoe UI,sans-serif" font-size="10">Total</text>
  <rect x="268" y="186" width="600" height="22" fill="#fff"/>
  <text x="280" y="201" fill="#212529" font-family="Segoe UI,sans-serif" font-size="10">Proveedor Demo S.A.</text>
  <text x="420" y="201" fill="#212529" font-family="Segoe UI,sans-serif" font-size="10">10/05/2026</text>
  <text x="520" y="201" fill="#212529" font-family="Segoe UI,sans-serif" font-size="10">$ 1.180.000</text>
', '#007bff'),

    'tablas-maestras.svg' => uiFrame('Tablas', '
  <text x="268" y="110" fill="#495057" font-family="Segoe UI,sans-serif" font-size="13">Condición de pago</text>
  <text x="268" y="138" fill="#495057" font-family="Segoe UI,sans-serif" font-size="13">Condición de compra</text>
  <text x="268" y="166" fill="#495057" font-family="Segoe UI,sans-serif" font-size="13">Condición de entrega</text>
  <text x="268" y="194" fill="#495057" font-family="Segoe UI,sans-serif" font-size="13">Retenciones impositivas</text>
  <text x="268" y="222" fill="#495057" font-family="Segoe UI,sans-serif" font-size="13">Sector legajo compra</text>
  <circle cx="248" cy="106" r="4" fill="#17a2b8"/>
  <circle cx="248" cy="134" r="4" fill="#17a2b8"/>
  <circle cx="248" cy="162" r="4" fill="#17a2b8"/>
', '#6c757d'),

    'circuito-documental.svg' => uiFrame('Circuito', '
  <rect x="270" y="130" width="100" height="44" rx="8" fill="#ffc107" opacity=".4" stroke="#ffc107"/>
  <text x="320" y="157" text-anchor="middle" fill="#856404" font-family="Segoe UI,sans-serif" font-size="10" font-weight="600">PENDIENTE</text>
  <path d="M370 152 H400" stroke="#adb5bd" stroke-width="2" marker-end="url(#ar)"/>
  <rect x="400" y="130" width="100" height="44" rx="8" fill="#17a2b8" opacity=".25" stroke="#17a2b8"/>
  <text x="450" y="157" text-anchor="middle" fill="#0c5460" font-family="Segoe UI,sans-serif" font-size="10" font-weight="600">EN COMPRAS</text>
  <path d="M500 152 H530" stroke="#adb5bd" stroke-width="2"/>
  <rect x="530" y="130" width="100" height="44" rx="8" fill="#6f42c1" opacity=".2" stroke="#6f42c1"/>
  <text x="580" y="152" text-anchor="middle" fill="#4a2c7a" font-family="Segoe UI,sans-serif" font-size="9" font-weight="600">ARBOL</text>
  <text x="580" y="165" text-anchor="middle" fill="#4a2c7a" font-family="Segoe UI,sans-serif" font-size="9" font-weight="600">APROBACIÓN</text>
  <path d="M630 152 H660" stroke="#adb5bd" stroke-width="2"/>
  <rect x="660" y="130" width="100" height="44" rx="8" fill="#28a745" opacity=".25" stroke="#28a745"/>
  <text x="710" y="157" text-anchor="middle" fill="#155724" font-family="Segoe UI,sans-serif" font-size="10" font-weight="600">APROBADA</text>
  <path d="M710 174 V210 H450 V246" stroke="#adb5bd" stroke-width="2" fill="none"/>
  <rect x="400" y="246" width="100" height="44" rx="8" fill="#007bff" opacity=".2" stroke="#007bff"/>
  <text x="450" y="268" text-anchor="middle" fill="#004085" font-family="Segoe UI,sans-serif" font-size="9" font-weight="600">ORDEN DE</text>
  <text x="450" y="281" text-anchor="middle" fill="#004085" font-family="Segoe UI,sans-serif" font-size="9" font-weight="600">COMPRA</text>
', '#17a2b8'),
];

foreach ($capturas as $file => $svg) {
    file_put_contents($dir . '/' . $file, $svg);
    $pngFile = preg_replace('/\.svg$/', '.png', $file);
    exportPngPlaceholder($dir . '/' . $pngFile, preg_replace('/\.svg$/', '', $file));
    echo "OK {$file} + {$pngFile}\n";
}

function exportPngPlaceholder(string $pngPath, string $label): void
{
    $w = 920;
    $h = 400;
    $im = imagecreatetruecolor($w, $h);
    $bg = imagecolorallocate($im, 244, 246, 249);
    $bar = imagecolorallocate($im, 52, 58, 64);
    $accent = imagecolorallocate($im, 60, 141, 188);
    $text = imagecolorallocate($im, 33, 37, 41);
    $muted = imagecolorallocate($im, 108, 117, 125);
    imagefilledrectangle($im, 0, 0, $w, $h, $bg);
    imagefilledrectangle($im, 0, 0, $w, 48, $bar);
    imagestring($im, 5, 20, 16, 'Anita ERP / Compras', imagecolorallocate($im, 255, 255, 255));
    imagefilledrectangle($im, 16, 64, 216, $h - 16, imagecolorallocate($im, 255, 255, 255));
    imagefilledrectangle($im, 232, 64, $w - 16, $h - 16, imagecolorallocate($im, 255, 255, 255));
    imagestring($im, 4, 248, 88, str_replace('-', ' ', ucfirst($label)), $accent);
    imagestring($im, 3, 248, 120, 'Vista representativa del modulo (captura de pantalla)', $muted);
    imagepng($im, $pngPath);
    imagedestroy($im);
}
