# Manual — Reportes definibles de sueldos

Ver pantalla en ERP: `sueldos/reporte-definible/manual`.

Fuentes Anita: `listmae`, `listcol`, `listcon` (`a-listgen.c` / `l-listgen.c`).

Comandos:

```bash
php artisan sueldos:importar-reportes-definibles
php artisan sueldos:importar-reportes-definibles --ejecutar
php artisan sueldos:sembrar-plantillas-reporte-definible --ejecutar
php artisan sueldos:paridad-reporte-definible --reporte=ID --liquidacion=ID
```
