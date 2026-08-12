# Manual — Reportes contables definibles

## Artefactos generados

| Archivo | Uso |
|---------|-----|
| `Manual_Usuario_AnitaERP_Reportes_Definibles_preview.html` | Vista previa en navegador (incluye diagramas SVG) |
| `Manual_Usuario_AnitaERP_Reportes_Definibles.pdf` | PDF (~35 páginas) |
| `Manual_Usuario_AnitaERP_Reportes_Definibles.docx` | Word (texto + tablas; diagramas en PDF/HTML) |

## Regenerar

```bash
php docs/manual-reporte-definible/generar.php
```

## Fuentes

| Archivo | Contenido |
|---------|-----------|
| `contenido.php` | Capítulos, párrafos, tablas, FAQs |
| `herramientas.php` | Catálogos de botones/campos por pantalla |
| `config/manual_reporte_definible.php` | Metadatos y vínculo captura → sección |
| `public/docs/manual-reporte-definible/img/*.svg` | Circuitos y wireframes de pantallas |

## Capturas PNG reales

Los SVG de `pantalla-*.svg` son wireframes. Cuando exista el comando de captura interno, guardar PNG con el mismo nombre base en `public/docs/manual-reporte-definible/img/` (el generador prioriza PNG sobre SVG).
