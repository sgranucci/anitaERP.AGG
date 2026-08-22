# Manuales de usuario — capturas de pantalla

Cada manual del ERP sigue el mismo patrón para incluir capturas de las pantallas (**PNG mockups 1280×760** como estándar; **SVG** solo para diagramas de flujo/circuitos).

## Estándar de pantallas (mockups GD)

```bash
# Regenerar todas las pantallas de los manuales activos
php artisan manual:generar-mockups all

# Un manual
php artisan manual:generar-mockups gastronomia

# Solo auditar (dimensiones, faltantes, PDF-como-PNG, duplicados)
php artisan manual:generar-mockups all --qa

# Verificar que cada clave de config tiene escena (salvo diagramas)
php artisan manual:generar-mockups all --check-escenas
```

Motor: `App\Support\Manuales\ManualMockupGdSupport`  
Escenas por módulo: `App\Support\Manuales\Escenas\*Escenas`  
Inventario: `App\Support\Manuales\ManualMockupCatalogo`

Los diagramas (`flujo-*`, `circuito-*`, etc.) se conservan en SVG y **no** se sobrescriben. Si existe un PNG homónimo de un diagrama, el pipeline lo prioriza: no dejar PNG basura sobre diagramas.

## Estructura

| Manual | Config | Imágenes | Mockups |
|--------|--------|----------|---------|
| Compras | `config/manual_compras.php` | `public/docs/manual-compras/img/` | `manual:generar-mockups compras` |
| Stock (recuento) | `config/manual_stock.php` | `public/docs/manual-stock/img/` | `manual:generar-mockups stock` |
| Stock (recepción + movimientos) | `config/manual_recepcion_movstock.php` | `public/docs/manual-recepcion-movstock/img/` | `manual:generar-mockups recepcion-movstock` |
| Stock (gastronomía / fórmulas / insumos) | `config/manual_stock_gastronomia.php` | `public/docs/manual-stock-gastronomia/img/` | `manual:generar-mockups stock-gastronomia` |
| Gastronomía | `config/manual_gastronomia.php` | `public/docs/manual-gastronomia/img/` | `manual:generar-mockups gastronomia` |
| Ventas (pedidos) | `config/manual_ventas.php` | `public/docs/manual-ventas/img/` | `manual:generar-mockups ventas` |
| Canjes marketing | `config/manual_canjes_marketing.php` | `public/docs/manual-canjes-marketing/img/` | `manual:generar-mockups canjes-marketing` |
| Vending | `config/manual_vending.php` | `public/docs/manual-vending/img/` | `manual:generar-mockups vending` |
| Caja (Flash, posición, máquinas, bingo) | `config/manual_caja.php` | `public/docs/manual-caja/img/` | `manual:generar-mockups caja` |
| Sueldos (sanciones) | `config/manual_sueldos.php` | `public/docs/manual-sueldos/img/` | (diagrama SVG; sin mockup de pantallas) |
| Solicitudes de pago | `config/manual_solicitudpago.php` | `public/docs/manual-solicitudpago/img/` | `manual:generar-mockups solicitudpago` |
| Propuesta de pagos / Tesorería AP | `config/manual_propuesta_pago.php` | `public/docs/manual-propuesta-pago/img/` | `manual:generar-mockups propuesta-pago` |
| Contable (cierres/aperturas) | `config/manual_contable.php` | `public/docs/manual-contable/img/` | `manual:generar-mockups contable` |
| Contaduría (cierres de rendiciones) | `config/manual_cierres_rendiciones.php` | `public/docs/manual-cierres-rendiciones/img/` | `manual:generar-mockups cierres-rendiciones` |
| UIF | `config/manual_uif.php` | `public/docs/manual-uif/img/` | `manual:generar-mockups uif` |
| Reportes contables definibles | `config/manual_reporte_definible.php` | `public/docs/manual-reporte-definible/img/` | `manual:generar-mockups reporte-definible` |

Manual IA: sin capturas de pantalla activas (no generar mockups artificiales).

## Cómo vincular una captura a un capítulo

1. En `config/manual_*.php`, agregar entrada en `capturas`.
2. Agregar la escena en `App\Support\Manuales\Escenas\{Manual}Escenas` (o marcarla como diagrama en `ManualMockupCatalogo::clavesDiagrama`).
3. En `docs/manual-*/contenido.php`: `'captura_id' => 'mi_pantalla'`.
4. El pipeline prioriza **PNG** sobre SVG si existen ambos con el mismo basename.

## Regenerar PDF / Word / preview

```bash
php docs/manual-gastronomia/generar.php
php docs/manual-compras/generar.php
php docs/manual-stock/generar.php
php docs/manual-recepcion-movstock/generar.php
php docs/manual-stock-gastronomia/generar.php
php docs/manual-ventas/generar.php
php docs/manual-canjes-marketing/generar.php
php docs/manual-vending/generar.php
php docs/manual-caja/generar.php
php docs/manual-sueldos/generar.php
php docs/manual-solicitudpago/generar.php
php docs/manual-propuesta-pago/generar.php
php docs/manual-contable/generar.php
php docs/manual-cierres-rendiciones/generar.php
php docs/manual-uif/generar.php
php docs/manual-reporte-definible/generar.php
```

## Legacy (DomPDF + Ghostscript)

Los comandos `manual:capturar-*-interno` quedan como alternativa histórica; la calidad operativa de pantallas es la de `manual:generar-mockups`.
