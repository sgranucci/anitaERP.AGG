# Manual Conciliación bancaria (premium v2.0)

Alcance documentado: oleadas 1–4 + polish (extracto/KPI/lock, clearing, worklist/residuales/CAMT-MT940, forecast/score/evidencia, ABM residuales, menú worklist, UI evidencia).

## Regenerar

```bash
php artisan manual:generar-mockups conciliacion-bancaria
php docs/manual-conciliacion-bancaria/generar.php
```

## Artefactos

| Qué | Dónde |
|---|---|
| Mockups PNG | `public/docs/manual-conciliacion-bancaria/img/` |
| PDF / Word / preview | `docs/manual-conciliacion-bancaria/Manual_Usuario_AnitaERP_Conciliacion_Bancaria.*` |
| En el ERP | `/contable/manual-conciliacion-bancaria` (Centro de ayuda) |

## Piezas

- Config: `config/manual_conciliacion_bancaria.php` (version `2.0`)
- Escenas: `App\Support\Manuales\Escenas\ConciliacionBancariaEscenas`
- Contenido: `contenido.php` + `herramientas.php`
- Service / Controller: `ManualConciliacionBancariaService` / `ManualConciliacionBancariaController`
