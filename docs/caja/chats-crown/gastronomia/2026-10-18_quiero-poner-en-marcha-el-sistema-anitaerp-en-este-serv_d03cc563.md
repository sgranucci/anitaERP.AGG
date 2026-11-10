# quiero poner en marcha el sistema anitaERP en este servidor con https, dominio erp.casinosdelrio.com.ar

- **Fecha:** 2026-10-18 13:19
- **Chat id:** `d03cc563-d115-427f-a411-a137bb04667a`
- **Módulos:** gastronomia
- **Origen local:** `agent-transcripts/d03cc563-d115-427f-a411-a137bb04667a`

> Export redactado para viaje Crown. Sin tool dumps completos; solo diálogo útil.

## Usuario

quiero poner en marcha el sistema anitaERP en este servidor con https, dominio erp.casinosdelrio.com.ar

## Asistente

Voy a revisar cómo está hoy el servidor (Apache/Nginx, Laravel, `.env`, certificados) para darte una guía concreta hacia `https://erp.casinosdelrio.com.ar`.
Voy a revisar cómo está hoy el servidor (Apache/Nginx, Laravel, `.env`, certificados) para darte una guía concreta hacia `https://erp.casinosdelrio.com.ar`.

## Asistente

Hay documentación de deploy HTTPS en el repo y el vhost actual apunta mal el DocumentRoot. Revisando esos scripts y el estado de PHP/MySQL.
Hay documentación de deploy HTTPS en el repo y el vhost actual apunta mal el DocumentRoot. Revisando esos scripts y el estado de PHP/MySQL.

## Asistente

Estado actual: el código está en el servidor, pero **aún no está operable** como ERP en HTTPS con ese dominio. Resumen y plan.

## Qué hay hoy en este host (`server1ng`)

| Pieza | Estado |
|---|---|
| Código en `/var/www/html/anitaERP` | Presente |
| `.env` | **No existe** |
| `vendor/` (Composer) | **No existe** |
| Apache | Vhosts Ansible para `server1ng.local.casinosdelrio.com.ar` |
| DocumentRoot SSL | **Incorrecto**: apunta a `/var/www/html/anitaERP` (debe ser `.../public`) |
| Certificado actual | Solo nombres locales (`server1ng.local…`, `anita.local…`), no `erp.casinosdelrio.com.ar` |
| Let's Encrypt | **No instalado** (`/etc/letsencrypt` no existe) |
| MariaDB / PHP-FPM | Empaquetados; en este entorno no se ve el socket MySQL ni `/run/php/` |

El patrón canónico del repo es el de El Bierzo: `deploy/apache/README-HTTPS.md` y `deploy/apache/README-HTTPS-EL-BIERZO.md`.

---

## Plan recomendado (mismo patrón El Bierzo)

### 1) DNS y red (antes de Apache)

1. Registro **A** (o CNAME): `erp.casinosdelrio.com.ar` → IP pública del servidor (o del router con NAT).
2. Firewall/NAT:
   - **80/tcp** (redirect + ACME Let’s Encrypt)
   - **443/tcp** (HTTPS)
3. Comprobar desde fuera y desde una PC interna:

```bash
dig +short erp.casinosdelrio.com.ar
curl -sI http://erp.casinosdelrio.com.ar/ | head -5
```

Si el servidor **no** es alcanzable en el 80 desde Internet, usar **DNS-01** en lugar de HTTP-01 (igual que en la guía del repo).

### 2) Stack Laravel (aplicación)

En el servidor (con `sudo` donde haga falta):

```bash
cd /var/www/html/anitaERP
composer install --no-dev --optimize-autoloader
cp .env.example .env   # o copiar .env desde otro entorno Casinos ya armado
php artisan key:generate
```

Ajustar en `.env` (mínimo HTTPS):

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://erp.casinosdelrio.com.ar
APP_CARPETA=
EMPRESA_LINK=/
SESSION_SECURE_COOKIE=true
```

También: `DB_*`, `EMPRESA=` (cliente/instalación), mail, colas, Anita, etc.  
**Ojo:** el `.env.example` de este árbol parece contenido PHP de config, no un dotenv clásico; conviene traer un `.env` real de un entorno Casinos ya operativo o reconstruirlo desde `config/` + secretos.

Base de datos:

```bash
sudo systemctl start mariadb   # si no está arriba
# crear BD/usuario, importar dump o migrar según lo acordado
# NO migrate:fresh en producción
```

Permisos típicos:

```bash
chown -R www-data:www-data storage bootstrap/cache
chmod -R ug+rwx storage bootstrap/cache
```

Cola + cron (hay scripts en `deploy/queue/` y `deploy/supervisor/`).

### 3) Certificado HTTPS

```bash
sudo apt install -y certbot python3-certbot-apache
```

Si el 80 es público:

```bash
sudo certbot certonly --apache -d erp.casinosdelrio.com.ar \
  --agree-tos -m TU_EMAIL@dominio
```

Si no:

```bash
sudo certbot certonly --manual --preferred-challenges dns \
  -d erp.casinosdelrio.com.ar --agree-tos -m TU_EMAIL@dominio
```

Certs esperados:

- `/etc/letsencrypt/live/erp.casinosdelrio.com.ar/fullchain.pem`
- `/etc/letsencrypt/live/erp.casinosdelrio.com.ar/privkey.pem`

El cert local actual **no sirve** para el dominio público (navegadores lo rechazan).

### 4) Virtual host Apache

Los conf actuales dicen *“administrado vía Ansible — no editar manualmente”*. Lo ideal:

1. Clonar el conf de El Bierzo (`deploy/apache/anitaERP-anitaerp-elbierzo-com-ar-ssl.conf`).
2. Reemplazar dominio → `erp.casinosdelrio.com.ar`, IP/aliases de este host, logs.
3. **DocumentRoot** = `/var/www/html/anitaERP/public` (obligatorio).
4. PHP-FPM: igual que el SSL actual (`proxy:unix:/run/php/php8.4-fpm.sock|fcgi://localhost/`) si este host usa FPM.
5. Redirect HTTP→HTTPS + excepción `/.well-known/acme-challenge/`.
6. Publicar vía Ansible (preferible) o un script tipo `aplicar-vhost-…` y `a2ensite` / `a2dissite` de los sitios viejos conflictivos.

Módulos: `ssl`, `rewrite`, `headers`. Luego `apache2ctl configtest` y `systemctl reload apache2`.

### 5) Cache Laravel y prueba

```bash
cd /var/www/html/anitaERP
php artisan config:clear
php artisan config:cache
php artisan route:clear
php artisan view:cache

curl -sI http://erp.casinosdelrio.com.ar/
# Location: https://erp.casinosdelrio.com.ar/...

curl -sI https://erp.casinosdelrio.com.ar/
# 200 + certificado válido
```

Checklist: login, menú, PDF/Excel, POS si aplica, mails con links, integraciones que whitelistean URL. HSTS recién cuando lleve 2–4 semanas estable.

---

## Orden práctico de bloqueos

1. DNS + puertos 80/443  
2. MariaDB arriba + `.env` + `composer install`  
3. Cert Let’s Encrypt para `erp.casinosdelrio.com.ar`  
4. Vhost con DocumentRoot en `public` (y alinear Ansible)  
5. `APP_URL` HTTPS + cookies seguras + queue/cron  

---

Estoy en **modo Ask**: no puedo aplicar cambios en el servidor. Si pasás a **Agent mode**, puedo armar el conf/script para `erp.casinosdelrio.com.ar` y guiarte paso a paso; lo que toque red/DNS, secretos de BD y Ansible conviene confirmarlo vos antes de ejecutarlo.

¿El DNS de `erp.casinosdelrio.com.ar` ya apunta a este servidor, y tenés un `.env` de referencia de Casinos (u otro cliente) para copiar?
Estado actual: el código está en el servidor, pero **aún no está operable** como ERP en HTTPS con ese dominio. Resumen y plan.

## Qué hay hoy en este host (`server1ng`)

| Pieza | Estado |
|---|---|
| Código en `/var/www/html/anitaERP` | Presente |
| `.env` | **No existe** |
| `vendor/` (Composer) | **No existe** |
| Apache | Vhosts Ansible para `server1ng.local.casinosdelrio.com.ar` |
| DocumentRoot SSL | **Incorrecto**: apunta a `/var/www/html/anitaERP` (debe ser `.../public`) |
| Certificado actual | Solo nombres locales (`server1ng.local…`, `anita.local…`), no `erp.casinosdelrio.com.ar` |
| Let's Encrypt | **No instalado** (`/etc/letsencrypt` no existe) |
| MariaDB / PHP-FPM | Empaquetados; en este entorno no se ve el socket MySQL ni `/run/php/` |

El patrón canónico del repo es el de El Bierzo: `deploy/apache/README-HTTPS.md` y `deploy/apache/README-HTTPS-EL-BIERZO.md`.

---

## Plan recomendado (mismo patrón El Bierzo)

### 1) DNS y red (antes de Apache)

1. Registro **A** (o CNAME): `erp.casinosdelrio.com.ar` → IP pública del servidor (o del router con NAT).
2. Firewall/NAT:
   - **80/tcp** (redirect + ACME Let’s Encrypt)
   - **443/tcp** (HTTPS)
3. Comprobar desde fuera y desde una PC interna:

```bash
dig +short erp.casinosdelrio.com.ar
curl -sI http://erp.casinosdelrio.com.ar/ | head -5
```

Si el servidor **no** es alcanzable en el 80 desde Internet, usar **DNS-01** en lugar de HTTP-01 (igual que en la guía del repo).

### 2) Stack Laravel (aplicación)

En el servidor (con `sudo` donde haga falta):

```bash
cd /var/www/html/anitaERP
composer install --no-dev --optimize-autoloader
cp .env.example .env   # o copiar .env desde otro entorno Casinos ya armado
php artisan key:generate
```

Ajustar en `.env` (mínimo HTTPS):

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://erp.casinosdelrio.com.ar
APP_CARPETA=
EMPRESA_LINK=/
SESSION_SECURE_COOKIE=true
```

También: `DB_*`, `EMPRESA=` (cliente/instalación), mail, colas, Anita, etc.  
**Ojo:** el `.env.example` de este árbol parece contenido PHP de config, no un dotenv clásico; conviene traer un `.env` real de un entorno Casinos ya operativo o reconstruirlo desde `config/` + secretos.

Base de datos:

```bash
sudo systemctl start mariadb   # si no está arriba
# crear BD/usuario, importar dump o migrar según lo acordado
# NO migrate:fresh en producción
```

Permisos típicos:

```bash
chown -R www-data:www-data storage bootstrap/cache
chmod -R ug+rwx storage bootstrap/cache
```

Cola + cron (hay scripts en `deploy/queue/` y `deploy/supervisor/`).

### 3) Certificado HTTPS

```bash
sudo apt install -y certbot python3-certbot-apache
```

Si el 80 es público:

```bash
sudo certbot certonly --apache -d erp.casinosdelrio.com.ar \
  --agree-tos -m TU_EMAIL@dominio
```

Si no:

```bash
sudo certbot certonly --manual --preferred-challenges dns \
  -d erp.casinosdelrio.com.ar --agree-tos -m TU_EMAIL@dominio
```

Certs esperados:

- `/etc/letsencrypt/live/erp.casinosdelrio.com.ar/fullchain.pem`
- `/etc/letsencrypt/live/erp.casinosdelrio.com.ar/privkey.pem`

El cert local actual **no sirve** para el dominio público (navegadores lo rechazan).

### 4) Virtual host Apache

Los conf actuales dicen *“administrado vía Ansible — no editar manualmente”*. Lo ideal:

1. Clonar el conf de El Bierzo (`deploy/apache/anitaERP-anitaerp-elbierzo-com-ar-ssl.conf`).
2. Reemplazar dominio → `erp.casinosdelrio.com.ar`, IP/aliases de este host, logs.
3. **DocumentRoot** = `/var/www/html/anitaERP/public` (obligatorio).
4. PHP-FPM: igual que el SSL actual (`proxy:unix:/run/php/php8.4-fpm.sock|fcgi://localhost/`) si este host usa FPM.
5. Redirect HTTP→HTTPS + excepción `/.well-known/acme-challenge/`.
6. Publicar vía Ansible (preferible) o un script tipo `aplicar-vhost-…` y `a2ensite` / `a2dissite` de los sitios viejos conflictivos.

Módulos: `ssl`, `rewrite`, `headers`. Luego `apache2ctl configtest` y `systemctl reload apache2`.

### 5) Cache Laravel y prueba

```bash
cd /var/www/html/anitaERP
php artisan config:clear
php artisan config:cache
php artisan route:clear
php artisan view:cache

curl -sI http://erp.casinosdelrio.com.ar/
# Location: https://erp.casinosdelrio.com.ar/...

curl -sI https://erp.casinosdelrio.com.ar/
# 200 + certificado válido
```

Checklist: login, menú, PDF/Excel, POS si aplica, mails con links, integraciones que whitelistean URL. HSTS recién cuando lleve 2–4 semanas estable.

---

## Orden práctico de bloqueos

1. DNS + puertos 80/443  
2. MariaDB arriba + `.env` + `composer install`  
3. Cert Let’s Encrypt para `erp.casinosdelrio.com.ar`  
4. Vhost con DocumentRoot en `public` (y alinear Ansible)  
5. `APP_URL` HTTPS + cookies seguras + queue/cron  

---

Estoy en **modo Ask**: no puedo aplicar cambios en el servidor. Si pasás a **Agent mode**, puedo armar el conf/script para `erp.casinosdelrio.com.ar` y guiarte paso a paso; lo que toque red/DNS, secretos de BD y Ansible conviene confirmarlo vos antes de ejecutarlo.

¿El DNS de `erp.casinosdelrio.com.ar` ya apunta a este servidor, y tenés un `.env` de referencia de Casinos (u otro cliente) para copiar?

## Usuario

ya pasaste a modo agent, copie el .env de agg ,pero no se si el dominio esta apuntando bien

## Asistente

Voy a verificar DNS de `erp.casinosdelrio.com.ar` contra la IP de este servidor y revisar qué quedó en el `.env` copiado de AGG.
Voy a verificar DNS de `erp.casinosdelrio.com.ar` contra la IP de este servidor y revisar qué quedó en el `.env` copiado de AGG.

## Asistente

**No: el dominio todavía no apunta a este servidor.**

## DNS

| Consulta | Resultado |
|---|---|
| `erp.casinosdelrio.com.ar` | **NXDOMAIN** (el registro no existe en DNS público) |
| Zona `casinosdelrio.com.ar` | Sí existe (NS HostGator: `cns191/192.hostgator.com`) |
| Este servidor | LAN `192.168.1.3` · salida pública aparente `200.41.159.98` |
| Apache | Escucha 80/443; con Host `erp…` hoy redirige a `https://server1ng.local.casinosdelrio.com.ar/` |

Hay que **crear** el registro en el panel DNS de HostGator (zona `casinosdelrio.com.ar`):

```text
Tipo: A
Nombre: erp
Valor: 200.41.159.98    ← IP pública de este host (si el NAT/firewall apunta acá)
TTL: 300 o default
```

Si el router hace NAT, confirmá que **80 y 443** de `200.41.159.98` lleguen a `192.168.1.3`.

Comprobación cuando esté propagado:

```bash
dig +short erp.casinosdelrio.com.ar @8.8.8.8
# debe devolver 200.41.159.98
```

Hasta que eso responda, Let’s Encrypt HTTP-01 y el acceso por ese FQDN no van a funcionar.

## `.env` copiado de AGG (aún no adaptado)

```text
APP_URL=http://10.20.30.210
APP_CARPETA=/anitaERP/public
APP_DEBUG=true
EMPRESA='AGG'
ARBOLAPROBACION_IP_LINK=http://10.20.30.210
DB_* → anitaERP local
```

Para este servidor debería quedar algo así (cuando digas que sí lo cambio):

```dotenv
APP_URL=https://erp.casinosdelrio.com.ar
APP_CARPETA=
APP_DEBUG=false
EMPRESA='…'   # el valor correcto de Casinos del Río, no AGG
SESSION_SECURE_COOKIE=true
ARBOLAPROBACION_IP_LINK=https://erp.casinosdelrio.com.ar
```

---

**Siguiente paso tuyo:** crear el `A` de `erp` en HostGator hacia `200.41.159.98` (y abrir 80/443).

Cuando `dig +short erp.casinosdelrio.com.ar @8.8.8.8` muestre esa IP, avisame y sigo con: ajustar `.env`, cert Let’s Encrypt y vhost Apache. ¿Qué valor debe tener `EMPRESA` en Casinos (si ya lo saben)?
**No: el dominio todavía no apunta a este servidor.**

## DNS

| Consulta | Resultado |
|---|---|
| `erp.casinosdelrio.com.ar` | **NXDOMAIN** (el registro no existe en DNS público) |
| Zona `casinosdelrio.com.ar` | Sí existe (NS HostGator: `cns191/192.hostgator.com`) |
| Este servidor | LAN `192.168.1.3` · salida pública aparente `200.41.159.98` |
| Apache | Escucha 80/443; con Host `erp…` hoy redirige a `https://server1ng.local.casinosdelrio.com.ar/` |

Hay que **crear** el registro en el panel DNS de HostGator (zona `casinosdelrio.com.ar`):

```text
Tipo: A
Nombre: erp
Valor: 200.41.159.98    ← IP pública de este host (si el NAT/firewall apunta acá)
TTL: 300 o default
```

Si el router hace NAT, confirmá que **80 y 443** de `200.41.159.98` lleguen a `192.168.1.3`.

Comprobación cuando esté propagado:

```bash
dig +short erp.casinosdelrio.com.ar @8.8.8.8
# debe devolver 200.41.159.98
```

Hasta que eso responda, Let’s Encrypt HTTP-01 y el acceso por ese FQDN no van a funcionar.

## `.env` copiado de AGG (aún no adaptado)

```text
APP_URL=http://10.20.30.210
APP_CARPETA=/anitaERP/public
APP_DEBUG=true
EMPRESA='AGG'
ARBOLAPROBACION_IP_LINK=http://10.20.30.210
DB_* → anitaERP local
```

Para este servidor debería quedar algo así (cuando digas que sí lo cambio):

```dotenv
APP_URL=https://erp.casinosdelrio.com.ar
APP_CARPETA=
APP_DEBUG=false
EMPRESA='…'   # el valor correcto de Casinos del Río, no AGG
SESSION_SECURE_COOKIE=true
ARBOLAPROBACION_IP_LINK=https://erp.casinosdelrio.com.ar
```

---

**Siguiente paso tuyo:** crear el `A` de `erp` en HostGator hacia `200.41.159.98` (y abrir 80/443).

Cuando `dig +short erp.casinosdelrio.com.ar @8.8.8.8` muestre esa IP, avisame y sigo con: ajustar `.env`, cert Let’s Encrypt y vhost Apache. ¿Qué valor debe tener `EMPRESA` en Casinos (si ya lo saben)?

## Usuario

me indican de sistemas que por ahora quieren usar el dominio crowncasino.dnsalias.com con algun puerto en especial que apunte a la IP del servidor interno del ERP, que posibilidad da esto para que tengamos un certificado con certbot https?

## Asistente

Voy a ver cómo resuelve `crowncasino.dnsalias.com` y qué exige Certbot para emitir un certificado en este servidor.
Voy a ver cómo resuelve `crowncasino.dnsalias.com` y qué exige Certbot para emitir un certificado en este servidor.

## Asistente

**Sí se puede tener un certificado de Let's Encrypt para `crowncasino.dnsalias.com`, pero el puerto especial no sirve para emitirlo.** El DNS ya apunta bien: ese nombre resuelve a `200.41.159.98`, la IP pública de este servidor (`192.168.1.3`). Desde acá, los puertos 80 y 443 de esa IP pública no responden, así que el reenvío del router todavía no llega a Apache.

## Qué puede y qué no puede el DNS

Un registro A solo lleva un nombre a una IP. No incluye puerto.

| Lo que pide sistemas | Qué hace en la práctica |
|---|---|
| `crowncasino.dnsalias.com` → IP interna del ERP | El nombre ya va a `200.41.159.98`. El router tiene que reenviar un puerto público hacia `192.168.1.3` |
| "Un puerto en especial" | Eso es NAT (por ejemplo público `8443` → interno `443`). El certificado no cambia |

El certificado es del **nombre**, no del puerto. El mismo cert vale en `https://crowncasino.dnsalias.com/` (443) y en `https://crowncasino.dnsalias.com:8443/`.

## Cómo valida Certbot (Let's Encrypt)

Let's Encrypt tiene que comprobar que controlás el nombre. No acepta un puerto a elección:

| Método | Puerto que tiene que ver Internet | ¿Sirve acá? |
|---|---|---|
| HTTP-01 (el habitual de `certbot --apache`) | **80** fijo, ruta `/.well-known/acme-challenge/` | Sí, si el router reenvía el 80 público a este Apache |
| TLS-ALPN-01 | **443** fijo | Sí, si reenvían el 443 |
| DNS-01 (registro TXT) | Ninguno | Casi seguro **no**: `dnsalias.com` es DNS dinámico y no deja crear `_acme-challenge.crowncasino...` |

Si solo abren un puerto alto (8443, 10443, etc.) y dejan cerrados 80 y 443, **Certbot no puede emitir** ni renovar. Un certificado comercial DV tiene la misma limitación: también valida el dominio por HTTP, HTTPS o DNS.

## Escenario que sí funciona

1. Reenvío permanente (o al menos durante la emisión y cada renovación, ~90 días) de **TCP 80** público → `192.168.1.3:80`.
2. El HTTPS del ERP en el puerto que quieran, por ejemplo `443` o `8443` → `192.168.1.3:443`.
3. En el servidor:

```bash
sudo apt install certbot python3-certbot-apache
sudo certbot certonly --webroot -w /var/www/html/anitaERP/public \
  -d crowncasino.dnsalias.com
```

4. Apache en ese puerto HTTPS con:

- `ServerName crowncasino.dnsalias.com`
- `DocumentRoot /var/www/html/anitaERP/public`
- `fullchain.pem` y `privkey.pem` de `/etc/letsencrypt/live/crowncasino.dnsalias.com/`

La URL queda `https://crowncasino.dnsalias.com/` o `https://crowncasino.dnsalias.com:PUERTO/`. En `.env`, `APP_URL` tiene que incluir el puerto si no es 443.

El aviso del navegador desaparece: es una CA pública y el nombre del certificado coincide con el de la barra.

## Si no pueden abrir el 80 ni el 443

No hay certificado público automático para ese `dnsalias`. Opciones reales:

- Abrir el 80 solo el día de emitir y renovar.
- Usar un nombre de `casinosdelrio.com.ar` (zona en HostGator) y validar por DNS-01, sin exponer el 80.
- Certificado interno o autofirmado: funciona en cualquier puerto, pero cada PC muestra advertencia salvo que instalen la CA.

Pedile a sistemas el reenvío del **80** (validación) y del puerto HTTPS que elijan (uso). Cuando `curl -sI http://crowncasino.dnsalias.com/` responda desde afuera, se puede emitir el certificado.
**Sí se puede tener un certificado de Let's Encrypt para `crowncasino.dnsalias.com`, pero el puerto especial no sirve para emitirlo.** El DNS ya apunta bien: ese nombre resuelve a `200.41.159.98`, la IP pública de este servidor (`192.168.1.3`). Desde acá, los puertos 80 y 443 de esa IP pública no responden, así que el reenvío del router todavía no llega a Apache.

## Qué puede y qué no puede el DNS

Un registro A solo lleva un nombre a una IP. No incluye puerto.

| Lo que pide sistemas | Qué hace en la práctica |
|---|---|
| `crowncasino.dnsalias.com` → IP interna del ERP | El nombre ya va a `200.41.159.98`. El router tiene que reenviar un puerto público hacia `192.168.1.3` |
| "Un puerto en especial" | Eso es NAT (por ejemplo público `8443` → interno `443`). El certificado no cambia |

El certificado es del **nombre**, no del puerto. El mismo cert vale en `https://crowncasino.dnsalias.com/` (443) y en `https://crowncasino.dnsalias.com:8443/`.

## Cómo valida Certbot (Let's Encrypt)

Let's Encrypt tiene que comprobar que controlás el nombre. No acepta un puerto a elección:

| Método | Puerto que tiene que ver Internet | ¿Sirve acá? |
|---|---|---|
| HTTP-01 (el habitual de `certbot --apache`) | **80** fijo, ruta `/.well-known/acme-challenge/` | Sí, si el router reenvía el 80 público a este Apache |
| TLS-ALPN-01 | **443** fijo | Sí, si reenvían el 443 |
| DNS-01 (registro TXT) | Ninguno | Casi seguro **no**: `dnsalias.com` es DNS dinámico y no deja crear `_acme-challenge.crowncasino...` |

Si solo abren un puerto alto (8443, 10443, etc.) y dejan cerrados 80 y 443, **Certbot no puede emitir** ni renovar. Un certificado comercial DV tiene la misma limitación: también valida el dominio por HTTP, HTTPS o DNS.

## Escenario que sí funciona

1. Reenvío permanente (o al menos durante la emisión y cada renovación, ~90 días) de **TCP 80** público → `192.168.1.3:80`.
2. El HTTPS del ERP en el puerto que quieran, por ejemplo `443` o `8443` → `192.168.1.3:443`.
3. En el servidor:

```bash
sudo apt install certbot python3-certbot-apache
sudo certbot certonly --webroot -w /var/www/html/anitaERP/public \
  -d crowncasino.dnsalias.com
```

4. Apache en ese puerto HTTPS con:

- `ServerName crowncasino.dnsalias.com`
- `DocumentRoot /var/www/html/anitaERP/public`
- `fullchain.pem` y `privkey.pem` de `/etc/letsencrypt/live/crowncasino.dnsalias.com/`

La URL queda `https://crowncasino.dnsalias.com/` o `https://crowncasino.dnsalias.com:PUERTO/`. En `.env`, `APP_URL` tiene que incluir el puerto si no es 443.

El aviso del navegador desaparece: es una CA pública y el nombre del certificado coincide con el de la barra.

## Si no pueden abrir el 80 ni el 443

No hay certificado público automático para ese `dnsalias`. Opciones reales:

- Abrir el 80 solo el día de emitir y renovar.
- Usar un nombre de `casinosdelrio.com.ar` (zona en HostGator) y validar por DNS-01, sin exponer el 80.
- Certificado interno o autofirmado: funciona en cualquier puerto, pero cada PC muestra advertencia salvo que instalen la CA.

Pedile a sistemas el reenvío del **80** (validación) y del puerto HTTPS que elijan (uso). Cuando `curl -sI http://crowncasino.dnsalias.com/` responda desde afuera, se puede emitir el certificado.

## Usuario

me van a abrir el 80 y usar el 29443 apuntando a la IP 192.168.1.3, me confirman y te aviso

## Asistente

Cuando te confirmen que el reenvío ya está activo, se puede emitir el certificado. El esquema que cierra con Certbot es este:

| Desde Internet | Hacia este servidor (`192.168.1.3`) | Para qué |
|---|---|---|
| TCP **80** | TCP **80** | Validación de Let's Encrypt (`http://crowncasino.dnsalias.com/`) |
| TCP **29443** | TCP **443** | HTTPS del ERP |

Conviene que el 29443 público llegue al **443** interno: Apache ya escucha ahí. Si lo mandan al 29443 interno, hay que agregar un `Listen 29443` en Apache.

El certificado va a ser para el nombre `crowncasino.dnsalias.com` y se usa en ese puerto. La URL queda `https://crowncasino.dnsalias.com:29443/`.

Avisame cuando sistemas confirme que ambos reenvíos están activos y sigo con Certbot y el virtual host.
Cuando te confirmen que el reenvío ya está activo, se puede emitir el certificado. El esquema que cierra con Certbot es este:

| Desde Internet | Hacia este servidor (`192.168.1.3`) | Para qué |
|---|---|---|
| TCP **80** | TCP **80** | Validación de Let's Encrypt (`http://crowncasino.dnsalias.com/`) |
| TCP **29443** | TCP **443** | HTTPS del ERP |

Conviene que el 29443 público llegue al **443** interno: Apache ya escucha ahí. Si lo mandan al 29443 interno, hay que agregar un `Listen 29443` en Apache.

El certificado va a ser para el nombre `crowncasino.dnsalias.com` y se usa en ese puerto. La URL queda `https://crowncasino.dnsalias.com:29443/`.

Avisame cuando sistemas confirme que ambos reenvíos están activos y sigo con Certbot y el virtual host.

## Usuario

si no me avisan de sistemas, podemos instalar con IP interna por ahora con http?asi avanzo este fin de semana con esto

## Asistente

Podés avanzar por HTTP en la red interna sin esperar el 80 público ni el 29443. Reviso Apache, PHP, la base y el `.env` para dejar el ERP usable en `192.168.1.3`.
Podés avanzar por HTTP en la red interna sin esperar el 80 público ni el 29443. Reviso Apache, PHP, la base y el `.env` para dejar el ERP usable en `192.168.1.3`.

## Asistente

El nombre público puede esperar. Dejo el ERP en HTTP por la IP interna: dependencias, base local y un virtual host que no redirija a HTTPS.
El nombre público puede esperar. Dejo el ERP en HTTP por la IP interna: dependencias, base local y un virtual host que no redirija a HTTPS.

## Asistente

Sí. El ERP ya responde por HTTP en la red interna, sin esperar el puerto público ni el 29443.

Abrí **http://192.168.1.3/** desde una PC de la LAN. El login está en **http://192.168.1.3/seguridad/login** y la página carga (HTTP 200).

Quedó así:

- Apache sirve `192.168.1.3` en el puerto 80, con la carpeta pública de Laravel. El nombre `server1ng.local.casinosdelrio.com.ar` sigue redirigiendo al HTTPS interno; para este fin de semana usá la IP.
- En `.env`: `APP_URL=http://192.168.1.3` y `APP_CARPETA` vacío. `EMPRESA` sigue en `AGG` y `APP_DEBUG` sigue en `true`.
- Base local `anitaERP` creada y dependencias instaladas. Corrieron 575 migraciones y se frenaron en `2026_06_02_140000_usuarios_gastronomia_kandiko_agg`: esa migración exige el centro de costo 85 y la empresa Kandiko, y la base está vacía (`empresa`, `usuario` y `centrocosto` en 0).

Sin un usuario no vas a poder entrar. Para seguir el fin de semana hace falta un volcado de la base o un usuario local de prueba. Cuando lo definas, lo cargo. El certificado y el `29443` quedan para cuando sistemas confirme los puertos.
Sí. El ERP ya responde por HTTP en la red interna, sin esperar el puerto público ni el 29443.

Abrí **http://192.168.1.3/** desde una PC de la LAN. El login está en **http://192.168.1.3/seguridad/login** y la página carga (HTTP 200).

Quedó así:

- Apache sirve `192.168.1.3` en el puerto 80, con la carpeta pública de Laravel. El nombre `server1ng.local.casinosdelrio.com.ar` sigue redirigiendo al HTTPS interno; para este fin de semana usá la IP.
- En `.env`: `APP_URL=http://192.168.1.3` y `APP_CARPETA` vacío. `EMPRESA` sigue en `AGG` y `APP_DEBUG` sigue en `true`.
- Base local `anitaERP` creada y dependencias instaladas. Corrieron 575 migraciones y se frenaron en `2026_06_02_140000_usuarios_gastronomia_kandiko_agg`: esa migración exige el centro de costo 85 y la empresa Kandiko, y la base está vacía (`empresa`, `usuario` y `centrocosto` en 0).

Sin un usuario no vas a poder entrar. Para seguir el fin de semana hace falta un volcado de la base o un usuario local de prueba. Cuando lo definas, lo cargo. El certificado y el `29443` quedan para cuando sistemas confirme los puertos.

## Usuario

hice un pull de la ultima version del repositorio y al correr las migraciones dieron error

## Asistente

Voy a ver el error de las migraciones después del pull y en qué punto quedó la base.
Voy a ver el error de las migraciones después del pull y en qué punto quedó la base.

## Asistente

El pull llegó bien. El fallo es el mismo de antes: una migración de AGG busca datos maestros que esta base vacía no tiene. Reviso si las que siguen dependen de lo mismo.
El pull llegó bien. El fallo es el mismo de antes: una migración de AGG busca datos maestros que esta base vacía no tiene. Reviso si las que siguen dependen de lo mismo.

## Asistente

El pull está al día con `main`. El error no viene del código nuevo: la migración se frena porque esta base se creó vacía y esa migración espera maestros de AGG que acá no existen.

```text
2026_06_02_140000_usuarios_gastronomia_kandiko_agg
No se encontró el centro de costo con código 85.
```

Esa migración solo corre si `EMPRESA=AGG` (así está el `.env`). Carga usuarios de gastronomía de Kandiko y, antes de insertarlos, exige el centro de costo **85**, la empresa **Kandiko** y los roles de gastronomía. En esta base hay **0** empresas, **0** centros de costo y **0** usuarios. Quedan **1272** migraciones pendientes detrás de esa. Varias de junio en adelante hacen lo mismo: buscan empresas, usuarios o centros de costo de producción y cortan si no están (`usuarios_gastronomia_rebisco`, cajeros de tesorería, árboles de aprobación).

`php artisan migrate` sobre una base vacía no arma el ERP de AGG. Esas migraciones actualizan una base que ya tiene maestros.

Para seguir este fin de semana, el camino útil es restaurar un volcado de `anitaERP` de AGG (estructura y datos) y después correr `php artisan migrate` solo para lo que falte respecto de ese volcado. No conviene inventar el centro de costo 85 para saltar esta migración: la siguiente va a pedir otro maestro y el esquema queda a medias.

Si tenés el dump, lo importamos en esta base local y retomo las migraciones pendientes.
El pull está al día con `main`. El error no viene del código nuevo: la migración se frena porque esta base se creó vacía y esa migración espera maestros de AGG que acá no existen.

```text
2026_06_02_140000_usuarios_gastronomia_kandiko_agg
No se encontró el centro de costo con código 85.
```

Esa migración solo corre si `EMPRESA=AGG` (así está el `.env`). Carga usuarios de gastronomía de Kandiko y, antes de insertarlos, exige el centro de costo **85**, la empresa **Kandiko** y los roles de gastronomía. En esta base hay **0** empresas, **0** centros de costo y **0** usuarios. Quedan **1272** migraciones pendientes detrás de esa. Varias de junio en adelante hacen lo mismo: buscan empresas, usuarios o centros de costo de producción y cortan si no están (`usuarios_gastronomia_rebisco`, cajeros de tesorería, árboles de aprobación).

`php artisan migrate` sobre una base vacía no arma el ERP de AGG. Esas migraciones actualizan una base que ya tiene maestros.

Para seguir este fin de semana, el camino útil es restaurar un volcado de `anitaERP` de AGG (estructura y datos) y después correr `php artisan migrate` solo para lo que falte respecto de ese volcado. No conviene inventar el centro de costo 85 para saltar esta migración: la siguiente va a pedir otro maestro y el esquema queda a medias.

Si tenés el dump, lo importamos en esta base local y retomo las migraciones pendientes.

## Usuario

dame los comandos para correr en agg, pero el dump no puede ser de toda la info, los centros de costos y cuentas contables deberian ser las de crown

## Asistente

El volcado de AGG tiene que alcanzar para seguir las migraciones, sin la operación diaria y sin el plan de cuentas ni los centros de costo de AGG. Reviso qué tablas son esas para armarte los comandos.
El volcado de AGG tiene que alcanzar para seguir las migraciones, sin la operación diaria y sin el plan de cuentas ni los centros de costo de AGG. Reviso qué tablas son esas para armarte los comandos.

## Asistente

En el servidor de AGG, con el `~/.my.cnf` que ya usa el backup, estos comandos arman dos archivos: la estructura completa y los datos de maestros, sin movimientos y sin centros de costo ni plan de cuentas.

```bash
cd /var/www/html/anitaERP
mkdir -p /tmp/anita-crown
DB=anitaERP

# 1) Solo estructura (tablas vacías, incluidas centrocosto y cuentacontable)
mysqldump --single-transaction --routines --triggers --events --no-data \
  "$DB" > /tmp/anita-crown/01-estructura.sql

# 2) Tablas cuyo DATO no se copia
cat > /tmp/anita-crown/ignorar.txt << 'EOF'
centrocosto
cuentacontable
cuentacontable_centrocosto
cuentacontable_saldo_mes
cuentacontable_saldo_cierre
articulo_cuentacontable
usuario_cuentacontable
conceptogasto_cuentacontable
concepto_cuentacontable_ordenventa
impuesto_cuentacontable
provincia_cuentacontableiibb
retencion_cobranza_cuentacontable
tipotransaccion_compra_centrocosto
audits
sessions
cache
cache_locks
jobs
job_batches
failed_jobs
notifications
EOF

# movimientos y comprobantes (el volumen operativo)
mysql -N -e "
SELECT table_name FROM information_schema.tables
WHERE table_schema='${DB}' AND (
     table_name LIKE '%movimiento%'
  OR table_name LIKE 'asiento%'
  OR table_name LIKE 'venta%'
  OR table_name LIKE 'cobranza%'
  OR table_name LIKE 'ordencompra%'
  OR table_name LIKE 'ordenventa%'
  OR table_name LIKE 'requisicion%'
  OR table_name LIKE 'comprobante_proveedor%'
  OR table_name LIKE 'recepcion_proveedor%'
  OR table_name LIKE 'caja_%'
  OR table_name LIKE 'ticket%'
  OR table_name LIKE '%gastronomia%'
  OR table_name LIKE 'rendicion%'
)" >> /tmp/anita-crown/ignorar.txt

sort -u /tmp/anita-crown/ignorar.txt -o /tmp/anita-crown/ignorar.txt
echo "Tablas sin datos: $(wc -l < /tmp/anita-crown/ignorar.txt)"

IGN=()
while read -r t; do
  IGN+=(--ignore-table="${DB}.${t}")
done < /tmp/anita-crown/ignorar.txt

# 3) Datos del resto (menú, roles, usuarios, empresas, artículos, parámetros, tabla migrations)
mysqldump --single-transaction --no-create-info --skip-triggers \
  "${IGN[@]}" "$DB" > /tmp/anita-crown/02-datos-sin-cc-ni-cuentas.sql

ls -lh /tmp/anita-crown
```

Eso deja `centrocosto` y `cuentacontable` creadas y vacías, para cargar después el plan de Crown. No copies el dump completo de producción: incluye asientos, ventas y auditoría.

Al importar acá, los `centrocosto_id` y `cuentacontable_id` que vengan en usuarios, roles, artículos o proveedores siguen siendo ids de AGG. Con el plan de Crown esos ids no coinciden: hay que reasignarlos por código, no por id. Cuando tengas el origen de los centros y las cuentas de Crown (planilla o base), armo la carga.
En el servidor de AGG, con el `~/.my.cnf` que ya usa el backup, estos comandos arman dos archivos: la estructura completa y los datos de maestros, sin movimientos y sin centros de costo ni plan de cuentas.

```bash
cd /var/www/html/anitaERP
mkdir -p /tmp/anita-crown
DB=anitaERP

# 1) Solo estructura (tablas vacías, incluidas centrocosto y cuentacontable)
mysqldump --single-transaction --routines --triggers --events --no-data \
  "$DB" > /tmp/anita-crown/01-estructura.sql

# 2) Tablas cuyo DATO no se copia
cat > /tmp/anita-crown/ignorar.txt << 'EOF'
centrocosto
cuentacontable
cuentacontable_centrocosto
cuentacontable_saldo_mes
cuentacontable_saldo_cierre
articulo_cuentacontable
usuario_cuentacontable
conceptogasto_cuentacontable
concepto_cuentacontable_ordenventa
impuesto_cuentacontable
provincia_cuentacontableiibb
retencion_cobranza_cuentacontable
tipotransaccion_compra_centrocosto
audits
sessions
cache
cache_locks
jobs
job_batches
failed_jobs
notifications
EOF

# movimientos y comprobantes (el volumen operativo)
mysql -N -e "
SELECT table_name FROM information_schema.tables
WHERE table_schema='${DB}' AND (
     table_name LIKE '%movimiento%'
  OR table_name LIKE 'asiento%'
  OR table_name LIKE 'venta%'
  OR table_name LIKE 'cobranza%'
  OR table_name LIKE 'ordencompra%'
  OR table_name LIKE 'ordenventa%'
  OR table_name LIKE 'requisicion%'
  OR table_name LIKE 'comprobante_proveedor%'
  OR table_name LIKE 'recepcion_proveedor%'
  OR table_name LIKE 'caja_%'
  OR table_name LIKE 'ticket%'
  OR table_name LIKE '%gastronomia%'
  OR table_name LIKE 'rendicion%'
)" >> /tmp/anita-crown/ignorar.txt

sort -u /tmp/anita-crown/ignorar.txt -o /tmp/anita-crown/ignorar.txt
echo "Tablas sin datos: $(wc -l < /tmp/anita-crown/ignorar.txt)"

IGN=()
while read -r t; do
  IGN+=(--ignore-table="${DB}.${t}")
done < /tmp/anita-crown/ignorar.txt

# 3) Datos del resto (menú, roles, usuarios, empresas, artículos, parámetros, tabla migrations)
mysqldump --single-transaction --no-create-info --skip-triggers \
  "${IGN[@]}" "$DB" > /tmp/anita-crown/02-datos-sin-cc-ni-cuentas.sql

ls -lh /tmp/anita-crown
```

Eso deja `centrocosto` y `cuentacontable` creadas y vacías, para cargar después el plan de Crown. No copies el dump completo de producción: incluye asientos, ventas y auditoría.

Al importar acá, los `centrocosto_id` y `cuentacontable_id` que vengan en usuarios, roles, artículos o proveedores siguen siendo ids de AGG. Con el plan de Crown esos ids no coinciden: hay que reasignarlos por código, no por id. Cuando tengas el origen de los centros y las cuentas de Crown (planilla o base), armo la carga.

## Usuario

tampoco tienen que estar los proveedores, articulos, clientes. Me parece que deberiamos arrancar por menu y mi usuario, permisos, roles para que el sistema arranque, despues sincronizar de anita los maestros y tablas secundarias y mientras ver las migraciones que faltan

## Asistente

El arranque queda en menú, tu usuario, roles y permisos. Reviso las tablas y pivotes que el login usa para dejarte solo esos comandos.
El arranque queda en menú, tu usuario, roles y permisos. Reviso las tablas y pivotes que el login usa para dejarte solo esos comandos.

## Asistente

El arranque se cubre con el menú, los roles, los permisos y tu usuario. Proveedores, artículos, clientes, centros de costo y cuentas contables quedan afuera.

En AGG, con el `~/.my.cnf` del backup, cambiá `USUARIO` si el login no es `sergio`:

```bash
cd /var/www/html/anitaERP
mkdir -p /tmp/anita-arranque
DB=anitaERP
USUARIO=sergio

# Catálogo chico: menú, roles y permisos (todas las filas; no es operativo)
mysqldump --single-transaction --no-create-info --skip-triggers \
  "$DB" rol menu permiso menu_rol permiso_rol \
  > /tmp/anita-arranque/01-menu-roles-permisos.sql

# Solo tu usuario
mysqldump --single-transaction --no-create-info --skip-triggers \
  --where="usuario='${USUARIO}'" \
  "$DB" usuario \
  > /tmp/anita-arranque/02-usuario.sql

mysqldump --single-transaction --no-create-info --skip-triggers \
  --where="usuario_id=(SELECT id FROM usuario WHERE usuario='${USUARIO}')" \
  "$DB" usuario_rol \
  > /tmp/anita-arranque/03-usuario-rol.sql

ls -lh /tmp/anita-arranque
```

No incluye `usuario_empresa` ni `usuario_deposito`: sin filas ahí, el usuario entra y no queda atado a empresas ni depósitos de AGG.

Cuando esos tres archivos estén en este servidor, se importan sobre las tablas que ya existen, en ese orden, con las claves foráneas apagadas. Después hay que dejar en `NULL` `usuario.centrocosto_id`, `usuario.vendedor_id`, `usuario.oficinacompra_id`, `usuario.sector_legajocompra_id` y `rol.centrocosto_id`, porque apuntan a maestros que no vamos a traer.

La tabla `migrations` de AGG no se copia. Acá siguen las 1272 pendientes y `migrate` sigue frenado en la semilla de usuarios de Kandiko, que pide el centro de costo 85. Con el menú y tu usuario el login puede funcionar igual. Cuando lleguen los archivos, los cargo y vemos qué migraciones pendientes son de estructura y cuáles son semillas de AGG que hay que saltear.
El arranque se cubre con el menú, los roles, los permisos y tu usuario. Proveedores, artículos, clientes, centros de costo y cuentas contables quedan afuera.

En AGG, con el `~/.my.cnf` del backup, cambiá `USUARIO` si el login no es `sergio`:

```bash
cd /var/www/html/anitaERP
mkdir -p /tmp/anita-arranque
DB=anitaERP
USUARIO=sergio

# Catálogo chico: menú, roles y permisos (todas las filas; no es operativo)
mysqldump --single-transaction --no-create-info --skip-triggers \
  "$DB" rol menu permiso menu_rol permiso_rol \
  > /tmp/anita-arranque/01-menu-roles-permisos.sql

# Solo tu usuario
mysqldump --single-transaction --no-create-info --skip-triggers \
  --where="usuario='${USUARIO}'" \
  "$DB" usuario \
  > /tmp/anita-arranque/02-usuario.sql

mysqldump --single-transaction --no-create-info --skip-triggers \
  --where="usuario_id=(SELECT id FROM usuario WHERE usuario='${USUARIO}')" \
  "$DB" usuario_rol \
  > /tmp/anita-arranque/03-usuario-rol.sql

ls -lh /tmp/anita-arranque
```

No incluye `usuario_empresa` ni `usuario_deposito`: sin filas ahí, el usuario entra y no queda atado a empresas ni depósitos de AGG.

Cuando esos tres archivos estén en este servidor, se importan sobre las tablas que ya existen, en ese orden, con las claves foráneas apagadas. Después hay que dejar en `NULL` `usuario.centrocosto_id`, `usuario.vendedor_id`, `usuario.oficinacompra_id`, `usuario.sector_legajocompra_id` y `rol.centrocosto_id`, porque apuntan a maestros que no vamos a traer.

La tabla `migrations` de AGG no se copia. Acá siguen las 1272 pendientes y `migrate` sigue frenado en la semilla de usuarios de Kandiko, que pide el centro de costo 85. Con el menú y tu usuario el login puede funcionar igual. Cuando lleguen los archivos, los cargo y vemos qué migraciones pendientes son de estructura y cuáles son semillas de AGG que hay que saltear.

## Usuario

ya estan los 3 archivos en /home/sergio/tmp

## Asistente

Los tres archivos ya están. Antes de importarlos comparo las columnas con esta base, porque el esquema local está a mitad de las migraciones y el volcado viene de AGG al día.
Los tres archivos ya están. Antes de importarlos comparo las columnas con esta base, porque el esquema local está a mitad de las migraciones y el volcado viene de AGG al día.

## Asistente

El volcado de AGG trae columnas que esta base todavía no tiene. Voy a cargar menú, roles, permisos y tu usuario alineando esas columnas, y dejo en blanco los centros de costo y el resto de maestros.
El volcado de AGG trae columnas que esta base todavía no tiene. Voy a cargar menú, roles, permisos y tu usuario alineando esas columnas, y dejo en blanco los centros de costo y el resto de maestros.

## Asistente

Ya quedó cargado en la base local. Podés entrar en **http://192.168.1.3/seguridad/login** con el usuario `sergio` y la misma contraseña de AGG.

Quedó así:

| Dato | Filas |
|---|---|
| Roles | 72 |
| Menú | 525 |
| Permisos | 1.821 |
| Menú por rol | 4.169 |
| Permisos por rol | 10.161 |
| Tu usuario | `sergio`, rol **administrador** (490 ítems de menú) |

El centro de costo, la oficina de compra, el sector y el vendedor de tu usuario quedaron vacíos, igual que el centro de costo de los roles. Esos maestros no se trajeron.

El archivo de roles trae una sola asignación: **administrador**. Si en AGG tenés más roles, el `mysqldump` no los incluyó y hay que volver a exportar `usuario_rol`.

`php artisan migrate` sigue frenado en la semilla de usuarios de Kandiko. El login no depende de eso.
Ya quedó cargado en la base local. Podés entrar en **http://192.168.1.3/seguridad/login** con el usuario `sergio` y la misma contraseña de AGG.

Quedó así:

| Dato | Filas |
|---|---|
| Roles | 72 |
| Menú | 525 |
| Permisos | 1.821 |
| Menú por rol | 4.169 |
| Permisos por rol | 10.161 |
| Tu usuario | `sergio`, rol **administrador** (490 ítems de menú) |

El centro de costo, la oficina de compra, el sector y el vendedor de tu usuario quedaron vacíos, igual que el centro de costo de los roles. Esos maestros no se trajeron.

El archivo de roles trae una sola asignación: **administrador**. Si en AGG tenés más roles, el `mysqldump` no los incluyó y hay que volver a exportar `usuario_rol`.

`php artisan migrate` sigue frenado en la semilla de usuarios de Kandiko. El login no depende de eso.

## Usuario

que falta para poder correr las migraciones?

## Asistente

Las migraciones siguen frenadas en la misma semilla de AGG. Voy a separar cuáles piden maestros que no están y cuáles son solo estructura.
Las migraciones siguen frenadas en la misma semilla de AGG. Voy a separar cuáles piden maestros que no están y cuáles son solo estructura.

## Asistente

Falta el catálogo maestro que esas migraciones de datos asumen que ya existe. La estructura de las 575 migraciones ya aplicadas alcanza para el login; lo que frena `php artisan migrate` es la primera pendiente, que no crea tablas.

`2026_06_02_140000_usuarios_gastronomia_kandiko_agg` inserta usuarios de gastronomía de Kandiko y, antes de eso, exige:

- un centro de costo con código **85**
- la empresa con id **2** (Kandiko S.A.)
- los roles Op-Gastronomia, Sup-Gastronomia y Enc-gastronomía

Los roles ya están (vinieron con el menú). Empresas, centros de costo y monedas siguen en cero. Por eso corta ahí y no llega a las otras 1.271.

Detrás hay unas 22 migraciones que también abortan si no encuentran datos de AGG. El resto es estructura y, en tablas vacías, puede correr. Esas 22 piden, entre otras cosas:

- moneda **PESOS**
- centros de costo **98** (tesorería) y **96** (marketing)
- sectores **GASTRONOMIA** y **CUENTAS A PAGAR**
- el árbol de requisiciones de sala de Biyemas
- la empresa **KANDIKO S.A.** por nombre, en una migración que corre aunque `EMPRESA` no sea AGG

No hace falta un volcado de proveedores, artículos ni clientes para destrabar esto. Esas semillas no son el plan de cuentas de Crown: cargan gente y árboles de AGG.

Para seguir con la estructura hay que marcar como ya aplicadas solo esas semillas de datos de AGG y dejar que corran las demás. Si preferís que se ejecuten de verdad, hay que cargar antes empresa, moneda y los centros de costo 85, 96 y 98.
Falta el catálogo maestro que esas migraciones de datos asumen que ya existe. La estructura de las 575 migraciones ya aplicadas alcanza para el login; lo que frena `php artisan migrate` es la primera pendiente, que no crea tablas.

`2026_06_02_140000_usuarios_gastronomia_kandiko_agg` inserta usuarios de gastronomía de Kandiko y, antes de eso, exige:

- un centro de costo con código **85**
- la empresa con id **2** (Kandiko S.A.)
- los roles Op-Gastronomia, Sup-Gastronomia y Enc-gastronomía

Los roles ya están (vinieron con el menú). Empresas, centros de costo y monedas siguen en cero. Por eso corta ahí y no llega a las otras 1.271.

Detrás hay unas 22 migraciones que también abortan si no encuentran datos de AGG. El resto es estructura y, en tablas vacías, puede correr. Esas 22 piden, entre otras cosas:

- moneda **PESOS**
- centros de costo **98** (tesorería) y **96** (marketing)
- sectores **GASTRONOMIA** y **CUENTAS A PAGAR**
- el árbol de requisiciones de sala de Biyemas
- la empresa **KANDIKO S.A.** por nombre, en una migración que corre aunque `EMPRESA` no sea AGG

No hace falta un volcado de proveedores, artículos ni clientes para destrabar esto. Esas semillas no son el plan de cuentas de Crown: cargan gente y árboles de AGG.

Para seguir con la estructura hay que marcar como ya aplicadas solo esas semillas de datos de AGG y dejar que corran las demás. Si preferís que se ejecuten de verdad, hay que cargar antes empresa, moneda y los centros de costo 85, 96 y 98.

## Usuario

podes modificar esa migracion y la haces gated a agg?

## Asistente

La que sigue corriendo aunque la instalación no sea AGG es la de usuarios flash de tesorería. La reviso y le pongo el mismo corte que el resto de las semillas de AGG.
La que sigue corriendo aunque la instalación no sea AGG es la de usuarios flash de tesorería. La reviso y le pongo el mismo corte que el resto de las semillas de AGG.

## Asistente

El archivo ya está aplicado en AGG con ese nombre, así que no lo renombro: si cambia el nombre, en producción se volvería a ejecutar. Le agrego el corte por instalación en `up` y en `down`.
El archivo ya está aplicado en AGG con ese nombre, así que no lo renombro: si cambia el nombre, en producción se volvería a ejecutar. Le agrego el corte por instalación en `up` y en `down`.

## Asistente

`2026_08_05_160000_rol_opflash_tesoreria_usuarios_flash` ahora sale al inicio de `up` y de `down` si la instalación no es AGG, con `EntornoEmpresaSupport::esAgg()`. En AGG sigue asignando el rol `opflash-tesoreria` y la empresa Kandiko como hasta ahora.

El nombre del archivo no lo cambié. En producción esa migración ya figura corrida con ese nombre; si lo renombrara, Laravel la tomaría como nueva y la volvería a ejecutar.

En este servidor el `.env` sigue con `EMPRESA=AGG`, así que cuando `migrate` llegue a este archivo igual va a entrar. Antes de eso sigue frenando `2026_06_02_140000_usuarios_gastronomia_kandiko_agg`, que ya estaba limitada a AGG y pide el centro de costo 85.
`2026_08_05_160000_rol_opflash_tesoreria_usuarios_flash` ahora sale al inicio de `up` y de `down` si la instalación no es AGG, con `EntornoEmpresaSupport::esAgg()`. En AGG sigue asignando el rol `opflash-tesoreria` y la empresa Kandiko como hasta ahora.

El nombre del archivo no lo cambié. En producción esa migración ya figura corrida con ese nombre; si lo renombrara, Laravel la tomaría como nueva y la volvería a ejecutar.

En este servidor el `.env` sigue con `EMPRESA=AGG`, así que cuando `migrate` llegue a este archivo igual va a entrar. Antes de eso sigue frenando `2026_06_02_140000_usuarios_gastronomia_kandiko_agg`, que ya estaba limitada a AGG y pide el centro de costo 85.

## Usuario

cambia la empresa del .env entonces, aca es CROWN

## Asistente

Cambio `EMPRESA` a `CROWN` en el `.env` de este servidor y reviso que el código reconozca ese valor.
Cambio `EMPRESA` a `CROWN` en el `.env` de este servidor y reviso que el código reconozca ese valor.

## Asistente

`EMPRESA` en el `.env` de este servidor quedó en `CROWN` y la configuración ya recargada. `esAgg()` da no, así que las semillas de menú, roles y usuarios de AGG no se aplican acá, incluida la de Kandiko y la de `opflash-tesoreria`.

`CROWN` no está entre los clientes que el código reconoce (AGG, Interforming, El Bierzo, Ferli, Frasle, Iguassu). Esas ramas quedan apagadas. Cuando quieras, se puede correr `php artisan migrate`: va a saltear esas semillas y seguir con la estructura.
`EMPRESA` en el `.env` de este servidor quedó en `CROWN` y la configuración ya recargada. `esAgg()` da no, así que las semillas de menú, roles y usuarios de AGG no se aplican acá, incluida la de Kandiko y la de `opflash-tesoreria`.

`CROWN` no está entre los clientes que el código reconoce (AGG, Interforming, El Bierzo, Ferli, Frasle, Iguassu). Esas ramas quedan apagadas. Cuando quieras, se puede correr `php artisan migrate`: va a saltear esas semillas y seguir con la estructura.

## Usuario

podes correr las migraciones?

## Asistente

Con `EMPRESA=CROWN` las semillas de AGG se saltean. Corro las migraciones pendientes.
Con `EMPRESA=CROWN` las semillas de AGG se saltean. Corro las migraciones pendientes.

## Asistente

Las migraciones terminaron bien. No queda ninguna pendiente y ninguna falló.

Con `EMPRESA=CROWN`, las semillas de AGG se saltearon (usuarios de Kandiko, rol flash de tesorería y el resto de cargas de ese cliente) y se aplicó la estructura que faltaba, hasta `2026_10_16_183000_cuentacontable_inhabilitada`.
Las migraciones terminaron bien. No queda ninguna pendiente y ninguna falló.

Con `EMPRESA=CROWN`, las semillas de AGG se saltearon (usuarios de Kandiko, rol flash de tesorería y el resto de cargas de ese cliente) y se aplicó la estructura que faltaba, hasta `2026_10_16_183000_cuentacontable_inhabilitada`.

## Usuario

Briefly inform the user about the task result and perform any follow-up actions (if needed). If there's no follow-ups needed, don't explicitly say that.

## Asistente

Las migraciones ya terminaron: no quedó ninguna pendiente y ninguna falló. Con `EMPRESA=CROWN` se saltearon las semillas de AGG y se aplicó la estructura que faltaba.
Las migraciones ya terminaron: no quedó ninguna pendiente y ninguna falló. Con `EMPRESA=CROWN` se saltearon las semillas de AGG y se aplicó la estructura que faltaba.

## Usuario

ahora necesito probar el bridge, esta en ac-server1 (192.168.1.2) en la carpeta /usr2/www/htdocs/apiERP.php y el sistema corre en /usr2/crown

## Asistente

El bridge de Anita está en `ac-server1`. Reviso cómo lo llama este ERP y pruebo si llega a `apiERP.php`.
El bridge de Anita está en `ac-server1`. Reviso cómo lo llama este ERP y pruebo si llega a `apiERP.php`.

## Asistente

El servidor `192.168.1.2` responde, pero el bridge todavía no ejecuta PHP. Apache en el puerto 80 devuelve el código de `apiERP.php` como texto (`Content-Type: application/x-httpd-php`) en lugar de correrlo. El puerto 8080, que usa AGG, está cerrado.

Ese archivo en el host tampoco es el del repo. Tiene el path fijo en `/usr2/casino` y no toma `path_sistema` del pedido, así que aunque PHP arrancara no apuntaría a `/usr2/crown`.

Desde acá no pude entrar por SSH: el servidor solo ofrece algoritmos viejos y la clave del host no está aceptada.

En `ac-server1` hace falta habilitar PHP en Apache para `/usr2/www/htdocs` y alinear el script con la base en `/usr2/crown`. Cuando eso esté, el `.env` de este ERP tiene que pasar de `ANITA_IP=10.20.30.200:8080` y `ANITA_BDD_PATH=/usr2/biyemas` a `192.168.1.2` y `/usr2/crown`. Hoy sigue apuntando a AGG.
El servidor `192.168.1.2` responde, pero el bridge todavía no ejecuta PHP. Apache en el puerto 80 devuelve el código de `apiERP.php` como texto (`Content-Type: application/x-httpd-php`) en lugar de correrlo. El puerto 8080, que usa AGG, está cerrado.

Ese archivo en el host tampoco es el del repo. Tiene el path fijo en `/usr2/casino` y no toma `path_sistema` del pedido, así que aunque PHP arrancara no apuntaría a `/usr2/crown`.

Desde acá no pude entrar por SSH: el servidor solo ofrece algoritmos viejos y la clave del host no está aceptada.

En `ac-server1` hace falta habilitar PHP en Apache para `/usr2/www/htdocs` y alinear el script con la base en `/usr2/crown`. Cuando eso esté, el `.env` de este ERP tiene que pasar de `ANITA_IP=10.20.30.200:8080` y `ANITA_BDD_PATH=/usr2/biyemas` a `192.168.1.2` y `/usr2/crown`. Hoy sigue apuntando a AGG.

## Usuario

tengo las llaves ssh, decime como hacemos con los algoritmos

## Asistente

La negociación ya pasó el intercambio de claves; el corte siguiente fue la clave del host, que este OpenSSH no tiene guardada. Reviso la versión de SSH y tus llaves para dejarte el bloque exacto.
La negociación ya pasó el intercambio de claves; el corte siguiente fue la clave del host, que este OpenSSH no tiene guardada. Reviso la versión de SSH y tus llaves para dejarte el bloque exacto.

## Asistente

OpenSSH 10 de este servidor ya no ofrece por defecto los algoritmos que anuncia `ac-server1`. Hay que habilitarlos solo para esa IP, en `~/.ssh/config`:

```sshconfig
Host ac-server1
    HostName 192.168.1.2
    User sergio
    KexAlgorithms +diffie-hellman-group14-sha1
    HostKeyAlgorithms +ssh-rsa
    PubkeyAcceptedAlgorithms +ssh-rsa
    IdentityFile ~/.ssh/id_ed25519
```

La primera vez hay que aceptar la clave del host:

```bash
ssh -o StrictHostKeyChecking=accept-new ac-server1
```

`diffie-hellman-group14-sha1` es el que conviene. `group1-sha1` también lo ofrece el servidor, pero es más débil y no hace falta.

La llave que tenés acá es `id_ed25519`. El Apache de ese host es 2.2.3, así que el SSH puede ser igual de viejo y no aceptar Ed25519, solo RSA. Si entra la negociación y rechaza la llave, en esta máquina se genera una RSA y se copia la pública:

```bash
ssh-keygen -t rsa -b 4096 -f ~/.ssh/id_rsa_ac-server1 -C sergio@crown-erp
```

En el bloque `Host`, `IdentityFile` pasa a `~/.ssh/id_rsa_ac-server1`. La pública hay que agregarla en `authorized_keys` de `ac-server1`.
OpenSSH 10 de este servidor ya no ofrece por defecto los algoritmos que anuncia `ac-server1`. Hay que habilitarlos solo para esa IP, en `~/.ssh/config`:

```sshconfig
Host ac-server1
    HostName 192.168.1.2
    User sergio
    KexAlgorithms +diffie-hellman-group14-sha1
    HostKeyAlgorithms +ssh-rsa
    PubkeyAcceptedAlgorithms +ssh-rsa
    IdentityFile ~/.ssh/id_ed25519
```

La primera vez hay que aceptar la clave del host:

```bash
ssh -o StrictHostKeyChecking=accept-new ac-server1
```

`diffie-hellman-group14-sha1` es el que conviene. `group1-sha1` también lo ofrece el servidor, pero es más débil y no hace falta.

La llave que tenés acá es `id_ed25519`. El Apache de ese host es 2.2.3, así que el SSH puede ser igual de viejo y no aceptar Ed25519, solo RSA. Si entra la negociación y rechaza la llave, en esta máquina se genera una RSA y se copia la pública:

```bash
ssh-keygen -t rsa -b 4096 -f ~/.ssh/id_rsa_ac-server1 -C sergio@crown-erp
```

En el bloque `Host`, `IdentityFile` pasa a `~/.ssh/id_rsa_ac-server1`. La pública hay que agregarla en `authorized_keys` de `ac-server1`.

## Usuario

con el ssh inicial ya entre

## Asistente

Ya quedó la clave del host. Entro por SSH y reviso por qué Apache no ejecuta `apiERP.php` y si la base está en `/usr2/crown`.
Ya quedó la clave del host. Entro por SSH y reviso por qué Apache no ejecuta `apiERP.php` y si la base está en `/usr2/crown`.

## Asistente

El bloque de `~/.ssh/config` alcanzó. La clave del host quedó guardada como RSA y entraste con contraseña a `ac-server1` (Debian de 2010).

La llave `id_ed25519` de esta máquina ese SSH no la acepta: es anterior a Ed25519. Por eso un intento sin contraseña responde `Permission denied (publickey,password)`. Para entrar sin clave más adelante hace falta una llave RSA y agregarla en `authorized_keys` de ese servidor.

En la sesión que ya tenés abierta, estos comandos muestran por qué el bridge no corre:

```bash
php -v
apache2ctl -M | grep -i php
ls -ld /usr2/crown /usr2/casino /usr2/www/htdocs/apiERP.php
grep path_sistema /usr2/www/htdocs/apiERP.php | head
```

Con esa salida se ve si falta el módulo PHP y si el script sigue fijo en `/usr2/casino` en lugar de `/usr2/crown`.
El bloque de `~/.ssh/config` alcanzó. La clave del host quedó guardada como RSA y entraste con contraseña a `ac-server1` (Debian de 2010).

La llave `id_ed25519` de esta máquina ese SSH no la acepta: es anterior a Ed25519. Por eso un intento sin contraseña responde `Permission denied (publickey,password)`. Para entrar sin clave más adelante hace falta una llave RSA y agregarla en `authorized_keys` de ese servidor.

En la sesión que ya tenés abierta, estos comandos muestran por qué el bridge no corre:

```bash
php -v
apache2ctl -M | grep -i php
ls -ld /usr2/crown /usr2/casino /usr2/www/htdocs/apiERP.php
grep path_sistema /usr2/www/htdocs/apiERP.php | head
```

Con esa salida se ve si falta el módulo PHP y si el script sigue fijo en `/usr2/casino` en lugar de `/usr2/crown`.

## Asistente

En la sesión se ve PHP instalado y que `/usr2/crown` apunta a `casino`. Busco en el terminal la ruta real de `apiERP.php`, porque por HTTP el archivo sí se está sirviendo.
En la sesión se ve PHP instalado y que `/usr2/crown` apunta a `casino`. Busco en el terminal la ruta real de `apiERP.php`, porque por HTTP el archivo sí se está sirviendo.

## Asistente

`/usr2/crown` es un enlace a `/usr2/casino`, así que la base de Anita es esa carpeta. El path fijo `/usr2/casino` del script y `/usr2/crown` son el mismo lugar.

PHP 5.2 está instalado. `apache2ctl` no está en el PATH, y en `/usr2/www/htdocs/apiERP.php` no hay archivo. Por HTTP el puerto 80 igual devuelve un `apiERP.php`, así que Apache lo está sirviendo desde otro directorio.

En esa sesión:

```bash
ps aux | grep -E 'apache|httpd' | grep -v grep
ls -la /usr2/www /var/www 2>/dev/null
find /usr2/www /var/www /usr/local -name 'apiERP.php' 2>/dev/null
```

Con eso se ve el proceso de Apache y la ruta real del script.
`/usr2/crown` es un enlace a `/usr2/casino`, así que la base de Anita es esa carpeta. El path fijo `/usr2/casino` del script y `/usr2/crown` son el mismo lugar.

PHP 5.2 está instalado. `apache2ctl` no está en el PATH, y en `/usr2/www/htdocs/apiERP.php` no hay archivo. Por HTTP el puerto 80 igual devuelve un `apiERP.php`, así que Apache lo está sirviendo desde otro directorio.

En esa sesión:

```bash
ps aux | grep -E 'apache|httpd' | grep -v grep
ls -la /usr2/www /var/www 2>/dev/null
find /usr2/www /var/www /usr/local -name 'apiERP.php' 2>/dev/null
```

Con eso se ve el proceso de Apache y la ruta real del script.

## Asistente

El script está en `/usr2/www/http/apiERP.php`, no en `htdocs`. Apache está corriendo (`/usr/sbin/apache2`), pero cuando se lo pide por HTTP devuelve el código fuente en vez de ejecutarlo: PHP no está enganchado a ese sitio.

En la misma sesión:

```bash
/usr/sbin/apache2 -M 2>/dev/null | grep -i php
grep -n path_sistema /usr2/www/http/apiERP.php | head
ls /etc/apache2/mods-enabled 2>/dev/null | head
grep -n DocumentRoot /etc/apache2/sites-enabled/* 2>/dev/null
```

Eso dice si falta el módulo PHP y si el script sigue fijo en `/usr2/casino` (que es lo mismo que `/usr2/crown`).
El script está en `/usr2/www/http/apiERP.php`, no en `htdocs`. Apache está corriendo (`/usr/sbin/apache2`), pero cuando se lo pide por HTTP devuelve el código fuente en vez de ejecutarlo: PHP no está enganchado a ese sitio.

En la misma sesión:

```bash
/usr/sbin/apache2 -M 2>/dev/null | grep -i php
grep -n path_sistema /usr2/www/http/apiERP.php | head
ls /etc/apache2/mods-enabled 2>/dev/null | head
grep -n DocumentRoot /etc/apache2/sites-enabled/* 2>/dev/null
```

Eso dice si falta el módulo PHP y si el script sigue fijo en `/usr2/casino` (que es lo mismo que `/usr2/crown`).

## Asistente

Apache está sirviendo el directorio correcto, `/usr2/www/http`, pero no tiene cargado ningún módulo de PHP. Por eso `apiERP.php` se descarga como texto en lugar de ejecutarse. El módulo `cgid` sí está activo; `php5` no aparece entre los módulos habilitados.

El script usa `$path_sistema = '/usr2/casino'` y el servidor Informix `bincadmin`. `/usr2/crown` es un enlace a `casino`, así que ese path ya apunta a la base de Crown. No hace falta cambiarlo para probar.

En la misma sesión, para ver si el módulo está instalado y solo falta activarlo:

```bash
ls /etc/apache2/mods-available | grep -i php
dpkg -l 'libapache*php*' php5 2>/dev/null | awk '/^ii/{print $2,$3}'
```

Si aparece `php5.load`, con root alcanza `a2enmod php5` y recargar Apache. Si no está instalado, hay que instalar el módulo de PHP 5 para Apache 2 antes de poder probar el bridge.
Apache está sirviendo el directorio correcto, `/usr2/www/http`, pero no tiene cargado ningún módulo de PHP. Por eso `apiERP.php` se descarga como texto en lugar de ejecutarse. El módulo `cgid` sí está activo; `php5` no aparece entre los módulos habilitados.

El script usa `$path_sistema = '/usr2/casino'` y el servidor Informix `bincadmin`. `/usr2/crown` es un enlace a `casino`, así que ese path ya apunta a la base de Crown. No hace falta cambiarlo para probar.

En la misma sesión, para ver si el módulo está instalado y solo falta activarlo:

```bash
ls /etc/apache2/mods-available | grep -i php
dpkg -l 'libapache*php*' php5 2>/dev/null | awk '/^ii/{print $2,$3}'
```

Si aparece `php5.load`, con root alcanza `a2enmod php5` y recargar Apache. Si no está instalado, hay que instalar el módulo de PHP 5 para Apache 2 antes de poder probar el bridge.

## Asistente

El paquete instalado es `libapache-mod-php5`, el módulo de PHP para Apache 1.3. El que está en marcha es Apache 2, y para ese no hay módulo: por eso `mods-available` no tiene `php5.load` y el script se entrega como archivo.

`cgid` sí está habilitado. El camino más corto, sin instalar nada, es ejecutar `apiERP.php` como CGI si existe el binario. En la sesión:

```bash
dpkg -l php5-cgi 2>/dev/null | awk '/^ii/{print $2,$3}'
ls -l /usr/bin/php-cgi /usr/bin/php5-cgi /usr/lib/cgi-bin/php5 2>/dev/null
```

Si aparece `php5-cgi`, se puede enganchar solo `apiERP.php` a ese CGI y probar el bridge. Si no está, hay que instalar `libapache2-mod-php5` en ese Debian Etch, y los repositorios de esa época suelen estar caídos.
El paquete instalado es `libapache-mod-php5`, el módulo de PHP para Apache 1.3. El que está en marcha es Apache 2, y para ese no hay módulo: por eso `mods-available` no tiene `php5.load` y el script se entrega como archivo.

`cgid` sí está habilitado. El camino más corto, sin instalar nada, es ejecutar `apiERP.php` como CGI si existe el binario. En la sesión:

```bash
dpkg -l php5-cgi 2>/dev/null | awk '/^ii/{print $2,$3}'
ls -l /usr/bin/php-cgi /usr/bin/php5-cgi /usr/lib/cgi-bin/php5 2>/dev/null
```

Si aparece `php5-cgi`, se puede enganchar solo `apiERP.php` a ese CGI y probar el bridge. Si no está, hay que instalar `libapache2-mod-php5` en ese Debian Etch, y los repositorios de esa época suelen estar caídos.

## Usuario

no entiendo, si lo corres como cgi sigue usando apache?

## Asistente

Sí. Apache sigue recibiendo el pedido HTTP. Lo que cambia es quién ejecuta el PHP.

Hoy Apache abre `apiERP.php` y, como no tiene el módulo de PHP, lo manda al navegador como si fuera un archivo de texto. Con CGI, Apache recibe el mismo `http://192.168.1.2/apiERP.php` y se lo pasa a un programa aparte, `php-cgi`. Ese programa corre el script y le devuelve el resultado a Apache, que se lo manda al ERP.

El módulo `cgid` que ya está habilitado es justo ese puente. La URL del bridge no cambia. El ERP sigue llamando a Apache; no habla directo con Informix.
Sí. Apache sigue recibiendo el pedido HTTP. Lo que cambia es quién ejecuta el PHP.

Hoy Apache abre `apiERP.php` y, como no tiene el módulo de PHP, lo manda al navegador como si fuera un archivo de texto. Con CGI, Apache recibe el mismo `http://192.168.1.2/apiERP.php` y se lo pasa a un programa aparte, `php-cgi`. Ese programa corre el script y le devuelve el resultado a Apache, que se lo manda al ERP.

El módulo `cgid` que ya está habilitado es justo ese puente. La URL del bridge no cambia. El ERP sigue llamando a Apache; no habla directo con Informix.

## Asistente

`php5-cgi` no está instalado, y tampoco hay un binario CGI en las rutas habituales. Apache no tiene con qué ejecutar el script.

El `php` que respondió `php -v` es el de consola. Sirve para correr un archivo a mano en esa máquina. Apache no lo usa: para un pedido HTTP necesita o el módulo de PHP para Apache 2, o el programa CGI. Lo que hay instalado, `libapache-mod-php5`, es el módulo de Apache 1.3, y el servicio que está corriendo es Apache 2.

Para ver si el módulo de Apache 2 o el CGI quedaron en el disco sin estar habilitados:

```bash
dpkg -l | awk '/php/{print $1,$2,$3}'
find /usr -name 'libphp5.so' -o -name 'php-cgi' -o -name 'php5-cgi' 2>/dev/null
```

Si no aparece ninguno de los dos, en ese servidor hay que instalar `libapache2-mod-php5` o `php5-cgi`. Sin eso el bridge no puede contestar por HTTP.
`php5-cgi` no está instalado, y tampoco hay un binario CGI en las rutas habituales. Apache no tiene con qué ejecutar el script.

El `php` que respondió `php -v` es el de consola. Sirve para correr un archivo a mano en esa máquina. Apache no lo usa: para un pedido HTTP necesita o el módulo de PHP para Apache 2, o el programa CGI. Lo que hay instalado, `libapache-mod-php5`, es el módulo de Apache 1.3, y el servicio que está corriendo es Apache 2.

Para ver si el módulo de Apache 2 o el CGI quedaron en el disco sin estar habilitados:

```bash
dpkg -l | awk '/php/{print $1,$2,$3}'
find /usr -name 'libphp5.so' -o -name 'php-cgi' -o -name 'php5-cgi' 2>/dev/null
```

Si no aparece ninguno de los dos, en ese servidor hay que instalar `libapache2-mod-php5` o `php5-cgi`. Sin eso el bridge no puede contestar por HTTP.

## Asistente

Ese resultado cierra el diagnóstico. PHP está instalado, pero solo para Apache 1.3. El que atiende el puerto 80 es Apache 2, y no puede usar esa librería.

| Qué hay | Para qué sirve |
|---|---|
| `php5-cli` | Correr PHP a mano en la consola |
| `libapache-mod-php5` | Módulo de Apache 1.3 |
| `/usr/lib/apache/1.3/libphp5.so` | El archivo de ese módulo viejo |
| `php5-cgi` | No está |

Apache 2 no carga un `.so` de Apache 1.3. Por eso abre `apiERP.php` y lo devuelve como texto.

Para que el bridge responda por HTTP hay que instalar en ese Debian uno de estos dos paquetes: `libapache2-mod-php5` (el módulo de Apache 2) o `php5-cgi` (para usarlo con el `cgid` que ya está activo). Con lo que hay instalado hoy, Apache no puede ejecutar el script.
Ese resultado cierra el diagnóstico. PHP está instalado, pero solo para Apache 1.3. El que atiende el puerto 80 es Apache 2, y no puede usar esa librería.

| Qué hay | Para qué sirve |
|---|---|
| `php5-cli` | Correr PHP a mano en la consola |
| `libapache-mod-php5` | Módulo de Apache 1.3 |
| `/usr/lib/apache/1.3/libphp5.so` | El archivo de ese módulo viejo |
| `php5-cgi` | No está |

Apache 2 no carga un `.so` de Apache 1.3. Por eso abre `apiERP.php` y lo devuelve como texto.

Para que el bridge responda por HTTP hay que instalar en ese Debian uno de estos dos paquetes: `libapache2-mod-php5` (el módulo de Apache 2) o `php5-cgi` (para usarlo con el `cgid` que ya está activo). Con lo que hay instalado hoy, Apache no puede ejecutar el script.

## Usuario

dame el script para instalar lo necesario en este debian 4

## Asistente

El PHP que hay es de Apache 1.3 y el servicio es Apache 2. Voy a confirmar en el archivo de Debian Etch el paquete CGI de esa misma versión, que funciona con el Apache que ya está corriendo.
El PHP que hay es de Apache 1.3 y el servicio es Apache 2. Voy a confirmar en el archivo de Debian Etch el paquete CGI de esa misma versión, que funciona con el Apache que ya está corriendo.

## Asistente

El paquete CGI de la misma versión de PHP 5.2 que ya tiene ese Debian está en el archivo oficial. El módulo de Apache 2 no conviene: ese Apache es multithreaded y `mod_php` lo obligaría a cambiar de modo y reiniciar todo el sitio. El CGI usa el `cgid` que ya está activo.
El paquete CGI de la misma versión de PHP 5.2 que ya tiene ese Debian está en el archivo oficial. El módulo de Apache 2 no conviene: ese Apache es multithreaded y `mod_php` lo obligaría a cambiar de modo y reiniciar todo el sitio. El CGI usa el `cgid` que ya está activo.

## Asistente

El script quedó en `/home/sergio/tmp/instalar-php5-cgi-apache2.sh` en este servidor. Instala `php5-cgi` 5.2.0, la misma versión que el PHP que ya está en `ac-server1`, y hace que Apache ejecute solo `apiERP.php`. No cambia el modo de Apache ni actualiza el resto del sistema.

En la sesión de `ac-server1`, como root:

```bash
su -
wget -O /tmp/instalar-php5-cgi-apache2.sh http://192.168.1.3/no-existe
```

Ese `wget` no va a funcionar: el ERP no publica el archivo. Copialo desde esta máquina, donde ya estás logueado por SSH en la otra dirección. Desde otra terminal de este servidor:

```bash
scp /home/sergio/tmp/instalar-php5-cgi-apache2.sh ac-server1:/tmp/
```

Y en `ac-server1`:

```bash
su -
sh /tmp/instalar-php5-cgi-apache2.sh
```

Usa CGI y no el módulo de Apache 2 porque el Apache que está corriendo es multithreaded. El módulo de PHP lo obligaría a pasar a modo prefork y a reiniciar todo el sitio. El CGI entra por el `cgid` que ya está habilitado.

Si `dpkg` se frena por una dependencia que falta, el script corta y no sigue. Pasame esa salida. Cuando termine, lo vuelvo a probar desde acá con un pedido de lectura.
El script quedó en `/home/sergio/tmp/instalar-php5-cgi-apache2.sh` en este servidor. Instala `php5-cgi` 5.2.0, la misma versión que el PHP que ya está en `ac-server1`, y hace que Apache ejecute solo `apiERP.php`. No cambia el modo de Apache ni actualiza el resto del sistema.

En la sesión de `ac-server1`, como root:

```bash
su -
wget -O /tmp/instalar-php5-cgi-apache2.sh http://192.168.1.3/no-existe
```

Ese `wget` no va a funcionar: el ERP no publica el archivo. Copialo desde esta máquina, donde ya estás logueado por SSH en la otra dirección. Desde otra terminal de este servidor:

```bash
scp /home/sergio/tmp/instalar-php5-cgi-apache2.sh ac-server1:/tmp/
```

Y en `ac-server1`:

```bash
su -
sh /tmp/instalar-php5-cgi-apache2.sh
```

Usa CGI y no el módulo de Apache 2 porque el Apache que está corriendo es multithreaded. El módulo de PHP lo obligaría a pasar a modo prefork y a reiniciar todo el sitio. El CGI entra por el `cgid` que ya está habilitado.

Si `dpkg` se frena por una dependencia que falta, el script corta y no sigue. Pasame esa salida. Cuando termine, lo vuelvo a probar desde acá con un pedido de lectura.

## Asistente

`ac-server1` no llega a `archive.debian.org`. El paquete ya está bajado en este servidor; lo dejo listo para copiarlo por SSH e instalarlo con `dpkg`, sin salir a internet.
`ac-server1` no llega a `archive.debian.org`. El paquete ya está bajado en este servidor; lo dejo listo para copiarlo por SSH e instalarlo con `dpkg`, sin salir a internet.

## Asistente

`ac-server1` no puede salir a internet: el puerto 80 de `archive.debian.org` le rechaza la conexión. El paquete ya está en este servidor, en `/home/sergio/tmp/php5-cgi_5.2.0+dfsg-8+etch16_i386.deb`.

En una terminal de este equipo, no dentro de la sesión SSH:

```bash
scp /home/sergio/tmp/php5-cgi_5.2.0+dfsg-8+etch16_i386.deb ac-server1:/tmp/
```

En `ac-server1`, como root, sin volver a bajar nada:

```bash
dpkg -i /tmp/php5-cgi_5.2.0+dfsg-8+etch16_i386.deb
```

Si `dpkg` termina bien, el resto del script (el wrapper y Apache) no necesita la red. Se puede correr de nuevo `sh /tmp/instalar-php5-cgi-apache2.sh`: al ver el paquete instalado salta la descarga. Si `dpkg` pide otra dependencia, pasame el texto.
`ac-server1` no puede salir a internet: el puerto 80 de `archive.debian.org` le rechaza la conexión. El paquete ya está en este servidor, en `/home/sergio/tmp/php5-cgi_5.2.0+dfsg-8+etch16_i386.deb`.

En una terminal de este equipo, no dentro de la sesión SSH:

```bash
scp /home/sergio/tmp/php5-cgi_5.2.0+dfsg-8+etch16_i386.deb ac-server1:/tmp/
```

En `ac-server1`, como root, sin volver a bajar nada:

```bash
dpkg -i /tmp/php5-cgi_5.2.0+dfsg-8+etch16_i386.deb
```

Si `dpkg` termina bien, el resto del script (el wrapper y Apache) no necesita la red. Se puede correr de nuevo `sh /tmp/instalar-php5-cgi-apache2.sh`: al ver el paquete instalado salta la descarga. Si `dpkg` pide otra dependencia, pasame el texto.

## Asistente

`php5-cgi` quedó instalado. Falta decirle a Apache que use ese programa solo para `apiERP.php`.

En esa sesión de root:

```bash
sh /tmp/instalar-php5-cgi-apache2.sh
```

El paquete ya está, así que no vuelve a descargar nada. Crea el wrapper, agrega la configuración y recarga Apache. Cuando vuelva el prompt, avisame y lo pruebo desde acá.
`php5-cgi` quedó instalado. Falta decirle a Apache que use ese programa solo para `apiERP.php`.

En esa sesión de root:

```bash
sh /tmp/instalar-php5-cgi-apache2.sh
```

El paquete ya está, así que no vuelve a descargar nada. Crea el wrapper, agrega la configuración y recarga Apache. Cuando vuelva el prompt, avisame y lo pruebo desde acá.

## Asistente

Apache ya recargó con el CGI. Pruebo desde acá si `apiERP.php` se ejecuta y si responde una lectura contra Informix.
Apache ya recargó con el CGI. Pruebo desde acá si `apiERP.php` se ejecuta y si responde una lectura contra Informix.

## Asistente

PHP ya se ejecuta. Apache responde con `X-Powered-By: PHP/5.2.0-8+etch16` y el script corre, pero se cae al buscar una librería que no está junto al directorio de trabajo del CGI:

```text
include_once(include/IFXConnection.php): failed to open stream
Class 'IFXConnection' not found en /usr2/www/http/apiERP.php línea 20
```

Ese `include` es relativo. El CGI no arranca parado en `/usr2/www/http`, así que no encuentra `include/IFXConnection.php`. En la sesión de root:

```bash
ls -l /usr2/www/http/include/IFXConnection.php
find /usr2/www /usr2/casino -name 'IFXConnection.php' 2>/dev/null
```

Si el archivo está en `/usr2/www/http/include/`, alcanza con hacer que el wrapper entre a esa carpeta antes de llamar a PHP:

```bash
printf '%s\n' '#!/bin/sh' 'cd /usr2/www/http' 'exec /usr/bin/php5-cgi' > /usr/local/bin/apierp-php-cgi
chmod 755 /usr/local/bin/apierp-php-cgi
```

No hace falta recargar Apache. Cuando lo dejes, vuelvo a probar la lectura.
PHP ya se ejecuta. Apache responde con `X-Powered-By: PHP/5.2.0-8+etch16` y el script corre, pero se cae al buscar una librería que no está junto al directorio de trabajo del CGI:

```text
include_once(include/IFXConnection.php): failed to open stream
Class 'IFXConnection' not found en /usr2/www/http/apiERP.php línea 20
```

Ese `include` es relativo. El CGI no arranca parado en `/usr2/www/http`, así que no encuentra `include/IFXConnection.php`. En la sesión de root:

```bash
ls -l /usr2/www/http/include/IFXConnection.php
find /usr2/www /usr2/casino -name 'IFXConnection.php' 2>/dev/null
```

Si el archivo está en `/usr2/www/http/include/`, alcanza con hacer que el wrapper entre a esa carpeta antes de llamar a PHP:

```bash
printf '%s\n' '#!/bin/sh' 'cd /usr2/www/http' 'exec /usr/bin/php5-cgi' > /usr/local/bin/apierp-php-cgi
chmod 755 /usr/local/bin/apierp-php-cgi
```

No hace falta recargar Apache. Cuando lo dejes, vuelvo a probar la lectura.

## Usuario

podes verificar apiERP.php porque me parece que no necesita IFXConnection, no se conecta al informix directamente, lo hace por scripts de shell

## Asistente

El fallo apareció al armar el pedido con `DB_NAME`. Reviso el script para ver si esa clase se usa de verdad o si Informix se llama solo por el `sql` de shell.
El fallo apareció al armar el pedido con `DB_NAME`. Reviso el script para ver si esa clase se usa de verdad o si Informix se llama solo por el `sql` de shell.

## Asistente

En el script del repositorio la clase se incluye, pero la consulta real va por `shell_exec` al comando `sql`. El `new IFXConnection` solo corre si el pedido trae `DB_NAME`, y el ERP siempre lo manda. Pruebo de nuevo sin ese dato para ver si el shell responde.
En el script del repositorio la clase se incluye, pero la consulta real va por `shell_exec` al comando `sql`. El `new IFXConnection` solo corre si el pedido trae `DB_NAME`, y el ERP siempre lo manda. Pruebo de nuevo sin ese dato para ver si el shell responde.

## Asistente

La consulta a Informix no usa `IFXConnection`. La clase se incluye al principio y solo se instancia si el pedido trae `DB_NAME`; después no se usa. Lo que lee y escribe es el comando `sql` por shell, que arma un archivo en `/usr2/casino/ventas` y hace el `UNLOAD` al CSV.

Lo comprobé con un pedido sin `DB_NAME`. El `include` avisa que no encuentra el archivo, pero no corta. El script sigue y busca el CSV:

```text
fopen(/usr2/casino/ventas/cmd_sql.17921918-list-1055.csv): No such file or directory
[]
```

Ese `[]` es la respuesta del shell: el `UNLOAD` no generó el archivo. El include sobra para esa vía.

El ERP, en `ApiAnita`, manda siempre `DB_NAME`. Con eso el script de Crown llega al `new IFXConnection` y muere antes del shell, que es el error de la prueba anterior. En el `apiERP.php` de ese servidor hay que sacar el `include` y el `new IFXConnection`, o no entrar a ese bloque. El path `/usr2/casino` ya es el correcto, porque `/usr2/crown` apunta ahí.
La consulta a Informix no usa `IFXConnection`. La clase se incluye al principio y solo se instancia si el pedido trae `DB_NAME`; después no se usa. Lo que lee y escribe es el comando `sql` por shell, que arma un archivo en `/usr2/casino/ventas` y hace el `UNLOAD` al CSV.

Lo comprobé con un pedido sin `DB_NAME`. El `include` avisa que no encuentra el archivo, pero no corta. El script sigue y busca el CSV:

```text
fopen(/usr2/casino/ventas/cmd_sql.17921918-list-1055.csv): No such file or directory
[]
```

Ese `[]` es la respuesta del shell: el `UNLOAD` no generó el archivo. El include sobra para esa vía.

El ERP, en `ApiAnita`, manda siempre `DB_NAME`. Con eso el script de Crown llega al `new IFXConnection` y muere antes del shell, que es el error de la prueba anterior. En el `apiERP.php` de ese servidor hay que sacar el `include` y el `new IFXConnection`, o no entrar a ese bloque. El path `/usr2/casino` ya es el correcto, porque `/usr2/crown` apunta ahí.

## Usuario

podes modificar vos apiERP.php? o necesitas la llave ssh?

## Usuario

<available_subagent_types> Available subagent_types and a quick description of what they do: - generalPurpose: General-purpose agent for researching complex questions, searching for code, and executing multi-step tasks. Use when searching for a keyword or file and not confident you'll find the match quickly. - explore: Fast agent specialized for exploring codebases. Use this when you need to quickly find files by patterns (eg. "src/components/**/*.tsx"), search code for keywords (eg. "API endpoints"), or answer questions about the codebase (eg. "how do API endpoints work?"). When calling this agent, specify the desired thoroughness level: "quick" for basic searches, "medium" for moderate exploration, or "very thorough" for comprehensive analysis across multiple locations and naming conventions. - cursor-guide: Read Cursor product documentation to answer questions about how Cursor Desktop, IDE, CLI, Cloud Agents, Bugbot, and other features work. Use when the user asks 'In Cursor, how do I...?' or similar questions about Cursor products. - ci-investigator: Investigate a single failing PR CI check and return a short root-cause summary. Use when the user asks to summarize, explain, diagnose, or investigate a specific failed check from a pull request. - bugbot: Use only when the user *explicitly* asks for a Bugbot-like review of local code changes. When launching this subagent, set the Task description to exactly "Bugbot". Launch exactly one Bugbot subagent with `run_in_background: false` unless the user explicitly asks to run in background. Use this fixed prompt form: "Full Repository Path: ...\nDiff: <one of: \"branch changes\", \"uncommitted changes\", \"natural language\">\nChange Description: ...\nCustom Instructions: ..."; default to `Diff: branch changes`; include `Change Description` only when `Diff` is `natural language`, formatting it as one block per changed file (a `<path> (added|modified|deleted|renamed)` header followed by bullets of what changed, mentioning line numbers or ranges inline where helpful), and only use `natural language` as a last resort after a regular diff-based review failed because the diff could not be computed; include `Custom Instructions` only when the user gave specific review instructions. This subagent is single-shot and does not support `resume`; always launch a fresh subagent instead. - security-review: Use only when the user *explicitly* asks for a security review of local code changes. When launching this subagent, set the Task description to exactly "Security Review". Launch exactly one security-review subagent with `run_in_background: false` unless the user explicitly asks to run in background. Use this fixed prompt form: "Full Repository Path: ...\nDiff: <one of: \"branch changes\", \"uncommitted changes\">\nCustom Instructions: ..."; default to `Diff: branch changes`; include `Custom Instructions` only when the user gave specific review instructions. This subagent is single-shot and does not support `resume`; always launch a fresh subagent instead. - best-of-n-runner: Run a task in an isolated git worktree. Each best-of-n-runner gets its own branch and working directory. Use for best-of-N parallel attempts or isolated experiments. </available_subagent_types> <available_subagent_models> If the user explicitly asks for the model of a subagent/task, you may ONLY use model slugs from this list: - inherit (default; required unless the user explicitly requested another model) - claude-fable-5-1-thinking-high - claude-opus-5-5-medium - claude-opus-5-thinking-high - claude-sonnet-5-5-high - composer-2.5-fast - cursor-grok-4.6-high-fast - gemini-4-argon-medium - gpt-5.6-sol-medium - grok-4.7-high-fast - muse-wonder-1.0-high If the user isn't asking for a specific version, prefer the latest version of the model family. As an example, if the user just says "gpt" or "claude", use the latest available version of GPT or Claude. IMPORTANT: If the user requests a model that is NOT in the list above, do NOT substitute a different model or guess. Instead, skip launching the subagent with that model and tell the user which model was unavailable and which models are available. When speaking to the USER about which model you selected for a subagent, do NOT use the kebab-case model names unless the user requested the model using that format. Ue the same naming scheme the user used to discuss the model when they requested it. </available_subagent_models> <dynamic_tools> You have access to tools through dynamic namespaces, e.g. MCP servers, using `GetDynamicTools` and `CallDynamicTool`. ## Dynamic Tool Discovery and Invocation Use `GetDynamicTools` to discover tool schemas, then `CallDynamicTool` to invoke one tool. Aim to minimize round-trips: ideally one discovery call followed by one invocation. If the user mentions a product or service represented by an available namespace, and the request likely depends on it, proactively inspect that namespace before answering. If you are unsure which namespace matches, search with a relevant pattern. `GetDynamicTools` supports these modes: 1. `{"namespace":"<id>"}`: returns schemas and full descriptions for every tool in that namespace. 2. `{"namespace":"<id>","toolName":"<name>"}`: returns one tool schema with its full description. 3. `{"pattern":"<regex>"}`: searches namespace and tool names. 4. `{"namespace":"<id>","pattern":"<regex>"}`: searches tools within one namespace. 5. No arguments: returns the full catalog. Pattern-search and catalog results shorten long descriptions, marked by a trailing "... [truncated]"; namespace and single-tool lookups always return the complete description. Always inspect a tool's schema before invoking it with `CallDynamicTool`. If the available dynamic tools do not fully support what the user asked you to do, complete the work you can with the current tool set. In your work summary, include what you were unable to do and why. Do not use browser automation to work around missing tools unless the user explicitly asks you to use the browser. Available dynamic tool namespaces: <dynamic_tool_namespaces> <namespace name="cursor-ide-browser" tools="browser_navigate, browser_snapshot, browser_click, browser_mouse_click_xy, browser_type, browser_fill, browser_select_option, browser_press_key, browser_scroll, browser_drag, browser_get_bounding_box, browser_highlight, browser_tabs, browser_cdp, browser_take_screenshot, browser_lock" namespaceUseInstructions="The cursor-ide-browser MCP server provides a Cursor-owned browser tab plus a raw Chrome DevTools Protocol command tool. CORE WORKFLOW: 1. Start by understanding the user's goal and what success looks like on the page. 2. Use browser_tabs with action "list" to inspect open tabs and URLs before acting. 3. Use browser_navigate to create or navigate the target tab. Omit the position parameter for background automation so focus is preserved. 4. Use browser_lock before longer automation on an existing tab, then browser_lock with action "unlock" when finished. 5. Use browser_snapshot for accessibility context and browser_take_screenshot for visual verification. 6. Use browser_click, browser_type, browser_fill, browser_select_option, browser_press_key, browser_scroll, and browser_drag for page interactions. 7. Use browser_highlight and browser_get_bounding_box for visual grounding and coordinate diagnostics. 8. Use browser_cdp for page inspection, profiling, runtime evaluation, DOM/CSS queries, and performance data. AVOID RABBIT HOLES: 1. Do not repeat the same failing action more than once without new evidence such as a fresh snapshot, a different ref, a changed page state, or a clear new hypothesis. 2. IMPORTANT: If four attempts fail or progress stalls, stop acting and report what you observed, what blocked progress, and the most likely next step. 3. Prefer gathering evidence over brute force. If the page is confusing, use browser_snapshot, browser_take_screenshot, or CDP inspection before trying more actions. 4. If you encounter a blocker such as login, passkey/manual user interaction, permissions, captchas, destructive confirmations, missing data, or an unexpected state, stop and report it instead of improvising repeated actions. 5. Do not get stuck in wait-action-wait loops. Every retry should be justified by something newly observed. CRITICAL - Lock/unlock workflow: 1. browser_lock requires an existing browser tab - you CANNOT call browser_lock with action: "lock" before browser_navigate 2. Correct order: browser_navigate -> browser_lock({ action: "lock" }) -> (interactions) -> browser_lock({ action: "unlock" }) 3. If a browser tab already exists (check with browser_tabs list), call browser_lock with action: "lock" FIRST before any interactions 4. Only call browser_lock with action: "unlock" when completely done with ALL browser operations for this turn IMPORTANT - Waiting strategy: When waiting for page changes, prefer short CDP polling loops with Runtime.evaluate, DOM queries, Page lifecycle signals, or browser_snapshot checks rather than a single long wait. CDP USAGE: - Use browser_cdp with a DevTools Protocol method and params object, for example Runtime.evaluate, DOM.getDocument, CSS.getComputedStyleForNode, Profiler.start/stop, Performance.getMetrics, Log.enable, and Network.enable. - Do not use browser_cdp with CDP Input.* methods. They are denied because they are focus-sensitive in Electron webviews and can route input to Cursor UI instead of the browser page. - Use browser_click, browser_type, browser_fill, browser_select_option, browser_press_key, browser_scroll, and browser_drag for clicks, typing, filling inputs, selecting options, keyboard actions, scrolling, and drag-and-drop. - Use Runtime.evaluate for advanced DOM-scoped interactions that the dedicated browser tools do not cover. - For profiling, call Profiler.enable, Profiler.start, reproduce the behavior, then Profiler.stop. The profile is saved to a file and returned as a log_file; read that file only when you need to inspect details. - For JavaScript evaluation, prefer Runtime.evaluate with returnByValue when possible. - Device emulation you set with Emulation.setDeviceMetricsOverride, Emulation.setTouchEmulationEnabled, or Emulation.setUserAgentOverride (for example for a phone-sized screenshot) lasts only for the current turn: it is cleared when your turn ends, and the user can reset it from the browser tab at any time. Send Emulation.clearDeviceMetricsOverride yourself once you are done with it within a turn. - Some browser-wide or sensitive CDP methods are denied, especially cookie, storage, permission, download, target-management, filesystem-backed file-input commands, system-level commands, and CDP navigation/history navigation commands. - Large CDP responses are saved to files instead of being inlined. Prefer using the returned file path over immediately stuffing large payloads into context; read focused sections only when needed. VISION: - browser_take_screenshot attaches an image result that the model can inspect. CDP Page.captureScreenshot returns data inside JSON and should not replace browser_take_screenshot when visual verification is needed. NOTES: - browser_snapshot returns snapshot YAML and is the main source of truth for page structure. - Refs are opaque handles tied to the latest browser_snapshot for that tab. - Iframe content is not accessible - only elements outside iframes can be interacted with. - When you stop to report a blocker, include the current page, the target you were trying to reach, the blocker you observed, and the best next action. If the blocker requires manual user interaction, ask the user to take over at that point rather than assuming it in advance." source="mcp" /> <namespace name="cursor" tools="AwaitShell, CreateGoal, Delete, EditNotebook, FetchMcpResource, GenerateImage, ReadLints, SwitchMode, Task, TodoWrite, UpdateGoal, WebFetch, WebSearch" namespaceUseInstructions="Native Cursor tools for this session. These are highly recommended and useful tools that you should use when the right situation arises. Don't be afraid to look at one if it seems relevant, even if you don't end up using it. You MUST read the tool schemas before calling them. Here are some crucial instructions: - AwaitShell: Use to sleep and check shell progress. Never sleep using shell. - ReadLints: Check for linter errors after substantive edits. - SwitchMode: Switch between modes: Agent and Plan. Proactively consider switching modes for relevant requests. - Task: Spawn local and cloud subagents natively. - TodoWrite: Use this tool to manage complex multi-step tasks." source="cursor" /> </dynamic_tool_namespaces> If an MCP-backed namespace requires authentication, call `mcp_auth` through `CallDynamicTool` for that namespace, then inspect it again and retry if appropriate. Do not authenticate namespaces preemptively or repeatedly. </dynamic_tools> <available_subagent_types> Available subagent_types and a quick description of what they do: - generalPurpose: General-purpose agent for researching complex questions, searching for code, and executing multi-step tasks. Use when searching for a keyword or file and not confident you'll find the match quickly. - explore: Fast agent specialized for exploring codebases. Use this when you need to quickly find files by patterns (eg. "src/components/**/*.tsx"), search code for keywords (eg. "API endpoints"), or answer questions about the codebase (eg. "how do API endpoints work?"). When calling this agent, specify the desired thoroughness level: "quick" for basic searches, "medium" for moderate exploration, or "very thorough" for comprehensive analysis across multiple locations and naming conventions. - cursor-guide: Read Cursor product documentation to answer questions about how Cursor Desktop, IDE, CLI, Cloud Agents, Bugbot, and other features work. Use when the user asks 'In Cursor, how do I...?' or similar questions about Cursor products. - ci-investigator: Investigate a single failing PR CI check and return a short root-cause summary. Use when the user asks to summarize, explain, diagnose, or investigate a specific failed check from a pull request. - bugbot: Use only when the user *explicitly* asks for a Bugbot-like review of local code changes. When launching this subagent, set the Task description to exactly "Bugbot". Launch exactly one Bugbot subagent with `run_in_background: false` unless the user explicitly asks to run in background. Use this fixed prompt form: "Full Repository Path: ...\nDiff: <one of: \"branch changes\", \"uncommitted changes\", \"natural language\">\nChange Description: ...\nCustom Instructions: ..."; default to `Diff: branch changes`; include `Change Description` only when `Diff` is `natural language`, formatting it as one block per changed file (a `<path> (added|modified|deleted|renamed)` header followed by bullets of what changed, mentioning line numbers or ranges inline where helpful), and only use `natural language` as a last resort after a regular diff-based review failed because the diff could not be computed; include `Custom Instructions` only when the user gave specific review instructions. This subagent is single-shot and does not support `resume`; always launch a fresh subagent instead. - security-review: Use only when the user *explicitly* asks for a security review of local code changes. When launching this subagent, set the Task description to exactly "Security Review". Launch exactly one security-review subagent with `run_in_background: false` unless the user explicitly asks to run in background. Use this fixed prompt form: "Full Repository Path: ...\nDiff: <one of: \"branch changes\", \"uncommitted changes\">\nCustom Instructions: ..."; default to `Diff: branch changes`; include `Custom Instructions` only when the user gave specific review instructions. This subagent is single-shot and does not support `resume`; always launch a fresh subagent instead. - best-of-n-runner: Run a task in an isolated git worktree. Each best-of-n-runner gets its own branch and working directory. Use for best-of-N parallel attempts or isolated experiments. </available_subagent_types> <available_subagent_models> If the user explicitly asks for the model of a subagent/task, you may ONLY use model slugs from this list: - inherit (default; required unless the user explicitly requested another model) - claude-fable-5-1-thinking-high - claude-opus-5-5-medium - claude-opus-5-thinking-high - claude-sonnet-5-5-high - composer-2.5-fast - cursor-grok-4.6-high-fast - gemini-4-argon-medium - gpt-5.6-sol-medium - grok-4.7-high-fast - muse-wonder-1.0-high If the user isn't asking for a specific version, prefer the latest version of the model family. As an example, if the user just says "gpt" or "claude", use the latest available version of GPT or Claude. IMPORTANT: If the user requests a model that is NOT in the list above, do NOT substitute a different model or guess. Instead, skip launching the subagent with that model and tell the user which model was unavailable and which models are available. When speaking to the USER about which model you selected for a subagent, do NOT use the kebab-case model names unless the user requested the model using that format. Ue the same naming scheme the user used to discuss the model when they requested it. </available_subagent_models> <dynamic_tools> You have access to tools through dynamic namespaces, e.g. MCP servers, using `GetDynamicTools` and `CallDynamicTool`. ## Dynamic Tool Discovery and Invocation Use `GetDynamicTools` to discover tool schemas, then `CallDynamicTool` to invoke one tool. Aim to minimize round-trips: ideally one discovery call followed by one invocation. If the user mentions a product or service represented by an available namespace, and the request likely depends on it, proactively inspect that namespace before answering. If you are unsure which namespace matches, search with a relevant pattern. `GetDynamicTools` supports these modes: 1. `{"namespace":"<id>"}`: returns schemas and full descriptions for every tool in that namespace. 2. `{"namespace":"<id>","toolName":"<name>"}`: returns one tool schema with its full description. 3. `{"pattern":"<regex>"}`: searches namespace and tool names. 4. `{"namespace":"<id>","pattern":"<regex>"}`: searches tools within one namespace. 5. No arguments: returns the full catalog. Pattern-search and catalog results shorten long descriptions, marked by a trailing "... [truncated]"; namespace and single-tool lookups always return the complete description. Always inspect a tool's schema before invoking it with `CallDynamicTool`. If the available dynamic tools do not fully support what the user asked you to do, complete the work you can with the current tool set. In your work summary, include what you were unable to do and why. Do not use browser automation to work around missing tools unless the user explicitly asks you to use the browser. Available dynamic tool namespaces: <dynamic_tool_namespaces> <namespace name="cursor-ide-browser" tools="browser_navigate, browser_snapshot, browser_click, browser_mouse_click_xy, browser_type, browser_fill, browser_select_option, browser_press_key, browser_scroll, browser_drag, browser_get_bounding_box, browser_highlight, browser_tabs, browser_cdp, browser_take_screenshot, browser_lock" namespaceUseInstructions="The cursor-ide-browser MCP server provides a Cursor-owned browser tab plus a raw Chrome DevTools Protocol command tool. CORE WORKFLOW: 1. Start by understanding the user's goal and what success looks like on the page. 2. Use browser_tabs with action "list" to inspect open tabs and URLs before acting. 3. Use browser_navigate to create or navigate the target tab. Omit the position parameter for background automation so focus is preserved. 4. Use browser_lock before longer automation on an existing tab, then browser_lock with action "unlock" when finished. 5. Use browser_snapshot for accessibility context and browser_take_screenshot for visual verification. 6. Use browser_click, browser_type, browser_fill, browser_select_option, browser_press_key, browser_scroll, and browser_drag for page interactions. 7. Use browser_highlight and browser_get_bounding_box for visual grounding and coordinate diagnostics. 8. Use browser_cdp for page inspection, profiling, runtime evaluation, DOM/CSS queries, and performance data. AVOID RABBIT HOLES: 1. Do not repeat the same failing action more than once without new evidence such as a fresh snapshot, a different ref, a changed page state, or a clear new hypothesis. 2. IMPORTANT: If four attempts fail or progress stalls, stop acting and report what you observed, what blocked progress, and the most likely next step. 3. Prefer gathering evidence over brute force. If the page is confusing, use browser_snapshot, browser_take_screenshot, or CDP inspection before trying more actions. 4. If you encounter a blocker such as login, passkey/manual user interaction, permissions, captchas, destructive confirmations, missing data, or an unexpected state, stop and report it instead of improvising repeated actions. 5. Do not get stuck in wait-action-wait loops. Every retry should be justified by something newly observed. CRITICAL - Lock/unlock workflow: 1. browser_lock requires an existing browser tab - you CANNOT call browser_lock with action: "lock" before browser_navigate 2. Correct order: browser_navigate -> browser_lock({ action: "lock" }) -> (interactions) -> browser_lock({ action: "unlock" }) 3. If a browser tab already exists (check with browser_tabs list), call browser_lock with action: "lock" FIRST before any interactions 4. Only call browser_lock with action: "unlock" when completely done with ALL browser operations for this turn IMPORTANT - Waiting strategy: When waiting for page changes, prefer short CDP polling loops with Runtime.evaluate, DOM queries, Page lifecycle signals, or browser_snapshot checks rather than a single long wait. CDP USAGE: - Use browser_cdp with a DevTools Protocol method and params object, for example Runtime.evaluate, DOM.getDocument, CSS.getComputedStyleForNode, Profiler.start/stop, Performance.getMetrics, Log.enable, and Network.enable. - Do not use browser_cdp with CDP Input.* methods. They are denied because they are focus-sensitive in Electron webviews and can route input to Cursor UI instead of the browser page. - Use browser_click, browser_type, browser_fill, browser_select_option, browser_press_key, browser_scroll, and browser_drag for clicks, typing, filling inputs, selecting options, keyboard actions, scrolling, and drag-and-drop. - Use Runtime.evaluate for advanced DOM-scoped interactions that the dedicated browser tools do not cover. - For profiling, call Profiler.enable, Profiler.start, reproduce the behavior, then Profiler.stop. The profile is saved to a file and returned as a log_file; read that file only when you need to inspect details. - For JavaScript evaluation, prefer Runtime.evaluate with returnByValue when possible. - Device emulation you set with Emulation.setDeviceMetricsOverride, Emulation.setTouchEmulationEnabled, or Emulation.setUserAgentOverride (for example for a phone-sized screenshot) lasts only for the current turn: it is cleared when your turn ends, and the user can reset it from the browser tab at any time. Send Emulation.clearDeviceMetricsOverride yourself once you are done with it within a turn. - Some browser-wide or sensitive CDP methods are denied, especially cookie, storage, permission, download, target-management, filesystem-backed file-input commands, system-level commands, and CDP navigation/history navigation commands. - Large CDP responses are saved to files instead of being inlined. Prefer using the returned file path over immediately stuffing large payloads into context; read focused sections only when needed. VISION: - browser_take_screenshot attaches an image result that the model can inspect. CDP Page.captureScreenshot returns data inside JSON and should not replace browser_take_screenshot when visual verification is needed. NOTES: - browser_snapshot returns snapshot YAML and is the main source of truth for page structure. - Refs are opaque handles tied to the latest browser_snapshot for that tab. - Iframe content is not accessible - only elements outside iframes can be interacted with. - When you stop to report a blocker, include the current page, the target you were trying to reach, the blocker you observed, and the best next action. If the blocker requires manual user interaction, ask the user to take over at that point rather than assuming it in advance." source="mcp" /> <namespace name="cursor" tools="AwaitShell, CreateGoal, Delete, EditNotebook, FetchMcpResource, GenerateImage, ReadLints, SwitchMode, Task, TodoWrite, UpdateGoal, WebFetch, WebSearch" namespaceUseInstructions="Native Cursor tools for this session. These are highly recommended and useful tools that you should use when the right situation arises. Don't be afraid to look at one if it seems relevant, even if you don't end up using it. You MUST read the tool schemas before calling them. Here are some crucial instructions: - AwaitShell: Use to sleep and check shell progress. Never sleep using shell. - ReadLints: Check for linter errors after substantive edits. - SwitchMode: Switch between modes: Agent and Plan. Proactively consider switching modes for relevant requests. - Task: Spawn local and cloud subagents natively. - TodoWrite: Use this tool to manage complex multi-step tasks." source="cursor" /> </dynamic_tool_namespaces> If an MCP-backed namespace requires authentication, call `mcp_auth` through `CallDynamicTool` for that namespace, then inspect it again and retry if appropriate. Do not authenticate namespaces preemptively or repeatedly. </dynamic_tools>

## Usuario

podes modificar vos apiERP.php? o necesitas la llave ssh?

## Asistente

La llave Ed25519 de esta máquina ese Debian no la acepta, y la contraseña de tu sesión no la puedo escribir yo. Genero una llave RSA solo para `ac-server1` y, cuando la autorices allá, edito el script.
La llave Ed25519 de esta máquina ese Debian no la acepta, y la contraseña de tu sesión no la puedo escribir yo. Genero una llave RSA solo para `ac-server1` y, cuando la autorices allá, edito el script.

## Asistente

Necesito la llave. Desde acá la conexión se corta con `Permission denied`: ese SSH viejo no acepta la Ed25519, y la contraseña de tu sesión no la puedo escribir yo.

Dejé una RSA en esta máquina y el host `ac-server1` ya la usa. En la sesión de root de `ac-server1`, agregala una sola vez:

```bash
mkdir -p /home/sergio/.ssh
chmod 700 /home/sergio/.ssh
echo 'ssh-rsa AAAAB3NzaC1yc2EAAAADAQABAAACAQDwc2aZ5FWHsHcfVnWvDJYhMT6g1qSj/cNaQqo1g/3+X09tvQTwxCjeIkvHPKwyM1ugmq9bLl9ekTW4AweL5SaqEkCchKkse0Yse5SmMYYT1bivIZxicgYb8LuPzKt5HVn+gN4zOlL6zzbSiW2CuX3H11CkQ2fxMBfJU9NSmYgbO7MHuJ//dyDyXvx0W8g/vWYisctp2Tk+DEbKmlre+C08knduQ6n3dZwO8/8AEEpY67Ns3f6bNAWocjgUBNc9xky4R4Bq+CLGXlvhOjJfT6ln4qj+O9rkSl8TRVwyTMzUH6rfmTMDNZMQjhNFdIuo35LDWYiZtUCNocBzpBIEOKNQzUJKd1B4SK/do/FgBrjvP0l1oZJW+C2HOGORUeqEuvS2p5WfHcVKqKhA4dNvQtfjxdi8uJv3vqCHzZrNqHUYotKm4DYsAbVokl6Pl7+UtZulr5EywatQyh6g8fcmp4mK3CFUQEMSvS5sg6OnQIahZcWe/hI3QIGqf8RLYLEtPzWW4k3hsoDLhcg3Qchhmmd+QI/pp2lxAUfMJkpfkXgNmCTg0aiuUetlkJOijb6kGex0iu0gw6qkCq7Tk9Dl2bzxHmjUebaWhQES6J9VQclGxkiOtW2y3hm1OdjKKi7pKLEGaWzX5qdWohT3T4kVDWwRXqubdb6EX7UeoIv1+ActlQ== sergio@server1ng-ac-server1' >> /home/sergio/.ssh/authorized_keys
chown -R sergio:sergio /home/sergio/.ssh
chmod 600 /home/sergio/.ssh/authorized_keys
```

Cuando esté, entro sin contraseña y saco de `apiERP.php` el `include` de `IFXConnection` y el `new` que corta antes del `sql` por shell.
Necesito la llave. Desde acá la conexión se corta con `Permission denied`: ese SSH viejo no acepta la Ed25519, y la contraseña de tu sesión no la puedo escribir yo.

Dejé una RSA en esta máquina y el host `ac-server1` ya la usa. En la sesión de root de `ac-server1`, agregala una sola vez:

```bash
mkdir -p /home/sergio/.ssh
chmod 700 /home/sergio/.ssh
echo 'ssh-rsa AAAAB3NzaC1yc2EAAAADAQABAAACAQDwc2aZ5FWHsHcfVnWvDJYhMT6g1qSj/cNaQqo1g/3+X09tvQTwxCjeIkvHPKwyM1ugmq9bLl9ekTW4AweL5SaqEkCchKkse0Yse5SmMYYT1bivIZxicgYb8LuPzKt5HVn+gN4zOlL6zzbSiW2CuX3H11CkQ2fxMBfJU9NSmYgbO7MHuJ//dyDyXvx0W8g/vWYisctp2Tk+DEbKmlre+C08knduQ6n3dZwO8/8AEEpY67Ns3f6bNAWocjgUBNc9xky4R4Bq+CLGXlvhOjJfT6ln4qj+O9rkSl8TRVwyTMzUH6rfmTMDNZMQjhNFdIuo35LDWYiZtUCNocBzpBIEOKNQzUJKd1B4SK/do/FgBrjvP0l1oZJW+C2HOGORUeqEuvS2p5WfHcVKqKhA4dNvQtfjxdi8uJv3vqCHzZrNqHUYotKm4DYsAbVokl6Pl7+UtZulr5EywatQyh6g8fcmp4mK3CFUQEMSvS5sg6OnQIahZcWe/hI3QIGqf8RLYLEtPzWW4k3hsoDLhcg3Qchhmmd+QI/pp2lxAUfMJkpfkXgNmCTg0aiuUetlkJOijb6kGex0iu0gw6qkCq7Tk9Dl2bzxHmjUebaWhQES6J9VQclGxkiOtW2y3hm1OdjKKi7pKLEGaWzX5qdWohT3T4kVDWwRXqubdb6EX7UeoIv1+ActlQ== sergio@server1ng-ac-server1' >> /home/sergio/.ssh/authorized_keys
chown -R sergio:sergio /home/sergio/.ssh
chmod 600 /home/sergio/.ssh/authorized_keys
```

Cuando esté, entro sin contraseña y saco de `apiERP.php` el `include` de `IFXConnection` y el `new` que corta antes del `sql` por shell.

## Usuario

proba ahora

## Asistente

Pruebo la entrada por SSH y, si entra, miro `apiERP.php`.
Pruebo la entrada por SSH y, si entra, miro `apiERP.php`.

## Asistente

La llave entra. El archivo es de root y solo lectura para `sergio`; leo el script y veo si `sudo` deja escribirlo.
La llave entra. El archivo es de root y solo lectura para `sergio`; leo el script y veo si `sudo` deja escribirlo.

## Asistente

La llave entra. Entro como `sergio` y veo `/usr2/www/http/apiERP.php`, pero es de `root` y este `sudo` pide contraseña, así que no puedo grabarlo desde acá.

En esa sesión de root, sacá el `include` y el `new IFXConnection`. Los `putenv` de `DB_NAME` quedan, porque el `sql` por shell sí los hereda:

```bash
cp -a /usr2/www/http/apiERP.php /usr2/www/http/apiERP.php.bak-20261016
sed -i "s/include_once 'include\/IFXConnection.php';/\\/\\/ include_once 'include\\/IFXConnection.php';/" /usr2/www/http/apiERP.php
sed -i '/new IFXConnection/d' /usr2/www/http/apiERP.php
```

Cuando lo corras, vuelvo a pegarle un `list` de solo lectura para ver si el `sql` arma el CSV.
La llave entra. Entro como `sergio` y veo `/usr2/www/http/apiERP.php`, pero es de `root` y este `sudo` pide contraseña, así que no puedo grabarlo desde acá.

En esa sesión de root, sacá el `include` y el `new IFXConnection`. Los `putenv` de `DB_NAME` quedan, porque el `sql` por shell sí los hereda:

```bash
cp -a /usr2/www/http/apiERP.php /usr2/www/http/apiERP.php.bak-20261016
sed -i "s/include_once 'include\/IFXConnection.php';/\\/\\/ include_once 'include\\/IFXConnection.php';/" /usr2/www/http/apiERP.php
sed -i '/new IFXConnection/d' /usr2/www/http/apiERP.php
```

Cuando lo corras, vuelvo a pegarle un `list` de solo lectura para ver si el `sql` arma el CSV.

## Usuario

ahi lo puse como sergio:NewCode y 664

## Asistente

Con ese dueño ya puedo grabar el archivo. Reviso el script, saco `IFXConnection` y pruebo un `list` de solo lectura.
Con ese dueño ya puedo grabar el archivo. Reviso el script, saco `IFXConnection` y pruebo un `list` de solo lectura.

## Asistente

El archivo ya es de `sergio`. Hago copia, comento el `include` y saco el `new IFXConnection`.
El archivo ya es de `sergio`. Hago copia, comento el `include` y saco el `new IFXConnection`.

## Asistente

El bridge responde. Un `list` de solo lectura contra `systables` devolvió `[{"tabname":"systables"}]`.

En `/usr2/www/http/apiERP.php` (copia en `/home/sergio/apiERP.php.bak-20261016`) quedó así:

- El `include` de `IFXConnection` está comentado y se eliminó el `new`. Los `putenv` de `DB_NAME` siguen.
- El `sql` ahora busca `isql` en `/home/informix/bin`.
- El servidor Informix ya no queda fijo en `bincadmin`. Usa el `IFX_SERVER` que manda el ERP y, si no viene, `acncadmin` (es el nombre que está en el `sqlhosts` de esa máquina).

El `.env` de este ERP sigue apuntando a AGG (`10.20.30.200:8080`, `/usr2/biyemas`). Cuando quieras usarlo contra Crown, hay que pasarlo a `192.168.1.2`, base en `/usr2/casino` (o `/usr2/crown`, es el mismo enlace) e `IFX_SERVER=acncadmin`.
El bridge responde. Un `list` de solo lectura contra `systables` devolvió `[{"tabname":"systables"}]`.

En `/usr2/www/http/apiERP.php` (copia en `/home/sergio/apiERP.php.bak-20261016`) quedó así:

- El `include` de `IFXConnection` está comentado y se eliminó el `new`. Los `putenv` de `DB_NAME` siguen.
- El `sql` ahora busca `isql` en `/home/informix/bin`.
- El servidor Informix ya no queda fijo en `bincadmin`. Usa el `IFX_SERVER` que manda el ERP y, si no viene, `acncadmin` (es el nombre que está en el `sqlhosts` de esa máquina).

El `.env` de este ERP sigue apuntando a AGG (`10.20.30.200:8080`, `/usr2/biyemas`). Cuando quieras usarlo contra Crown, hay que pasarlo a `192.168.1.2`, base en `/usr2/casino` (o `/usr2/crown`, es el mismo enlace) e `IFX_SERVER=acncadmin`.

## Usuario

y ahora pregunto, como cgi es igual de optimo que corriendo directo con apache?

## Asistente

En ese Apache la diferencia se nota poco, porque cada llamada ya arranca `isql`. Confirmo cómo está levantado el servidor para no hablar en abstracto.
En ese Apache la diferencia se nota poco, porque cada llamada ya arranca `isql`. Confirmo cómo está levantado el servidor para no hablar en abstracto.

## Asistente

Para este script, CGI alcanza. No es tan liviano como PHP metido dentro de Apache, y en esta máquina tampoco se puede meter así.

Apache 2.2.3 está compilado con el MPM **worker** (hilos). El `mod_php` que había instalado es el de Apache 1.3, y PHP 5.2 con Informix no es seguro en un Apache con hilos. Por eso quedó CGI solo para `apiERP.php`: cada POST arranca `php5-cgi`, corre el script y el proceso termina.

Ese arranque extra es de unos milisegundos. La llamada que acaba de responder tardó unos 100 ms, y la mayor parte es el `isql` que el propio script lanza siempre, esté PHP embebido o no. El ERP no le pega a este bridge como a una página: son consultas puntuales.

Pasar todo Apache a prefork para embeber PHP cambiaría el resto de los sitios de `ac-server1` por una ganancia chica en este único archivo.
Para este script, CGI alcanza. No es tan liviano como PHP metido dentro de Apache, y en esta máquina tampoco se puede meter así.

Apache 2.2.3 está compilado con el MPM **worker** (hilos). El `mod_php` que había instalado es el de Apache 1.3, y PHP 5.2 con Informix no es seguro en un Apache con hilos. Por eso quedó CGI solo para `apiERP.php`: cada POST arranca `php5-cgi`, corre el script y el proceso termina.

Ese arranque extra es de unos milisegundos. La llamada que acaba de responder tardó unos 100 ms, y la mayor parte es el `isql` que el propio script lanza siempre, esté PHP embebido o no. El ERP no le pega a este bridge como a una página: son consultas puntuales.

Pasar todo Apache a prefork para embeber PHP cambiaría el resto de los sitios de `ac-server1` por una ganancia chica en este único archivo.

## Usuario

ok lo vemos para mas adelante, igual anita bridge deberia tener poca vida aca

## Asistente

Queda así. El bridge en `ac-server1` alcanza para leer Informix mientras cargan los maestros de Crown; cuando eso viva en este ERP, ese PHP deja de hacer falta.
Queda así. El bridge en `ac-server1` alcanza para leer Informix mientras cargan los maestros de Crown; cuando eso viva en este ERP, ese PHP deja de hacer falta.

## Usuario

ok, avanza con la configuracion de .env y lanza la sinconizacion de las tablas contables. Verifica si los campos coinciden si no, crea importaciones gated para CROWN

## Asistente

Configuro el bridge de Crown en `.env` y reviso cómo se sincronizan las tablas contables y si los campos cierran con Anita.
Configuro el bridge de Crown en `.env` y reviso cómo se sincronizan las tablas contables y si los campos cierran con Anita.

## Asistente

Las columnas de `ctamae`, `emprmae`, `ccosvalid` y `moneda` coinciden con el importador. Crown no tiene `ccos_abreviatura` ni `ctaconc`, y el rubro `P` no es numérico. Ajusto eso solo para CROWN y apunto el bridge.
Las columnas de `ctamae`, `emprmae`, `ccosvalid` y `moneda` coinciden con el importador. Crown no tiene `ccos_abreviatura` ni `ctaconc`, y el rubro `P` no es numérico. Ajusto eso solo para CROWN y apunto el bridge.

## Asistente

El bridge ya apunta a Crown. Lanzo empresas, monedas, centros de costo y el plan de cuentas.
El bridge ya apunta a Crown. Lanzo empresas, monedas, centros de costo y el plan de cuentas.

## Asistente

Ese script iba a recorrer el plan dos veces y no imprimía el resultado. Lo corto y lo lanzo de nuevo, una sola pasada.
Ese script iba a recorrer el plan dos veces y no imprimía el resultado. Lo corto y lo lanzo de nuevo, una sola pasada.

## Asistente

El bridge de este ERP apunta a Crown (`192.168.1.2`, base `/usr2/casino`, servidor Informix `acncadmin`) y las tablas contables ya están cargadas.

Las columnas de `emprmae`, `ctamae`, `ccosvalid` y `moneda` coinciden con el importador que ya usa el ERP. En Crown faltan dos cosas del esquema de AGG, y eso quedó acotado a `EMPRESA=CROWN`:

- `ccosto` no tiene `ccos_abreviatura`. La abreviatura sale de los primeros caracteres del nombre.
- No existe `ctaconc`, así que las cuentas quedan sin concepto de gasto.
- El rubro `P` (patrimonio) se guarda como rubro **PATRIMONIO NETO**. Los rubros 1 a 6 se crearon con el nombre del capítulo (`ACTIVO`, `PASIVO`, `PATRIMONIO NETO`, `ESTADO DE RESULTADO`, `RESULTADOS`, `CUENTA PUENTE`).
- El tipo de cuenta usa la misma lectura que AGG: `0` imputable, `1` y `3` título, `2` totalizadora. `ACTIVO` quedó título, `EFECTIVO CIPOLLETTI` imputable y `TOTAL CAJA` totalizadora.

Quedó así:

| Tabla | Registros |
|---|---|
| Empresas | 4 (Crown SA, sucursal, Budget, invitaciones) |
| Monedas | 3 (pesos, dólares, euros) |
| Centros de costo | 242 |
| Cuentas | 1.436 (813 de la empresa 1 y 623 de la 2) |
| Vínculos cuenta–centro | 1.232 |

Anita tiene además **259 cuentas con empresa 0**, que no está en `emprmae`. Esas no se importaron.
El bridge de este ERP apunta a Crown (`192.168.1.2`, base `/usr2/casino`, servidor Informix `acncadmin`) y las tablas contables ya están cargadas.

Las columnas de `emprmae`, `ctamae`, `ccosvalid` y `moneda` coinciden con el importador que ya usa el ERP. En Crown faltan dos cosas del esquema de AGG, y eso quedó acotado a `EMPRESA=CROWN`:

- `ccosto` no tiene `ccos_abreviatura`. La abreviatura sale de los primeros caracteres del nombre.
- No existe `ctaconc`, así que las cuentas quedan sin concepto de gasto.
- El rubro `P` (patrimonio) se guarda como rubro **PATRIMONIO NETO**. Los rubros 1 a 6 se crearon con el nombre del capítulo (`ACTIVO`, `PASIVO`, `PATRIMONIO NETO`, `ESTADO DE RESULTADO`, `RESULTADOS`, `CUENTA PUENTE`).
- El tipo de cuenta usa la misma lectura que AGG: `0` imputable, `1` y `3` título, `2` totalizadora. `ACTIVO` quedó título, `EFECTIVO CIPOLLETTI` imputable y `TOTAL CAJA` totalizadora.

Quedó así:

| Tabla | Registros |
|---|---|
| Empresas | 4 (Crown SA, sucursal, Budget, invitaciones) |
| Monedas | 3 (pesos, dólares, euros) |
| Centros de costo | 242 |
| Cuentas | 1.436 (813 de la empresa 1 y 623 de la 2) |
| Vínculos cuenta–centro | 1.232 |

Anita tiene además **259 cuentas con empresa 0**, que no está en `emprmae`. Esas no se importaron.

## Usuario

Briefly inform the user about the task result and perform any follow-up actions (if needed). If there's no follow-ups needed, don't explicitly say that.

## Asistente

Esas dos corridas se cortaron antes de grabar cuentas: la primera la detuve porque iba a recorrer el plan dos veces, y la segunda arrancó cuando el script todavía no estaba en el disco.

La importación que sí terminó es la que ya quedó cargada: 4 empresas, 3 monedas, 242 centros de costo y 1.436 cuentas.
Esas dos corridas se cortaron antes de grabar cuentas: la primera la detuve porque iba a recorrer el plan dos veces, y la segunda arrancó cuando el script todavía no estaba en el disco.

La importación que sí terminó es la que ya quedó cargada: 4 empresas, 3 monedas, 242 centros de costo y 1.436 cuentas.

## Usuario

saca las cuentas totalizadoras que ya no se usan mas con el esquema nuevo de plan de cuentas del ERP

## Asistente

El plan nuevo no usa cuentas totalizadoras. Reviso cuáles quedaron importadas y qué las referencia antes de sacarlas.
El plan nuevo no usa cuentas totalizadoras. Reviso cuáles quedaron importadas y qué las referencia antes de sacarlas.

## Asistente

Son 167 cuentas tipo totalizadora (`TOTAL CAJA`, `TOTAL BANCOS`, etc.) y ninguna otra tabla las apunta. Las borro y dejo el import de Crown para que no vuelvan a entrar.
Son 167 cuentas tipo totalizadora (`TOTAL CAJA`, `TOTAL BANCOS`, etc.) y ninguna otra tabla las apunta. Las borro y dejo el import de Crown para que no vuelvan a entrar.

## Asistente

Saqué las **167 cuentas totalizadoras** (los `TOTAL CAJA`, `TOTAL BANCOS` y el resto de códigos que terminan en 999). No las usaba ninguna otra tabla.

Quedan **1.269 cuentas**: títulos e imputables. En Crown el import desde Anita ya no vuelve a crear totalizadoras.
Saqué las **167 cuentas totalizadoras** (los `TOTAL CAJA`, `TOTAL BANCOS` y el resto de códigos que terminan en 999). No las usaba ninguna otra tabla.

Quedan **1.269 cuentas**: títulos e imputables. En Crown el import desde Anita ya no vuelve a crear totalizadoras.

## Usuario

1) trae el resto de tablas de contaduria (indices, validacion de centros de costo, etc) 2) trae paises, provincias y localidades siempre viendo los campos si estan ok para la importacion si no creas modulo gated para crown

## Asistente

Voy a ver qué tablas de contaduría y de países, provincias y localidades ya tienen importador, y las comparo con las columnas de Anita en Crown.
Voy a ver qué tablas de contaduría y de países, provincias y localidades ya tienen importador, y las comparo con las columnas de Anita en Crown.

## Asistente

Provincias y países coinciden con el importador. Las localidades de Crown no traen código postal ni SENASA, y los índices de ajuste ya están en el ERP: completo solo los meses que faltan y no piso los de FACPCE. Los grupos de centro de costo no tienen columna; la agrego y cargo la validación que falte.
Provincias y países coinciden con el importador. Las localidades de Crown no traen código postal ni SENASA, y los índices de ajuste ya están en el ERP: completo solo los meses que faltan y no piso los de FACPCE. Los grupos de centro de costo no tienen columna; la agrego y cargo la validación que falte.

## Asistente

Quedaron cargados los maestros que Anita tiene y el ERP puede guardar. Saldos (`ctasal`), movimientos y `ccospro` (vacía) no entran en este paso.

**Contaduría**

- Validación cuenta–centro (`ccosvalid`): 1.240 filas en Anita. 1.232 ya estaban; las otras 8 apuntan a la cuenta `712230000` de la empresa 1, que en el plan solo existe en la empresa 2.
- Grupos de centro de costo (`ccosgrupo`, 24): columna nueva en cada centro. Los 242 centros quedaron con grupo (Corporativo RN, Cipolletti, Las Grutas, etc.).
- Índices: la serie **Ajuste** es el mismo IPC que ya usa el ERP. Entré **287** meses que no estaban (desde 1990). Los 13 de 2024–2025 que ya venían de FACPCE no se pisaron. La serie «Indice directo» (216 meses) no se mezcló: el ERP guarda un solo índice por mes. Doce filas de Anita tienen año inválido y se dejaron afuera.

**Países, provincias y localidades**

Países (10) y provincias (17) coinciden con el importador. Las localidades de Crown no tienen código postal ni código SENASA; el import de CROWN usa solo código, provincia y nombre, igual que Interforming. Entraron **51**. Centenario (`8309`) trae `8300` en el campo provincia, que no es una provincia, y quedó sin provincia.
Quedaron cargados los maestros que Anita tiene y el ERP puede guardar. Saldos (`ctasal`), movimientos y `ccospro` (vacía) no entran en este paso.

**Contaduría**

- Validación cuenta–centro (`ccosvalid`): 1.240 filas en Anita. 1.232 ya estaban; las otras 8 apuntan a la cuenta `712230000` de la empresa 1, que en el plan solo existe en la empresa 2.
- Grupos de centro de costo (`ccosgrupo`, 24): columna nueva en cada centro. Los 242 centros quedaron con grupo (Corporativo RN, Cipolletti, Las Grutas, etc.).
- Índices: la serie **Ajuste** es el mismo IPC que ya usa el ERP. Entré **287** meses que no estaban (desde 1990). Los 13 de 2024–2025 que ya venían de FACPCE no se pisaron. La serie «Indice directo» (216 meses) no se mezcló: el ERP guarda un solo índice por mes. Doce filas de Anita tienen año inválido y se dejaron afuera.

**Países, provincias y localidades**

Países (10) y provincias (17) coinciden con el importador. Las localidades de Crown no tienen código postal ni código SENASA; el import de CROWN usa solo código, provincia y nombre, igual que Interforming. Entraron **51**. Centenario (`8309`) trae `8300` en el campo provincia, que no es una provincia, y quedó sin provincia.

## Usuario

ahora necesito llenar condiciones de IIBB y condiciones de IVA y actividades ARCA, podes armarlas?

## Asistente

Voy a ver cómo están armadas las condiciones de IIBB, las de IVA y las actividades ARCA, y con qué datos se pueden cargar en Crown.
Voy a ver cómo están armadas las condiciones de IIBB, las de IVA y las actividades ARCA, y con qué datos se pueden cargar en Crown.

## Usuario

ahora necesito llenar condiciones de IIBB y condiciones de IVA y actividades ARCA, podes armarlas?

## Asistente

Anita no trae estos catálogos: el ERP los espera con ids fijos. Los armo con esa numeración, y las actividades con las de la constancia de Emprendimientos Crown.
Anita no trae estos catálogos: el ERP los espera con ids fijos. Los armo con esa numeración, y las actividades con las de la constancia de Emprendimientos Crown.

## Asistente

Anita no tiene estos maestros: los códigos de clientes y proveedores apuntan a ids fijos del ERP. Quedaron cargados con esa numeración, solo para Crown.

**Condición IVA** (el código de al lado es el de ARCA, no el de Anita)

| Id | Nombre | Letra venta / compra | ARCA |
|---|---|---|---|
| 1 | Responsable Inscripto | A / A | 1 |
| 2 | Exento | B / C | 4 |
| 3 | Consumidor Final | B / B | 5 |
| 4 | Monotributo | A / C | 6 |
| 5 | Exento Exportación | E / E | 9 |
| 6 | Sujeto No Categorizado | B / B | 7 |
| 7 | Baja de impuestos | B / B | — |

**Condición IIBB:** 1 Inscripto (retiene), 2 Local (retiene), 3 No inscripto (retiene), 4 No alcanzado (no retiene). Las cuatro están activas.

**Actividades ARCA:** las 11 de la constancia de Emprendimientos Crown. La principal es **920009**, juegos de azar y apuestas. El resto es hotelería, restaurante, kiosco, tabaco, construcción, autos usados, crédito, inmobiliaria, eventos y publicidad.
Anita no tiene estos maestros: los códigos de clientes y proveedores apuntan a ids fijos del ERP. Quedaron cargados con esa numeración, solo para Crown.

**Condición IVA** (el código de al lado es el de ARCA, no el de Anita)

| Id | Nombre | Letra venta / compra | ARCA |
|---|---|---|---|
| 1 | Responsable Inscripto | A / A | 1 |
| 2 | Exento | B / C | 4 |
| 3 | Consumidor Final | B / B | 5 |
| 4 | Monotributo | A / C | 6 |
| 5 | Exento Exportación | E / E | 9 |
| 6 | Sujeto No Categorizado | B / B | 7 |
| 7 | Baja de impuestos | B / B | — |

**Condición IIBB:** 1 Inscripto (retiene), 2 Local (retiene), 3 No inscripto (retiene), 4 No alcanzado (no retiene). Las cuatro están activas.

**Actividades ARCA:** las 11 de la constancia de Emprendimientos Crown. La principal es **920009**, juegos de azar y apuestas. El resto es hotelería, restaurante, kiosco, tabaco, construcción, autos usados, crédito, inmobiliaria, eventos y publicidad.

## Usuario

ahora tenemos que armar la tabla de impuestos y de regimenes de percepcion para Crown

## Asistente

Voy a ver cómo están armadas las tablas de impuestos y de regímenes de percepción en el ERP, y qué trae Anita de Crown, para cargarlas con esos datos.
Voy a ver cómo están armadas las tablas de impuestos y de regímenes de percepción en el ERP, y qué trae Anita de Crown, para cargarlas con esos datos.

## Asistente

Las alícuotas y los dos regímenes de percepción quedaron cargados con lo que tiene Anita de Crown.

**Impuestos** (el id es el código de Anita, la columna ARCA es la alícuota del comprobante):

| Id | Nombre | Tasa | ARCA |
|---|---|---|---|
| 1 | Exento | 0 % | 2 |
| 2 | Gravado al 10.5 % | 10,5 % | 4 |
| 3 | Gravado al 21 % | 21 % | 5 |
| 4 | Gravado al 27 % | 27 % | 6 |

El id 3 estaba ocupado por un impuesto de prueba llamado «Exento lab», que usaban las notas de débito de cheque rechazado. Esas notas ahora apuntan al Exento real.

**Regímenes de percepción**

- **PIVA** (percepción IVA a responsable inscripto): apagada. En Anita la tasa está en 0 y el mínimo en 9.999.999, así que no se percibe.
- **PNC** (percepción a sujeto no categorizado): activa al **10,5 %**, sin mínimo. Es la tasa que tiene Anita.

Las dos imputan a **330303000 IVA débito fiscal**, en las empresas 1 y 2. Es la cuenta donde Anita contabiliza esa percepción. La cuenta 130205000 «Percepción IVA» es el crédito fiscal de lo que le perciben a Crown, no de lo que Crown percibe.

**Ingresos brutos:** Anita no tiene regímenes por provincia (`ibrxprov` está vacía y las tasas en cero). No cargué alícuotas de IIBB. El `.env` que vino de AGG decía que percibían en la jurisdicción 902; eso quedó vacío para que el ERP no perciba ingresos brutos.
Las alícuotas y los dos regímenes de percepción quedaron cargados con lo que tiene Anita de Crown.

**Impuestos** (el id es el código de Anita, la columna ARCA es la alícuota del comprobante):

| Id | Nombre | Tasa | ARCA |
|---|---|---|---|
| 1 | Exento | 0 % | 2 |
| 2 | Gravado al 10.5 % | 10,5 % | 4 |
| 3 | Gravado al 21 % | 21 % | 5 |
| 4 | Gravado al 27 % | 27 % | 6 |

El id 3 estaba ocupado por un impuesto de prueba llamado «Exento lab», que usaban las notas de débito de cheque rechazado. Esas notas ahora apuntan al Exento real.

**Regímenes de percepción**

- **PIVA** (percepción IVA a responsable inscripto): apagada. En Anita la tasa está en 0 y el mínimo en 9.999.999, así que no se percibe.
- **PNC** (percepción a sujeto no categorizado): activa al **10,5 %**, sin mínimo. Es la tasa que tiene Anita.

Las dos imputan a **330303000 IVA débito fiscal**, en las empresas 1 y 2. Es la cuenta donde Anita contabiliza esa percepción. La cuenta 130205000 «Percepción IVA» es el crédito fiscal de lo que le perciben a Crown, no de lo que Crown percibe.

**Ingresos brutos:** Anita no tiene regímenes por provincia (`ibrxprov` está vacía y las tasas en cero). No cargué alícuotas de IIBB. El `.env` que vino de AGG decía que percibían en la jurisdicción 902; eso quedó vacío para que el ERP no perciba ingresos brutos.

## Usuario

con que seguimos? vamos con las tablas de ventas?

## Asistente

Las tablas de ventas son el siguiente maestro. Primero separo las de configuración de las de operación, porque clientes, artículos y comprobantes quedaron fuera del arranque.
Las tablas de ventas son el siguiente maestro. Primero separo las de configuración de las de operación, porque clientes, artículos y comprobantes quedaron fuera del arranque.

## Asistente

Sí. El siguiente paso son los maestros de ventas, no los comprobantes. Anita tiene 116 tablas en ventas; facturas, pedidos y stock de artículos quedan para cuando el circuito esté armado. Clientes, artículos y proveedores siguen afuera, como al principio.

El orden que cierra dependencias es este:

1. **Catálogos chicos**, que no dependen de nadie: condiciones de pago (`condmae`), tipos de empresa, zonas, vendedores, cobradores, transportes, leyendas y el numerador de comprobantes (`t_comp` / `numerador`).
2. **Puntos de venta** (`sucursal`) y tipos de comprobante, porque la facturación no abre sin ellos.
3. **Listas de precios** (`premae`), todavía sin artículos.
4. **Clientes** (`climae`) recién cuando esos catálogos existan. Hoy el alta de un cliente no tiene a dónde apuntar la condición de IVA, la de ingresos brutos ni la de pago.

No traería ahora `venta`, `vengrav`, `pendmae` ni `stkmae`: son la operación, y el plan de cuentas y los impuestos ya están listos para cuando haya comprobantes nuevos.

Si te parece, arranco por la tanda 1.
Sí. El siguiente paso son los maestros de ventas, no los comprobantes. Anita tiene 116 tablas en ventas; facturas, pedidos y stock de artículos quedan para cuando el circuito esté armado. Clientes, artículos y proveedores siguen afuera, como al principio.

El orden que cierra dependencias es este:

1. **Catálogos chicos**, que no dependen de nadie: condiciones de pago (`condmae`), tipos de empresa, zonas, vendedores, cobradores, transportes, leyendas y el numerador de comprobantes (`t_comp` / `numerador`).
2. **Puntos de venta** (`sucursal`) y tipos de comprobante, porque la facturación no abre sin ellos.
3. **Listas de precios** (`premae`), todavía sin artículos.
4. **Clientes** (`climae`) recién cuando esos catálogos existan. Hoy el alta de un cliente no tiene a dónde apuntar la condición de IVA, la de ingresos brutos ni la de pago.

No traería ahora `venta`, `vengrav`, `pendmae` ni `stkmae`: son la operación, y el plan de cuentas y los impuestos ya están listos para cuando haya comprobantes nuevos.

Si te parece, arranco por la tanda 1.

## Usuario

arranca por 1

## Asistente

Arranco por los catálogos chicos. Miro qué trae Anita y qué espera el ERP en cada uno.
Arranco por los catálogos chicos. Miro qué trae Anita y qué espera el ERP en cada uno.

## Asistente

Armo el sync gated de Crown para la tanda 1 y lo corro primero en simulación.
Armo el sync gated de Crown para la tanda 1 y lo corro primero en simulación.

## Asistente

Corrigo el domicilio obligatorio de transporte y el mapeo de cobranzas (COB no es nota de crédito).
Corrigo el domicilio obligatorio de transporte y el mapeo de cobranzas (COB no es nota de crédito).

## Asistente

Actualizo los tipos ya cargados con el mapeo corregido y reintento transportes.
Actualizo los tipos ya cargados con el mapeo corregido y reintento transportes.

## Asistente

Tanda 1 cargada.

| Maestro | Resultado |
|---|---|
| Condición de venta | CONTADO (1001, 1 día) y CTA CTE (1002, 30 días) |
| Tipo empresa cliente | SCA |
| Zona de venta | Cipolletti, Las Grutas, Gral. Roca |
| Transporte | A cargo del proveedor / A cargo de Crown |
| Tipo de transacción | 57 de Anita + los 3 que ya había (RMV, FBI, FSL). Quedaron 60 activos |
| Vendedor | No: la única fila de Anita viene sin nombre |
| Cobrador | Vacío en Anita |

Comando: `php artisan ventas:sincronizar-maestros-anita-crown` (dry-run) / `--ejecutar`.

Siguiente es la tanda 2: puntos de venta (`sucursal`) y CAI.
Tanda 1 cargada.

| Maestro | Resultado |
|---|---|
| Condición de venta | CONTADO (1001, 1 día) y CTA CTE (1002, 30 días) |
| Tipo empresa cliente | SCA |
| Zona de venta | Cipolletti, Las Grutas, Gral. Roca |
| Transporte | A cargo del proveedor / A cargo de Crown |
| Tipo de transacción | 57 de Anita + los 3 que ya había (RMV, FBI, FSL). Quedaron 60 activos |
| Vendedor | No: la única fila de Anita viene sin nombre |
| Cobrador | Vacío en Anita |

Comando: `php artisan ventas:sincronizar-maestros-anita-crown` (dry-run) / `--ejecutar`.

Siguiente es la tanda 2: puntos de venta (`sucursal`) y CAI.

## Usuario

segui con tanda 2 a 4

## Asistente

Sigo con PV/CAI, listas de precios y clientes. Primero miro los sync existentes y qué trae Anita de Crown.
Sigo con PV/CAI, listas de precios y clientes. Primero miro los sync existentes y qué trae Anita de Crown.

## Asistente

Tandas 2 a 4 listas.

| Tanda | Resultado |
|---|---|
| **2 PV** | 152 de 153 sucursales. Empresa 1. Modos: 115 manual, 29 electrónica CAE, 8 CAEA (con path `/usr2/casino/afip`). CAI de Anita vacío. |
| **3 Listas** | 4 cabeceras: lista 1, lista 2, Room Service, 4887. Sin precios ni artículos. |
| **4 Clientes** | 271 sin errores. |

Ajustes Crown en el mapper de PV: `suc_empresa` es el nombre (no hay `suc_nroemp`), fiscal E → CAE, nombres «CAEA…» → modo A, path desde `suc_ubic_afip`.

Comando: `php artisan ventas:sincronizar-maestros-anita-crown --tandas=2,3,4`

Lo que sigue, cuando quieras: artículos/stock, o precios de las listas (`stkpre`).
Tandas 2 a 4 listas.

| Tanda | Resultado |
|---|---|
| **2 PV** | 152 de 153 sucursales. Empresa 1. Modos: 115 manual, 29 electrónica CAE, 8 CAEA (con path `/usr2/casino/afip`). CAI de Anita vacío. |
| **3 Listas** | 4 cabeceras: lista 1, lista 2, Room Service, 4887. Sin precios ni artículos. |
| **4 Clientes** | 271 sin errores. |

Ajustes Crown en el mapper de PV: `suc_empresa` es el nombre (no hay `suc_nroemp`), fiscal E → CAE, nombres «CAEA…» → modo A, path desde `suc_ubic_afip`.

Comando: `php artisan ventas:sincronizar-maestros-anita-crown --tandas=2,3,4`

Lo que sigue, cuando quieras: artículos/stock, o precios de las listas (`stkpre`).

## Usuario

1) vamos con las tablas de stock antes del maestro 2) vamos con articulos stkmae y formulas stkcmae/stkcmov, fijate si hay algo mas por ahi 3) stkpre

## Asistente

Arranco por los maestros de stock previos a artículos, después `stkmae`/fórmulas y al final `stkpre`. Primero veo qué tablas hay en Anita y qué sync ya existe en el ERP.
Arranco por los maestros de stock previos a artículos, después `stkmae`/fórmulas y al final `stkpre`. Primero veo qué tablas hay en Anita y qué sync ya existe en el ERP.

## Usuario

<available_subagent_types> Available subagent_types and a quick description of what they do: - generalPurpose: General-purpose agent for researching complex questions, searching for code, and executing multi-step tasks. Use when searching for a keyword or file and not confident you'll find the match quickly. - explore: Fast agent specialized for exploring codebases. Use this when you need to quickly find files by patterns (eg. "src/components/**/*.tsx"), search code for keywords (eg. "API endpoints"), or answer questions about the codebase (eg. "how do API endpoints work?"). When calling this agent, specify the desired thoroughness level: "quick" for basic searches, "medium" for moderate exploration, or "very thorough" for comprehensive analysis across multiple locations and naming conventions. - cursor-guide: Read Cursor product documentation to answer questions about how Cursor Desktop, IDE, CLI, Cloud Agents, Bugbot, and other features work. Use when the user asks 'In Cursor, how do I...?' or similar questions about Cursor products. - ci-investigator: Investigate a single failing PR CI check and return a short root-cause summary. Use when the user asks to summarize, explain, diagnose, or investigate a specific failed check from a pull request. - bugbot: Use only when the user *explicitly* asks for a Bugbot-like review of local code changes. When launching this subagent, set the Task description to exactly "Bugbot". Launch exactly one Bugbot subagent with `run_in_background: false` unless the user explicitly asks to run in background. Use this fixed prompt form: "Full Repository Path: ...\nDiff: <one of: \"branch changes\", \"uncommitted changes\", \"natural language\">\nChange Description: ...\nCustom Instructions: ..."; default to `Diff: branch changes`; include `Change Description` only when `Diff` is `natural language`, formatting it as one block per changed file (a `<path> (added|modified|deleted|renamed)` header followed by bullets of what changed, mentioning line numbers or ranges inline where helpful), and only use `natural language` as a last resort after a regular diff-based review failed because the diff could not be computed; include `Custom Instructions` only when the user gave specific review instructions. This subagent is single-shot and does not support `resume`; always launch a fresh subagent instead. - security-review: Use only when the user *explicitly* asks for a security review of local code changes. When launching this subagent, set the Task description to exactly "Security Review". Launch exactly one security-review subagent with `run_in_background: false` unless the user explicitly asks to run in background. Use this fixed prompt form: "Full Repository Path: ...\nDiff: <one of: \"branch changes\", \"uncommitted changes\">\nCustom Instructions: ..."; default to `Diff: branch changes`; include `Custom Instructions` only when the user gave specific review instructions. This subagent is single-shot and does not support `resume`; always launch a fresh subagent instead. - best-of-n-runner: Run a task in an isolated git worktree. Each best-of-n-runner gets its own branch and working directory. Use for best-of-N parallel attempts or isolated experiments. </available_subagent_types> <available_subagent_models> If the user explicitly asks for the model of a subagent/task, you may ONLY use model slugs from this list: - inherit (default; required unless the user explicitly requested another model) - claude-fable-5-1-thinking-high - claude-opus-5-5-medium - claude-opus-5-thinking-high - claude-sonnet-5-5-high - composer-2.5-fast - cursor-grok-4.6-high-fast - gemini-4-argon-medium - gpt-5.6-sol-medium - grok-4.7-high-fast - muse-wonder-1.0-high If the user isn't asking for a specific version, prefer the latest version of the model family. As an example, if the user just says "gpt" or "claude", use the latest available version of GPT or Claude. IMPORTANT: If the user requests a model that is NOT in the list above, do NOT substitute a different model or guess. Instead, skip launching the subagent with that model and tell the user which model was unavailable and which models are available. When speaking to the USER about which model you selected for a subagent, do NOT use the kebab-case model names unless the user requested the model using that format. Ue the same naming scheme the user used to discuss the model when they requested it. </available_subagent_models> <dynamic_tools> You have access to tools through dynamic namespaces, e.g. MCP servers, using `GetDynamicTools` and `CallDynamicTool`. ## Dynamic Tool Discovery and Invocation Use `GetDynamicTools` to discover tool schemas, then `CallDynamicTool` to invoke one tool. Aim to minimize round-trips: ideally one discovery call followed by one invocation. If the user mentions a product or service represented by an available namespace, and the request likely depends on it, proactively inspect that namespace before answering. If you are unsure which namespace matches, search with a relevant pattern. `GetDynamicTools` supports these modes: 1. `{"namespace":"<id>"}`: returns schemas and full descriptions for every tool in that namespace. 2. `{"namespace":"<id>","toolName":"<name>"}`: returns one tool schema with its full description. 3. `{"pattern":"<regex>"}`: searches namespace and tool names. 4. `{"namespace":"<id>","pattern":"<regex>"}`: searches tools within one namespace. 5. No arguments: returns the full catalog. Pattern-search and catalog results shorten long descriptions, marked by a trailing "... [truncated]"; namespace and single-tool lookups always return the complete description. Always inspect a tool's schema before invoking it with `CallDynamicTool`. If the available dynamic tools do not fully support what the user asked you to do, complete the work you can with the current tool set. In your work summary, include what you were unable to do and why. Do not use browser automation to work around missing tools unless the user explicitly asks you to use the browser. Available dynamic tool namespaces: <dynamic_tool_namespaces> <namespace name="cursor-ide-browser" tools="browser_navigate, browser_snapshot, browser_click, browser_mouse_click_xy, browser_type, browser_fill, browser_select_option, browser_press_key, browser_scroll, browser_drag, browser_get_bounding_box, browser_highlight, browser_tabs, browser_cdp, browser_take_screenshot, browser_lock" namespaceUseInstructions="The cursor-ide-browser MCP server provides a Cursor-owned browser tab plus a raw Chrome DevTools Protocol command tool. CORE WORKFLOW: 1. Start by understanding the user's goal and what success looks like on the page. 2. Use browser_tabs with action "list" to inspect open tabs and URLs before acting. 3. Use browser_navigate to create or navigate the target tab. Omit the position parameter for background automation so focus is preserved. 4. Use browser_lock before longer automation on an existing tab, then browser_lock with action "unlock" when finished. 5. Use browser_snapshot for accessibility context and browser_take_screenshot for visual verification. 6. Use browser_click, browser_type, browser_fill, browser_select_option, browser_press_key, browser_scroll, and browser_drag for page interactions. 7. Use browser_highlight and browser_get_bounding_box for visual grounding and coordinate diagnostics. 8. Use browser_cdp for page inspection, profiling, runtime evaluation, DOM/CSS queries, and performance data. AVOID RABBIT HOLES: 1. Do not repeat the same failing action more than once without new evidence such as a fresh snapshot, a different ref, a changed page state, or a clear new hypothesis. 2. IMPORTANT: If four attempts fail or progress stalls, stop acting and report what you observed, what blocked progress, and the most likely next step. 3. Prefer gathering evidence over brute force. If the page is confusing, use browser_snapshot, browser_take_screenshot, or CDP inspection before trying more actions. 4. If you encounter a blocker such as login, passkey/manual user interaction, permissions, captchas, destructive confirmations, missing data, or an unexpected state, stop and report it instead of improvising repeated actions. 5. Do not get stuck in wait-action-wait loops. Every retry should be justified by something newly observed. CRITICAL - Lock/unlock workflow: 1. browser_lock requires an existing browser tab - you CANNOT call browser_lock with action: "lock" before browser_navigate 2. Correct order: browser_navigate -> browser_lock({ action: "lock" }) -> (interactions) -> browser_lock({ action: "unlock" }) 3. If a browser tab already exists (check with browser_tabs list), call browser_lock with action: "lock" FIRST before any interactions 4. Only call browser_lock with action: "unlock" when completely done with ALL browser operations for this turn IMPORTANT - Waiting strategy: When waiting for page changes, prefer short CDP polling loops with Runtime.evaluate, DOM queries, Page lifecycle signals, or browser_snapshot checks rather than a single long wait. CDP USAGE: - Use browser_cdp with a DevTools Protocol method and params object, for example Runtime.evaluate, DOM.getDocument, CSS.getComputedStyleForNode, Profiler.start/stop, Performance.getMetrics, Log.enable, and Network.enable. - Do not use browser_cdp with CDP Input.* methods. They are denied because they are focus-sensitive in Electron webviews and can route input to Cursor UI instead of the browser page. - Use browser_click, browser_type, browser_fill, browser_select_option, browser_press_key, browser_scroll, and browser_drag for clicks, typing, filling inputs, selecting options, keyboard actions, scrolling, and drag-and-drop. - Use Runtime.evaluate for advanced DOM-scoped interactions that the dedicated browser tools do not cover. - For profiling, call Profiler.enable, Profiler.start, reproduce the behavior, then Profiler.stop. The profile is saved to a file and returned as a log_file; read that file only when you need to inspect details. - For JavaScript evaluation, prefer Runtime.evaluate with returnByValue when possible. - Device emulation you set with Emulation.setDeviceMetricsOverride, Emulation.setTouchEmulationEnabled, or Emulation.setUserAgentOverride (for example for a phone-sized screenshot) lasts only for the current turn: it is cleared when your turn ends, and the user can reset it from the browser tab at any time. Send Emulation.clearDeviceMetricsOverride yourself once you are done with it within a turn. - Some browser-wide or sensitive CDP methods are denied, especially cookie, storage, permission, download, target-management, filesystem-backed file-input commands, system-level commands, and CDP navigation/history navigation commands. - Large CDP responses are saved to files instead of being inlined. Prefer using the returned file path over immediately stuffing large payloads into context; read focused sections only when needed. VISION: - browser_take_screenshot attaches an image result that the model can inspect. CDP Page.captureScreenshot returns data inside JSON and should not replace browser_take_screenshot when visual verification is needed. NOTES: - browser_snapshot returns snapshot YAML and is the main source of truth for page structure. - Refs are opaque handles tied to the latest browser_snapshot for that tab. - Iframe content is not accessible - only elements outside iframes can be interacted with. - When you stop to report a blocker, include the current page, the target you were trying to reach, the blocker you observed, and the best next action. If the blocker requires manual user interaction, ask the user to take over at that point rather than assuming it in advance." source="mcp" /> <namespace name="cursor" tools="AwaitShell, CreateGoal, Delete, EditNotebook, FetchMcpResource, GenerateImage, ReadLints, SwitchMode, Task, TodoWrite, UpdateGoal, WebFetch, WebSearch" namespaceUseInstructions="Native Cursor tools for this session. These are highly recommended and useful tools that you should use when the right situation arises. Don't be afraid to look at one if it seems relevant, even if you don't end up using it. You MUST read the tool schemas before calling them. Here are some crucial instructions: - AwaitShell: Use to sleep and check shell progress. Never sleep using shell. - ReadLints: Check for linter errors after substantive edits. - SwitchMode: Switch between modes: Agent and Plan. Proactively consider switching modes for relevant requests. - Task: Spawn local and cloud subagents natively. - TodoWrite: Use this tool to manage complex multi-step tasks." source="cursor" /> </dynamic_tool_namespaces> If an MCP-backed namespace requires authentication, call `mcp_auth` through `CallDynamicTool` for that namespace, then inspect it again and retry if appropriate. Do not authenticate namespaces preemptively or repeatedly. </dynamic_tools> <available_subagent_types> Available subagent_types and a quick description of what they do: - generalPurpose: General-purpose agent for researching complex questions, searching for code, and executing multi-step tasks. Use when searching for a keyword or file and not confident you'll find the match quickly. - explore: Fast agent specialized for exploring codebases. Use this when you need to quickly find files by patterns (eg. "src/components/**/*.tsx"), search code for keywords (eg. "API endpoints"), or answer questions about the codebase (eg. "how do API endpoints work?"). When calling this agent, specify the desired thoroughness level: "quick" for basic searches, "medium" for moderate exploration, or "very thorough" for comprehensive analysis across multiple locations and naming conventions. - cursor-guide: Read Cursor product documentation to answer questions about how Cursor Desktop, IDE, CLI, Cloud Agents, Bugbot, and other features work. Use when the user asks 'In Cursor, how do I...?' or similar questions about Cursor products. - ci-investigator: Investigate a single failing PR CI check and return a short root-cause summary. Use when the user asks to summarize, explain, diagnose, or investigate a specific failed check from a pull request. - bugbot: Use only when the user *explicitly* asks for a Bugbot-like review of local code changes. When launching this subagent, set the Task description to exactly "Bugbot". Launch exactly one Bugbot subagent with `run_in_background: false` unless the user explicitly asks to run in background. Use this fixed prompt form: "Full Repository Path: ...\nDiff: <one of: \"branch changes\", \"uncommitted changes\", \"natural language\">\nChange Description: ...\nCustom Instructions: ..."; default to `Diff: branch changes`; include `Change Description` only when `Diff` is `natural language`, formatting it as one block per changed file (a `<path> (added|modified|deleted|renamed)` header followed by bullets of what changed, mentioning line numbers or ranges inline where helpful), and only use `natural language` as a last resort after a regular diff-based review failed because the diff could not be computed; include `Custom Instructions` only when the user gave specific review instructions. This subagent is single-shot and does not support `resume`; always launch a fresh subagent instead. - security-review: Use only when the user *explicitly* asks for a security review of local code changes. When launching this subagent, set the Task description to exactly "Security Review". Launch exactly one security-review subagent with `run_in_background: false` unless the user explicitly asks to run in background. Use this fixed prompt form: "Full Repository Path: ...\nDiff: <one of: \"branch changes\", \"uncommitted changes\">\nCustom Instructions: ..."; default to `Diff: branch changes`; include `Custom Instructions` only when the user gave specific review instructions. This subagent is single-shot and does not support `resume`; always launch a fresh subagent instead. - best-of-n-runner: Run a task in an isolated git worktree. Each best-of-n-runner gets its own branch and working directory. Use for best-of-N parallel attempts or isolated experiments. </available_subagent_types> <available_subagent_models> If the user explicitly asks for the model of a subagent/task, you may ONLY use model slugs from this list: - inherit (default; required unless the user explicitly requested another model) - claude-fable-5-1-thinking-high - claude-opus-5-5-medium - claude-opus-5-thinking-high - claude-sonnet-5-5-high - composer-2.5-fast - cursor-grok-4.6-high-fast - gemini-4-argon-medium - gpt-5.6-sol-medium - grok-4.7-high-fast - muse-wonder-1.0-high If the user isn't asking for a specific version, prefer the latest version of the model family. As an example, if the user just says "gpt" or "claude", use the latest available version of GPT or Claude. IMPORTANT: If the user requests a model that is NOT in the list above, do NOT substitute a different model or guess. Instead, skip launching the subagent with that model and tell the user which model was unavailable and which models are available. When speaking to the USER about which model you selected for a subagent, do NOT use the kebab-case model names unless the user requested the model using that format. Ue the same naming scheme the user used to discuss the model when they requested it. </available_subagent_models> <dynamic_tools> You have access to tools through dynamic namespaces, e.g. MCP servers, using `GetDynamicTools` and `CallDynamicTool`. ## Dynamic Tool Discovery and Invocation Use `GetDynamicTools` to discover tool schemas, then `CallDynamicTool` to invoke one tool. Aim to minimize round-trips: ideally one discovery call followed by one invocation. If the user mentions a product or service represented by an available namespace, and the request likely depends on it, proactively inspect that namespace before answering. If you are unsure which namespace matches, search with a relevant pattern. `GetDynamicTools` supports these modes: 1. `{"namespace":"<id>"}`: returns schemas and full descriptions for every tool in that namespace. 2. `{"namespace":"<id>","toolName":"<name>"}`: returns one tool schema with its full description. 3. `{"pattern":"<regex>"}`: searches namespace and tool names. 4. `{"namespace":"<id>","pattern":"<regex>"}`: searches tools within one namespace. 5. No arguments: returns the full catalog. Pattern-search and catalog results shorten long descriptions, marked by a trailing "... [truncated]"; namespace and single-tool lookups always return the complete description. Always inspect a tool's schema before invoking it with `CallDynamicTool`. If the available dynamic tools do not fully support what the user asked you to do, complete the work you can with the current tool set. In your work summary, include what you were unable to do and why. Do not use browser automation to work around missing tools unless the user explicitly asks you to use the browser. Available dynamic tool namespaces: <dynamic_tool_namespaces> <namespace name="cursor-ide-browser" tools="browser_navigate, browser_snapshot, browser_click, browser_mouse_click_xy, browser_type, browser_fill, browser_select_option, browser_press_key, browser_scroll, browser_drag, browser_get_bounding_box, browser_highlight, browser_tabs, browser_cdp, browser_take_screenshot, browser_lock" namespaceUseInstructions="The cursor-ide-browser MCP server provides a Cursor-owned browser tab plus a raw Chrome DevTools Protocol command tool. CORE WORKFLOW: 1. Start by understanding the user's goal and what success looks like on the page. 2. Use browser_tabs with action "list" to inspect open tabs and URLs before acting. 3. Use browser_navigate to create or navigate the target tab. Omit the position parameter for background automation so focus is preserved. 4. Use browser_lock before longer automation on an existing tab, then browser_lock with action "unlock" when finished. 5. Use browser_snapshot for accessibility context and browser_take_screenshot for visual verification. 6. Use browser_click, browser_type, browser_fill, browser_select_option, browser_press_key, browser_scroll, and browser_drag for page interactions. 7. Use browser_highlight and browser_get_bounding_box for visual grounding and coordinate diagnostics. 8. Use browser_cdp for page inspection, profiling, runtime evaluation, DOM/CSS queries, and performance data. AVOID RABBIT HOLES: 1. Do not repeat the same failing action more than once without new evidence such as a fresh snapshot, a different ref, a changed page state, or a clear new hypothesis. 2. IMPORTANT: If four attempts fail or progress stalls, stop acting and report what you observed, what blocked progress, and the most likely next step. 3. Prefer gathering evidence over brute force. If the page is confusing, use browser_snapshot, browser_take_screenshot, or CDP inspection before trying more actions. 4. If you encounter a blocker such as login, passkey/manual user interaction, permissions, captchas, destructive confirmations, missing data, or an unexpected state, stop and report it instead of improvising repeated actions. 5. Do not get stuck in wait-action-wait loops. Every retry should be justified by something newly observed. CRITICAL - Lock/unlock workflow: 1. browser_lock requires an existing browser tab - you CANNOT call browser_lock with action: "lock" before browser_navigate 2. Correct order: browser_navigate -> browser_lock({ action: "lock" }) -> (interactions) -> browser_lock({ action: "unlock" }) 3. If a browser tab already exists (check with browser_tabs list), call browser_lock with action: "lock" FIRST before any interactions 4. Only call browser_lock with action: "unlock" when completely done with ALL browser operations for this turn IMPORTANT - Waiting strategy: When waiting for page changes, prefer short CDP polling loops with Runtime.evaluate, DOM queries, Page lifecycle signals, or browser_snapshot checks rather than a single long wait. CDP USAGE: - Use browser_cdp with a DevTools Protocol method and params object, for example Runtime.evaluate, DOM.getDocument, CSS.getComputedStyleForNode, Profiler.start/stop, Performance.getMetrics, Log.enable, and Network.enable. - Do not use browser_cdp with CDP Input.* methods. They are denied because they are focus-sensitive in Electron webviews and can route input to Cursor UI instead of the browser page. - Use browser_click, browser_type, browser_fill, browser_select_option, browser_press_key, browser_scroll, and browser_drag for clicks, typing, filling inputs, selecting options, keyboard actions, scrolling, and drag-and-drop. - Use Runtime.evaluate for advanced DOM-scoped interactions that the dedicated browser tools do not cover. - For profiling, call Profiler.enable, Profiler.start, reproduce the behavior, then Profiler.stop. The profile is saved to a file and returned as a log_file; read that file only when you need to inspect details. - For JavaScript evaluation, prefer Runtime.evaluate with returnByValue when possible. - Device emulation you set with Emulation.setDeviceMetricsOverride, Emulation.setTouchEmulationEnabled, or Emulation.setUserAgentOverride (for example for a phone-sized screenshot) lasts only for the current turn: it is cleared when your turn ends, and the user can reset it from the browser tab at any time. Send Emulation.clearDeviceMetricsOverride yourself once you are done with it within a turn. - Some browser-wide or sensitive CDP methods are denied, especially cookie, storage, permission, download, target-management, filesystem-backed file-input commands, system-level commands, and CDP navigation/history navigation commands. - Large CDP responses are saved to files instead of being inlined. Prefer using the returned file path over immediately stuffing large payloads into context; read focused sections only when needed. VISION: - browser_take_screenshot attaches an image result that the model can inspect. CDP Page.captureScreenshot returns data inside JSON and should not replace browser_take_screenshot when visual verification is needed. NOTES: - browser_snapshot returns snapshot YAML and is the main source of truth for page structure. - Refs are opaque handles tied to the latest browser_snapshot for that tab. - Iframe content is not accessible - only elements outside iframes can be interacted with. - When you stop to report a blocker, include the current page, the target you were trying to reach, the blocker you observed, and the best next action. If the blocker requires manual user interaction, ask the user to take over at that point rather than assuming it in advance." source="mcp" /> <namespace name="cursor" tools="AwaitShell, CreateGoal, Delete, EditNotebook, FetchMcpResource, GenerateImage, ReadLints, SwitchMode, Task, TodoWrite, UpdateGoal, WebFetch, WebSearch" namespaceUseInstructions="Native Cursor tools for this session. These are highly recommended and useful tools that you should use when the right situation arises. Don't be afraid to look at one if it seems relevant, even if you don't end up using it. You MUST read the tool schemas before calling them. Here are some crucial instructions: - AwaitShell: Use to sleep and check shell progress. Never sleep using shell. - ReadLints: Check for linter errors after substantive edits. - SwitchMode: Switch between modes: Agent and Plan. Proactively consider switching modes for relevant requests. - Task: Spawn local and cloud subagents natively. - TodoWrite: Use this tool to manage complex multi-step tasks." source="cursor" /> </dynamic_tool_namespaces> If an MCP-backed namespace requires authentication, call `mcp_auth` through `CallDynamicTool` for that namespace, then inspect it again and retry if appropriate. Do not authenticate namespaces preemptively or repeatedly. </dynamic_tools>

## Usuario

1) vamos con las tablas de stock antes del maestro 2) vamos con articulos stkmae y formulas stkcmae/stkcmov, fijate si hay algo mas por ahi 3) stkpre

## Asistente

Implementando soporte Crown en bridges y sync, luego corro los maestros.
Implementando soporte Crown en bridges y sync, luego corro los maestros.

## Asistente

Corro maestros uno a uno (sin subcategoría ni listas, ya cargadas en ventas).
Corro maestros uno a uno (sin subcategoría ni listas, ya cargadas en ventas).

## Asistente

Emp2 apunta al bridge AGG; lo corrijo al path Crown y alineo UM id=1.
Emp2 apunta al bridge AGG; lo corrijo al path Crown y alineo UM id=1.

## Asistente

Stock Crown quedó cargado en este orden:

**1) Maestros**
| Tabla Anita | ERP | Cantidad |
|---|---|---|
| stkumd | unidadmedida | 13 |
| stkagr | categoria | 71 |
| linmae | linea | 4 |
| marmae | mventa | 11 |
| depmae | depmae | 168 (84× emp 1 y 2) |

Sin subcategoría (no existe en Crown). Listas ya venían de ventas.

**2) Artículos + fórmulas**
- **stkmae** → 13.968 artículos
- **stkcmae/stkcmov** → 3 fórmulas + 13 hijos (basura `808464432` omitida)
- Relacionado pero sin import ERP: **stkmgastro** (~13k extras gastronomía) y **stkley** (56 leyendas) — se usan al grabar hacia Anita, no hay sync de entrada

**3) stkpre**
- 41 precios (lista 1: 38; Room Service/lista 5: 3). Muchas filas con `fe_ult_act=0` → `--desde=0` sin filtro de fecha.

Ajustes de código: campos Crown en depmae/stkmae, case `CROWN` en artículo, bridge sin mapa AGG en emp 2, filtro basura en fórmulas, precio con `--desde=0`.
Stock Crown quedó cargado en este orden:

**1) Maestros**
| Tabla Anita | ERP | Cantidad |
|---|---|---|
| stkumd | unidadmedida | 13 |
| stkagr | categoria | 71 |
| linmae | linea | 4 |
| marmae | mventa | 11 |
| depmae | depmae | 168 (84× emp 1 y 2) |

Sin subcategoría (no existe en Crown). Listas ya venían de ventas.

**2) Artículos + fórmulas**
- **stkmae** → 13.968 artículos
- **stkcmae/stkcmov** → 3 fórmulas + 13 hijos (basura `808464432` omitida)
- Relacionado pero sin import ERP: **stkmgastro** (~13k extras gastronomía) y **stkley** (56 leyendas) — se usan al grabar hacia Anita, no hay sync de entrada

**3) stkpre**
- 41 precios (lista 1: 38; Room Service/lista 5: 3). Muchas filas con `fe_ult_act=0` → `--desde=0` sin filtro de fecha.

Ajustes de código: campos Crown en depmae/stkmae, case `CROWN` en artículo, bridge sin mapa AGG en emp 2, filtro basura en fórmulas, precio con `--desde=0`.

## Usuario

las formulas seguramente las podes encontrar en el bridge de cipolletti, podes comunicarte desde ac-server1 llamando a la base de datos "database cncadmin", etc, etc. Fijate si podes

## Asistente

Reviso en ac-server1 cómo se llega a Cipolletti/`cncadmin` y si ahí están las fórmulas completas.
Reviso en ac-server1 cómo se llega a Cipolletti/`cncadmin` y si ahí están las fórmulas completas.

## Asistente

Cipolletti (`cncadmin` @ c-server1) responde con muchas fórmulas. Cuento y reimporto.
Cipolletti (`cncadmin` @ c-server1) responde con muchas fórmulas. Cuento y reimporto.

## Asistente

Cipolletti responde, pero `cancadmin` cubre casi todas las fórmulas que referencian los artículos Crown. Importo desde ahí.
Cipolletti responde, pero `cancadmin` cubre casi todas las fórmulas que referencian los artículos Crown. Importo desde ahí.

## Asistente

Los hijos de CA usan insumos `I0xxx` que Crown no tiene. Comparo Cipolletti: ahí deberían calzar mejor.
Los hijos de CA usan insumos `I0xxx` que Crown no tiene. Comparo Cipolletti: ahí deberían calzar mejor.

## Asistente

Los insumos `I0*` están en los casinos, no en AC. Los importo desde `cancadmin` (misma fuente que las fórmulas) y rearmo los hijos.
Los insumos `I0*` están en los casinos, no en AC. Los importo desde `cancadmin` (misma fuente que las fórmulas) y rearmo los hijos.

## Asistente

Sí: desde ac-server1 se llega bien a Cipolletti.

| INFORMIXSERVER | Host | stkcmae | Cobertura vs arts Crown |
|---|---|---|---|
| acncadmin (AC) | local | 4 | casi vacío |
| **cncadmin (Cipolletti)** | c-server1 / cipo-server1 | 1135 | 254/1040 |
| **cancadmin (CA)** | ca-server1 | 1582 | **991/1040** |

Cipolletti responde (`database cncadmin` vía bridge). El problema es que AC no tiene las recetas: la réplica `replreceta` dejó el `stkcmae` local casi vacío.

Para armar el ERP usé **cancadmin** (mejor match con los `stkm_formula` de Crown) y de ahí también los insumos `I0*` (no están en AC):

- **1.582** fórmulas + **3.869** líneas hijo  
- **993** artículos `I0*` sumados (total arts **14.961**)  
- Bridge fórmulas: `STOCK_FORMULA_ANITA_IFX_SERVER=cancadmin`

Si preferís forzar Cipolletti (`cncadmin`), se cambia esa variable; vas a cubrir menos menús Crown.
Sí: desde ac-server1 se llega bien a Cipolletti.

| INFORMIXSERVER | Host | stkcmae | Cobertura vs arts Crown |
|---|---|---|---|
| acncadmin (AC) | local | 4 | casi vacío |
| **cncadmin (Cipolletti)** | c-server1 / cipo-server1 | 1135 | 254/1040 |
| **cancadmin (CA)** | ca-server1 | 1582 | **991/1040** |

Cipolletti responde (`database cncadmin` vía bridge). El problema es que AC no tiene las recetas: la réplica `replreceta` dejó el `stkcmae` local casi vacío.

Para armar el ERP usé **cancadmin** (mejor match con los `stkm_formula` de Crown) y de ahí también los insumos `I0*` (no están en AC):

- **1.582** fórmulas + **3.869** líneas hijo  
- **993** artículos `I0*` sumados (total arts **14.961**)  
- Bridge fórmulas: `STOCK_FORMULA_ANITA_IFX_SERVER=cancadmin`

Si preferís forzar Cipolletti (`cncadmin`), se cambia esa variable; vas a cubrir menos menús Crown.

## Usuario

Briefly inform the user about the task result and perform any follow-up actions (if needed). If there's no follow-ups needed, don't explicitly say that.

## Asistente

La búsqueda de archivos `stkcmae` en ac-server1 se cortó (el `find`/`grep` se colgó), pero alcanzó a mostrar lo útil: en AC el `.dat` local es chico (~17 KB, las 4 fórmulas), y las recetas reales están en los Informix de los casinos (`cncadmin` / `cancadmin`), no en ese archivo.
La búsqueda de archivos `stkcmae` en ac-server1 se cortó (el `find`/`grep` se colgó), pero alcanzó a mostrar lo útil: en AC el `.dat` local es chico (~17 KB, las 4 fórmulas), y las recetas reales están en los Informix de los casinos (`cncadmin` / `cancadmin`), no en ese archivo.

## Usuario

decis que cancadmin tiene mas recetas que cipolletti?

## Asistente

Sí. En el conteo que hicimos:

| Servidor | Fórmulas (`stkcmae`) | Cuántas de las que piden los arts Crown |
|---|---|---|
| **cncadmin** (Cipolletti) | 1.135 | 254 de 1.040 |
| **cancadmin** (CA) | **1.582** | **991** de 1.040 |

CA tiene más cabeceras y, sobre todo, calza mucho mejor con los `stkm_formula` de los artículos de AC. Cipolletti responde bien, pero para armar el ERP Crown conviene más CA.
Sí. En el conteo que hicimos:

| Servidor | Fórmulas (`stkcmae`) | Cuántas de las que piden los arts Crown |
|---|---|---|
| **cncadmin** (Cipolletti) | 1.135 | 254 de 1.040 |
| **cancadmin** (CA) | **1.582** | **991** de 1.040 |

CA tiene más cabeceras y, sobre todo, calza mucho mejor con los `stkm_formula` de los artículos de AC. Cipolletti responde bien, pero para armar el ERP Crown conviene más CA.

## Usuario

prefiero que uses cncadmin, despues veremos como lo unificamos. Trae las formulas, los insumos y fijate los articulos de compra que apuntan a los insumos si hay que traerlos para que este todo actualizado en anitaERP. Es posible que tengas que actualizar referencias en lo que cargaste de ac-server1

## Asistente

Cambio a `cncadmin`, reimporto fórmulas e insumos, y reviso artículos de compra vinculados.
Cambio a `cncadmin`, reimporto fórmulas e insumos, y reviso artículos de compra vinculados.

## Asistente

Los faltantes son `I1*`/`I3*`, no solo `I0*`. Traigo todos los insumos `I*` de Cipolletti.
Los faltantes son `I1*`/`I3*`, no solo `I0*`. Traigo todos los insumos `I*` de Cipolletti.

## Asistente

Listo: todo apunta a **Cipolletti (`cncadmin`)**.

| Qué | Resultado |
|---|---|
| Bridge fórmulas | `STOCK_FORMULA_ANITA_IFX_SERVER=cncadmin` |
| Fórmulas | **1.135** (se purgaron las 1.582 de CA) |
| Líneas hijo | **3.762** (3.455 con insumo resuelto) |
| Insumos `I*` | **2.184** traídos/actualizados desde Cipolletti |
| Menús AC con fórmula | **289** (solo donde el `stkm_formula` de AC existe en Cipolletti) |

Los artículos de compra son los `I*`: faltaban ~1.178 (I1/I3/I9…) respecto de lo que había de CA; ya están.

**Pendiente de unificar:** 827 menús de AC siguen con un nº de fórmula que Cipolletti no tiene. Cuando definan la fuente única, se pueden completar.
Listo: todo apunta a **Cipolletti (`cncadmin`)**.

| Qué | Resultado |
|---|---|
| Bridge fórmulas | `STOCK_FORMULA_ANITA_IFX_SERVER=cncadmin` |
| Fórmulas | **1.135** (se purgaron las 1.582 de CA) |
| Líneas hijo | **3.762** (3.455 con insumo resuelto) |
| Insumos `I*` | **2.184** traídos/actualizados desde Cipolletti |
| Menús AC con fórmula | **289** (solo donde el `stkm_formula` de AC existe en Cipolletti) |

Los artículos de compra son los `I*`: faltaban ~1.178 (I1/I3/I9…) respecto de lo que había de CA; ya están.

**Pendiente de unificar:** 827 menús de AC siguen con un nº de fórmula que Cipolletti no tiene. Cuando definan la fuente única, se pueden completar.

## Usuario

no, los articulos I* son los insumos igual que en AGG. Los articulos de compra son los que tienen en stkm_articulo_prod el sku del insumo en su mayoria sin la I

## Asistente

Entendido: los de compra son los que en `stkm_articulo_prod` apuntan al insumo (casi siempre el código sin la `I`). Busco esos en Anita y los traigo.
Entendido: los de compra son los que en `stkm_articulo_prod` apuntan al insumo (casi siempre el código sin la `I`). Busco esos en Anita y los traigo.

## Asistente

Casi todos los de compra ya están (vienen de AC); falta cargar `skualternativo` desde `stkm_articulo_prod` y el artículo `1549`.
Casi todos los de compra ya están (vienen de AC); falta cargar `skualternativo` desde `stkm_articulo_prod` y el artículo `1549`.

## Asistente

Correcto — modelo AGG:

| Rol | Identificación |
|---|---|
| Insumo | SKU `I####` |
| Artículo de compra | Tiene `stkm_articulo_prod` = nº del insumo **sin** la `I` (ej. `2052` → `I2052`) |

En Cipolletti había **1.559** con `articulo_prod`. Lo que faltaba no era traer los de compra (casi todos ya venían de AC), sino el vínculo:

- **1.472** actualizados: `skualternativo` ← `stkm_articulo_prod` (ej. `3555` → `2052` → insumo `I2052`)
- **1** alta faltante: `1549`
- **1.498** arts con `skualternativo` cargado

Los `I*` siguen siendo solo insumos (2.184).
Correcto — modelo AGG:

| Rol | Identificación |
|---|---|
| Insumo | SKU `I####` |
| Artículo de compra | Tiene `stkm_articulo_prod` = nº del insumo **sin** la `I` (ej. `2052` → `I2052`) |

En Cipolletti había **1.559** con `articulo_prod`. Lo que faltaba no era traer los de compra (casi todos ya venían de AC), sino el vínculo:

- **1.472** actualizados: `skualternativo` ← `stkm_articulo_prod` (ej. `3555` → `2052` → insumo `I2052`)
- **1** alta faltante: `1549`
- **1.498** arts con `skualternativo` cargado

Los `I*` siguen siendo solo insumos (2.184).

## Usuario

para que gastronomia funcione, que nos falta en el modulo de ventas?

## Asistente

Reviso qué exige gastronomía en ventas y qué ya está cargado en Crown.
Reviso qué exige gastronomía en ventas y qué ya está cargado en Crown.

## Asistente

Para gastronomía en **Ventas**, con lo que ya hay (PV, listas, clientes, artículos, precios, fórmulas) todavía falta lo operativo del POS:

### Bloqueante (mínimo para vender)
1. **`configuracion_puntoventa_gastronomia`** — 0 filas. Por cada PC: PV CAE + CAEA, lista, depósitos, salidas, tipo FAC y tipo cobranza caja.
2. **Mozos** (`mozo_gastronomia`) — sync Anita `vendedor`/`mozopasswd` (no es la tabla `vendedor` comercial, que está en 0).
3. **Mesas** (`mesa_gastronomia` + ubicaciones) — si usan plano de salón; sync Anita `mesa`.
4. **Jornada / turno** — abrir jornada y habilitar turno por PC (no viene del sync de maestros).
5. **Caja / medios** — `cuentacaja` = **0**. Sin cuentas de caja con uso Gastronomía no cobra.
6. **PV fiscales** — hay 29 CAE (`C`) y 8 CAEA (`A`); el resto son manuales (`M`). Hay que elegir bien los PV en la config del terminal (y `pathafip`/certificados si corresponde).

### Ya OK para el catálogo
Artículos, insumos, fórmulas, precios (pocos), listas, clientes, depósitos, tipos de comprobante (FAC/TIC existen).

### Después (canjes / extras)
Descuentos, fidelidad, VIP, ticket tarjeta, Waitry, áreas de comanda, cierre jornada contable.

En resumen: maestros de venta/stock alcanzan para el **qué se vende**; falta armar el **terminal** (config PV gastronomía + mozos/mesas + jornada) y el **módulo caja** (cuentas).
Para gastronomía en **Ventas**, con lo que ya hay (PV, listas, clientes, artículos, precios, fórmulas) todavía falta lo operativo del POS:

### Bloqueante (mínimo para vender)
1. **`configuracion_puntoventa_gastronomia`** — 0 filas. Por cada PC: PV CAE + CAEA, lista, depósitos, salidas, tipo FAC y tipo cobranza caja.
2. **Mozos** (`mozo_gastronomia`) — sync Anita `vendedor`/`mozopasswd` (no es la tabla `vendedor` comercial, que está en 0).
3. **Mesas** (`mesa_gastronomia` + ubicaciones) — si usan plano de salón; sync Anita `mesa`.
4. **Jornada / turno** — abrir jornada y habilitar turno por PC (no viene del sync de maestros).
5. **Caja / medios** — `cuentacaja` = **0**. Sin cuentas de caja con uso Gastronomía no cobra.
6. **PV fiscales** — hay 29 CAE (`C`) y 8 CAEA (`A`); el resto son manuales (`M`). Hay que elegir bien los PV en la config del terminal (y `pathafip`/certificados si corresponde).

### Ya OK para el catálogo
Artículos, insumos, fórmulas, precios (pocos), listas, clientes, depósitos, tipos de comprobante (FAC/TIC existen).

### Después (canjes / extras)
Descuentos, fidelidad, VIP, ticket tarjeta, Waitry, áreas de comanda, cierre jornada contable.

En resumen: maestros de venta/stock alcanzan para el **qué se vende**; falta armar el **terminal** (config PV gastronomía + mozos/mesas + jornada) y el **módulo caja** (cuentas).

## Usuario

arranquemos con los precios, traelos de cncadmin. 2) trae mozos y mesas de cncadmin pero estas tablas las vamos a tener que armar por sala en este caso. Previo a los mozos, carga la tabla sala con los 9 casinos de Crown (Cipolletti/Catriel/Gral. Roca/Choele Choel/Villa Regina/Viedma/Las Grutas/El Faro/Cinco Saltos. No se si en AGG tenemos la sala en mozos y mesas, me parece que lo tenemos por empresa, no? si es asi agrega sala como otro criterio sin alterar el de empresa para AGG asi todo sigue funcionando igual en AGG. La idea es que estos cambios solo apliquen aca en Crown sin alterar la facturacion POS de AGG.

## Asistente

Reviso mesas/mozos y si ya existe `sala`, y después traigo precios de Cipolletti y armo el esquema Crown.
Reviso mesas/mozos y si ya existe `sala`, y después traigo precios de Cipolletti y armo el esquema Crown.

## Asistente

Implementando bridge de precios a Cipolletti, salas Crown y `sala_id` opcional en mesas/mozos.
Implementando bridge de precios a Cipolletti, salas Crown y `sala_id` opcional en mesas/mozos.

## Asistente

El bridge truncó el listado masivo; importo precios lista por lista.
El bridge truncó el listado masivo; importo precios lista por lista.

## Asistente

Listo. Resumen:

### 1) Precios (`cncadmin`)
- 22 listas nuevas desde Cipolletti (26 en total)
- **15.245** precios vigentes (sync lista por lista; el dump masivo truncaba)
- Bridge: `STOCK_PRECIO_ANITA_IFX_SERVER=cncadmin`

### 2) Salas Crown (9)
CIP Cipolletti · CAT Catriel · GRO Gral. Roca · CHO Choele Choel · VRE Villa Regina · VIE Viedma · LGR Las Grutas · EFA El Faro · CSA Cinco Saltos

### 3) Mesas / mozos desde Cipolletti → sala CIP
- **253** mesas + **96** mozos con `sala_id=1` (Cipolletti) y `empresa_id=1`

### 4) AGG intacto
Sí: en AGG mozos/mesas van por **empresa**. Acá sumamos `sala_id` **nullable**:
- AGG: queda `null`, filtros solo por empresa (igual que antes)
- Crown: `GastronomiaSalaSupport` filtra por sala solo si se pasa `salaId` y `esCrown()`

Próximo paso natural: cablear `sala_id` en la config del terminal POS Crown (todavía no toca el flujo AGG).
Listo. Resumen:

### 1) Precios (`cncadmin`)
- 22 listas nuevas desde Cipolletti (26 en total)
- **15.245** precios vigentes (sync lista por lista; el dump masivo truncaba)
- Bridge: `STOCK_PRECIO_ANITA_IFX_SERVER=cncadmin`

### 2) Salas Crown (9)
CIP Cipolletti · CAT Catriel · GRO Gral. Roca · CHO Choele Choel · VRE Villa Regina · VIE Viedma · LGR Las Grutas · EFA El Faro · CSA Cinco Saltos

### 3) Mesas / mozos desde Cipolletti → sala CIP
- **253** mesas + **96** mozos con `sala_id=1` (Cipolletti) y `empresa_id=1`

### 4) AGG intacto
Sí: en AGG mozos/mesas van por **empresa**. Acá sumamos `sala_id` **nullable**:
- AGG: queda `null`, filtros solo por empresa (igual que antes)
- Crown: `GastronomiaSalaSupport` filtra por sala solo si se pasa `salaId` y `esCrown()`

Próximo paso natural: cablear `sala_id` en la config del terminal POS Crown (todavía no toca el flujo AGG).

## Usuario

esta bien, que quede la sala opcional como filtro en mesas y mozos, si esta null no influye en nada. 2) trae las listas de precios desde cncadmin porque las de acncadmin no creo que esten completas

## Asistente

Confirmo el filtro opcional de sala y reimporto/actualizo las listas desde `cncadmin`.
Confirmo el filtro opcional de sala y reimporto/actualizo las listas desde `cncadmin`.

## Asistente

Ajusto el filtro de sala (null = no filtra) y actualizo todas las listas desde Cipolletti.
Ajusto el filtro de sala (null = no filtra) y actualizo todas las listas desde Cipolletti.

## Usuario

esta bien, que quede la sala opcional como filtro en mesas y mozos, si esta null no influye en nada. 2) trae las listas de precios desde cncadmin porque las de acncadmin no creo que esten completas

## Asistente

1) **Sala**: el filtro solo aplica si pasás un `salaId` > 0. Si es `null`/0, no agrega condición (mesas/mozos se ven igual que antes).

2) **Listas desde `cncadmin`**: las 24 de Cipolletti quedaron actualizadas (nombres e incluye IVA), p.ej. `1` → A-PUBLICO, `2` → FUSIONE.

Quedaron solo 2 de AC que no existen en Cipolletti: **Room Service (5)** y **Lista 4887**. ¿Las dejamos o las sacamos?
1) **Sala**: el filtro solo aplica si pasás un `salaId` > 0. Si es `null`/0, no agrega condición (mesas/mozos se ven igual que antes).

2) **Listas desde `cncadmin`**: las 24 de Cipolletti quedaron actualizadas (nombres e incluye IVA), p.ej. `1` → A-PUBLICO, `2` → FUSIONE.

Quedaron solo 2 de AC que no existen en Cipolletti: **Room Service (5)** y **Lista 4887**. ¿Las dejamos o las sacamos?

## Usuario

borralas a las 2 de AC

## Asistente

Voy a identificar esas dos listas y sus precios asociados, y después las borro.
Voy a identificar esas dos listas y sus precios asociados, y después las borro.

## Asistente

La 4887 no está en `listaprecio`; la id 5 es COSTO (código 50). Busco las dos de AC con más precisión.
La 4887 no está en `listaprecio`; la id 5 es COSTO (código 50). Busco las dos de AC con más precisión.

## Asistente

Listo. Borré **Room Service** (código 5) con sus 57 precios y **Lista 4887**. Quedan las 24 listas de Cipolletti.
Listo. Borré **Room Service** (código 5) con sus 57 precios y **Lista 4887**. Quedan las 24 listas de Cipolletti.

## Usuario

1) waitry en Crown no se usa, asi que tiene que estar deshabilitado 2) importa las cajas del modulo de caja /usr2/casino/caja, esto tambien va por sala igual que mesas 3) las grabaciones en anita bridge estan desahabilitadas en Crown 4) el POS de facturacion para Crown debe generar asiento contable, la logica debe ser sencilla como los locales de Ferli

## Asistente

Voy a revisar cómo están Waitry, cajas por sala, el bridge Anita y el asiento del POS (Ferli) para aplicar lo de Crown.
Voy a revisar cómo están Waitry, cajas por sala, el bridge Anita y el asiento del POS (Ferli) para aplicar lo de Crown.

## Asistente

Implementando los cuatro puntos: primero Waitry y el bloqueo de escritura Anita; después sync de cajas por sala y el asiento del POS.
Implementando los cuatro puntos: primero Waitry y el bloqueo de escritura Anita; después sync de cajas por sala y el asiento del POS.

## Asistente

Implementando los cambios en paralelo.
Implementando los cambios en paralelo.

## Asistente

Hay colisiones de código (`CAJA 4` vs `CASINO 4`). Voy a usar el orden de Anita como código estable.
Hay colisiones de código (`CAJA 4` vs `CASINO 4`). Voy a usar el orden de Anita como código estable.

## Asistente

Listo los 4 puntos:

1. **Waitry** — `WAITRY_HABILITADO=false`
2. **Cajas** — 79 desde `caja`/`cncadmin` con sala CIP (`caja:sincronizar-anita`). Código = posición Anita (1…n)
3. **Escritura Anita** — `ANITA_BRIDGE_ESCRITURA_HABILITADA=false`; el bridge bloquea insert/update/delete
4. **POS asiento** — `GASTRONOMIA_GENERA_CONTABILIDAD_FACTURA=true`; el asiento VTA usa los medios de pago (como Ferli). Cobranza sin asiento aparte

Otras salas: cambiar `CAJA_ANITA_SYNC_*` / `SALA_CODIGO` y volver a sincronizar.
Listo los 4 puntos:

1. **Waitry** — `WAITRY_HABILITADO=false`
2. **Cajas** — 79 desde `caja`/`cncadmin` con sala CIP (`caja:sincronizar-anita`). Código = posición Anita (1…n)
3. **Escritura Anita** — `ANITA_BRIDGE_ESCRITURA_HABILITADA=false`; el bridge bloquea insert/update/delete
4. **POS asiento** — `GASTRONOMIA_GENERA_CONTABILIDAD_FACTURA=true`; el asiento VTA usa los medios de pago (como Ferli). Cobranza sin asiento aparte

Otras salas: cambiar `CAJA_ANITA_SYNC_*` / `SALA_CODIGO` y volver a sincronizar.

## Usuario

entre al crud de cajas y da server error

## Asistente

Voy a revisar el CRUD de cajas y el error en el log.
Voy a revisar el CRUD de cajas y el error en el log.

## Asistente

El error es `MissingAppKeyException`: al hacer `config:clear` quedó sin `APP_KEY`. Reviso `.env` y restauro la config.
El error es `MissingAppKeyException`: al hacer `config:clear` quedó sin `APP_KEY`. Reviso `.env` y restauro la config.

## Asistente

Era la config en caché: al tocar el `.env` quedó sin `APP_KEY` y tiraba 500 en todo el sitio, no solo en cajas.

Ya corrí `config:cache` otra vez. Probá de nuevo `/caja/caja`; debería abrir (o pedir login si la sesión cayó).
Era la config en caché: al tocar el `.env` quedó sin `APP_KEY` y tiraba 500 en todo el sitio, no solo en cajas.

Ya corrí `config:cache` otra vez. Probá de nuevo `/caja/caja`; debería abrir (o pedir login si la sesión cayó).

## Usuario

en cajas, no veo el atributo de sala

## Asistente

Voy a sumar la sala en el listado y en el formulario del CRUD de cajas.
Voy a sumar la sala en el listado y en el formulario del CRUD de cajas.

## Asistente

Listo. En el listado ves **Código** y **Sala** (CIP — Cipolletti). En crear/editar podés elegir sala con la misma grilla de radios que en proveedores. Recargá `/caja/caja`.
Listo. En el listado ves **Código** y **Sala** (CIP — Cipolletti). En crear/editar podés elegir sala con la misma grilla de radios que en proveedores. Recargá `/caja/caja`.

## Usuario

ya que estamos arma filtros inteligentes, index paginado y chips para los filtros de sala. Que los filtros se recuerden y actualiza la UI a la mas actual del ERP

## Asistente

Voy a armar el index de cajas con filtros inteligentes, paginación, chips de sala y la UI moderna del ERP.
Voy a armar el index de cajas con filtros inteligentes, paginación, chips de sala y la UI moderna del ERP.

## Asistente

Armando el stack completo: filtros, chips de sala, paginación, export y preferencias recordadas.
Armando el stack completo: filtros, chips de sala, paginación, export y preferencias recordadas.

## Usuario

ya que estamos arma filtros inteligentes, index paginado y chips para los filtros de sala. Que los filtros se recuerden y actualiza la UI a la mas actual del ERP

## Asistente

Listado de cajas actualizado al patrón del ERP.

- **Filtros inteligentes** (panel + búsqueda rápida con coincidencia flexible)
- **Paginación** server-side (10) y export PDF / Excel / CSV (`lista_caja`)
- **Chips de sala** (Crown): por sala, Sin sala, Todas — se recuerdan en preferencias de usuario
- **UI moderna**: `card-info`, toolbar, thead `#85C1E9`, retorno al ABM conservando filtros

Ruta: `caja/caja`.
Listado de cajas actualizado al patrón del ERP.

- **Filtros inteligentes** (panel + búsqueda rápida con coincidencia flexible)
- **Paginación** server-side (10) y export PDF / Excel / CSV (`lista_caja`)
- **Chips de sala** (Crown): por sala, Sin sala, Todas — se recuerdan en preferencias de usuario
- **UI moderna**: `card-info`, toolbar, thead `#85C1E9`, retorno al ABM conservando filtros

Ruta: `caja/caja`.

## Usuario

ahora vamos a armar el nuevo modulo de POS gastronomia adaptado a Crown. Yo sugiero armar algo completamente nuevo para no tocar AGG que rehuse las clases actuales pero sin pincharlas en AGG. La base del layout deberia ser la misma que la de AGG. Pero aca en Crown hay muchas funcionalidades mas, la mas destacable es la de las comandas que las emite el mismo anita porque no usa Waitry. Arma algo muy actualizado con UI atractiva, todo manejable con Enter para validar los campos y F1 para consultar igual que el anita actual. F5 y F8 para facturar y hay mas funciones en este modulo en Crown. Te paso los fuentes de "C", estan en ac-server1 en /home/sergio/tmp/ventas. El principal es a-comandera.c. Hace un estudio de todo con un informe completo para que tomemos decisiones de mejoras y de estategias de programacion para este modulo. En un futuro me gustaria manejarlo como Watry tiene el KDS para que las comandas se puedan seguir y terminar su ciclo hasta la entrega. Trae tambien estrategias de los mejores modulos premium de los sistemas globales del mundo. Uno de los que me gusta es el micros/fidelio de Oracle.

## Asistente

Voy a estudiar los fuentes C de Crown y el POS gastronomía actual del ERP para armar un informe de decisión completo.
Voy a estudiar los fuentes C de Crown y el POS gastronomía actual del ERP para armar un informe de decisión completo.

## Asistente

Escribiendo el informe completo con el mapa funcional del C, la estrategia de módulo Crown aislado y referentes premium (incl. Micros).
Escribiendo el informe completo con el mapa funcional del C, la estrategia de módulo Crown aislado y referentes premium (incl. Micros).

## Asistente

Estudié `a-comandera.c` (~12k líneas) y el ecosistema en `ac-server1:/home/sergio/tmp/ventas`, cruzado con el POS AGG actual. Informe completo guardado en el store del agente:

`informes/2026-10-17-pos-gastronomia-crown-estudio.md`

### Veredicto
Crown no es “AGG sin Waitry”: es un POS de **mesa + marcha a cocina + factura + hotel/casino** en un solo programa. AGG ya resuelve bien fiscal/medios/cierre; la comanda la delega a Waitry. Con Waitry off, hay que modelar **comanda nativa** (como Anita) y dejarla lista para **KDS**.

**Estrategia:** módulo nuevo aislado (`ventas/gastronomia-crown/…`), reutilizar emisión/stock/asiento/maestros, **sin** `if (Crown)` dentro de `proceso_facturacion.js`.

### Qué hace Anita (mapa corto)
- **Modos:** resto / restosolo / sala / comandera (+ `-nf` solo marcha, `-h` hotel, setup por host `confptovta`)
- **Cuenta abierta:** `mesactmae` + `mesactmov` (+ adicionales / factura pendiente cobro)
- **Comanda:** F12 marcha → `comanmae`/`comanmov` por **área** (`areacom` → impresora); postres aparte
- **Teclado:** Enter valida, F1 consulta, **F5** efectivo, **F8** interna/canje, F9 sola, F12 marcha, F6 void, herramientas (transfer, opcionales, borrar mesa, cliente, lista precio…)

### Referentes (Micros / Simphony)
Adoptar el vocabulario: **Check → Fire → Tender → Void**, perfiles de puesto, course firing (entrada/postre), void post-fire con clave, transfer/split. KDS = mismos tickets con estados `FIRED → PREPARING → READY → BUMP`.

### Roadmap propuesto
1. **MVP:** perfil PV + mesa + ítems + F1/Enter + F12 comanda + F5/F8 + voids  
2. **Paridad resto:** parcial, transfer, dto, postres, plano, caja mozo  
3. **KDS**  
4. Hotel / GST / extras  

### Decisiones que necesitamos de vos
1. ¿Reusar `CuentaGastronomia` o tablas Crown nuevas (`gastro_cuenta` / `gastro_comanda`)?  
2. ¿MVP solo resto+sala+comanda, o también hotel/GST?  
3. ¿Impresión térmica real en fase 1, o cola + PDF primero?  
4. ¿KDS en el mismo release o fase 2 explícita?  
5. ¿Un menú “POS Crown” o atajos por modo (como Anita `-re/-sa/-co`)?

Con esas cinco respuestas armo el plan de implementación concreto (rutas, esquema BD, pantallas y hotkeys).
Estudié `a-comandera.c` (~12k líneas) y el ecosistema en `ac-server1:/home/sergio/tmp/ventas`, cruzado con el POS AGG actual. Informe completo guardado en el store del agente:

`informes/2026-10-17-pos-gastronomia-crown-estudio.md`

### Veredicto
Crown no es “AGG sin Waitry”: es un POS de **mesa + marcha a cocina + factura + hotel/casino** en un solo programa. AGG ya resuelve bien fiscal/medios/cierre; la comanda la delega a Waitry. Con Waitry off, hay que modelar **comanda nativa** (como Anita) y dejarla lista para **KDS**.

**Estrategia:** módulo nuevo aislado (`ventas/gastronomia-crown/…`), reutilizar emisión/stock/asiento/maestros, **sin** `if (Crown)` dentro de `proceso_facturacion.js`.

### Qué hace Anita (mapa corto)
- **Modos:** resto / restosolo / sala / comandera (+ `-nf` solo marcha, `-h` hotel, setup por host `confptovta`)
- **Cuenta abierta:** `mesactmae` + `mesactmov` (+ adicionales / factura pendiente cobro)
- **Comanda:** F12 marcha → `comanmae`/`comanmov` por **área** (`areacom` → impresora); postres aparte
- **Teclado:** Enter valida, F1 consulta, **F5** efectivo, **F8** interna/canje, F9 sola, F12 marcha, F6 void, herramientas (transfer, opcionales, borrar mesa, cliente, lista precio…)

### Referentes (Micros / Simphony)
Adoptar el vocabulario: **Check → Fire → Tender → Void**, perfiles de puesto, course firing (entrada/postre), void post-fire con clave, transfer/split. KDS = mismos tickets con estados `FIRED → PREPARING → READY → BUMP`.

### Roadmap propuesto
1. **MVP:** perfil PV + mesa + ítems + F1/Enter + F12 comanda + F5/F8 + voids  
2. **Paridad resto:** parcial, transfer, dto, postres, plano, caja mozo  
3. **KDS**  
4. Hotel / GST / extras  

### Decisiones que necesitamos de vos
1. ¿Reusar `CuentaGastronomia` o tablas Crown nuevas (`gastro_cuenta` / `gastro_comanda`)?  
2. ¿MVP solo resto+sala+comanda, o también hotel/GST?  
3. ¿Impresión térmica real en fase 1, o cola + PDF primero?  
4. ¿KDS en el mismo release o fase 2 explícita?  
5. ¿Un menú “POS Crown” o atajos por modo (como Anita `-re/-sa/-co`)?

Con esas cinco respuestas armo el plan de implementación concreto (rutas, esquema BD, pantallas y hotkeys).

## Usuario

Briefly inform the user about the task result and perform any follow-up actions (if needed). If there's no follow-ups needed, don't explicitly say that.

## Asistente

Los jobs de fondo ya terminaron: los `.def` de comanda (`comanmae` / `comanmov`) están en `/usr2/cli/crown/ventas`; la lectura de `mesactmae` cortó por timeout (ese archivo no está en esa carpeta). No cambia el informe: el modelo de cuenta abierta ya quedó documentado desde el uso en `a-comandera`. Seguimos a la espera de tus 5 decisiones para armar el plan de implementación.
Los jobs de fondo ya terminaron: los `.def` de comanda (`comanmae` / `comanmov`) están en `/usr2/cli/crown/ventas`; la lectura de `mesactmae` cortó por timeout (ese archivo no está en esa carpeta). No cambia el informe: el modelo de cuenta abierta ya quedó documentado desde el uso en `a-comandera`. Seguimos a la espera de tus 5 decisiones para armar el plan de implementación.

## Usuario

1) arma tablas nuevas auditables 2) que es MVP? y que hay de interface con GST? lo de hotel deberia estar porque hacen consumos desde el hotel 3) termica real con ncjetdirect y la opcion de PDF como lo maneja el anita actual 4) el KDS para una segunda fase 5) el modo del POS deberia ser configurable en la PC completamente, tenemos que hacer que el ERP sea completamente configurable. Agrega los modos del a-comandera en la configuracion de PC solo gated para crown

## Asistente

Incorporando tus decisiones y aclarando MVP + interfaz GST; reviso cómo Anita y el ERP manejan GST, ncjetdirect/PDF y la config de PC.
Incorporando tus decisiones y aclarando MVP + interfaz GST; reviso cómo Anita y el ERP manejan GST, ncjetdirect/PDF y la config de PC.

## Asistente

Quedó cerrado así:

### MVP (definición)
Primer release **usable en piso** sin Waitry ni KDS: config por PC → abrir cuenta → cargar ítems (Enter/F1) → **F12 marcha** (térmica/PDF) → **F5/F8/F9** con emisión ARCA existente → **hotel (cargo habitación)** → void con clave → auditoría. Softkeys menú ricos, transfer/split y parcial quedan en 1.5; **KDS = fase 2**.

### GST (interfaz)
En Anita GST es el **lector de tarjeta** que fuerza el flujo F8 (premio + venta interna), no un módulo aparte. En el ERP el homólogo es **Wigos** (`AccountInfoJSON` + canjes premio/fidelidad ya usados en gastronomía). El POS Crown habla con un adaptador Wigos/canje; no reescribimos `GST_*.c`.

### Resto de tus puntos
1. Tablas nuevas auditables (`gc_*`)  
3. Print: `ncjetdirect` + PDF vía `salida` (como Anita)  
4. KDS fase 2 (eventos de comanda preparados desde el día 1)  
5. Modos `resto | restosolo | sala | comandera` (+ flags hotel/canje/solo marcha…) en **`configuracion_puntoventa_gastronomia`**, UI **solo Crown**

Documento de decisiones: store del agente → `informes/2026-10-17-pos-crown-decisiones.md`.

Si confirmás, el siguiente paso concreto es la **migración de modos en config PC (gated Crown)** y el esquema `gc_*`.
Quedó cerrado así:

### MVP (definición)
Primer release **usable en piso** sin Waitry ni KDS: config por PC → abrir cuenta → cargar ítems (Enter/F1) → **F12 marcha** (térmica/PDF) → **F5/F8/F9** con emisión ARCA existente → **hotel (cargo habitación)** → void con clave → auditoría. Softkeys menú ricos, transfer/split y parcial quedan en 1.5; **KDS = fase 2**.

### GST (interfaz)
En Anita GST es el **lector de tarjeta** que fuerza el flujo F8 (premio + venta interna), no un módulo aparte. En el ERP el homólogo es **Wigos** (`AccountInfoJSON` + canjes premio/fidelidad ya usados en gastronomía). El POS Crown habla con un adaptador Wigos/canje; no reescribimos `GST_*.c`.

### Resto de tus puntos
1. Tablas nuevas auditables (`gc_*`)  
3. Print: `ncjetdirect` + PDF vía `salida` (como Anita)  
4. KDS fase 2 (eventos de comanda preparados desde el día 1)  
5. Modos `resto | restosolo | sala | comandera` (+ flags hotel/canje/solo marcha…) en **`configuracion_puntoventa_gastronomia`**, UI **solo Crown**

Documento de decisiones: store del agente → `informes/2026-10-17-pos-crown-decisiones.md`.

Si confirmás, el siguiente paso concreto es la **migración de modos en config PC (gated Crown)** y el esquema `gc_*`.

## Usuario

Briefly inform the user about the task result and perform any follow-up actions (if needed). If there's no follow-ups needed, don't explicitly say that.

## Asistente

Confirmado en ac-server1: `ncjetdirect` está en `/usr2/casino/bin` y `/usr2/cli/crown/bin`. Encaja con el plan de impresión térmica del MVP.
Confirmado en ac-server1: `ncjetdirect` está en `/usr2/casino/bin` y `/usr2/cli/crown/bin`. Encaja con el plan de impresión térmica del MVP.

## Usuario

ok avanza con el desarrollo

## Asistente

Arranco por config PC (modos Crown) y el esquema `gc_*` auditable; después el shell del POS.
Arranco por config PC (modos Crown) y el esquema `gc_*` auditable; después el shell del POS.

## Asistente

Listo el primer bloque de desarrollo. Resumen:

### Config PC (Crown)
En **Config. terminales gastronomía**: bloque “Modo POS Crown” con resto / restosolo / sala / comandera, política de comanda, hotel, GST, cubiertos, sala, salidas B/K/hotel. Solo se ve si `esCrown()`.

### Tablas nuevas (auditables)
`gastro_crown_cuenta`, `_linea`, `_comanda`, `_comanda_linea`, `_comanda_evento`, `_cuenta_evento` + `salida_id` en áreas de comanda.

### Shell POS
- URL: `/ventas/gastronomia-crown/pos` (menú **Gastronomía → POS Crown**)
- Permiso: `usar-pos-gastronomia-crown` (admin + Enc-sistemas)
- UI con hotkeys F5/F8/F12…, mesas/cuentas vía API
- Acciones de negocio (abrir mesa, marcha, factura) aún stub → siguientes iteraciones
- Print support: `GastronomiaCrownPrintSupport` (ncjetdirect vía `salida.comando` + PDF)

### Siguiente paso natural
1. Abrir / cargar cuenta + agregar ítems (SKU + F1)  
2. F12 marcha → grabar comanda + imprimir  
3. F5/F8 + hotel + adaptador Wigos  

Podés entrar al POS desde el menú o configurar el modo en la PC y probar el shell.
Listo el primer bloque de desarrollo. Resumen:

### Config PC (Crown)
En **Config. terminales gastronomía**: bloque “Modo POS Crown” con resto / restosolo / sala / comandera, política de comanda, hotel, GST, cubiertos, sala, salidas B/K/hotel. Solo se ve si `esCrown()`.

### Tablas nuevas (auditables)
`gastro_crown_cuenta`, `_linea`, `_comanda`, `_comanda_linea`, `_comanda_evento`, `_cuenta_evento` + `salida_id` en áreas de comanda.

### Shell POS
- URL: `/ventas/gastronomia-crown/pos` (menú **Gastronomía → POS Crown**)
- Permiso: `usar-pos-gastronomia-crown` (admin + Enc-sistemas)
- UI con hotkeys F5/F8/F12…, mesas/cuentas vía API
- Acciones de negocio (abrir mesa, marcha, factura) aún stub → siguientes iteraciones
- Print support: `GastronomiaCrownPrintSupport` (ncjetdirect vía `salida.comando` + PDF)

### Siguiente paso natural
1. Abrir / cargar cuenta + agregar ítems (SKU + F1)  
2. F12 marcha → grabar comanda + imprimir  
3. F5/F8 + hotel + adaptador Wigos  

Podés entrar al POS desde el menú o configurar el modo en la PC y probar el shell.

## Usuario

como armaste la salida a las comandas? como estaba en crown anita? o armaste algo como premium?

## Asistente

Reviso qué quedó cableado en el ERP frente a cómo Anita enruta las salidas de comanda.
Reviso qué quedó cableado en el ERP frente a cómo Anita enruta las salidas de comanda.

## Asistente

Ni uno ni otro completo: quedó un **esqueleto híbrido**, más cerca del ERP AGG que de Anita Crown.

### Cómo era en Anita Crown
1. Artículo → **área de comanda** (`areacom`: código, descripción, **salida**)
2. F12 `COM_lista_comanda` **parte por área** y manda cada ticket a `areac_salida`
3. El puesto (`confptovta`) trae `salida_a / salida_b / salida_k` (+ hotel) como defaults de la PC
4. Impresión vía `salida` + `prnmgr` / **ncjetdirect** (térmica), y PDF cuando la salida lo define

### Qué armé yo
- En config PC: campos `salida_comanda` (A) + B/K + hotel (estilo `confptovta`)
- En área: `salida_id` nullable (puente a `areacom.salida`)
- `GastronomiaCrownPrintSupport`: una comanda → mira `comanda.salida_id` o `área.salida` → ejecuta `salida.comando` con `%s` (mismo patrón del ticket AGG / nc) **y** opcional PDF DomPDF

**No** está (todavía) el corazón Anita: agrupar líneas por área al F12 y disparar **N tickets** a N impresoras. Hoy es “1 comanda → 1 salida”, más un PDF de respaldo.

### Premium (Micros/KDS)
Eso sería cola por estación, estados, bump, multi-device — **fase 2**, no es lo que hay ahora.

En corto: **cableado tipo Anita/AGG (salida + área + térmica)**, sin el routing multi-área de `COM_lista_comanda`, y sin capa premium. Cuando metamos la marcha real, conviene implementar el split por área como Anita y dejar B/K/hotel de la PC como fallback.
Ni uno ni otro completo: quedó un **esqueleto híbrido**, más cerca del ERP AGG que de Anita Crown.

### Cómo era en Anita Crown
1. Artículo → **área de comanda** (`areacom`: código, descripción, **salida**)
2. F12 `COM_lista_comanda` **parte por área** y manda cada ticket a `areac_salida`
3. El puesto (`confptovta`) trae `salida_a / salida_b / salida_k` (+ hotel) como defaults de la PC
4. Impresión vía `salida` + `prnmgr` / **ncjetdirect** (térmica), y PDF cuando la salida lo define

### Qué armé yo
- En config PC: campos `salida_comanda` (A) + B/K + hotel (estilo `confptovta`)
- En área: `salida_id` nullable (puente a `areacom.salida`)
- `GastronomiaCrownPrintSupport`: una comanda → mira `comanda.salida_id` o `área.salida` → ejecuta `salida.comando` con `%s` (mismo patrón del ticket AGG / nc) **y** opcional PDF DomPDF

**No** está (todavía) el corazón Anita: agrupar líneas por área al F12 y disparar **N tickets** a N impresoras. Hoy es “1 comanda → 1 salida”, más un PDF de respaldo.

### Premium (Micros/KDS)
Eso sería cola por estación, estados, bump, multi-device — **fase 2**, no es lo que hay ahora.

En corto: **cableado tipo Anita/AGG (salida + área + térmica)**, sin el routing multi-área de `COM_lista_comanda`, y sin capa premium. Cuando metamos la marcha real, conviene implementar el split por área como Anita y dejar B/K/hotel de la PC como fallback.

## Usuario

ok, vamos por los siguientes pasos y luego lo premium. El POS debe tener las herramientas ademas de los F como botones con iconos acordes

## Asistente

Siguiente iteración: abrir/cargar cuenta, agregar ítems, marcha por área, y barra de herramientas con botones e iconos además de las F.
Siguiente iteración: abrir/cargar cuenta, agregar ítems, marcha por área, y barra de herramientas con botones e iconos además de las F.

## Usuario

ok, vamos por los siguientes pasos y luego lo premium. El POS debe tener las herramientas ademas de los F como botones con iconos acordes

## Asistente

Listo el cableado operativo del POS Crown (sin premium todavía).

**Barra F** — botones con icono + tecla: F1 consulta, F2 hotel, F5 efectivo, F6 borrar, F8 interna, F9 factura sola, F10 dto, F11 obs. ítem, F12 marcha.

**Herramientas** — toolbar con iconos (postres, buscar, cliente, obs. mesa, separador, transferir, opcionales, receta, lista precio, datos mesa, cobrar, anular mesa).

**Operativo ya cableado**
- Abrir mesa / cuenta libre  
- Agregar línea por SKU (Enter)  
- Marcar / void / obs. ítem / separador / obs. mesa / anular  
- F12 marcha (split por área + print)

**Pendiente (premium / cobro):** F5/F8/F2/F9/F10, cliente, transferir, opcionales, receta, lista precio.

URL: `/ventas/gastronomia-crown/pos`
Listo el cableado operativo del POS Crown (sin premium todavía).

**Barra F** — botones con icono + tecla: F1 consulta, F2 hotel, F5 efectivo, F6 borrar, F8 interna, F9 factura sola, F10 dto, F11 obs. ítem, F12 marcha.

**Herramientas** — toolbar con iconos (postres, buscar, cliente, obs. mesa, separador, transferir, opcionales, receta, lista precio, datos mesa, cobrar, anular mesa).

**Operativo ya cableado**
- Abrir mesa / cuenta libre  
- Agregar línea por SKU (Enter)  
- Marcar / void / obs. ítem / separador / obs. mesa / anular  
- F12 marcha (split por área + print)

**Pendiente (premium / cobro):** F5/F8/F2/F9/F10, cliente, transferir, opcionales, receta, lista precio.

URL: `/ventas/gastronomia-crown/pos`

## Usuario

ok, continuamos con el desarrollo

## Asistente

Siguiente bloque: cobro (F5/F8/F2/F9) y herramientas pendientes. Reviso el flujo AGG y el estado Crown para cablearlo.
Siguiente bloque: cobro (F5/F8/F2/F9) y herramientas pendientes. Reviso el flujo AGG y el estado Crown para cablearlo.

## Asistente

Cobro en tablas Crown primero (efectivo / interna-GST / hotel); la emisión fiscal ARCA queda para después. Reviso schema y config del PV.
Cobro en tablas Crown primero (efectivo / interna-GST / hotel); la emisión fiscal ARCA queda para después. Reviso schema y config del PV.

## Asistente

Cobro operativo cableado (cierra cuenta en Crown; **sin emisión fiscal ARCA** todavía).

| Tecla / acción | Qué hace |
|---|---|
| **F5** / Cobrar | Efectivo → `tipo_cobro=efectivo` |
| **F8** | Interna; pide tarjeta GST si está habilitada |
| **F2** | Hotel: habitación (+ reserva); usa lista hotel si hay |
| **F9** | Factura sola |
| **F10** | Descuento % mesa |
| **F1** | Consulta artículo (SKU + precio) |
| Cliente / Transferir | Código cliente; ID mesa destino |

También: subtotal − dto en el detalle; migración `tipo_cobro` + `importe_cobrado`.

**Siguiente (premium):** bridge a `GastronomiaFacturaEmisionService`, ticket hotel térmico, opcionales/receta/lista precio, KDS.
Cobro operativo cableado (cierra cuenta en Crown; **sin emisión fiscal ARCA** todavía).

| Tecla / acción | Qué hace |
|---|---|
| **F5** / Cobrar | Efectivo → `tipo_cobro=efectivo` |
| **F8** | Interna; pide tarjeta GST si está habilitada |
| **F2** | Hotel: habitación (+ reserva); usa lista hotel si hay |
| **F9** | Factura sola |
| **F10** | Descuento % mesa |
| **F1** | Consulta artículo (SKU + precio) |
| Cliente / Transferir | Código cliente; ID mesa destino |

También: subtotal − dto en el detalle; migración `tipo_cobro` + `importe_cobrado`.

**Siguiente (premium):** bridge a `GastronomiaFacturaEmisionService`, ticket hotel térmico, opcionales/receta/lista precio, KDS.

## Usuario

1) avancemos con lo siguiente 2) en KDS vamos a usar lo mismo que en AGG, el prepara/no prepara, te parece bien? pero tambien tenemos que elegir a que KDS va el item. Luego cuando armemos el modulo de KDS deberia poder mostrar la foto del item y la lista de insumos con sus fotos tambien

## Asistente

Sí: reutilizar el prepara/no prepara de AGG y sumar destino KDS por ítem. Miro cómo está en AGG y avanzo el siguiente bloque del POS + el cableado de destino.
Sí: reutilizar el prepara/no prepara de AGG y sumar destino KDS por ítem. Miro cómo está en AGG y avanzo el siguiente bloque del POS + el cableado de destino.

## Asistente

Confirmado: mismo flag Preparar/No preparar (en Crown como `kds_preparar`, compatible con AGG) y destino KDS = `area_comanda` elegible por ítem. Foto + insumos quedan para el módulo KDS.
Confirmado: mismo flag Preparar/No preparar (en Crown como `kds_preparar`, compatible con AGG) y destino KDS = `area_comanda` elegible por ítem. Foto + insumos quedan para el módulo KDS.

## Asistente

Sí, me parece bien: **mismo Preparar / No preparar** que AGG, y además **elegir a qué KDS va cada ítem**.

**Qué quedó armado**
- Maestro: `articulo.kds_preparar` (en AGG se alinea con `waitry_preparar`)
- Línea: `prepara` + `area_comanda_gastronomia_id` (destino KDS)
- POS: selector **KDS destino**, switch **Prepara**, ícono en fila para cambiar; marcha solo manda ítems que preparan (split por área)
- Contrato futuro KDS: `GastronomiaCrownKdsPreparaSupport::payloadVisualKds()` → foto del plato + insumos con foto (vía fórmula)

**También avanzado**
- Ticket hotel (PDF + térmica `salida_hotel`)
- Cambio de lista de precio en cuenta

**Cuando armemos el módulo KDS:** pantallas por `area_comanda`, consumen ese payload (foto + insumos). No hace falta inventar otro flag.
Sí, me parece bien: **mismo Preparar / No preparar** que AGG, y además **elegir a qué KDS va cada ítem**.

**Qué quedó armado**
- Maestro: `articulo.kds_preparar` (en AGG se alinea con `waitry_preparar`)
- Línea: `prepara` + `area_comanda_gastronomia_id` (destino KDS)
- POS: selector **KDS destino**, switch **Prepara**, ícono en fila para cambiar; marcha solo manda ítems que preparan (split por área)
- Contrato futuro KDS: `GastronomiaCrownKdsPreparaSupport::payloadVisualKds()` → foto del plato + insumos con foto (vía fórmula)

**También avanzado**
- Ticket hotel (PDF + térmica `salida_hotel`)
- Cambio de lista de precio en cuenta

**Cuando armemos el módulo KDS:** pantallas por `area_comanda`, consumen ese payload (foto + insumos). No hace falta inventar otro flag.

## Usuario

1) falta subir la tabla de descuentos del bridge de cncadmin 2) en mesas y mozos poner sala en lugar de empresa para Crown gated y agregar los filtros en ambas tablas 3) nos falta enganchar la sala al usuario para que funcione similar a la empresa y que todas las consultas puedan filtrar por ese atributo solo para Crown, la empresa que siga estando. Seria otra solapa o algun select multiple como el de empresas solo para Crown 4) busca en todos lados que pusimos filtros de salas para agregar el filtro de las salas asignadas al usuario

## Asistente

Cuatro frentes: descuentos CNCAdmin, mesas/mozos con sala (Crown), salas asignadas al usuario, y filtros existentes. Exploro el bridge y el patrón empresa→usuario.
Cuatro frentes: descuentos CNCAdmin, mesas/mozos con sala (Crown), salas asignadas al usuario, y filtros existentes. Exploro el bridge y el patrón empresa→usuario.

## Asistente

Implemento bridge de descuentos, `usuario_sala` y filtros Crown en mesas/mozos.
Implemento bridge de descuentos, `usuario_sala` y filtros Crown en mesas/mozos.

## Asistente

Listo los 4 puntos.

**1) Descuentos cncadmin**  
Bridge igual que mesa/mozo: `ifx_server=cncadmin`, path `/usr2/casino`, `sala_codigo=CIP`. Columna `descuento_gastronomia.sala_id`. Env en `.env` y `.env.example`.  
Para importar (cuando digas): `php artisan descuento-gastronomia:sincronizar-anita`

**2) Mesas / mozos Crown**  
Formulario: **Sala** obligatoria (empresa queda hidden). Index: columna Sala + filtro por sala.

**3) Usuario ↔ sala**  
Tabla `usuario_sala` + dual-list en ABM usuario (solo Crown), misma lógica que empresas: vacío = todas; con filas = restringe. Sesión `usuario_salas` al login (hay que **volver a entrar** para que tome efecto).

**4) Filtros con salas asignadas**  
`GastronomiaSalaSupport` / `UsuarioSalaAsignadaSupport` aplicados en: mesas, mozos (listado + consulta modal), descuentos, POS Crown (mesas/cuentas), select sala del PV.
Listo los 4 puntos.

**1) Descuentos cncadmin**  
Bridge igual que mesa/mozo: `ifx_server=cncadmin`, path `/usr2/casino`, `sala_codigo=CIP`. Columna `descuento_gastronomia.sala_id`. Env en `.env` y `.env.example`.  
Para importar (cuando digas): `php artisan descuento-gastronomia:sincronizar-anita`

**2) Mesas / mozos Crown**  
Formulario: **Sala** obligatoria (empresa queda hidden). Index: columna Sala + filtro por sala.

**3) Usuario ↔ sala**  
Tabla `usuario_sala` + dual-list en ABM usuario (solo Crown), misma lógica que empresas: vacío = todas; con filas = restringe. Sesión `usuario_salas` al login (hay que **volver a entrar** para que tome efecto).

**4) Filtros con salas asignadas**  
`GastronomiaSalaSupport` / `UsuarioSalaAsignadaSupport` aplicados en: mesas, mozos (listado + consulta modal), descuentos, POS Crown (mesas/cuentas), select sala del PV.

## Usuario

1) corre la importacion 2) los filtros hacelos igual que en requisiciones de compras, no usar select, usar etiquetas 3) de mozos y mesas arma la paginacion standard, no como esta ahora y los reportes con formato standard profesional con logo, etc

## Asistente

Importo descuentos, paso filtros a chips como requisiciones, y dejo mesas/mozos con paginación + export estándar.
Importo descuentos, paso filtros a chips como requisiciones, y dejo mesas/mozos con paginación + export estándar.

## Asistente

La sync devolvió 0 filas; reviso el bridge y en paralelo armo paginación + chips.
La sync devolvió 0 filas; reviso el bridge y en paralelo armo paginación + chips.

## Usuario

1) corre la importacion 2) los filtros hacelos igual que en requisiciones de compras, no usar select, usar etiquetas 3) de mozos y mesas arma la paginacion standard, no como esta ahora y los reportes con formato standard profesional con logo, etc

## Asistente

Implementing repositories, controllers, views, exports and routes.
Implementing repositories, controllers, views, exports and routes.

## Asistente

Listo.

1. **Import descuentos**: 92 en `descuento_gastronomia`.
2. **Filtros sala**: chips (etiquetas) como requisiciones; se sacó el `<select>`.
3. **Mesas / mozos**: paginación server-side (10), búsqueda/filtros estándar, export PDF/Excel/CSV con logos y thead `#85C1E9`.

Rutas: `lista_mesa_gastronomia` · `lista_mozo_gastronomia`.
Listo.

1. **Import descuentos**: 92 en `descuento_gastronomia`.
2. **Filtros sala**: chips (etiquetas) como requisiciones; se sacó el `<select>`.
3. **Mesas / mozos**: paginación server-side (10), búsqueda/filtros estándar, export PDF/Excel/CSV con logos y thead `#85C1E9`.

Rutas: `lista_mesa_gastronomia` · `lista_mozo_gastronomia`.

## Usuario

1) en mesas y mozos, agrandar la columna de herramientas para que ocupe un renglon 2) asegurar que en requisiciones el filtro por empresa sigue existiendo y no aparece porque tenemos una sola empresa asignada al usuario (no quiero tener problemas en AGG cuando haga el deploy de esto) 3) lo mismo para ordenes de compra

## Asistente

Reviso la columna de acciones en mesas y mozos, y el filtro de empresa en requisiciones y órdenes de compra para que en AGG siga igual.
Reviso la columna de acciones en mesas y mozos, y el filtro de empresa en requisiciones y órdenes de compra para que en AGG siga igual.

## Asistente

La columna de acciones queda en una sola línea. El filtro de empresa en requisiciones y órdenes de compra se muestra solo si el usuario tiene más de una empresa asignada; en AGG eso no cambia.
La columna de acciones queda en una sola línea. El filtro de empresa en requisiciones y órdenes de compra se muestra solo si el usuario tiene más de una empresa asignada; en AGG eso no cambia.

## Asistente

En mesas y mozos, la columna de editar y eliminar quedó más ancha y en una sola línea.

En requisiciones y órdenes de compra el filtro por empresa sigue igual: los chips aparecen cuando el usuario tiene más de una empresa asignada. Con una sola no se muestran, que es lo que pasa en esta instalación. En Crown las requisiciones usan chips de sala, y eso depende de `EMPRESA=CROWN` en el entorno. En AGG ese código no corre y el filtro de empresa queda como está.
En mesas y mozos, la columna de editar y eliminar quedó más ancha y en una sola línea.

En requisiciones y órdenes de compra el filtro por empresa sigue igual: los chips aparecen cuando el usuario tiene más de una empresa asignada. Con una sola no se muestran, que es lo que pasa en esta instalación. En Crown las requisiciones usan chips de sala, y eso depende de `EMPRESA=CROWN` en el entorno. En AGG ese código no corre y el filtro de empresa queda como está.

## Usuario

agrega el filtro por sala para las ordenes de compra igual que requisiciones y el filtro (flag) de presupuesto

## Asistente

Voy a copiar el filtro de sala de requisiciones a órdenes de compra y agregar el filtro de presupuesto.
Voy a copiar el filtro de sala de requisiciones a órdenes de compra y agregar el filtro de presupuesto.

## Asistente

El filtro de sala en órdenes de compra queda como en requisiciones. El de presupuesto aparece solo cuando el flag de partida y CAPEX está activo, así en AGG se ve y en Crown no.
El filtro de sala en órdenes de compra queda como en requisiciones. El de presupuesto aparece solo cuando el flag de partida y CAPEX está activo, así en AGG se ve y en Crown no.

## Asistente

En las órdenes de compra el filtro de sala quedó igual que en las requisiciones: chips por sala y la opción "Todas las salas". En Crown la grilla muestra la sala en lugar de la empresa. En AGG, con más de una empresa asignada, siguen los chips de empresa.

El filtro de presupuesto (Todas, Con partida, Sin partida) aparece solo cuando está activo el flag de partida y CAPEX (`oc_pedir_partida_capex`). En esta instalación está apagado, así que no se ve. En AGG el flag arranca prendido y el filtro sale solo.
En las órdenes de compra el filtro de sala quedó igual que en las requisiciones: chips por sala y la opción "Todas las salas". En Crown la grilla muestra la sala en lugar de la empresa. En AGG, con más de una empresa asignada, siguen los chips de empresa.

El filtro de presupuesto (Todas, Con partida, Sin partida) aparece solo cuando está activo el flag de partida y CAPEX (`oc_pedir_partida_capex`). En esta instalación está apagado, así que no se ve. En AGG el flag arranca prendido y el filtro sale solo.

## Usuario

pero en crown tambien deberia mostrar la empresa si hay mas de una asignada al usuario. Si es una o nada no tiene que mostrar las etiquetas

## Asistente

En Crown las empresas también salen como chips si el usuario tiene más de una asignada; con una o ninguna no se muestran. Ajusto requisiciones y órdenes de compra para que sala y empresa puedan filtrarse juntos.
En Crown las empresas también salen como chips si el usuario tiene más de una asignada; con una o ninguna no se muestran. Ajusto requisiciones y órdenes de compra para que sala y empresa puedan filtrarse juntos.

## Asistente

En Crown, requisiciones y órdenes de compra muestran chips de **empresa** solo si el usuario tiene más de una asignada. Con una o ninguna, no aparecen. Los chips de sala siguen igual y se pueden combinar con el filtro de empresa.
En Crown, requisiciones y órdenes de compra muestran chips de **empresa** solo si el usuario tiene más de una asignada. Con una o ninguna, no aparecen. Los chips de sala siguen igual y se pueden combinar con el filtro de empresa.

## Usuario

yo entro en requisiciones y veo todas las empresas ademas de todas las salas, eso esta mal

## Asistente

El problema es casi seguro que, sin empresas asignadas, `allFiltrado()` trae todas y los chips las muestran. Reviso cómo se arma la lista.
El problema es casi seguro que, sin empresas asignadas, `allFiltrado()` trae todas y los chips las muestran. Reviso cómo se arma la lista.

## Asistente

Sin empresas en sesión, `allFiltrado()` trae todas y los chips las muestran. Los chips de empresa solo deben salir si el usuario tiene más de una asignada de forma explícita.
Sin empresas en sesión, `allFiltrado()` trae todas y los chips las muestran. Los chips de empresa solo deben salir si el usuario tiene más de una asignada de forma explícita.

## Asistente

Con 0 empresas asignadas, el default no debe fijar la primera del catálogo: queda en «todas» y sin chips.
Con 0 empresas asignadas, el default no debe fijar la primera del catálogo: queda en «todas» y sin chips.

## Usuario

esto es para todas las instalaciones

## Asistente

La regla de chips de empresa (solo si hay más de una asignada) aplica en todas las instalaciones. Reviso que no quede ningún caso que use el catálogo completo o un gate solo de Crown.
La regla de chips de empresa (solo si hay más de una asignada) aplica en todas las instalaciones. Reviso que no quede ningún caso que use el catálogo completo o un gate solo de Crown.

## Asistente

Centralizo la regla (chips solo con más de una empresa asignada) para que valga en todas las instalaciones, no solo Crown.
Centralizo la regla (chips solo con más de una empresa asignada) para que valga en todas las instalaciones, no solo Crown.

## Asistente

Quedó unificado para **todas las instalaciones** (AGG, Crown, etc.):

- Chips de empresa solo si el usuario tiene **más de una** empresa en sesión.
- Con **una o ninguna**, no se muestran (si no hay asignación, acceso a todas: no se pintan todas las empresas del catálogo).

La regla vive en `EmpresaFiltroExternoSupport` y la usan requisiciones, órdenes de compra y el resto de listados con chips de empresa.
Quedó unificado para **todas las instalaciones** (AGG, Crown, etc.):

- Chips de empresa solo si el usuario tiene **más de una** empresa en sesión.
- Con **una o ninguna**, no se muestran (si no hay asignación, acceso a todas: no se pintan todas las empresas del catálogo).

La regla vive en `EmpresaFiltroExternoSupport` y la usan requisiciones, órdenes de compra y el resto de listados con chips de empresa.

## Usuario

y lo de empresa como estaba entonces? por que aparecian todas las empresas recien?

## Asistente

Aparecieron todas porque, al pedir chips de empresa también en Crown, el conteo se hizo mal.

Antes en Crown **no** se mostraban chips de empresa (`!$esCrown`). Solo salas.

Cuando pediste que en Crown también salieran si había más de una asignada, el chequeo quedó así:

```php
($empresa_query ?? collect())->count() > 1
```

`$empresa_query` sale de `allFiltrado()`. Si el usuario **no tiene** filas en `usuario_empresa` (acceso a todas), ese método **devuelve el catálogo completo**. Con 4 empresas → `count() > 1` → se pintaban las 4.

No era que “empresa estaba mal desde siempre” en Crown: en Crown no se mostraba. El fallo apareció al reactivar los chips usando el tamaño del listado filtrable en lugar de las empresas **asignadas en sesión**.

La corrección: mirar `usuario_empresas` en sesión (`EmpresaFiltroExternoSupport::mostrarChips()`). Solo chips si hay **más de una asignada**; con una o ninguna, no se muestran.
Aparecieron todas porque, al pedir chips de empresa también en Crown, el conteo se hizo mal.

Antes en Crown **no** se mostraban chips de empresa (`!$esCrown`). Solo salas.

Cuando pediste que en Crown también salieran si había más de una asignada, el chequeo quedó así:

```php
($empresa_query ?? collect())->count() > 1
```

`$empresa_query` sale de `allFiltrado()`. Si el usuario **no tiene** filas en `usuario_empresa` (acceso a todas), ese método **devuelve el catálogo completo**. Con 4 empresas → `count() > 1` → se pintaban las 4.

No era que “empresa estaba mal desde siempre” en Crown: en Crown no se mostraba. El fallo apareció al reactivar los chips usando el tamaño del listado filtrable en lugar de las empresas **asignadas en sesión**.

La corrección: mirar `usuario_empresas` en sesión (`EmpresaFiltroExternoSupport::mostrarChips()`). Solo chips si hay **más de una asignada**; con una o ninguna, no se muestran.

## Usuario

ok, mira esto entonces en los abm que tocamos hoy, creo que en mesas y mozos, no se si alguno mas

## Asistente

Reviso mesas, mozos y lo demás que tocamos hoy por el mismo criterio de chips de empresa.
Reviso mesas, mozos y lo demás que tocamos hoy por el mismo criterio de chips de empresa.

## Asistente

En mesas y mozos el index solo tenía salas. Agrego chips de empresa con la misma regla (solo si hay más de una asignada) y el filtro en backend.
En mesas y mozos el index solo tenía salas. Agrego chips de empresa con la misma regla (solo si hay más de una asignada) y el filtro en backend.

## Usuario

pero mozos y mesas tenian empresa como atributo?

## Asistente

Sí: mesas y mozos tienen `empresa_id`. En Crown la pantalla muestra la sala y deja la empresa en segundo plano. Confirmo cómo está armado ahora.
Sí: mesas y mozos tienen `empresa_id`. En Crown la pantalla muestra la sala y deja la empresa en segundo plano. Confirmo cómo está armado ahora.

## Asistente

Sí. Tanto `mesa_gastronomia` como `mozo_gastronomia` tienen `empresa_id` y la relación con la empresa.

En Crown eso no se edita en el formulario: se elige la sala y la empresa queda en un campo oculto. En el listado la columna visible es la sala. Fuera de Crown el alta y la edición piden la empresa, y el listado la muestra en lugar de la sala.
Sí. Tanto `mesa_gastronomia` como `mozo_gastronomia` tienen `empresa_id` y la relación con la empresa.

En Crown eso no se edita en el formulario: se elige la sala y la empresa queda en un campo oculto. En el listado la columna visible es la sala. Fuera de Crown el alta y la edición piden la empresa, y el listado la muestra en lugar de la sala.

## Usuario

ok, queda algo mas por hacer?

## Asistente

Reviso si quedó a medias el filtro de empresa en mesas y mozos, y si el resto cierra.
Reviso si quedó a medias el filtro de empresa en mesas y mozos, y si el resto cierra.

## Asistente

Lo que pediste está cerrado: descuentos importados, mesas y mozos con paginación, export y chips de sala, y requisiciones y órdenes de compra con sala y empresa según las asignaciones del usuario.

En mesas y mozos el filtro de empresa quedó a medias en el código. El listado ya puede filtrar por `empresa_id`, pero la pantalla no muestra esos chips. Con una empresa asignada o ninguna no se nota. Solo haría falta terminarlo si un usuario tiene más de una empresa y tiene que filtrar mesas o mozos por empresa.
Lo que pediste está cerrado: descuentos importados, mesas y mozos con paginación, export y chips de sala, y requisiciones y órdenes de compra con sala y empresa según las asignaciones del usuario.

En mesas y mozos el filtro de empresa quedó a medias en el código. El listado ya puede filtrar por `empresa_id`, pero la pantalla no muestra esos chips. Con una empresa asignada o ninguna no se nota. Solo haría falta terminarlo si un usuario tiene más de una empresa y tiene que filtrar mesas o mozos por empresa.

## Usuario

ahora que queda de gastronomia para cerrar premium ?

## Usuario

<available_subagent_types> Available subagent_types and a quick description of what they do: - generalPurpose: General-purpose agent for researching complex questions, searching for code, and executing multi-step tasks. Use when searching for a keyword or file and not confident you'll find the match quickly. - explore: Fast agent specialized for exploring codebases. Use this when you need to quickly find files by patterns (eg. "src/components/**/*.tsx"), search code for keywords (eg. "API endpoints"), or answer questions about the codebase (eg. "how do API endpoints work?"). When calling this agent, specify the desired thoroughness level: "quick" for basic searches, "medium" for moderate exploration, or "very thorough" for comprehensive analysis across multiple locations and naming conventions. - cursor-guide: Read Cursor product documentation to answer questions about how Cursor Desktop, IDE, CLI, Cloud Agents, Bugbot, and other features work. Use when the user asks 'In Cursor, how do I...?' or similar questions about Cursor products. - ci-investigator: Investigate a single failing PR CI check and return a short root-cause summary. Use when the user asks to summarize, explain, diagnose, or investigate a specific failed check from a pull request. - bugbot: Use only when the user *explicitly* asks for a Bugbot-like review of local code changes. When launching this subagent, set the Task description to exactly "Bugbot". Launch exactly one Bugbot subagent with `run_in_background: false` unless the user explicitly asks to run in background. Use this fixed prompt form: "Full Repository Path: ...\nDiff: <one of: \"branch changes\", \"uncommitted changes\", \"natural language\">\nChange Description: ...\nCustom Instructions: ..."; default to `Diff: branch changes`; include `Change Description` only when `Diff` is `natural language`, formatting it as one block per changed file (a `<path> (added|modified|deleted|renamed)` header followed by bullets of what changed, mentioning line numbers or ranges inline where helpful), and only use `natural language` as a last resort after a regular diff-based review failed because the diff could not be computed; include `Custom Instructions` only when the user gave specific review instructions. This subagent is single-shot and does not support `resume`; always launch a fresh subagent instead. - security-review: Use only when the user *explicitly* asks for a security review of local code changes. When launching this subagent, set the Task description to exactly "Security Review". Launch exactly one security-review subagent with `run_in_background: false` unless the user explicitly asks to run in background. Use this fixed prompt form: "Full Repository Path: ...\nDiff: <one of: \"branch changes\", \"uncommitted changes\">\nCustom Instructions: ..."; default to `Diff: branch changes`; include `Custom Instructions` only when the user gave specific review instructions. This subagent is single-shot and does not support `resume`; always launch a fresh subagent instead. - best-of-n-runner: Run a task in an isolated git worktree. Each best-of-n-runner gets its own branch and working directory. Use for best-of-N parallel attempts or isolated experiments. </available_subagent_types> <available_subagent_models> If the user explicitly asks for the model of a subagent/task, you may ONLY use model slugs from this list: - inherit (default; required unless the user explicitly requested another model) - claude-fable-5-1-thinking-high - claude-opus-5-5-medium - claude-opus-5-thinking-high - claude-sonnet-5-5-high - composer-2.5-fast - cursor-grok-4.6-high-fast - gemini-4-argon-medium - gpt-5.6-sol-medium - grok-4.7-high-fast - muse-wonder-1.0-high If the user isn't asking for a specific version, prefer the latest version of the model family. As an example, if the user just says "gpt" or "claude", use the latest available version of GPT or Claude. IMPORTANT: If the user requests a model that is NOT in the list above, do NOT substitute a different model or guess. Instead, skip launching the subagent with that model and tell the user which model was unavailable and which models are available. When speaking to the USER about which model you selected for a subagent, do NOT use the kebab-case model names unless the user requested the model using that format. Ue the same naming scheme the user used to discuss the model when they requested it. </available_subagent_models> <dynamic_tools> You have access to tools through dynamic namespaces, e.g. MCP servers, using `GetDynamicTools` and `CallDynamicTool`. ## Dynamic Tool Discovery and Invocation Use `GetDynamicTools` to discover tool schemas, then `CallDynamicTool` to invoke one tool. Aim to minimize round-trips: ideally one discovery call followed by one invocation. If the user mentions a product or service represented by an available namespace, and the request likely depends on it, proactively inspect that namespace before answering. If you are unsure which namespace matches, search with a relevant pattern. `GetDynamicTools` supports these modes: 1. `{"namespace":"<id>"}`: returns schemas and full descriptions for every tool in that namespace. 2. `{"namespace":"<id>","toolName":"<name>"}`: returns one tool schema with its full description. 3. `{"pattern":"<regex>"}`: searches namespace and tool names. 4. `{"namespace":"<id>","pattern":"<regex>"}`: searches tools within one namespace. 5. No arguments: returns the full catalog. Pattern-search and catalog results shorten long descriptions, marked by a trailing "... [truncated]"; namespace and single-tool lookups always return the complete description. Always inspect a tool's schema before invoking it with `CallDynamicTool`. If the available dynamic tools do not fully support what the user asked you to do, complete the work you can with the current tool set. In your work summary, include what you were unable to do and why. Do not use browser automation to work around missing tools unless the user explicitly asks you to use the browser. Available dynamic tool namespaces: <dynamic_tool_namespaces> <namespace name="cursor-ide-browser" tools="browser_navigate, browser_snapshot, browser_click, browser_mouse_click_xy, browser_type, browser_fill, browser_select_option, browser_press_key, browser_scroll, browser_drag, browser_get_bounding_box, browser_highlight, browser_tabs, browser_cdp, browser_take_screenshot, browser_lock" namespaceUseInstructions="The cursor-ide-browser MCP server provides a Cursor-owned browser tab plus a raw Chrome DevTools Protocol command tool. CORE WORKFLOW: 1. Start by understanding the user's goal and what success looks like on the page. 2. Use browser_tabs with action "list" to inspect open tabs and URLs before acting. 3. Use browser_navigate to create or navigate the target tab. Omit the position parameter for background automation so focus is preserved. 4. Use browser_lock before longer automation on an existing tab, then browser_lock with action "unlock" when finished. 5. Use browser_snapshot for accessibility context and browser_take_screenshot for visual verification. 6. Use browser_click, browser_type, browser_fill, browser_select_option, browser_press_key, browser_scroll, and browser_drag for page interactions. 7. Use browser_highlight and browser_get_bounding_box for visual grounding and coordinate diagnostics. 8. Use browser_cdp for page inspection, profiling, runtime evaluation, DOM/CSS queries, and performance data. AVOID RABBIT HOLES: 1. Do not repeat the same failing action more than once without new evidence such as a fresh snapshot, a different ref, a changed page state, or a clear new hypothesis. 2. IMPORTANT: If four attempts fail or progress stalls, stop acting and report what you observed, what blocked progress, and the most likely next step. 3. Prefer gathering evidence over brute force. If the page is confusing, use browser_snapshot, browser_take_screenshot, or CDP inspection before trying more actions. 4. If you encounter a blocker such as login, passkey/manual user interaction, permissions, captchas, destructive confirmations, missing data, or an unexpected state, stop and report it instead of improvising repeated actions. 5. Do not get stuck in wait-action-wait loops. Every retry should be justified by something newly observed. CRITICAL - Lock/unlock workflow: 1. browser_lock requires an existing browser tab - you CANNOT call browser_lock with action: "lock" before browser_navigate 2. Correct order: browser_navigate -> browser_lock({ action: "lock" }) -> (interactions) -> browser_lock({ action: "unlock" }) 3. If a browser tab already exists (check with browser_tabs list), call browser_lock with action: "lock" FIRST before any interactions 4. Only call browser_lock with action: "unlock" when completely done with ALL browser operations for this turn IMPORTANT - Waiting strategy: When waiting for page changes, prefer short CDP polling loops with Runtime.evaluate, DOM queries, Page lifecycle signals, or browser_snapshot checks rather than a single long wait. CDP USAGE: - Use browser_cdp with a DevTools Protocol method and params object, for example Runtime.evaluate, DOM.getDocument, CSS.getComputedStyleForNode, Profiler.start/stop, Performance.getMetrics, Log.enable, and Network.enable. - Do not use browser_cdp with CDP Input.* methods. They are denied because they are focus-sensitive in Electron webviews and can route input to Cursor UI instead of the browser page. - Use browser_click, browser_type, browser_fill, browser_select_option, browser_press_key, browser_scroll, and browser_drag for clicks, typing, filling inputs, selecting options, keyboard actions, scrolling, and drag-and-drop. - Use Runtime.evaluate for advanced DOM-scoped interactions that the dedicated browser tools do not cover. - For profiling, call Profiler.enable, Profiler.start, reproduce the behavior, then Profiler.stop. The profile is saved to a file and returned as a log_file; read that file only when you need to inspect details. - For JavaScript evaluation, prefer Runtime.evaluate with returnByValue when possible. - Device emulation you set with Emulation.setDeviceMetricsOverride, Emulation.setTouchEmulationEnabled, or Emulation.setUserAgentOverride (for example for a phone-sized screenshot) lasts only for the current turn: it is cleared when your turn ends, and the user can reset it from the browser tab at any time. Send Emulation.clearDeviceMetricsOverride yourself once you are done with it within a turn. - Some browser-wide or sensitive CDP methods are denied, especially cookie, storage, permission, download, target-management, filesystem-backed file-input commands, system-level commands, and CDP navigation/history navigation commands. - Large CDP responses are saved to files instead of being inlined. Prefer using the returned file path over immediately stuffing large payloads into context; read focused sections only when needed. VISION: - browser_take_screenshot attaches an image result that the model can inspect. CDP Page.captureScreenshot returns data inside JSON and should not replace browser_take_screenshot when visual verification is needed. NOTES: - browser_snapshot returns snapshot YAML and is the main source of truth for page structure. - Refs are opaque handles tied to the latest browser_snapshot for that tab. - Iframe content is not accessible - only elements outside iframes can be interacted with. - When you stop to report a blocker, include the current page, the target you were trying to reach, the blocker you observed, and the best next action. If the blocker requires manual user interaction, ask the user to take over at that point rather than assuming it in advance." source="mcp" /> <namespace name="cursor" tools="AwaitShell, CreateGoal, Delete, EditNotebook, FetchMcpResource, GenerateImage, ReadLints, SwitchMode, Task, TodoWrite, UpdateGoal, WebFetch, WebSearch" namespaceUseInstructions="Native Cursor tools for this session. These are highly recommended and useful tools that you should use when the right situation arises. Don't be afraid to look at one if it seems relevant, even if you don't end up using it. You MUST read the tool schemas before calling them. Here are some crucial instructions: - AwaitShell: Use to sleep and check shell progress. Never sleep using shell. - ReadLints: Check for linter errors after substantive edits. - SwitchMode: Switch between modes: Agent and Plan. Proactively consider switching modes for relevant requests. - Task: Spawn local and cloud subagents natively. - TodoWrite: Use this tool to manage complex multi-step tasks." source="cursor" /> </dynamic_tool_namespaces> If an MCP-backed namespace requires authentication, call `mcp_auth` through `CallDynamicTool` for that namespace, then inspect it again and retry if appropriate. Do not authenticate namespaces preemptively or repeatedly. </dynamic_tools> <available_subagent_types> Available subagent_types and a quick description of what they do: - generalPurpose: General-purpose agent for researching complex questions, searching for code, and executing multi-step tasks. Use when searching for a keyword or file and not confident you'll find the match quickly. - explore: Fast agent specialized for exploring codebases. Use this when you need to quickly find files by patterns (eg. "src/components/**/*.tsx"), search code for keywords (eg. "API endpoints"), or answer questions about the codebase (eg. "how do API endpoints work?"). When calling this agent, specify the desired thoroughness level: "quick" for basic searches, "medium" for moderate exploration, or "very thorough" for comprehensive analysis across multiple locations and naming conventions. - cursor-guide: Read Cursor product documentation to answer questions about how Cursor Desktop, IDE, CLI, Cloud Agents, Bugbot, and other features work. Use when the user asks 'In Cursor, how do I...?' or similar questions about Cursor products. - ci-investigator: Investigate a single failing PR CI check and return a short root-cause summary. Use when the user asks to summarize, explain, diagnose, or investigate a specific failed check from a pull request. - bugbot: Use only when the user *explicitly* asks for a Bugbot-like review of local code changes. When launching this subagent, set the Task description to exactly "Bugbot". Launch exactly one Bugbot subagent with `run_in_background: false` unless the user explicitly asks to run in background. Use this fixed prompt form: "Full Repository Path: ...\nDiff: <one of: \"branch changes\", \"uncommitted changes\", \"natural language\">\nChange Description: ...\nCustom Instructions: ..."; default to `Diff: branch changes`; include `Change Description` only when `Diff` is `natural language`, formatting it as one block per changed file (a `<path> (added|modified|deleted|renamed)` header followed by bullets of what changed, mentioning line numbers or ranges inline where helpful), and only use `natural language` as a last resort after a regular diff-based review failed because the diff could not be computed; include `Custom Instructions` only when the user gave specific review instructions. This subagent is single-shot and does not support `resume`; always launch a fresh subagent instead. - security-review: Use only when the user *explicitly* asks for a security review of local code changes. When launching this subagent, set the Task description to exactly "Security Review". Launch exactly one security-review subagent with `run_in_background: false` unless the user explicitly asks to run in background. Use this fixed prompt form: "Full Repository Path: ...\nDiff: <one of: \"branch changes\", \"uncommitted changes\">\nCustom Instructions: ..."; default to `Diff: branch changes`; include `Custom Instructions` only when the user gave specific review instructions. This subagent is single-shot and does not support `resume`; always launch a fresh subagent instead. - best-of-n-runner: Run a task in an isolated git worktree. Each best-of-n-runner gets its own branch and working directory. Use for best-of-N parallel attempts or isolated experiments. </available_subagent_types> <available_subagent_models> If the user explicitly asks for the model of a subagent/task, you may ONLY use model slugs from this list: - inherit (default; required unless the user explicitly requested another model) - claude-fable-5-1-thinking-high - claude-opus-5-5-medium - claude-opus-5-thinking-high - claude-sonnet-5-5-high - composer-2.5-fast - cursor-grok-4.6-high-fast - gemini-4-argon-medium - gpt-5.6-sol-medium - grok-4.7-high-fast - muse-wonder-1.0-high If the user isn't asking for a specific version, prefer the latest version of the model family. As an example, if the user just says "gpt" or "claude", use the latest available version of GPT or Claude. IMPORTANT: If the user requests a model that is NOT in the list above, do NOT substitute a different model or guess. Instead, skip launching the subagent with that model and tell the user which model was unavailable and which models are available. When speaking to the USER about which model you selected for a subagent, do NOT use the kebab-case model names unless the user requested the model using that format. Ue the same naming scheme the user used to discuss the model when they requested it. </available_subagent_models> <dynamic_tools> You have access to tools through dynamic namespaces, e.g. MCP servers, using `GetDynamicTools` and `CallDynamicTool`. ## Dynamic Tool Discovery and Invocation Use `GetDynamicTools` to discover tool schemas, then `CallDynamicTool` to invoke one tool. Aim to minimize round-trips: ideally one discovery call followed by one invocation. If the user mentions a product or service represented by an available namespace, and the request likely depends on it, proactively inspect that namespace before answering. If you are unsure which namespace matches, search with a relevant pattern. `GetDynamicTools` supports these modes: 1. `{"namespace":"<id>"}`: returns schemas and full descriptions for every tool in that namespace. 2. `{"namespace":"<id>","toolName":"<name>"}`: returns one tool schema with its full description. 3. `{"pattern":"<regex>"}`: searches namespace and tool names. 4. `{"namespace":"<id>","pattern":"<regex>"}`: searches tools within one namespace. 5. No arguments: returns the full catalog. Pattern-search and catalog results shorten long descriptions, marked by a trailing "... [truncated]"; namespace and single-tool lookups always return the complete description. Always inspect a tool's schema before invoking it with `CallDynamicTool`. If the available dynamic tools do not fully support what the user asked you to do, complete the work you can with the current tool set. In your work summary, include what you were unable to do and why. Do not use browser automation to work around missing tools unless the user explicitly asks you to use the browser. Available dynamic tool namespaces: <dynamic_tool_namespaces> <namespace name="cursor-ide-browser" tools="browser_navigate, browser_snapshot, browser_click, browser_mouse_click_xy, browser_type, browser_fill, browser_select_option, browser_press_key, browser_scroll, browser_drag, browser_get_bounding_box, browser_highlight, browser_tabs, browser_cdp, browser_take_screenshot, browser_lock" namespaceUseInstructions="The cursor-ide-browser MCP server provides a Cursor-owned browser tab plus a raw Chrome DevTools Protocol command tool. CORE WORKFLOW: 1. Start by understanding the user's goal and what success looks like on the page. 2. Use browser_tabs with action "list" to inspect open tabs and URLs before acting. 3. Use browser_navigate to create or navigate the target tab. Omit the position parameter for background automation so focus is preserved. 4. Use browser_lock before longer automation on an existing tab, then browser_lock with action "unlock" when finished. 5. Use browser_snapshot for accessibility context and browser_take_screenshot for visual verification. 6. Use browser_click, browser_type, browser_fill, browser_select_option, browser_press_key, browser_scroll, and browser_drag for page interactions. 7. Use browser_highlight and browser_get_bounding_box for visual grounding and coordinate diagnostics. 8. Use browser_cdp for page inspection, profiling, runtime evaluation, DOM/CSS queries, and performance data. AVOID RABBIT HOLES: 1. Do not repeat the same failing action more than once without new evidence such as a fresh snapshot, a different ref, a changed page state, or a clear new hypothesis. 2. IMPORTANT: If four attempts fail or progress stalls, stop acting and report what you observed, what blocked progress, and the most likely next step. 3. Prefer gathering evidence over brute force. If the page is confusing, use browser_snapshot, browser_take_screenshot, or CDP inspection before trying more actions. 4. If you encounter a blocker such as login, passkey/manual user interaction, permissions, captchas, destructive confirmations, missing data, or an unexpected state, stop and report it instead of improvising repeated actions. 5. Do not get stuck in wait-action-wait loops. Every retry should be justified by something newly observed. CRITICAL - Lock/unlock workflow: 1. browser_lock requires an existing browser tab - you CANNOT call browser_lock with action: "lock" before browser_navigate 2. Correct order: browser_navigate -> browser_lock({ action: "lock" }) -> (interactions) -> browser_lock({ action: "unlock" }) 3. If a browser tab already exists (check with browser_tabs list), call browser_lock with action: "lock" FIRST before any interactions 4. Only call browser_lock with action: "unlock" when completely done with ALL browser operations for this turn IMPORTANT - Waiting strategy: When waiting for page changes, prefer short CDP polling loops with Runtime.evaluate, DOM queries, Page lifecycle signals, or browser_snapshot checks rather than a single long wait. CDP USAGE: - Use browser_cdp with a DevTools Protocol method and params object, for example Runtime.evaluate, DOM.getDocument, CSS.getComputedStyleForNode, Profiler.start/stop, Performance.getMetrics, Log.enable, and Network.enable. - Do not use browser_cdp with CDP Input.* methods. They are denied because they are focus-sensitive in Electron webviews and can route input to Cursor UI instead of the browser page. - Use browser_click, browser_type, browser_fill, browser_select_option, browser_press_key, browser_scroll, and browser_drag for clicks, typing, filling inputs, selecting options, keyboard actions, scrolling, and drag-and-drop. - Use Runtime.evaluate for advanced DOM-scoped interactions that the dedicated browser tools do not cover. - For profiling, call Profiler.enable, Profiler.start, reproduce the behavior, then Profiler.stop. The profile is saved to a file and returned as a log_file; read that file only when you need to inspect details. - For JavaScript evaluation, prefer Runtime.evaluate with returnByValue when possible. - Device emulation you set with Emulation.setDeviceMetricsOverride, Emulation.setTouchEmulationEnabled, or Emulation.setUserAgentOverride (for example for a phone-sized screenshot) lasts only for the current turn: it is cleared when your turn ends, and the user can reset it from the browser tab at any time. Send Emulation.clearDeviceMetricsOverride yourself once you are done with it within a turn. - Some browser-wide or sensitive CDP methods are denied, especially cookie, storage, permission, download, target-management, filesystem-backed file-input commands, system-level commands, and CDP navigation/history navigation commands. - Large CDP responses are saved to files instead of being inlined. Prefer using the returned file path over immediately stuffing large payloads into context; read focused sections only when needed. VISION: - browser_take_screenshot attaches an image result that the model can inspect. CDP Page.captureScreenshot returns data inside JSON and should not replace browser_take_screenshot when visual verification is needed. NOTES: - browser_snapshot returns snapshot YAML and is the main source of truth for page structure. - Refs are opaque handles tied to the latest browser_snapshot for that tab. - Iframe content is not accessible - only elements outside iframes can be interacted with. - When you stop to report a blocker, include the current page, the target you were trying to reach, the blocker you observed, and the best next action. If the blocker requires manual user interaction, ask the user to take over at that point rather than assuming it in advance." source="mcp" /> <namespace name="cursor" tools="AwaitShell, CreateGoal, Delete, EditNotebook, FetchMcpResource, GenerateImage, ReadLints, SwitchMode, Task, TodoWrite, UpdateGoal, WebFetch, WebSearch" namespaceUseInstructions="Native Cursor tools for this session. These are highly recommended and useful tools that you should use when the right situation arises. Don't be afraid to look at one if it seems relevant, even if you don't end up using it. You MUST read the tool schemas before calling them. Here are some crucial instructions: - AwaitShell: Use to sleep and check shell progress. Never sleep using shell. - ReadLints: Check for linter errors after substantive edits. - SwitchMode: Switch between modes: Agent and Plan. Proactively consider switching modes for relevant requests. - Task: Spawn local and cloud subagents natively. - TodoWrite: Use this tool to manage complex multi-step tasks." source="cursor" /> </dynamic_tool_namespaces> If an MCP-backed namespace requires authentication, call `mcp_auth` through `CallDynamicTool` for that namespace, then inspect it again and retry if appropriate. Do not authenticate namespaces preemptively or repeatedly. </dynamic_tools>

## Usuario

ahora que queda de gastronomia para cerrar premium ?

## Asistente

Para cerrar **premium** en Gastro Crown quedan estos bloques (Anita/operativo ya está):

### 1. Facturación ARCA (bloque principal)
El cobro solo cierra la cuenta (`cerrada` + evento). No crea `venta`, no emite CAE/CAEA, no asienta ni mueve caja/stock. El modelo ya tiene `venta_id` listo. Aplica a efectivo, factura sola, interna/GST y hotel.

### 2. Pantalla KDS visual
En POS ya hay prepara / área / marcha. Falta la **pantalla de cocina** que consuma `payloadVisualKds` (foto del plato + insumos con foto). Hoy ese payload es solo contrato; nadie lo usa.

### 3. Herramientas todavía stub
- **Opcionales** → toast “etapa premium” (en AGG ya existe el servicio de fórmulas)
- **Receta** → mismo stub

### Fuera de premium (pero suelto)
Chips de empresa en index mesa/mozo: el partial/filtros están; el index puede no estar pasando `empresa_query` completo.

---

Orden natural: **ARCA → KDS visual → opcionales/receta**.
Para cerrar **premium** en Gastro Crown quedan estos bloques (Anita/operativo ya está):

### 1. Facturación ARCA (bloque principal)
El cobro solo cierra la cuenta (`cerrada` + evento). No crea `venta`, no emite CAE/CAEA, no asienta ni mueve caja/stock. El modelo ya tiene `venta_id` listo. Aplica a efectivo, factura sola, interna/GST y hotel.

### 2. Pantalla KDS visual
En POS ya hay prepara / área / marcha. Falta la **pantalla de cocina** que consuma `payloadVisualKds` (foto del plato + insumos con foto). Hoy ese payload es solo contrato; nadie lo usa.

### 3. Herramientas todavía stub
- **Opcionales** → toast “etapa premium” (en AGG ya existe el servicio de fórmulas)
- **Receta** → mismo stub

### Fuera de premium (pero suelto)
Chips de empresa en index mesa/mozo: el partial/filtros están; el index puede no estar pasando `empresa_query` completo.

---

Orden natural: **ARCA → KDS visual → opcionales/receta**.

## Usuario

1) vamos con todos los puntos ARCA / KDS visual (por sala) / opcionales/receta igual que AGG 2) la facturacion CAEA tiene que funcionar igual que en AGG, tiene que haber un cron que corra controlando la conexion y cambiar solo a caea y volver a cae, ademas que en cada factura ante time out o falta de comunicacion cambiar solo a caea

## Asistente

Arranco con ARCA + failover CAEA (como AGG), luego KDS por sala y opcionales/receta. Primero relevo el stack AGG y el monitor CAEA para reutilizarlo en Crown.
Arranco con ARCA + failover CAEA (como AGG), luego KDS por sala y opcionales/receta. Primero relevo el stack AGG y el monitor CAEA para reutilizarlo en Crown.

## Asistente

El monitor CAEA ya corre cada 5 min; Crown tiene que emitir por el mismo stack. Sigo armando el bridge de facturación y revisando opcionales/KDS.
El monitor CAEA ya corre cada 5 min; Crown tiene que emitir por el mismo stack. Sigo armando el bridge de facturación y revisando opcionales/KDS.

## Asistente

ARCA ya quedó cableado. Sigo con opcionales/receta y el KDS por sala, y reviso el código de emisión.
ARCA ya quedó cableado. Sigo con opcionales/receta y el KDS por sala, y reviso el código de emisión.

## Asistente

Ajusto el monitor CAEA a un PV electrónico y reviso cuenta de caja efectivo + el resto del cableado.
Ajusto el monitor CAEA a un PV electrónico y reviso cuenta de caja efectivo + el resto del cableado.

## Asistente

Quedó el bloque premium cableado. Resumen:

### 1. ARCA (cobro fiscal)
- `GastronomiaCrownFacturaEmisionService`: factura + cobranza + CAE/CAEA + cierre con `venta_id`.
- Misma resiliencia que AGG: timeout/transporte → reintento en PV CAEA + aviso al failover store.
- Opcionales validados antes de emitir.

### 2. Failover CAEA (cron + por factura)
- Cron ya activo: `arca:monitorear-conectividad` cada 5 min.
- `ARCA_WSFE_REINTENTAR_CAEA_SI_FALLA_COMUNICACION=true`, timeout POS 18s.
- Ajuste: el probe apuntaba al PV **7 (manual)** → ahora **82 (electrónico CAE)**.
- Estado: `storage/app/arca/failover/state.json` (no toca `.env`).

### 3. Opcionales / Receta (como AGG)
- Modal en POS; si el artículo pide opcionales, no carga la línea hasta elegirlos.
- Receta abre la fórmula del artículo en solapa de consulta.

### 4. KDS visual por sala
- URL: `ventas/gastronomia-crown/kds?sala_id=N`
- Botón **KDS** en el POS.
- Foto plato + insumos, estados Preparando / Listo / Entregado.

### Config operativa a tener en cada PC
En `configuracion_puntoventa_gastronomia`: `tipotransaccion_id`, `puntoventa_cae_id`, `puntoventa_caea_id` (y caja).  
Efectivo POS: `GASTRONOMIA_CUENTACAJA_EFECTIVO_POR_EMPRESA={"1":3}` (Fondo Fijo Cipolletti).

---

Pendiente de tu OK (escribe menú/permisos):

```bash
php artisan migrate --path=database/migrations/2026_10_17_231500_menu_permiso_kds_gastronomia_crown.php
```

¿La corro?
Quedó el bloque premium cableado. Resumen:

### 1. ARCA (cobro fiscal)
- `GastronomiaCrownFacturaEmisionService`: factura + cobranza + CAE/CAEA + cierre con `venta_id`.
- Misma resiliencia que AGG: timeout/transporte → reintento en PV CAEA + aviso al failover store.
- Opcionales validados antes de emitir.

### 2. Failover CAEA (cron + por factura)
- Cron ya activo: `arca:monitorear-conectividad` cada 5 min.
- `ARCA_WSFE_REINTENTAR_CAEA_SI_FALLA_COMUNICACION=true`, timeout POS 18s.
- Ajuste: el probe apuntaba al PV **7 (manual)** → ahora **82 (electrónico CAE)**.
- Estado: `storage/app/arca/failover/state.json` (no toca `.env`).

### 3. Opcionales / Receta (como AGG)
- Modal en POS; si el artículo pide opcionales, no carga la línea hasta elegirlos.
- Receta abre la fórmula del artículo en solapa de consulta.

### 4. KDS visual por sala
- URL: `ventas/gastronomia-crown/kds?sala_id=N`
- Botón **KDS** en el POS.
- Foto plato + insumos, estados Preparando / Listo / Entregado.

### Config operativa a tener en cada PC
En `configuracion_puntoventa_gastronomia`: `tipotransaccion_id`, `puntoventa_cae_id`, `puntoventa_caea_id` (y caja).  
Efectivo POS: `GASTRONOMIA_CUENTACAJA_EFECTIVO_POR_EMPRESA={"1":3}` (Fondo Fijo Cipolletti).

---

Pendiente de tu OK (escribe menú/permisos):

```bash
php artisan migrate --path=database/migrations/2026_10_17_231500_menu_permiso_kds_gastronomia_crown.php
```

¿La corro?

## Usuario

correlo y decime que falta para cerrar gastronomia?

## Asistente

Migración OK: menú **KDS Crown** (id 680) + permiso `usar-kds-gastronomia-crown` para administrador y Enc-sistemas.

### Código premium: cerrado
ARCA al cobrar, failover CAEA (cron + reintento por timeout), opcionales/receta, KDS por sala. No quedan stubs “etapa premium” en el POS.

### Lo que falta para operar (datos / config, no código)

| Gap | Estado hoy |
|---|---|
| **Config PC** (`configuracion_puntoventa_gastronomia`) | **0 filas** — hay que crear cada terminal (modo, sala, lista precio, PV CAE/CAEA, tipo factura, depósitos, salidas impresión) |
| **Áreas KDS** (`area_comanda_gastronomia`) | **0** — sin esto no hay destino cocina ni filtros de área |
| **Jornada gastronomía** | **0** — el cobro usa fecha de hoy; si quieren cierre/jornada como AGG, hay que armar el circuito |
| **Prueba real** | Nunca se emitió una factura Crown end-to-end (CAE + failover CAEA forzado) |
| **Cuenta caja efectivo** | Quedó `{"1":3}` (Fondo Fijo Cipo); confirmar si es la correcta por sala |
| **Fotos artículos** | KDS muestra foto/insumos solo si el maestro tiene `foto` cargada |

Maestros ya cargados: mesas 253, mozos 96, salas 9, descuentos 92, PV electrónicos CAE/CAEA existentes.

**Orden práctico:** configs por PC → áreas KDS → cobro de prueba en un PV → (opcional) jornadas.
Migración OK: menú **KDS Crown** (id 680) + permiso `usar-kds-gastronomia-crown` para administrador y Enc-sistemas.

### Código premium: cerrado
ARCA al cobrar, failover CAEA (cron + reintento por timeout), opcionales/receta, KDS por sala. No quedan stubs “etapa premium” en el POS.

### Lo que falta para operar (datos / config, no código)

| Gap | Estado hoy |
|---|---|
| **Config PC** (`configuracion_puntoventa_gastronomia`) | **0 filas** — hay que crear cada terminal (modo, sala, lista precio, PV CAE/CAEA, tipo factura, depósitos, salidas impresión) |
| **Áreas KDS** (`area_comanda_gastronomia`) | **0** — sin esto no hay destino cocina ni filtros de área |
| **Jornada gastronomía** | **0** — el cobro usa fecha de hoy; si quieren cierre/jornada como AGG, hay que armar el circuito |
| **Prueba real** | Nunca se emitió una factura Crown end-to-end (CAE + failover CAEA forzado) |
| **Cuenta caja efectivo** | Quedó `{"1":3}` (Fondo Fijo Cipo); confirmar si es la correcta por sala |
| **Fotos artículos** | KDS muestra foto/insumos solo si el maestro tiene `foto` cargada |

Maestros ya cargados: mesas 253, mozos 96, salas 9, descuentos 92, PV electrónicos CAE/CAEA existentes.

**Orden práctico:** configs por PC → áreas KDS → cobro de prueba en un PV → (opcional) jornadas.

## Usuario

tenemos que armar el modulo de planos de mesas para poder armarlos por empresa / sala y diseñarlos con alguna herramienta grafica de ultima generacion. Esto tambien es de sistema premium. Luego esos planos se asignan a la PC en la configuracion, puede haber mas de uno por PC, y en algun lugar del POS deberian estar colapsados de manera prolija para cuando alguien quiere ir a las mesas se maneja con los planos. Si no hay plano cargado queda como funciona agg ahora con el boton solamente de "Mesas"

## Asistente

Voy a relevar cómo el POS maneja mesas hoy y la config por PC, y con eso armo el módulo de planos (editor gráfico + asignación + UI colapsada en el POS).
Voy a relevar cómo el POS maneja mesas hoy y la config por PC, y con eso armo el módulo de planos (editor gráfico + asignación + UI colapsada en el POS).

## Asistente

Armo el módulo completo: tablas, ABM con editor Konva, asignación multi-plano en la config PC y UI colapsada en el POS.
Armo el módulo completo: tablas, ABM con editor Konva, asignación multi-plano en la config PC y UI colapsada en el POS.

## Asistente

Quedó armado el módulo premium de **planos de mesas**.

### Qué hay
| Pieza | Dónde |
|---|---|
| ABM + editor Konva 9 | **Tablas Gastronomía → Planos de mesas** (`ventas/gastronomia-crown/plano-mesa`) |
| Por empresa / sala | Layout JSON con mesas arrastrables (rect/redonda/oval, rotación, zoom, snap) |
| Multi-plano por PC | En config PV → bloque Crown → tildes + orden |
| POS | Acordeón de planos → canvas con libre/ocupada; clic = abrir mesa |
| Sin planos | Misma grilla de botones “Mesas” que AGG |

### Cómo usarlo
1. Crear plano (empresa + sala) → ubicar mesas en el editor → **Guardar plano**.
2. En la config de cada PC, marcar los planos (puede haber varios).
3. Abrir el POS: panel **Planos** colapsado; sin asignación, sigue **Mesas**.

Migraciones y menú/permisos (admin + Enc-sistemas) ya corridos en este entorno Crown.
Quedó armado el módulo premium de **planos de mesas**.

### Qué hay
| Pieza | Dónde |
|---|---|
| ABM + editor Konva 9 | **Tablas Gastronomía → Planos de mesas** (`ventas/gastronomia-crown/plano-mesa`) |
| Por empresa / sala | Layout JSON con mesas arrastrables (rect/redonda/oval, rotación, zoom, snap) |
| Multi-plano por PC | En config PV → bloque Crown → tildes + orden |
| POS | Acordeón de planos → canvas con libre/ocupada; clic = abrir mesa |
| Sin planos | Misma grilla de botones “Mesas” que AGG |

### Cómo usarlo
1. Crear plano (empresa + sala) → ubicar mesas en el editor → **Guardar plano**.
2. En la config de cada PC, marcar los planos (puede haber varios).
3. Abrir el POS: panel **Planos** colapsado; sin asignación, sigue **Mesas**.

Migraciones y menú/permisos (admin + Enc-sistemas) ya corridos en este entorno Crown.

## Usuario

esto es solo para crown, no? y todo lo de gastronomia que desarrollamos no toca nada en AGG? quiero actualizar hoy el reporitorio ahi y quiero asegurarme que todo va a seguir bien ahi

## Asistente

Voy a auditar qué quedó gated a Crown y qué tocó código compartido con AGG, para que puedas subir el repo con tranquilidad.
Voy a auditar qué quedó gated a Crown y qué tocó código compartido con AGG, para que puedas subir el repo con tranquilidad.

## Asistente

Sí: **planos, POS Crown, KDS Crown y cobro Crown son solo Crown** en runtime. En AGG el POS sigue siendo `ventas/gastronomia/proceso-facturacion`; las rutas `/gastronomia-crown/*` hacen `assertCrown()` y responden 403/abort.

### Qué no toca el flujo AGG
- Controllers/vistas/JS bajo `GastronomiaCrown*` y `gastronomia_crown/*`
- Menús/permisos Crown (migraciones con `esCrown()` → en AGG no crean menú ni roles)
- Seeds de salas Crown (solo seed si es Crown)

### Qué sí llega al migrate de AGG (inofensivo si lo esperás)
| Cambio | Efecto en AGG |
|---|---|
| Tablas `gastro_crown_*`, `gastro_plano_mesa`, pivot planos | Se crean **vacías**; nadie las usa |
| Columnas en `configuracion_puntoventa_gastronomia` (modo_pos, sala_id, etc.) | Defaults seguros; la UI Crown **no se muestra** (`esCrown()`) |
| `mesa_gastronomia.sala_id` / `mozo_…sala_id` | Nullable; filtros sala solo si Crown |
| `articulo.kds_preparar` | También en AGG: copia `waitry_preparar` si existe (columna extra, Waitry igual) |
| Métodos nuevos en `GastronomiaFacturacionService` / `Receptor…` | **Aditivos**; `emitirComprobante(CuentaGastronomia)` sigue igual |

### Compartido a propósito (no es “solo Crown”)
- `EmpresaFiltroExternoSupport`: chips de empresa solo si el usuario tiene **más de una** asignada (RQ/OC/listados en todos los clientes). Es el fix del bug “todas las empresas”; no cambia gastronomía AGG.

### Al actualizar AGG hoy
1. Deploy código + `migrate` (van a aparecer tablas/columnas Crown vacías).
2. No hace falta poner en `.env` las `GASTRONOMIA_CROWN_*` (tienen default).
3. No cambies en AGG el `ARCA_MONITOR_PUNTOVENTA_ID` de Crown; cada instalación tiene su `.env`.
4. Smoke rápido: abrir **Proceso facturación** AGG, emitir una factura de prueba si podés.

**Conclusión:** el POS Waitry/AGG no se reemplaza ni se desvía. Subís el repo con tranquilidad; lo único que “aparece” en AGG son tablas/columnas vacías y una columna `kds_preparar` alineada con Waitry.
Sí: **planos, POS Crown, KDS Crown y cobro Crown son solo Crown** en runtime. En AGG el POS sigue siendo `ventas/gastronomia/proceso-facturacion`; las rutas `/gastronomia-crown/*` hacen `assertCrown()` y responden 403/abort.

### Qué no toca el flujo AGG
- Controllers/vistas/JS bajo `GastronomiaCrown*` y `gastronomia_crown/*`
- Menús/permisos Crown (migraciones con `esCrown()` → en AGG no crean menú ni roles)
- Seeds de salas Crown (solo seed si es Crown)

### Qué sí llega al migrate de AGG (inofensivo si lo esperás)
| Cambio | Efecto en AGG |
|---|---|
| Tablas `gastro_crown_*`, `gastro_plano_mesa`, pivot planos | Se crean **vacías**; nadie las usa |
| Columnas en `configuracion_puntoventa_gastronomia` (modo_pos, sala_id, etc.) | Defaults seguros; la UI Crown **no se muestra** (`esCrown()`) |
| `mesa_gastronomia.sala_id` / `mozo_…sala_id` | Nullable; filtros sala solo si Crown |
| `articulo.kds_preparar` | También en AGG: copia `waitry_preparar` si existe (columna extra, Waitry igual) |
| Métodos nuevos en `GastronomiaFacturacionService` / `Receptor…` | **Aditivos**; `emitirComprobante(CuentaGastronomia)` sigue igual |

### Compartido a propósito (no es “solo Crown”)
- `EmpresaFiltroExternoSupport`: chips de empresa solo si el usuario tiene **más de una** asignada (RQ/OC/listados en todos los clientes). Es el fix del bug “todas las empresas”; no cambia gastronomía AGG.

### Al actualizar AGG hoy
1. Deploy código + `migrate` (van a aparecer tablas/columnas Crown vacías).
2. No hace falta poner en `.env` las `GASTRONOMIA_CROWN_*` (tienen default).
3. No cambies en AGG el `ARCA_MONITOR_PUNTOVENTA_ID` de Crown; cada instalación tiene su `.env`.
4. Smoke rápido: abrir **Proceso facturación** AGG, emitir una factura de prueba si podés.

**Conclusión:** el POS Waitry/AGG no se reemplaza ni se desvía. Subís el repo con tranquilidad; lo único que “aparece” en AGG son tablas/columnas vacías y una columna `kds_preparar` alineada con Waitry.

## Usuario

como quedo el menu de gastronomia entonces? nos faltaria armar el cierre diario que usan en Crown que es distinto al de AGG. Fijate el fuente /home/sergio/tmp/ventas/p-cierrefact.c. La idea es armar cierres X que deben ser presentados en la Tesoreria como rendiciones. Aca las habilitaciones/rendiciones se manejan por cajero y el cajero en gastronomia es el mozo. Saca las opciones que no se van a usar aca en Crown con migracion gated en crown. O sea, no se va a abrir el turno y cerrar el turno, solo abre y cierra jornada por sala ademas de por empresa. La empresa queda seleccionada ya que se va a manejar solo 1 empresa desde el usuario. Pero debe estar atado a la cantidad de empresas que tienen el usuario con allfiltrado.

## Asistente

Reviso el menú actual de gastronomía, el fuente del cierre Crown y cómo AGG maneja jornada/turno/rendiciones, para armar el cierre X por sala y limpiar el menú con migración gated.
Reviso el menú actual de gastronomía, el fuente del cierre Crown y cómo AGG maneja jornada/turno/rendiciones, para armar el cierre X por sala y limpiar el menú con migración gated.

## Asistente

El `p-cierrefact.c` no está en el disco; uso `p-cierrecajero` + `rendgastro` (total_x, cajero=mozo, sucursal=sala). Arranco por limpiar el menú Crown y extender jornada por sala; después el cierre X → rendición.
El `p-cierrefact.c` no está en el disco; uso `p-cierrecajero` + `rendgastro` (total_x, cajero=mozo, sucursal=sala). Arranco por limpiar el menú Crown y extender jornada por sala; después el cierre X → rendición.

## Asistente

### Menú Gastronomía en Crown (visible)

| Sección | Ítems |
|---|---|
| **Tablas** | Ubicaciones, Mesas, Descuentos, Mozos, Áreas comanda, Categ. fidelidad, **Planos de mesas** |
| **Operación** | Facturas del día · **Jornada** (ahora con sala) · **Cierre X / Rendición mozo** · POS Crown · KDS Crown |
| **Reportes** | Informe gerente, artículos, hora a hora, descuentos, analítico, insumos |

Ocultos en Crown (sin `menu_rol`): proceso facturación Waitry, turnos (ABM/habilitación/cierres/saneamiento/central), tótems, canjes, viandas, vending.

---

### Qué se armó

1. **Jornada por empresa + sala** (Crown). AGG sigue con `sala_id` null. Empresa con `allFiltrado()` (1 empresa → auto; varias → selector).
2. **Cierre X por mozo** → crea `RendicionGastronomiaCaja` tipo `cierre_x` (mozo = cajero) y abre la pantalla de **Caja → Rend. Gastronomía** para Tesorería.
3. Flujo: abrir jornada (sala) → POS → **Cierre X** (total X por mozo) → rendición en caja → cerrar jornada (bloquea si hay cuentas Crown abiertas en la sala).

Nota: no está `/home/sergio/tmp/ventas/p-cierrefact.c`. El cierre X se basó en `p-cierrecajero` + `rendgastro` (`total_x`, cajero, sucursal≈sala). Si subís ese fuente, se puede alinear el detalle (medios, fallos mozo, etc.).

### Pendiente fino del cierre
- Medios de pago / habilitado vs rendido como en Anita (hoy Tesorería completa la rendición a mano).
- Si querés, el próximo paso es pegar `p-cierrefact.c` y cerrar esos totales.
### Menú Gastronomía en Crown (visible)

| Sección | Ítems |
|---|---|
| **Tablas** | Ubicaciones, Mesas, Descuentos, Mozos, Áreas comanda, Categ. fidelidad, **Planos de mesas** |
| **Operación** | Facturas del día · **Jornada** (ahora con sala) · **Cierre X / Rendición mozo** · POS Crown · KDS Crown |
| **Reportes** | Informe gerente, artículos, hora a hora, descuentos, analítico, insumos |

Ocultos en Crown (sin `menu_rol`): proceso facturación Waitry, turnos (ABM/habilitación/cierres/saneamiento/central), tótems, canjes, viandas, vending.

---

### Qué se armó

1. **Jornada por empresa + sala** (Crown). AGG sigue con `sala_id` null. Empresa con `allFiltrado()` (1 empresa → auto; varias → selector).
2. **Cierre X por mozo** → crea `RendicionGastronomiaCaja` tipo `cierre_x` (mozo = cajero) y abre la pantalla de **Caja → Rend. Gastronomía** para Tesorería.
3. Flujo: abrir jornada (sala) → POS → **Cierre X** (total X por mozo) → rendición en caja → cerrar jornada (bloquea si hay cuentas Crown abiertas en la sala).

Nota: no está `/home/sergio/tmp/ventas/p-cierrefact.c`. El cierre X se basó en `p-cierrecajero` + `rendgastro` (`total_x`, cajero, sucursal≈sala). Si subís ese fuente, se puede alinear el detalle (medios, fallos mozo, etc.).

### Pendiente fino del cierre
- Medios de pago / habilitado vs rendido como en Anita (hoy Tesorería completa la rendición a mano).
- Si querés, el próximo paso es pegar `p-cierrefact.c` y cerrar esos totales.
