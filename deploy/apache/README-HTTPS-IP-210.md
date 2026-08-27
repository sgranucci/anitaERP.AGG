# HTTPS de prueba por IP (10.20.30.210) — sin tocar HTTP

Prueba de cámara en vivo del pickeo. **No cambia** el acceso actual por
`http://10.20.30.210/anitaERP/public/...`.

| Qué | Valor |
|---|---|
| HTTP | Intactos `000-default` y `anitaERP` en `:80` |
| HTTPS | Solo se **suma** `:443` |
| Path | Sigue `/anitaERP/public/...` |
| `.env` | No tocar (`APP_URL`, `APP_CARPETA`, mails) |
| Certificado | Autofirmado con SAN = IP `10.20.30.210` |
| Teléfono | Aviso de certificado (inevitable sin dominio) |

URL de prueba:

```
https://10.20.30.210/anitaERP/public/stock/transferencia-mercaderia
```

## Archivos (revisar antes de instalar)

| Archivo | Qué hace |
|---|---|
| `anitaERP-prod-210-ssl-ip.conf` | Vhost **solo** `:443`. `DocumentRoot /var/www/html` (igual que `000-default`). Sin redirect. |
| `openssl-ip-210.cnf` | Plantilla del certificado (CN + SAN = IP). |
| `generar-cert-ip-210.sh` | Crea cert/key en `/etc/ssl/anitaERP/ip-210/`. No toca Apache. |
| `aplicar-vhost-210-ssl-ip.sh` | Genera cert si falta, habilita el sitio SSL, recarga Apache. No deshabilita HTTP. |
| `quitar-vhost-210-ssl-ip.sh` | Saca el sitio `:443` y recarga. HTTP queda igual. El cert no se borra. |

## Instalar (después de revisar)

```bash
cd /var/www/html/anitaERP
sudo ./deploy/apache/aplicar-vhost-210-ssl-ip.sh
```

## Quitar

```bash
cd /var/www/html/anitaERP
sudo ./deploy/apache/quitar-vhost-210-ssl-ip.sh
```

## Qué no hace este paquete

- No redirige HTTP → HTTPS.
- No vacía `APP_CARPETA` ni cambia `APP_URL`.
- No usa el vhost `anitaERP-prod-210.conf` (ese apunta `DocumentRoot` a `public/` y reescribe `/anitaERP/public`).
- No usa Let's Encrypt ni un nombre DNS.
- No habilita `default-ssl` (snakeoil `CN=anitanextgen`, sin la IP).
