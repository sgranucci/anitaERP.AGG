# como puedo replicar el caea pedido en el ERP en anita bridge?

- **Fecha:** 2026-10-29 17:49
- **Chat id:** `c5055867-f9a2-4b3f-8710-e00fc5d46326`
- **Módulos:** gastronomia
- **Origen local:** `agent-transcripts/c5055867-f9a2-4b3f-8710-e00fc5d46326`

> Export redactado para viaje Crown. Sin tool dumps completos; solo diálogo útil.

## Usuario

como puedo replicar el caea pedido en el ERP en anita bridge?

## Asistente

Voy a buscar en el ERP cómo se pide el CAEA y qué expone Anita Bridge para replicar ese pedido.
Voy a buscar en el ERP cómo se pide el CAEA y qué expone Anita Bridge para replicar ese pedido.

## Asistente

El CAEA autorizado en el ERP ya se replica a Anita por el bridge HTTP, en la tabla Informix `caea`. Lo hace `ArcaCaeaAnitaSyncService`.

## Automático, al pedir el CAEA

Cuando la solicitud a ARCA sale autorizada (WSFE o MTXCA), el ERP llama a `intentarGrabarTrasSolicitud()`. Si Anita no responde, el CAEA queda igual en el ERP y el mensaje de la pantalla suma una advertencia (`Advertencia Anita: …`). Un fallo del bridge no anula el pedido.

Eso corre solo si `ARCA_CAEA_REPLICAR_ANITA` está en `true` (es el default en `config/arca.php`).

## A mano, si no llegó a Anita

En **Ventas → CAEA ARCA**, en una fila **autorizada**, el ícono de base de datos (**Grabar en Anita**) vuelve a mandar ese registro. El mismo botón está en el detalle del CAEA. Hace falta el permiso `solicitar-arca-caea`.

El POST es `ventas/arca-caea/{id}/grabar-anita`. Busca en Anita por **CUIT + período + quincena** (`caea_orden`):

| Situación en Anita | Qué hace |
|---|---|
| No existe | Inserta (toma el próximo `caea_serial`) |
| Existe y el número coincide | No toca nada: «CAEA ya registrado en Anita» |
| Existe y el número es otro | Actualiza número y fechas |

Campos que escribe: `caea_nro_caea`, `caea_periodo`, `caea_orden`, `caea_desde_fecha`, `caea_hasta_fecha`, `caea_fecha_tope`, `caea_fecha_proc`, `caea_cuit`. Las fechas van en `Ymd`.

## Verificar el bridge

```bash
php artisan arca:diagnosticar-anita-caea
```

Muestra la URL del bridge y lista `caea` desde 2026. Si la lista viene vacía y sin error JSON, el aviso del comando apunta a copiar el `apiERP.php` corregido en `/usr2/www/htdocs/` del servidor Anita.
El CAEA autorizado en el ERP ya se replica a Anita por el bridge HTTP, en la tabla Informix `caea`. Lo hace `ArcaCaeaAnitaSyncService`.

## Automático, al pedir el CAEA

Cuando la solicitud a ARCA sale autorizada (WSFE o MTXCA), el ERP llama a `intentarGrabarTrasSolicitud()`. Si Anita no responde, el CAEA queda igual en el ERP y el mensaje de la pantalla suma una advertencia (`Advertencia Anita: …`). Un fallo del bridge no anula el pedido.

Eso corre solo si `ARCA_CAEA_REPLICAR_ANITA` está en `true` (es el default en `config/arca.php`).

## A mano, si no llegó a Anita

En **Ventas → CAEA ARCA**, en una fila **autorizada**, el ícono de base de datos (**Grabar en Anita**) vuelve a mandar ese registro. El mismo botón está en el detalle del CAEA. Hace falta el permiso `solicitar-arca-caea`.

El POST es `ventas/arca-caea/{id}/grabar-anita`. Busca en Anita por **CUIT + período + quincena** (`caea_orden`):

| Situación en Anita | Qué hace |
|---|---|
| No existe | Inserta (toma el próximo `caea_serial`) |
| Existe y el número coincide | No toca nada: «CAEA ya registrado en Anita» |
| Existe y el número es otro | Actualiza número y fechas |

Campos que escribe: `caea_nro_caea`, `caea_periodo`, `caea_orden`, `caea_desde_fecha`, `caea_hasta_fecha`, `caea_fecha_tope`, `caea_fecha_proc`, `caea_cuit`. Las fechas van en `Ymd`.

## Verificar el bridge

```bash
php artisan arca:diagnosticar-anita-caea
```

Muestra la URL del bridge y lista `caea` desde 2026. Si la lista viene vacía y sin error JSON, el aviso del comando apunta a copiar el `apiERP.php` corregido en `/usr2/www/htdocs/` del servidor Anita.

## Usuario

no veo el boton de grabar en anita

## Asistente

El botón solo aparece bajo ciertas condiciones. Voy a revisar en la pantalla y en el código cuáles están cortando la visibilidad.
El botón solo aparece bajo ciertas condiciones. Voy a revisar en la pantalla y en el código cuáles están cortando la visibilidad.

## Asistente

El botón no está en la pantalla porque en este entorno Crown la réplica a Anita está apagada.

En `.env` figura:

```text
ARCA_CAEA_REPLICAR_ANITA=false  # Crown: sin escritura Anita
```

Con ese valor, `ArcaCaeaAnitaSyncService` queda deshabilitado y la vista no dibuja ni el ícono verde de la grilla ni el botón **Grabar en Anita** del detalle. El pedido automático a ARCA sigue funcionando; lo que no corre es la escritura en la tabla Informix `caea`.

Para que aparezca hace falta poner `ARCA_CAEA_REPLICAR_ANITA=true` y correr `php artisan config:clear`. Después el ícono se muestra solo en filas ya autorizadas, y solo si el usuario tiene el permiso `solicitar-arca-caea`.

¿Lo activo?
El botón no está en la pantalla porque en este entorno Crown la réplica a Anita está apagada.

En `.env` figura:

```text
ARCA_CAEA_REPLICAR_ANITA=false  # Crown: sin escritura Anita
```

Con ese valor, `ArcaCaeaAnitaSyncService` queda deshabilitado y la vista no dibuja ni el ícono verde de la grilla ni el botón **Grabar en Anita** del detalle. El pedido automático a ARCA sigue funcionando; lo que no corre es la escritura en la tabla Informix `caea`.

Para que aparezca hace falta poner `ARCA_CAEA_REPLICAR_ANITA=true` y correr `php artisan config:clear`. Después el ícono se muestra solo en filas ya autorizadas, y solo si el usuario tiene el permiso `solicitar-arca-caea`.

¿Lo activo?

## Usuario

si activalo

## Asistente

Lo activo en este entorno Crown: la réplica del CAEA a Anita queda encendida y el botón vuelve a mostrarse en los CAEA autorizados.
Lo activo en este entorno Crown: la réplica del CAEA a Anita queda encendida y el botón vuelve a mostrarse en los CAEA autorizados.

## Asistente

La réplica del CAEA a Anita quedó activa. `ARCA_CAEA_REPLICAR_ANITA` pasó a `true` y la configuración en runtime confirma ese valor.

Recargá **Ventas → CAEA ARCA**. En cada fila autorizada aparece el ícono verde de base de datos (**Grabar en Anita**); el mismo botón está en el detalle. Hace falta el permiso `solicitar-arca-caea`. Los pedidos nuevos que ARCA autorice también se graban solos en la tabla Informix `caea`.
La réplica del CAEA a Anita quedó activa. `ARCA_CAEA_REPLICAR_ANITA` pasó a `true` y la configuración en runtime confirma ese valor.

Recargá **Ventas → CAEA ARCA**. En cada fila autorizada aparece el ícono verde de base de datos (**Grabar en Anita**); el mismo botón está en el detalle. Hace falta el permiso `solicitar-arca-caea`. Los pedidos nuevos que ARCA autorice también se graban solos en la tabla Informix `caea`.
