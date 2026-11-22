# PHP: control de seguridad post-deploy

Tras un aviso de DigitalOcean / PHP.org, o al desplegar anitaERP en **otro host / empresa** (AGG, Crown, Ferli, Interforming, El Bierzo, etc.), correr el control en **ese** servidor. El fix de Ubuntu no se ve en `php -v` (sigue en `8.3.6`); va en el sufijo del paquete (`.14`, etc.).

## Comando

```bash
cd /var/www/html/anitaERP   # o la ruta del deploy
./scripts/check-php-security.sh
```

Exit `0` = cumple. Exit `1` = falta backport o mitigación. Con `--strict` también falla ante WARN críticos del script.

## Qué verifica

| Chequeo | Por qué |
|---|---|
| Rama PHP ≥ 8.2 | 8.1 y menores sin security fixes |
| Changelog del paquete Ubuntu (`CVE-2026-129289`, `CVE-2026-115560`, …) | Backport sin subir a 8.3.37 |
| Upgrade pendiente `apt` | Hay `.N+1` de seguridad sin aplicar |
| `session.upload_progress.enabled=Off` (CLI + FPM) | CVE-2026-115560 |
| `fastcgi_keep_conn` / `enablereuse` no en on | CVE-2026-129289 (defensa en profundidad) |
| PHP 8.5 + `php.ini` cargado | CVE-2026-129474 (Docker oficiales) |

## Si falla: actualizar (Ubuntu 24.04)

```bash
sudo apt update
sudo apt install --only-upgrade "php8.3*"
sudo systemctl restart php8.3-fpm apache2   # o nginx, según el host
./scripts/check-php-security.sh
```

En otro minor (`8.2` / `8.4`), cambiar el prefijo del paquete. Si el PHP viene de imagen Docker, subir el tag a ≥ `8.2.36` / `8.3.37` / `8.4.28` / `8.5.13` (o el que indique el aviso).

## Mitigaciones temporales (solo si no se puede parchear ya)

En `php.ini` / pool FPM (no alcanza `ini_set` ni `.user.ini`):

- `session.upload_progress.enabled=0`
- nginx: `fastcgi_keep_conn off` · Apache: `enablereuse=off`
- `zend.exception_ignore_args=1`
- PHP 8.5: `fatal_error_backtraces=0`
- FPM: `catch_workers_output=no`

Seguir vulnerables hasta el paquete/tag parcheado.

## Referencia

- Aviso DigitalOcean (nov/2026): PHP 8.5.13 / 8.4.28 / 8.3.37 / 8.2.36
- El Bierzo (`bie-svrv-sis2`): verificado OK con `8.3.6-0ubuntu0.24.04.14` (21/nov/2026)
- Script: `scripts/check-php-security.sh`
