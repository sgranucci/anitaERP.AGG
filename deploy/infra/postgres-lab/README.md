# Laboratorio PostgreSQL

Entorno aislado para comprobar que el esquema de anitaERP puede crearse en
PostgreSQL. No publica el puerto de PostgreSQL y no usa la base MySQL operativa.

Referencia: `docs/arquitectura/portabilidad-base-datos.md` (Fase 5).

CI: `.github/workflows/postgres-lab.yml` → `./ci-run.sh` (migrate + smoke + seed).

## Preparación

1. Copiar un snapshot del código a `app/` (sin `.env` de producción), o un
   symlink al árbol Laravel.
2. Copiar `.env.example` a `.env` y generar valores aleatorios para
   `POSTGRES_PASSWORD` y `APP_KEY` (o dejar que `ci-run.sh` los genere).
3. `composer install` en el árbol montado como `app/`.
4. Levantar PostgreSQL:

```bash
docker compose up -d postgres
docker compose build migrator
```

En el host de lab (`10.20.30.211`) el directorio operativo es
`/home/sergio/anitaerp-postgres-lab/` (mismo compose). Usar `EMPRESA=LAB_PG`
para evitar seeds exclusivos de AGG.

## Análisis sin persistir

```bash
docker compose run --rm -e EMPRESA=LAB_PG migrator php artisan migrate --pretend --force
```

## Persistencia en la base vacía

Solo después de aprobación explícita:

```bash
docker compose run --rm -e EMPRESA=LAB_PG migrator php artisan migrate --force
```

## Smoke (Fase 5.3)

```bash
docker compose run --rm -e EMPRESA=LAB_PG migrator php scripts/postgres-lab-smoke.php
```

## Seed mínimo + smoke operativo

Crea empresa `codigo=900001`, usuario `lab_pg` (activo, vinculado), rol `Lab-PG`
(`usuario_rol`) y valida `UsuarioOperativoSupport`.

```bash
docker compose run --rm -e EMPRESA=LAB_PG migrator php scripts/postgres-lab-seed-minimo.php
docker compose run --rm -e EMPRESA=LAB_PG -e SMOKE_EXPECT_SEED=1 migrator php scripts/postgres-lab-smoke.php
docker compose run --rm -e EMPRESA=LAB_PG -e SMOKE_EXPECT_SEED=1 migrator php scripts/postgres-lab-smoke-http.php
```

Credencial solo lab (no producción): usuario `lab_pg` / `LabPg-SoloPrueba-2026`.

El smoke HTTP hace GET/POST a `/seguridad/login` (CSRF + sesión file), entra al
inicio autenticado y recorre indexes de negocio (artículos, movimientos de stock,
cuentas de caja, pedidos, órdenes de compra, proveedores, cuentas contables,
asientos, canjes marketing, requisiciones, facturas de proveedor, recepciones,
capex, partidas de gasto, presupuestos) para detectar SQL/vistas
que fallan en PostgreSQL.
Con `EMPRESA=LAB_PG` no dispara el auto-import Anita de index vacío.
El migrator usa `CACHE_DRIVER=array` para no reutilizar slugs de permiso
en disco entre corridas (`PermisoCacheSupport::forgetRol` también se llama
desde el seed).

## Pipeline local (igual que CI)

Desde este directorio, con `app/` apuntando al código y `vendor` instalado:

```bash
./ci-run.sh
```

Variables: `EMPRESA` (default vía entorno `LAB_PG` en el script),
`POSTGRES_LAB_CON_SEED=0` para omitir seed.

## Limpieza

```bash
docker compose down --volumes
```
