# Guía rápida — Liquidación de sueldos y jornales

Mini guía operativa del cierre de período (legajo → novedades / importación → calcular → cerrar), con bajadas de pantalla.

## En el ERP

- Pantalla: `/sueldos/guia-rapida`
- Centro de ayuda → **Sueldos — Guía rápida de liquidación**
- Atajo desde el Manual completo de Sueldos

## Regenerar

```bash
# Bajadas de pantallas (HTML autenticado → PNG)
php artisan manual:capturar-guia-rapida-sueldos --usuario=sergio

# Word + PDF + preview HTML
php docs/guia-rapida-sueldos/generar.php
```

Imágenes: `public/docs/guia-rapida-sueldos/img/`  
Config de capturas: `config/manual_guia_rapida_sueldos.php`
