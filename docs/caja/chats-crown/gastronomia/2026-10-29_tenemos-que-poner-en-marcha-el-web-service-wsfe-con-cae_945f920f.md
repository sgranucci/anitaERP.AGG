# tenemos que poner en marcha el web service wsfe con cae y caea igual que en AGG. Los certificados estan en /home/sergio/

- **Fecha:** 2026-10-29 12:38
- **Chat id:** `945f920f-825b-4524-9916-b0d9ef0b27fe`
- **Módulos:** gastronomia
- **Origen local:** `agent-transcripts/945f920f-825b-4524-9916-b0d9ef0b27fe`

> Export redactado para viaje Crown. Sin tool dumps completos; solo diálogo útil.

## Usuario

tenemos que poner en marcha el web service wsfe con cae y caea igual que en AGG. Los certificados estan en /home/sergio/tmp de este servidor (cert.crt y privada.key). Fijate si estan correctas las importaciones de los puntos de venta desde acncadmin

## Asistente

Voy a revisar cómo está armado el WSFE con CAE y CAEA en AGG, y contrastarlo con los certificados y los puntos de venta importados desde acncadmin en este servidor.
Voy a revisar cómo está armado el WSFE con CAE y CAEA en AGG, y contrastarlo con los certificados y los puntos de venta importados desde acncadmin en este servidor.

## Asistente

Los certificados de Crown ya están instalados y coinciden con los de `/home/sergio/tmp`. Ahora reviso si el WSFE responde y cómo quedaron los puntos de venta importados desde acncadmin.
Los certificados de Crown ya están instalados y coinciden con los de `/home/sergio/tmp`. Ahora reviso si el WSFE responde y cómo quedaron los puntos de venta importados desde acncadmin.

## Asistente

En el ERP hay 152 puntos de venta, todos con webservice `wsfev1`. Voy a contrastarlos con la tabla `sucursal` de acncadmin y a probar si el WSFE responde con el certificado de Crown.
En el ERP hay 152 puntos de venta, todos con webservice `wsfev1`. Voy a contrastarlos con la tabla `sucursal` de acncadmin y a probar si el WSFE responde con el certificado de Crown.

## Asistente

El WSFE de Crown ya está en marcha, con el mismo certificado que está en `/home/sergio/tmp`, y la importación desde acncadmin coincide con Anita. Lo que no coincide es Anita contra ARCA en varios puntos de venta electrónicos.

**Certificado.** `cert.crt` y `privada.key` son el par de Emprendimientos Crown (CUIT 30-70822285-9, CN «webservice adm central»), vigente del 16/09/2026 al 15/09/2028, y la clave corresponde al certificado. Es el mismo par que ya está en `storage/app/arca/wsfe/certs/crown/` desde el 17/10. No hace falta volver a copiarlo.

**WSFE en vivo.** `FEDummy` respondió AppServer, DbServer y AuthServer en OK. El monitor de las 12:25 también está OK (401 chequeos seguidos, failover CAEA apagado) y consulta el punto 00082, factura electrónica de administración central. Los CAEA de la empresa 1 ya están pedidos:

| Período | Orden | CAEA | Vigencia | Tope de informe |
|---|---|---|---|---|
| 202610 | 2 | 86417327975276 | 16/10 al 31/10 | 05/11 |
| 202611 | 1 | 86439658740058 | 01/11 al 15/11 | 20/11 |

La gastronomía de Cipolletti apunta a puntos que ARCA tiene como CAE (00090, 00098, 00099, 00100, 00101) y al CAEA 00117. Esos últimos autorizados responden: el 00090 en 65128, el 00098 en 316497 y el 00117 en 13658.

**Puntos de venta desde acncadmin.** Anita devolvió 153 sucursales. El ERP tiene 152: se omitió la sucursal 0, que no es un punto de venta. Código, nombre, modo y path de AFIP salen del mapeo de Crown: fiscal `E` queda como electrónica CAE (modo C) y el nombre «CAEA …» queda como contingencia (modo A) aunque Anita los marque fiscal `F`. Los 8 CAEA 00117–00124 y los 29 CAE de gastronomía y administración coinciden con ARCA.

Anita no refleja el padrón de ARCA en estos casos. Están en ARCA como CAE y en el ERP quedaron manuales porque Anita los tiene en fiscal `F`, `N` o vacío:

| Punto | Nombre en Anita / ERP | Fiscal Anita | ARCA |
|---|---|---|---|
| 00078 | HOTEL CIPOLLETTI FACTURA T | F | CAE |
| 00079 | HOTEL GRAL.ROCA FACTURA T | F | CAE |
| 00080 | Sucursal 80 Factura T Las Grutas | F | CAE |
| 00091 | Sucursal 91 | vacío | CAE |
| 00125 | Suc 125 | N | CAE |
| 00127 | HOTEL VIEDMA | F | CAE |
| 00130 | Hotel Gral Roca | F | CAE |
| 00131 | Hotel Cipolletti | F | CAE |
| 00132 | PV 00132 | vacío | CAE |

En ARCA y ausentes en acncadmin y en el ERP: **00094** y **00095** (CAE), **00133** (CAEA) y **00134** (CAE).

El CAEA 00121 (El Faro) en Anita tiene CUIT 30-70885999-2, que no es el de Crown. ARCA igual lo tiene habilitado bajo el CUIT 30-70822285-9, y el ERP lo dejó en la empresa 1.

Todos los puntos heredaron el domicilio de la empresa: Cipolletti, Río Negro. Anita no trae calle ni código postal en `sucursal`.

Si querés, alineo esos 9 puntos al modo CAE de ARCA y doy de alta 00094, 00095, 00133 y 00134. Para los cuatro nuevos hace falta el nombre de cada uno, porque ARCA no lo informa.
El WSFE de Crown ya está en marcha, con el mismo certificado que está en `/home/sergio/tmp`, y la importación desde acncadmin coincide con Anita. Lo que no coincide es Anita contra ARCA en varios puntos de venta electrónicos.

**Certificado.** `cert.crt` y `privada.key` son el par de Emprendimientos Crown (CUIT 30-70822285-9, CN «webservice adm central»), vigente del 16/09/2026 al 15/09/2028, y la clave corresponde al certificado. Es el mismo par que ya está en `storage/app/arca/wsfe/certs/crown/` desde el 17/10. No hace falta volver a copiarlo.

**WSFE en vivo.** `FEDummy` respondió AppServer, DbServer y AuthServer en OK. El monitor de las 12:25 también está OK (401 chequeos seguidos, failover CAEA apagado) y consulta el punto 00082, factura electrónica de administración central. Los CAEA de la empresa 1 ya están pedidos:

| Período | Orden | CAEA | Vigencia | Tope de informe |
|---|---|---|---|---|
| 202610 | 2 | 86417327975276 | 16/10 al 31/10 | 05/11 |
| 202611 | 1 | 86439658740058 | 01/11 al 15/11 | 20/11 |

La gastronomía de Cipolletti apunta a puntos que ARCA tiene como CAE (00090, 00098, 00099, 00100, 00101) y al CAEA 00117. Esos últimos autorizados responden: el 00090 en 65128, el 00098 en 316497 y el 00117 en 13658.

**Puntos de venta desde acncadmin.** Anita devolvió 153 sucursales. El ERP tiene 152: se omitió la sucursal 0, que no es un punto de venta. Código, nombre, modo y path de AFIP salen del mapeo de Crown: fiscal `E` queda como electrónica CAE (modo C) y el nombre «CAEA …» queda como contingencia (modo A) aunque Anita los marque fiscal `F`. Los 8 CAEA 00117–00124 y los 29 CAE de gastronomía y administración coinciden con ARCA.

Anita no refleja el padrón de ARCA en estos casos. Están en ARCA como CAE y en el ERP quedaron manuales porque Anita los tiene en fiscal `F`, `N` o vacío:

| Punto | Nombre en Anita / ERP | Fiscal Anita | ARCA |
|---|---|---|---|
| 00078 | HOTEL CIPOLLETTI FACTURA T | F | CAE |
| 00079 | HOTEL GRAL.ROCA FACTURA T | F | CAE |
| 00080 | Sucursal 80 Factura T Las Grutas | F | CAE |
| 00091 | Sucursal 91 | vacío | CAE |
| 00125 | Suc 125 | N | CAE |
| 00127 | HOTEL VIEDMA | F | CAE |
| 00130 | Hotel Gral Roca | F | CAE |
| 00131 | Hotel Cipolletti | F | CAE |
| 00132 | PV 00132 | vacío | CAE |

En ARCA y ausentes en acncadmin y en el ERP: **00094** y **00095** (CAE), **00133** (CAEA) y **00134** (CAE).

El CAEA 00121 (El Faro) en Anita tiene CUIT 30-70885999-2, que no es el de Crown. ARCA igual lo tiene habilitado bajo el CUIT 30-70822285-9, y el ERP lo dejó en la empresa 1.

Todos los puntos heredaron el domicilio de la empresa: Cipolletti, Río Negro. Anita no trae calle ni código postal en `sucursal`.

Si querés, alineo esos 9 puntos al modo CAE de ARCA y doy de alta 00094, 00095, 00133 y 00134. Para los cuatro nuevos hace falta el nombre de cada uno, porque ARCA no lo informa.

## Usuario

actualiza los puntos de venta a como estan en ARCA, alinealos. Agrega los nombres de los que faltan. y prende el failover de caea

## Asistente

Voy a alinear los puntos de venta con el padrón de ARCA, completar los que faltan y activar el failover de CAEA por el estado en runtime, no por el `.env`.
Voy a alinear los puntos de venta con el padrón de ARCA, completar los que faltan y activar el failover de CAEA por el estado en runtime, no por el `.env`.

## Asistente

ARCA no trae razón social, así que a los cuatro puntos que faltan les pongo un nombre con el número y el modo. El alineado queda en el ERP y el sync desde Anita no va a volver a bajar a manual un punto que ya esté en CAE o CAEA. El failover lo dejo prendido por configuración, porque el monitor lo apaga solo si ARCA responde bien.
ARCA no trae razón social, así que a los cuatro puntos que faltan les pongo un nombre con el número y el modo. El alineado queda en el ERP y el sync desde Anita no va a volver a bajar a manual un punto que ya esté en CAE o CAEA. El failover lo dejo prendido por configuración, porque el monitor lo apaga solo si ARCA responde bien.

## Asistente

Los puntos de venta electrónicos quedaron alineados con ARCA y el POS quedó emitiendo en CAEA.

**Modo.** Nueve puntos que Anita tenía como manuales y ARCA tiene como CAE pasaron a modo electrónico: 00078, 00079, 00080, 00091, 00125, 00127, 00130, 00131 y 00132. El 00078 ya había quedado en CAE en el primer intento.

**Altas.** ARCA no informa el nombre comercial, así que los cuatro que no estaban en acncadmin entraron con el número y el modo:

| Código | Nombre | Modo |
|---|---|---|
| 00094 | Factura Electronica 00094 | CAE |
| 00095 | Factura Electronica 00095 | CAE |
| 00133 | CAEA 00133 | CAEA |
| 00134 | Factura Electronica 00134 | CAE |

Quedaron en la empresa Crown, Cipolletti, activos y con webservice `wsfev1`. El cruce contra el padrón de ARCA cierra: 41 puntos CAE y 9 CAEA. Si el nombre no es el del local, se cambia en el ABM de puntos de venta.

Una sincronización desde Anita ya no vuelve a dejar en manual un punto que esté en CAE o CAEA. Anita sigue marcando varios de esos puntos como fiscal `F` o `N`.

**CAEA.** `ARCA_WSFE_FORZAR_MODO_CAEA=true`. El facturador no pide CAE en línea y usa el punto CAEA de cada terminal. El archivo de failover también quedó activo. El monitor lo apaga cuando ARCA responde bien; esa variable no la toca el cron. Para volver a CAE en línea hay que ponerla en `false`.
Los puntos de venta electrónicos quedaron alineados con ARCA y el POS quedó emitiendo en CAEA.

**Modo.** Nueve puntos que Anita tenía como manuales y ARCA tiene como CAE pasaron a modo electrónico: 00078, 00079, 00080, 00091, 00125, 00127, 00130, 00131 y 00132. El 00078 ya había quedado en CAE en el primer intento.

**Altas.** ARCA no informa el nombre comercial, así que los cuatro que no estaban en acncadmin entraron con el número y el modo:

| Código | Nombre | Modo |
|---|---|---|
| 00094 | Factura Electronica 00094 | CAE |
| 00095 | Factura Electronica 00095 | CAE |
| 00133 | CAEA 00133 | CAEA |
| 00134 | Factura Electronica 00134 | CAE |

Quedaron en la empresa Crown, Cipolletti, activos y con webservice `wsfev1`. El cruce contra el padrón de ARCA cierra: 41 puntos CAE y 9 CAEA. Si el nombre no es el del local, se cambia en el ABM de puntos de venta.

Una sincronización desde Anita ya no vuelve a dejar en manual un punto que esté en CAE o CAEA. Anita sigue marcando varios de esos puntos como fiscal `F` o `N`.

**CAEA.** `ARCA_WSFE_FORZAR_MODO_CAEA=true`. El facturador no pide CAE en línea y usa el punto CAEA de cada terminal. El archivo de failover también quedó activo. El monitor lo apaga cuando ARCA responde bien; esa variable no la toca el cron. Para volver a CAE en línea hay que ponerla en `false`.
