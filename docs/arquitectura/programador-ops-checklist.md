# Programador — checklist operativo (post unificación)

## Día siguiente al deploy

1. **08:05** — historial `listado.envios_workbench` (OK o idle sin envíos activos).
2. **08:10** — `consulta_inteligente.envios_programados`.
3. **Poll 15′** — Flash/definibles: si hay suscripción vencida, debe aparecer log (o idle omitido).
4. **08:15 / 08:20** — AI digest/alerta solo si `ai.habilitado`.
5. **08:30 / 09:00** — digest / recordatorios árbol.
6. **Reglas** — `aviso_regla_log` + campanita a admins (aging / préstamo / recepción).

## Si algo no corre

- Ficha → Rango: activa, no pausada, hora/días, confirmar cambio si editaste.
- Cockpit → fallos 7d / historial global.
- Suscripciones Flash: activas + horario; pausa desde Programador no borra destinatarios.

## Comandos útiles (solo lectura / simular)

```bash
php artisan avisos:despachar-programados --simular
php artisan compras:enviar-listados-programados --dry-run
php artisan direccion:enviar-consultas-inteligentes --dry-run
```
