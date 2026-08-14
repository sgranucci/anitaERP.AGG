# Manuales de usuario — capturas de pantalla

Cada manual del ERP sigue el mismo patrón para incluir **capturas reales** de las pantallas (preferencia PNG; SVG solo para diagramas).

## Estructura

| Manual | Config | Imágenes | Comando de captura |
|--------|--------|----------|-------------------|
| Compras | `config/manual_compras.php` | `public/docs/manual-compras/img/` | `php artisan manual:capturar-compras-interno` |
| Stock (recuento) | `config/manual_stock.php` | `public/docs/manual-stock/img/` | `php artisan manual:capturar-stock-interno` |
| Stock (recepción + movimientos) | `config/manual_recepcion_movstock.php` | `public/docs/manual-recepcion-movstock/img/` | `php artisan manual:capturar-recepcion-movstock-interno` |
| Gastronomía | `config/manual_gastronomia.php` | `public/docs/manual-gastronomia/img/` | `php artisan manual:capturar-gastronomia-interno` |
| Ventas (pedidos) | `config/manual_ventas.php` | `public/docs/manual-ventas/img/` | `php artisan manual:capturar-ventas-interno` |
| Canjes marketing | `config/manual_canjes_marketing.php` | `public/docs/manual-canjes-marketing/img/` | `php artisan manual:capturar-canjes-marketing-interno` |
| Vending | `config/manual_vending.php` | `public/docs/manual-vending/img/` | `php artisan manual:capturar-vending-interno` |
| Solicitudes de pago | `config/manual_solicitudpago.php` | `public/docs/manual-solicitudpago/img/` | `php artisan manual:capturar-solicitudpago-interno` |
| Propuesta de pagos / Tesorería AP | `config/manual_propuesta_pago.php` | `public/docs/manual-propuesta-pago/img/` | (SVG de respaldo; captura PNG a definir) |
| Contable (cierres/aperturas) | `config/manual_contable.php` | `public/docs/manual-contable/img/` | `php artisan manual:capturar-contable-interno` |
| UIF (clientes / premios / informe) | `config/manual_uif.php` | `public/docs/manual-uif/img/` | `php artisan manual:capturar-uif-interno` |
| Reportes contables definibles | `config/manual_reporte_definible.php` | `public/docs/manual-reporte-definible/img/` | (SVG de circuitos/wireframes; captura PNG a definir) |

## Cómo vincular una captura a un capítulo

1. En `config/manual_*.php`, agregar entrada en `capturas`:

```php
'mi_pantalla' => [
    'archivo' => 'mi-pantalla.png',
    'titulo' => 'Leyenda bajo la imagen',
    'seccion' => '4. Título exacto del capítulo', // o usar captura_id en contenido.php
],
```

2. En `docs/manual-*/contenido.php`, en la sección correspondiente:

```php
'captura_id' => 'mi_pantalla',
```

3. La vista del manual incluye `@include('manual.partials.capturas-seccion', …)` — prioriza **PNG** sobre SVG si existen ambos.

## Generar capturas (servidor)

Requisitos: **Ghostscript** (`gs`) y usuario ERP con permisos de las pantallas.

```bash
# Ejemplo: gastronomía (empresa con PV configurado)
php artisan manual:capturar-gastronomia-interno --usuario=admin

# Canjes marketing
php artisan manual:capturar-canjes-marketing-interno --usuario=admin

# Regenerar PDF/Word del manual
php docs/manual-gastronomia/generar.php
php docs/manual-canjes-marketing/generar.php
php docs/manual-vending/generar.php
php docs/manual-recepcion-movstock/generar.php
php docs/manual-solicitudpago/generar.php
php docs/manual-propuesta-pago/generar.php
php docs/manual-contable/generar.php
php docs/manual-uif/generar.php
```

Capturas Contable:

```bash
php artisan manual:capturar-contable-interno --usuario=admin
php docs/manual-contable/generar.php
```

Capturas UIF:

```bash
php artisan manual:capturar-uif-interno --usuario=admin
php docs/manual-uif/generar.php
```

Reportes contables definibles (diagramas SVG incluidos; PNG de pantallas opcionales):

```bash
php docs/manual-reporte-definible/generar.php
```

Alternativa Compras/Stock con navegador: `python3 docs/manual-compras/capturar_playwright.py` (requiere Chromium).

## Placeholder SVG

Si aún no se corrió el comando, el manual muestra el **SVG** de respaldo (diagramas o wireframes). Tras capturar, el PNG reemplaza al SVG automáticamente.
