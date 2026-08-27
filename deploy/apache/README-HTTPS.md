# HTTPS para anitaERP (test .211 → luego prod .210)

> **Prueba por IP en .210 (cámara pickeo, sin tocar HTTP ni `.env`)**:
> [`README-HTTPS-IP-210.md`](README-HTTPS-IP-210.md).

> **El Bierzo (público `anitaerp.elbierzo.com.ar`)**: guía completa en
> [`README-HTTPS-EL-BIERZO.md`](README-HTTPS-EL-BIERZO.md)
> (conf `anitaERP-anitaerp-elbierzo-com-ar-ssl.conf` + script aplicar).
> Bookmarks viejos `ip.elbierzo.com.ar:12280/anitaERP/public` → 301 al canónico.

## Resumen

| Pregunta | Respuesta |
|---|---|
| ¿Qué certificado usan todos los navegadores sin aviso? | **CA pública**: Let's Encrypt (gratis) o comercial (DigiCert, etc.). Mismo nivel de confianza. |
| ¿Sirve un certificado autofirmado? | **No** para usuarios finales: cada PC muestra advertencia hasta instalar excepción. |
| ¿Sirve certificado solo para IP `10.20.30.211`? | **No** con CA pública. Hace falta un **nombre DNS** (ej. `anitaerp-test.agg.com`). |
| ¿Binlogs en test? | No (ver `RESTORE.md`). |

## Nombre recomendado

Usar un FQDN en **minúsculas**, sin guión bajo:

```
anitaerp-test.agg.com   → 10.20.30.211   (test)
anitaerp.agg.com        → 10.20.30.210   (prod, cuando migren)
```

`anitaERP_test.AGG.com` no es buena práctica DNS (guiones bajos inválidos en hostnames estándar).

## Paso 1 — DNS interno

En el DNS corporativo de AGG (Active Directory, pfSense, etc.):

```
anitaerp-test.agg.com.  IN  A  10.20.30.211
```

**Split-horizon** (recomendado): el mismo nombre resuelve a la IP interna dentro de la red y, si hace falta validación Let's Encrypt, el registro público apunta al mismo host o se usa **DNS-01**.

Comprobar desde una PC de usuario:

```bash
ping anitaerp-test.agg.com
# debe responder 10.20.30.211
```

## Paso 2 — Obtener certificado (elegir un camino)

### Opción A — Let's Encrypt + DNS-01 (recomendada si el servidor NO es alcanzable desde internet)

No requiere abrir el puerto 80 al mundo. Solo crear un registro TXT en el DNS de `agg.com`:

```bash
# En .211 (una vez instalado certbot: sudo apt install certbot)
sudo certbot certonly --manual --preferred-challenges dns \
  -d anitaerp-test.agg.com \
  --agree-tos -m sergiogranucci@gmail.com
```

Certbot pedirá un TXT `_acme-challenge.anitaerp-test.agg.com`. Lo cargás en el panel DNS de AGG, esperás propagación, Enter.

Renovación: automatizar con plugin del proveedor DNS (Cloudflare, Route53, etc.) o repetir manual cada ~90 días.

### Opción B — Let's Encrypt + HTTP-01 (si `anitaerp-test.agg.com:80` es público)

```bash
sudo apt install certbot python3-certbot-apache
sudo certbot --apache -d anitaerp-test.agg.com
```

### Opción C — Certificado comercial

Comprar certificado para `anitaerp-test.agg.com` (o wildcard `*.agg.com`), instalar `fullchain.pem` + `privkey.pem` en Apache. Misma confianza que Let's Encrypt; suele costar y renovar manual/anual.

### Opción D — CA interna (solo si IT distribuye el root CA por GPO)

Windows Server CA o **step-ca**. Todos los navegadores confían **solo si** el certificado raíz está instalado en cada PC. No es “sin configuración” para el usuario.

## Paso 3 — Apache en .211

```bash
ssh sergio@10.20.30.211
cd /var/www/html/anitaERP
sudo ./deploy/apache/aplicar-vhost-211-ssl.sh
```

Eso habilita `ssl`, `rewrite`, `headers`, instala el vhost y recarga Apache.

**Después de tener el certificado**, activar redirect global HTTP→HTTPS en el bloque `<VirtualHost *:80>`:

```apache
Redirect permanent / https://anitaerp-test.agg.com/
```

## Paso 4 — Laravel (.env en .211)

```dotenv
APP_URL=https://anitaerp-test.agg.com
APP_CARPETA=
EMPRESA_LINK=/
```

Luego:

```bash
php artisan config:clear
php artisan cache:clear
php artisan route:clear
```

`APP_CARPETA` vacío hace que rutas y JS usen `/ventas/...` en lugar de `/anitaERP/public/ventas/...`. Apache redirige las URLs viejas con `RedirectMatch`.

`AppServiceProvider` fuerza `https` en links cuando `APP_URL` contiene `https` (cualquier entorno).

## Paso 5 — Redirects (sin avisar usuario por usuario)

| Origen | Destino | Quién lo hace |
|---|---|---|
| `http://anitaerp-test.agg.com/...` | `https://anitaerp-test.agg.com/...` | Apache `:80` Redirect |
| `http://10.20.30.211/...` | `https://anitaerp-test.agg.com/...` | Apache (opcional en conf) |
| `/anitaERP/public/ventas/...` | `https://anitaerp-test.agg.com/ventas/...` | Apache `RedirectMatch 301` |
| Links generados por Laravel | `https://.../ventas/...` | `APP_URL` + `APP_CARPETA` vacío |

Los bookmarks viejos con `/anitaERP/public` **siguen funcionando** (301 automático).

## Paso 6 — Probar en .211 antes de producción

```bash
curl -sI http://anitaerp-test.agg.com/anitaERP/public/ventas/cliente
# Location: https://anitaerp-test.agg.com/ventas/cliente

curl -sI https://anitaerp-test.agg.com/
# HTTP/2 200, certificado válido, X-AnitaERP-Entorno: test
```

Checklist:

- [ ] Login, menú, export PDF/Excel
- [ ] Punto de venta gastronomía (si aplica)
- [ ] Links en emails / PDFs generados
- [ ] AFIP / integraciones que whitelistean URL

## Paso 7 — Llevar a producción (.210)

Mismo patrón con `anitaerp.agg.com` → `10.20.30.210`:

1. DNS
2. Certificado
3. Vhost SSL prod (copiar plantilla de test)
4. `.env` prod: `APP_URL=https://anitaerp.agg.com`, `APP_CARPETA=`
5. Activar redirect HTTP→HTTPS
6. HSTS solo cuando esté estable

## Renovación Let's Encrypt

```bash
sudo certbot renew --dry-run
sudo systemctl reload apache2
```
