<?php

/**
 * Genera PDF del diagrama ER del módulo Gastronomía (adjunto para mail).
 * Ejecutar: php docs/gastronomia/generar_diagrama_er.php
 */

declare(strict_types=1);

$root = dirname(__DIR__, 2);
require $root . '/vendor/autoload.php';

$app = require $root . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Barryvdh\DomPDF\Facade\Pdf;

$outDir = __DIR__;
$baseName = 'Diagrama_ER_Modulo_Gastronomia';
$pdfPath = $outDir . '/' . $baseName . '.pdf';
$htmlPath = $outDir . '/' . $baseName . '_preview.html';

$fecha = date('d/m/Y');

$tablas = [
    ['Layout', 'ubicaciones_gastronomia', 'Sectores/salones por empresa', 'empresa_id → empresa'],
    ['Layout', 'mesa_gastronomia', 'Mesas por ubicación', 'ubicacion_id, empresa_id'],
    ['Layout', 'mozo_gastronomia', 'Mozos / camareros', 'empresa_id → empresa'],
    ['Layout', 'descuento_gastronomia', 'Descuentos e invitaciones', 'cliente_id → cliente (opc.)'],
    ['Layout', 'turno_gastronomia', 'Turnos horarios maestros', 'empresa_id → empresa'],
    ['Comandas', 'area_comanda_gastronomia', 'Áreas impresión comanda', 'empresa_id → empresa'],
    ['Comandas', 'subcategoria_area_comanda', 'N:M subcategoría ↔ área', 'subcategoria_id, area_comanda_gastronomia_id'],
    ['Config', 'configuracion_puntoventa_gastronomia', 'Terminal por PC', 'empresa, puntoventa, ubicacion, salida, listaprecio, depmae, tipotransaccion'],
    ['Config', 'totem_waitry_gastronomia', 'Tótems Waitry', 'empresa_id, ubicacion_id (UK compuesto)'],
    ['Config', 'gastronomia_cierre_jornada_config', 'Cuentas contables cierre jornada', 'empresa_id (UK), puntoventa_id, cuentas contables'],
    ['Jornada', 'jornada_gastronomia', 'Día operativo', 'empresa_id, usuario_apertura/cierre'],
    ['Jornada', 'turno_operativo_gastronomia', 'Turno habilitado por terminal', 'jornada, turno, config_pv, usuarios'],
    ['Jornada', 'cierre_parcial_turno_gastronomia', 'Cierres X parciales', 'turno_operativo_gastronomia_id (cascade)'],
    ['Jornada', 'cierre_totem_jornada_gastronomia', 'Cierre tickets totem', 'jornada_gastronomia_id (UK)'],
    ['Jornada', 'gastronomia_cierre_jornada_proceso_snapshot', 'Snapshot contable', 'jornada_gastronomia_id (UK)'],
    ['Operación', 'cuenta_gastronomia', 'Mesa o cuenta libre', 'mesa, mozo, cliente, descuento, config_pv, venta'],
    ['Operación', 'cuenta_gastronomia_linea', 'Ítems de la cuenta', 'cuenta_gastronomia_id (cascade), articulo_id'],
    ['Operación', 'venta_gastronomia_emision', 'Extensión 1:1 de venta', 'venta_id (PK), cuenta_gastronomia_id, config_pv'],
    ['Operación', 'waitry_comanda_envio', 'Cola envío comandas Waitry', 'venta_id (UK), cuenta_gastronomia_id'],
    ['Canjes', 'categoriafidelidad_gastronomia', 'Categorías fidelidad Wigos', '—'],
    ['Canjes', 'categoriafidelidad_articulo_gastronomia', 'Artículos canjeables', 'categoriafidelidad_id, articulo_id'],
    ['Canjes', 'categoriafidelidad_entrega_gastronomia', 'Entregas fidelidad', 'categoriafidelidad_id, articulo_id, venta_id'],
    ['Canjes', 'tickettarjeta_gastronomia', 'Canje ticket tarjeta CTG', 'venta_id, usuario_id'],
    ['Canjes', 'ticketcanje_gastronomia', 'Canje premio cupón', 'articulo_id, mozo_id, venta_id, usuariocanje_id'],
    ['Caja', 'rendicion_gastronomia_caja', 'Rendición turno o jornada', 'turno_operativo, jornada (UK), cierre_totem, caja, puntoventa'],
    ['Caja', 'rendicion_gastronomia_movimiento_caja', 'Medios de cobro', 'rendicion_gastronomia_caja_id, cuentacaja_id'],
    ['Caja', 'rendicion_gastronomia_secuencia_empresa', 'Secuencia nro Anita', 'empresa_id (PK)'],
];

$relaciones = [
    ['ubicaciones_gastronomia', 'mesa_gastronomia', '1 : N', 'ubicacion_id'],
    ['ubicaciones_gastronomia', 'configuracion_puntoventa_gastronomia', '1 : 0..N', 'ubicacion_id'],
    ['empresa', 'totem_waitry_gastronomia + ubicacion', '1 : 0..1 por ubicación', 'UK (empresa_id, ubicacion_id)'],
    ['subcategoria', 'area_comanda_gastronomia', 'N : M', 'subcategoria_area_comanda'],
    ['jornada_gastronomia', 'turno_operativo_gastronomia', '1 : N', 'jornada_gastronomia_id'],
    ['turno_operativo_gastronomia', 'cierre_parcial_turno_gastronomia', '1 : N', 'turno_operativo_gastronomia_id'],
    ['jornada_gastronomia', 'cierre_totem_jornada_gastronomia', '1 : 0..1', 'jornada_gastronomia_id UK'],
    ['jornada_gastronomia', 'gastronomia_cierre_jornada_proceso_snapshot', '1 : 0..1', 'jornada_gastronomia_id UK'],
    ['cuenta_gastronomia', 'cuenta_gastronomia_linea', '1 : N', 'cuenta_gastronomia_id cascade'],
    ['venta', 'venta_gastronomia_emision', '1 : 1', 'venta_id PK'],
    ['venta', 'waitry_comanda_envio', '1 : 0..1', 'venta_id UK'],
    ['jornada_gastronomia', 'rendicion_gastronomia_caja (tipo jornada)', '1 : 0..1', 'jornada_gastronomia_id UK'],
    ['turno_operativo_gastronomia', 'rendicion_gastronomia_caja (tipo turno)', '1 : N', 'turno_operativo_gastronomia_id'],
    ['rendicion_gastronomia_caja', 'rendicion_gastronomia_movimiento_caja', '1 : N', 'rendicion_gastronomia_caja_id'],
];

$externas = [
    'empresa', 'cliente', 'articulo', 'venta', 'usuario', 'puntoventa', 'salida',
    'depmae', 'listaprecio', 'tipotransaccion', 'tipotransaccion_caja', 'tipodocumento',
    'subcategoria', 'caja', 'cuentacaja',
];

$html = <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<style>
@page { margin: 18mm 15mm; }
body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #17202A; line-height: 1.35; }
h1 { color: #1F4E79; font-size: 20px; margin: 0 0 4px; text-align: center; }
h2 { color: #1F4E79; font-size: 13px; margin: 18px 0 6px; border-bottom: 1.5px solid #2E75B6; padding-bottom: 4px; }
.sub { color: #2E75B6; font-size: 12px; text-align: center; margin-bottom: 6px; }
.meta { color: #666; font-size: 9px; text-align: center; margin-bottom: 20px; }
.flow { width: 100%; border-collapse: collapse; margin: 10px 0 16px; }
.flow td { border: 1px solid #2E75B6; padding: 6px 4px; text-align: center; font-size: 8px; background: #eaf2f8; vertical-align: middle; }
.flow .arrow { border: none; background: transparent; font-size: 12px; color: #2E75B6; width: 18px; }
table.data { width: 100%; border-collapse: collapse; margin-top: 6px; }
table.data th { background: #85C1E9; color: #17202A; text-align: left; padding: 5px 6px; border: 1px solid #ccc; font-size: 8.5px; }
table.data td { padding: 4px 6px; border: 1px solid #ccc; vertical-align: top; font-size: 8px; }
table.data tr:nth-child(even) td { background: #f5f5f5; }
code { font-family: DejaVu Sans Mono, monospace; font-size: 7.5px; }
.box { border: 1px solid #ccc; background: #f8fafc; padding: 8px; margin: 8px 0; font-size: 8px; }
.page-break { page-break-before: always; }
.footer { text-align: center; color: #888; font-size: 7px; margin-top: 16px; }
</style>
</head>
<body>

<h1>Modelo de datos — Módulo Gastronomía</h1>
<p class="sub">AnitaERP · Diagrama entidad-relación</p>
<p class="meta">27 tablas del módulo · Generado: {$fecha}<br>Namespace: App\\Models\\Ventas\\*Gastronomia* · Caja: RendicionGastronomia*</p>

<h2>Flujo operativo simplificado</h2>
<table class="flow">
<tr>
<td><strong>1. Abrir jornada</strong><br>jornada_gastronomia</td>
<td class="arrow">→</td>
<td><strong>2. Habilitar turno</strong><br>turno_operativo_gastronomia</td>
<td class="arrow">→</td>
<td><strong>3. Cuentas</strong><br>cuenta_gastronomia + líneas</td>
<td class="arrow">→</td>
<td><strong>4. Facturar</strong><br>venta + venta_gastronomia_emision</td>
<td class="arrow">→</td>
<td><strong>5. Cierre / rendición</strong><br>rendicion_gastronomia_caja</td>
</tr>
</table>

<h2>Grupos funcionales</h2>
<div class="box">
<strong>Maestros / layout:</strong> ubicaciones, mesas, mozos, descuentos, turnos horarios, áreas comanda<br>
<strong>Configuración:</strong> terminal PV, tótems Waitry, config contable cierre jornada<br>
<strong>Jornada:</strong> día operativo, turnos por PC, cierres parciales, cierre totem, snapshot contable<br>
<strong>Operación:</strong> cuenta (mesa/libre), líneas, emisión venta, cola Waitry<br>
<strong>Canjes:</strong> fidelidad Wigos, premio cupón, ticket tarjeta CTG<br>
<strong>Caja:</strong> rendición Anita, movimientos por cuenta de caja, secuencia por empresa
</div>

<h2>Catálogo de tablas (27)</h2>
<table class="data">
<thead><tr><th>Grupo</th><th>Tabla</th><th>Descripción</th><th>FK principales</th></tr></thead>
<tbody>
HTML;

foreach ($tablas as [$grupo, $tabla, $desc, $fks]) {
    $html .= '<tr><td>' . e($grupo) . '</td><td><code>' . e($tabla) . '</code></td><td>' . e($desc) . '</td><td>' . e($fks) . '</td></tr>';
}

$html .= <<<HTML
</tbody>
</table>

<div class="page-break"></div>

<h2>Relaciones principales</h2>
<table class="data">
<thead><tr><th>Desde</th><th>Hacia</th><th>Cardinalidad</th><th>Campo / nota</th></tr></thead>
<tbody>
HTML;

foreach ($relaciones as [$desde, $hacia, $card, $campo]) {
    $html .= '<tr><td><code>' . e($desde) . '</code></td><td><code>' . e($hacia) . '</code></td><td>' . e($card) . '</td><td>' . e($campo) . '</td></tr>';
}

$html .= <<<HTML
</tbody>
</table>

<h2>Diagrama ER — Maestros y layout</h2>
<div class="box">
<code>empresa</code> 1──N <code>ubicaciones_gastronomia</code> 1──N <code>mesa_gastronomia</code><br>
<code>empresa</code> 1──N <code>mozo_gastronomia</code> · <code>turno_gastronomia</code> · <code>area_comanda_gastronomia</code><br>
<code>ubicaciones_gastronomia</code> 1──0..1 <code>totem_waitry_gastronomia</code> (UK empresa+ubicación)<br>
<code>subcategoria</code> N──M <code>area_comanda_gastronomia</code> vía <code>subcategoria_area_comanda</code><br>
<code>cliente</code> 0..1──N <code>descuento_gastronomia</code>
</div>

<h2>Diagrama ER — Configuración terminal</h2>
<div class="box">
<code>configuracion_puntoventa_gastronomia</code> → <code>puntoventa</code> (CAE + CAEA), <code>ubicaciones_gastronomia</code>,<br>
<code>salida</code> (comanda + factura), <code>listaprecio</code>, <code>depmae</code> (venta + insumos),<br>
<code>tipotransaccion</code> (factura + NC), <code>tipotransaccion_caja</code><br>
<code>empresa</code> 1──1 <code>gastronomia_cierre_jornada_config</code> (cuentas contables, puntoventa_id)
</div>

<h2>Diagrama ER — Jornada y turnos</h2>
<div class="box">
<code>jornada_gastronomia</code> 1──N <code>turno_operativo_gastronomia</code> N──1 <code>turno_gastronomia</code><br>
<code>turno_operativo_gastronomia</code> N──1 <code>configuracion_puntoventa_gastronomia</code><br>
<code>turno_operativo_gastronomia</code> 1──N <code>cierre_parcial_turno_gastronomia</code><br>
<code>jornada_gastronomia</code> 1──0..1 <code>cierre_totem_jornada_gastronomia</code><br>
<code>jornada_gastronomia</code> 1──0..1 <code>gastronomia_cierre_jornada_proceso_snapshot</code>
</div>

<div class="page-break"></div>

<h2>Diagrama ER — Operación y facturación</h2>
<div class="box">
<code>cuenta_gastronomia</code> (tipo mesa|cuenta, estado abierta|cerrada|facturada)<br>
&nbsp;&nbsp;→ <code>mesa_gastronomia</code>, <code>mozo_gastronomia</code>, <code>cliente</code> (factura + interno descuento),<br>
&nbsp;&nbsp;&nbsp;&nbsp;<code>descuento_gastronomia</code>, <code>configuracion_puntoventa_gastronomia</code>, <code>venta</code><br>
<code>cuenta_gastronomia</code> 1──N <code>cuenta_gastronomia_linea</code> N──1 <code>articulo</code><br>
<code>venta</code> 1──1 <code>venta_gastronomia_emision</code> N──1 <code>cuenta_gastronomia</code><br>
<code>venta</code> 1──0..1 <code>waitry_comanda_envio</code>
</div>

<h2>Diagrama ER — Canjes y fidelidad</h2>
<div class="box">
<code>categoriafidelidad_gastronomia</code> 1──N <code>categoriafidelidad_articulo_gastronomia</code> N──1 <code>articulo</code><br>
<code>categoriafidelidad_gastronomia</code> 1──N <code>categoriafidelidad_entrega_gastronomia</code> → <code>venta</code><br>
<code>venta</code> 1──N <code>tickettarjeta_gastronomia</code> (CTG cobranza)<br>
<code>venta</code> 1──N <code>ticketcanje_gastronomia</code> → <code>mozo_gastronomia</code>, <code>articulo</code>
</div>

<h2>Diagrama ER — Rendición caja</h2>
<div class="box">
<code>rendicion_gastronomia_caja</code> (tipo turno|jornada)<br>
&nbsp;&nbsp;→ <code>turno_operativo_gastronomia</code> o <code>jornada_gastronomia</code> (UK),<br>
&nbsp;&nbsp;&nbsp;&nbsp;<code>cierre_totem_jornada_gastronomia</code>, <code>caja</code>, <code>puntoventa</code> (CAE/CAEA)<br>
<code>rendicion_gastronomia_caja</code> 1──N <code>rendicion_gastronomia_movimiento_caja</code> N──1 <code>cuentacaja</code><br>
<code>empresa</code> 1──1 <code>rendicion_gastronomia_secuencia_empresa</code>
</div>

<h2>Tablas externas referenciadas</h2>
<p style="font-size:8px;">Estas tablas pertenecen a otros módulos del ERP pero son FK del módulo gastronomía:</p>
<p style="font-size:8px;"><code>
HTML;

$html .= e(implode('</code> · <code>', $externas));

$html .= <<<HTML
</code></p>

<p class="footer">AnitaERP — Diagrama ER Módulo Gastronomía — Documento para uso interno</p>
</body>
</html>
HTML;

file_put_contents($htmlPath, $html);

Pdf::loadHTML($html)
    ->setPaper('a4', 'portrait')
    ->save($pdfPath);

echo "Generado:\n";
echo "  PDF:  {$pdfPath}\n";
echo "  HTML: {$htmlPath}\n";
echo "  (interactivo Mermaid): " . __DIR__ . "/diagrama-er-gastronomia.html\n";

function e(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}
