# Manual — Reportes definibles de sueldos

El texto de uso está en el manual único: `docs/manual-sueldos/contenido.php`, capítulo «Reportes definibles y criterios premium».

Pantalla corta en el ERP: `sueldos/reporte-definible/manual` (enlace al manual completo).

Fuentes Anita: `listmae`, `listcol`, `listcon` (`a-listgen.c` / `l-listgen.c`).

Comandos:

```bash
php artisan sueldos:importar-reportes-definibles
php artisan sueldos:importar-reportes-definibles --ejecutar
php artisan sueldos:sembrar-plantillas-reporte-definible --ejecutar
php artisan sueldos:paridad-reporte-definible --reporte=ID --liquidacion=ID
```
