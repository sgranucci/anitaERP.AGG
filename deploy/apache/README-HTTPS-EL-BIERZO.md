# HTTPS El Bierzo — `anitaerp.elbierzo.com.ar`

Guía operativa. **No aplica cambios sola**: corré el script de corte o los comandos a mano.

| | |
|---|---|
| Servidor | `bie-svrv-sis2` / `192.168.59.122` |
| URL vieja | `http://ip.elbierzo.com.ar:12280/anitaERP/public/` |
| URL nueva | `https://anitaerp.elbierzo.com.ar/` |
| Conf Apache | `deploy/apache/anitaERP-anitaerp-elbierzo-com-ar-ssl.conf` |
| Script aplicar | `deploy/apache/aplicar-vhost-anitaerp-elbierzo-com-ar-ssl.sh` |
| Corte todo-en-uno | `deploy/apache/corte-https-anitaerp-elbierzo.sh` |

## Cómo encontrar esta guía desde otro Cursor

```text
Seguí la guía deploy/apache/README-HTTPS-EL-BIERZO.md (HTTPS El Bierzo / anitaerp.elbierzo.com.ar).
```

También referenciada desde `deploy/apache/README-HTTPS.md`.

---

## Pre-requisitos (antes de tocar Apache)

1. DNS público: `anitaerp.elbierzo.com.ar` → IP pública del router (misma que hoy usa `ip.elbierzo.com.ar`, tip. `190.12.108.82`).
2. Opcional: dejar `ip.elbierzo.com.ar` y `anita.elbierzo.com.ar` apuntando al mismo host (el vhost los trae como `ServerAlias` y redirige al canónico).
3. NAT/firewall hacia `192.168.59.122`:
   - **80/tcp** (Let's Encrypt + redirect)
   - **443/tcp** (HTTPS)
   - **12280/tcp** (redirect de bookmarks; más adelante se puede cerrar)
4. Acceso `sudo` en el servidor.

Comprobar:

```bash
dig +short anitaerp.elbierzo.com.ar
curl -sI http://anitaerp.elbierzo.com.ar/ | head -5
```

---

## 1) Módulos Apache (`a2enmod`)

```bash
sudo a2enmod ssl
sudo a2enmod rewrite
sudo a2enmod headers
```

Asegurar puerto 443 en `/etc/apache2/ports.conf`:

```apache
Listen 80
Listen 12280

<IfModule ssl_module>
	Listen 443
</IfModule>
```

---

## 2) Certificado Let's Encrypt

```bash
sudo apt update
sudo apt install -y certbot python3-certbot-apache
```

### Opción recomendada (HTTP-01, puerto 80 público)

```bash
sudo certbot --apache -d anitaerp.elbierzo.com.ar
```

O solo emitir (después aplicar nuestro vhost):

```bash
sudo certbot certonly --apache -d anitaerp.elbierzo.com.ar \
  --agree-tos -m sergiogranucci@gmail.com
```

Certs en:

- `/etc/letsencrypt/live/anitaerp.elbierzo.com.ar/fullchain.pem`
- `/etc/letsencrypt/live/anitaerp.elbierzo.com.ar/privkey.pem`
- `/etc/letsencrypt/options-ssl-apache.conf`

### Si el 80 no es alcanzable desde Internet

```bash
sudo certbot certonly --manual --preferred-challenges dns \
  -d anitaerp.elbierzo.com.ar \
  --agree-tos -m sergiogranucci@gmail.com
```

Crear el TXT `_acme-challenge.anitaerp.elbierzo.com.ar` que pida certbot, esperar propagación, Enter.

---

## 3) Instalar el vhost SSL

Archivo fuente:

```text
deploy/apache/anitaERP-anitaerp-elbierzo-com-ar-ssl.conf
```

Incluye:

- `:80` → HTTPS + exención ACME
- `:12280` → HTTPS (bookmarks `/anitaERP/public/...` y hosts legacy)
- `:443` → DocumentRoot `/var/www/html/anitaERP/public` + headers

### Aplicar con script (después del certificado)

```bash
cd /var/www/html/anitaERP
chmod +x deploy/apache/aplicar-vhost-anitaerp-elbierzo-com-ar-ssl.sh
sudo ./deploy/apache/aplicar-vhost-anitaerp-elbierzo-com-ar-ssl.sh
```

---

## 4) Cambios exactos en `.env`

| Clave | Valor nuevo |
|---|---|
| `APP_ENV` | `production` |
| `APP_DEBUG` | `false` |
| `APP_URL` | `https://anitaerp.elbierzo.com.ar` |
| `APP_CARPETA` | *(vacío)* `APP_CARPETA=` |
| `EMPRESA_LINK` | `/` |
| `SESSION_SECURE_COOKIE` | `true` |

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://anitaerp.elbierzo.com.ar
APP_CARPETA=
EMPRESA_LINK=/
SESSION_SECURE_COOKIE=true
```

```bash
cd /var/www/html/anitaERP
php artisan config:clear
php artisan config:cache
php artisan route:clear
php artisan view:cache
```

Verificar:

```bash
php -r "require 'vendor/autoload.php'; \$app = require 'bootstrap/app.php'; \$app->make('Illuminate\\Contracts\\Console\\Kernel')->bootstrap(); echo config('app.url').PHP_EOL; echo 'env='.config('app.env').' debug='.(config('app.debug')?'1':'0').PHP_EOL; echo 'carpeta='.var_export(config('app.app_carpeta'), true).PHP_EOL;"
```

Esperado: `https://anitaerp.elbierzo.com.ar`, `env=production`, `debug=0`, carpeta vacía/`null`.

---

## 5) Redirects (sin ir PC por PC)

| Origen (bookmark viejo) | Destino |
|---|---|
| `http://ip.elbierzo.com.ar:12280/anitaERP/public/` | `https://anitaerp.elbierzo.com.ar/` |
| `http://ip.elbierzo.com.ar:12280/anitaERP/public/ventas/...` | `https://anitaerp.elbierzo.com.ar/ventas/...` |
| `http://anita.elbierzo.com.ar/...` | `https://anitaerp.elbierzo.com.ar/...` |
| `http://anitaerp.elbierzo.com.ar/...` | `https://anitaerp.elbierzo.com.ar/...` |
| `https://192.168.59.122/...` / `https://ip.elbierzo.com.ar/...` | `https://anitaerp.elbierzo.com.ar/...` |

---

## 6) Pruebas

```bash
curl -sI http://ip.elbierzo.com.ar:12280/anitaERP/public/
# Location: https://anitaerp.elbierzo.com.ar/

curl -sI http://ip.elbierzo.com.ar:12280/anitaERP/public/ventas/cliente
# Location: https://anitaerp.elbierzo.com.ar/ventas/cliente

curl -sI https://anitaerp.elbierzo.com.ar/
# HTTP/2 200 (o 302 a login), certificado válido

sudo certbot renew --dry-run
```

Checklist UI: login, menú/assets, export PDF/Excel, cookie Secure.

---

## Orden corto del día

1. DNS público `anitaerp.elbierzo.com.ar` + NAT **80/443/12280**
2. Script todo-en-uno:

```bash
cd /var/www/html/anitaERP
chmod +x deploy/apache/corte-https-anitaerp-elbierzo.sh
./deploy/apache/corte-https-anitaerp-elbierzo.sh
```

3. Editar `.env` (tabla de arriba) + cache Laravel
4. Probar curls + login
5. Avisar URL nueva: `https://anitaerp.elbierzo.com.ar/`

---

## Notas de archivos viejos

| Archivo | Uso |
|---|---|
| `anitaERP-anita-elbierzo-com-ar-ssl.conf` | Obsoleto (`anita.`); wrappers redirigen |
| `anitaERP-ip-elbierzo-com-ar-ssl.conf` | Obsoleto (`ip.`); stub |
| `anitaERP-ipelbierzo-ssl.conf` | Hostname corto + CA interna — no usar para LE |
| `corte-https-anita-elbierzo.sh` / `corte-https-ip-elbierzo.sh` | Wrappers → `corte-https-anitaerp-elbierzo.sh` |
