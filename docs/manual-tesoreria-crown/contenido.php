<?php

/**
 * Manual de usuario — Tesorería Crown (solo instalación EMPRESA=CROWN).
 * Audiencia: operadores de tesorería, cajeros supervisados y gerencia de sala.
 * Datos de ejemplo de las capturas: sala CIP, jornadas octubre 2026.
 */
return [
    'titulo' => 'Manual de Usuario',
    'subtitulo' => 'Anita ERP — Tesorería Crown (CROWN · bajadas reales Chromium)',
    'version' => '1.3',
    'fecha' => null,
    'empresa' => null,
    'url_base' => null,
    'secciones' => [
        [
            'titulo' => '1. Introducción — qué es Tesorería Crown',
            'parrafos' => [
                'Tesorería Crown es el módulo de caja del casino Crown en anitaERP. Solo existe cuando la instalación corre con EMPRESA=CROWN (EntornoEmpresaSupport::esCrown). En AGG, Interforming, El Bierzo u otras empresas el menú no aparece y las rutas responden 404.',
                'Opera por sala de juego (orden de reportes): Cipolletti (CIP), Gral. Roca, Viedma, Las Grutas, Catriel, Choele Choel, Villa Regina y Cinco Saltos. El Faro está cerrado y no opera en tesorería ni Flash. Cada sala tiene su jornada, sus cajeros, sus cotizaciones, su numeración de habilitaciones/rendiciones/cierres y su bóveda. Una sala no mezcla comprobantes con otra.',
                'El menú raíz es Tesorería Crown. El día se vive en Jornada (escritorio con solapas Cajeros, Mesas, Bóveda, Slots, Bingo, Stock de fichas / Poder público y Anexos). El listado transversal de papeles está en Habilitaciones. La bandeja de hallazgos está en Avisos.',
                'Este manual (v1.3) describe el circuito completo: apertura, habilitaciones y rendiciones con PIN propio del cajero, no conforme / reversa, cierres fiscales ERP+Anita, cierres parciales con permiso de auditor, avisos de auditoría, mesas/propinas, slots, bóveda, Valores/conversión, anexos, Flash Crown y cierre de jornada. El cálculo de anexos y Flash es ERP-first; los imports Anita son carga histórica o fallback del día, no fuente viva exclusiva.',
            ],
            'items' => [
                'Gate: EMPRESA=CROWN + TesoreriaCrownAcceso::abortSiNoEsCrown() en controllers.',
                'No confundir EMPRESA (instalación Crown) con la tabla empresa / sala (CIP, Catriel, …).',
                'Los anexos se consultan y exportan; no graban Anita.',
                'Las capturas de pantalla son bajadas reales con Chromium desde la instalación Crown (CIP). Regenerar: php artisan manual:capturar-tesoreria-crown && php docs/manual-tesoreria-crown/generar.php.',
            ],
        ],
        [
            'titulo' => '2. Mapa de pantallas y menú',
            'parrafos' => [
                'Todas las pantallas operativas viven bajo caja/tesoreria-crown/…. Las tablas maestras (valores, depósitos, cotizaciones) están en el submenú Tablas.',
            ],
            'tabla' => [
                'caption' => 'Pantallas principales — Tesorería Crown',
                'headers' => ['Menú', 'Ruta', 'Para qué sirve'],
                'rows' => [
                    ['Jornada', 'caja/tesoreria-crown/jornada', 'Abrir/cerrar día por sala; escritorio del día; asignar cajas.'],
                    ['Habilitaciones', '…/movimiento', 'Listado y carga de habilitaciones (H) y rendiciones (F) con firmas.'],
                    ['Mesas', '…/mesa', 'Habilitación, reposición, crédito, estado final, drop y propina.'],
                    ['Bóveda', '…/boveda', 'INGR/OEGR/PDEP/HAB/REN; saldo de valores; arqueo de turno.'],
                    ['Kardex de valores', '…/kardex-valores', 'Movimientos confirmados bóveda + pre-depósito + caja chica.'],
                    ['Slots', '…/slot', 'Drop de máquinas y soft-count (contadora / manual).'],
                    ['Bingo', '…/bingo', 'Resultado bingo del día (cartones, premios, RSE).'],
                    ['Poder público', '…/poder', 'Stock de fichas y poder público slot del día/mes/acumulado.'],
                    ['Anexos', '…/anexo', 'I, II, resumen cajeros, III, VII, VIII — consulta y export.'],
                    ['Resultado online', '…/resultado-online', 'Coin-in/out, soft-count subtotal, vouchers (alimenta poder slot).'],
                    ['Cotizaciones', '…/cotizacion', 'Compra/venta por tipo de valor y jornada (USD/EUR del día).'],
                    ['Valores / Depósitos', '…/valor, …/deposito', 'Maestros FCOL/FVAL/ARS/USD/EUR, conversión del día y lugares BOVEDA/CAJA/MESA.'],
                    ['Cajeros', '…/cajero', 'Alta/consulta de cajeros por sala (número, legajo, PIN).'],
                    ['Avisos', '…/aviso', 'Bandeja de auditoría: diferencias, reversas, VIII vs variación, cotización, etc.'],
                    ['Flash Crown', 'caja/flash-crown', 'Resumen gerencial del día por sala (Anexo I/II + resultado online).'],
                    ['Flash — reportes', 'caja/flash-crown/reporte', 'Período, export Excel y suscripciones de envío.'],
                ],
            ],
            'parrafos2' => [
                'En el escritorio de una jornada abierta, la barra de sala muestra chips de sala y, a la derecha, Pdf / Excel / Csv compactos del listado activo (regla de export Crown).',
            ],
        ],
        [
            'titulo' => '3. Circuito diario de punta a punta',
            'captura_id' => 'circuito_diario',
            'parrafos' => [
                'El orden canónico del día evita diferencias entre cajero, bóveda, anexos y Anita. Respetarlo es obligatorio operativo.',
            ],
            'items' => [
                '1) Abrir la jornada de la sala y la fecha (una sola abierta por sala).',
                '2) La tarea del cajero (T/G/H/K/E/L) vive en el maestro de cajeros; no hay asignación de caja.',
                '3) Tesorería crea habilitación(es) eligiendo cajero: carga efectivo, pide firmas (tesorería entrega + cajero con PIN) → Conforme. Sale de bóveda (HAB). El día del cajero arranca con el primer movimiento.',
                '4) Durante el día: mesas (A/R/C/F/D), slots (drop/soft-count), bingo, movimientos de bóveda, resultado online.',
                '5) Cajero rinde: efectivo + fichas + medios; en G/H aplica cierres fiscales (ERP, Anita si falta) y/o PXSol. Pide firmas: cajero entrega + tesorería recibe → Conforme. Entra a bóveda (REN).',
                '6) Cerrar cada cajero con movimiento: control habilitación vs rendición, tolerancia, firmas. Estados terminales: conforme / revisada / cierre de auditoría (permiso auditor).',
                '7) Cuando todos los cajeros con comprobantes tienen cierre terminal → cerrar jornada.',
                '8) Consultar y exportar anexos I–VIII (caja final = Anexo VIII). Revisar bandeja de Avisos / mail de la mañana.',
            ],
            'tabla' => [
                'caption' => 'Estados que frenan o habilitan el cierre de jornada',
                'headers' => ['Situación', '¿Se puede cerrar la jornada?'],
                'rows' => [
                    ['Caja asignada sin ningún comprobante', 'Sí (no frena)'],
                    ['Caja con habilitación/rendición en borrador o pendiente de firma', 'No'],
                    ['Cierre en “a revisión” o “auditoría”', 'No'],
                    ['Cierre conforme / revisada / cierre de auditoría', 'Sí'],
                ],
            ],
        ],
        [
            'titulo' => '4. Apertura de jornada y asignación de cajas',
            'captura_id' => 'jornada_listado',
            'herramientas_grupos' => [
                ['titulo' => 'Listado de jornadas', 'clave' => 'jornada_listado', 'incluir_listado' => true],
                ['titulo' => 'Escritorio del día', 'clave' => 'jornada_escritorio'],
            ],
            'parrafos' => [
                'Pantalla: Tesorería Crown → Jornada. Una jornada = sala + fecha. Estados: Abierta (operar) / Cerrada (solo consulta).',
                'Al abrir, el escritorio muestra chips de sala, las asignaciones del día y el acceso a solapas. Ejemplo CIP 30/10/2026: jornada abierta con 34 asignaciones y 100 movimientos.',
            ],
            'tabla' => [
                'caption' => 'Tareas al asignar una caja',
                'headers' => ['Código', 'Tarea', 'Efecto en la rendición / cierre'],
                'rows' => [
                    ['T', 'Venta y pago', 'Caja de sala estándar; fórmula de cierre con cashless/vouchers/online/apuestas.'],
                    ['G', 'Gastronomía', 'Muestra X/Z/NC/fallo mozo; aplica cierres fiscales de ventas.'],
                    ['H', 'Hotel', 'Igual que G + cuenta corriente/transferencia; botón Traer de PXSol.'],
                    ['K', 'Kiosco', 'Misma lógica de cierre que venta/pago.'],
                    ['E', 'Empleados', 'Diferencia de cierre = 0.'],
                    ['L', 'Cashless', 'Misma lógica de cierre que venta/pago (énfasis cashless).'],
                ],
            ],
            'items' => [
                'La tarea se elige al asignar y no cambia.',
                'Quitar asignación solo si no hay comprobantes y la jornada está abierta.',
                'Turnos: Mañana / Tarde / Noche.',
                'Captura secundaria: escritorio con filas de cajero (habilitación, rendición y cierre conformes).',
            ],
            'parrafos2' => [
                'Desde el escritorio, la columna Cierre abre el cierre parcial de esa asignación. Los números de habilitación y rendición abren el comprobante.',
            ],
        ],
        [
            'titulo' => '5. Habilitaciones y circuito de firmas',
            'captura_id' => 'habilitacion_firma',
            'herramientas_grupos' => [
                ['titulo' => 'Comprobante', 'clave' => 'comprobante_firma'],
            ],
            'parrafos' => [
                'La habilitación (tipo H) es lo que tesorería entrega al cajero: solo efectivo ARS / USD / EUR. Las fichas no entran en la habilitación.',
                'Numeración: secuencia por sala, ocho dígitos, se asigna al crear (aunque esté en borrador). Ejemplo CIP: 02479244.',
                'Cada comprobante tiene dos lados y los firman dos personas distintas. No puede firmar el mismo usuario en ambos lados.',
            ],
            'tabla' => [
                'caption' => 'Firmas en habilitación',
                'headers' => ['Lado', 'Quién', 'Cómo firma'],
                'rows' => [
                    ['Entrega', 'Tesorería', 'Usuario y clave del sistema con permiso editar-tesoreria-crown-movimiento (o administrador).'],
                    ['Recibe', 'Cajero de la caja', 'Número de cajero + PIN propio (4–8 dígitos). Ver apartado 19.'],
                ],
            ],
            'items' => [
                'Borrador → editable. Pedir firmas congela cantidades y pasa a Pendiente de firma.',
                'Si nadie firmó: Modificar cantidades vuelve a borrador.',
                'Si ya hay una firma: Volver a borrador borra firmas.',
                'Al quedar Conforme: asiento automático HAB en bóveda (sale efectivo).',
                'No conforme: pide motivo, estado Auditoría; deja de sumar en el cierre. Hace falta otra rendición conforme.',
                'Revertir (caja abierta): crea comprobante inverso pendiente de las dos firmas; una sola vez; no se revierte una reversa.',
                'Mensaje de firmante genérico (no revela si falló usuario, clave o permiso). Tope de 5 intentos de PIN / usuario.',
                'Banner amarillo si el cajero aún no tiene PIN propio.',
            ],
            'parrafos2' => [
                'Soporte: TesoreriaCrownFirmaSupport (PIN propio, blanqueo, rate-limit, pasarAPendiente, aplicar, aplicarCajero, cerrarSiCorresponde). Al conforme también se sellan cotiz_dolar / cotiz_euro del día en el comprobante.',
            ],
        ],
        [
            'titulo' => '6. Rendiciones de cajeros con firma',
            'captura_id' => 'rendicion_firma',
            'herramientas_grupos' => [
                ['titulo' => 'Rendición', 'clave' => 'rendicion'],
            ],
            'parrafos' => [
                'La rendición (tipo F) es lo que el cajero devuelve. Exige al menos una habilitación conforme de la misma caja (no revertida). Puede haber varias habilitaciones y varias rendiciones en el día; el cierre suma todos los conformes.',
                'En la rendición se cargan: efectivo (ARS/USD/EUR), fichas, y los medios en el orden Anita: Z cashless (pagos/ventas), pagos parcial, apuestas, casino on-line (ventas/pagos), débito, crédito, recargo, cuenta corriente, transferencia, vouchers y QR.',
                'Recargo en sala (no gastronomía/hotel): crédito − (crédito / 1,05) desde el 8/11/2024 (antes / 1,10). No se tipea: se calcula.',
            ],
            'tabla' => [
                'caption' => 'Firmas en rendición (invertidas respecto de la habilitación)',
                'headers' => ['Lado', 'Quién', 'Cómo firma'],
                'rows' => [
                    ['Entrega', 'Cajero', 'Número + PIN'],
                    ['Recibe', 'Tesorería', 'Usuario + clave del sistema'],
                ],
            ],
            'items' => [
                'Al Conforme: asiento REN en bóveda (entra efectivo). Las fichas no pasan por bóveda.',
                'PDF del comprobante disponible al quedar conforme.',
                'Estados: Borrador → Pendiente de firma → Conforme | Auditoría (no conforme).',
                'Una rendición en auditoría no reemplaza a una conforme: hace falta otra para cerrar la caja.',
            ],
        ],
        [
            'titulo' => '7. Rendiciones externas (hotel, gastronomía, imports)',
            'captura_id' => 'rendicion_externa',
            'parrafos' => [
                'No existe una entidad “rendición externa”. En operación se llama así a todo lo que no nace del conteo físico del cajero de sala y entra a la rendición o a los anexos desde otro sistema.',
            ],
            'tabla' => [
                'caption' => 'Fuentes externas',
                'headers' => ['Fuente', 'Dónde se usa', 'Qué trae'],
                'rows' => [
                    ['Cierres fiscales ERP + Anita', 'Rendición tarea G u H', 'Pendientes del día: primero ERP (tesoreria_crown_cierre_fiscal / Cierre X); si falta alguno, Anita (cierrefiscal) sin repetir sucursal+X/Z. Aplicar a la rendición; carga manual si falta uno. No se modifica Anita.'],
                    ['PXSol (API / bridge)', 'Rendición hotel (H)', 'Facturado, crédito, IVA facturas e IVA NC entre 1ª habilitación y la consulta. Completa X/Z/NC. Config: TESORERIA_CROWN_PXSOL_*.'],
                    ['Imports Anita', 'Mesas, slots, bóveda, fichas, bingo, online, anexo VIII (arqueo A / propina / monextr)', 'Comandos tesoreria-crown:importar-*. Soft-count nativo no pisa el import.'],
                    ['Resultado online', 'Poder público slot / Anexo I', 'coin_in − coin_out − pago_manual; subtotal_slot (soft count).'],
                ],
            ],
            'parrafos2' => [
                'Un cajero no opera a medias en ERP y Anita: el cruce ERP+Anita es red de seguridad (transición o hueco del día). Cuando gastronomía vive completo en el ERP, casi todo sale del ERP; Anita solo completa lo que falta. Si PXSol no está configurado, el botón avisa. Los importes traídos se pueden corregir antes de pedir firmas.',
            ],
        ],
        [
            'titulo' => '8. Cierres parciales de caja',
            'captura_id' => 'cierre_caja',
            'herramientas_grupos' => [
                ['titulo' => 'Cierre de caja', 'clave' => 'cierre_caja'],
            ],
            'parrafos' => [
                'El cierre de caja no es una rendición: es el control habilitación vs rendición de esa asignación. Se abre desde la columna Cierre del escritorio.',
                'Condiciones para cerrar: ningún comprobante en borrador/pendiente (tampoco una reversa sin firmar); al menos una rendición conforme; cotización de venta > 0 para cada moneda extranjera usada.',
                'Todo el cierre se expresa en pesos. Solo entran comprobantes conformes. Una reversa conforme resta. Al cerrar firman tesorería (usuario+clave) y cajero (PIN; si es inicial, elige uno nuevo) y eligen Conforme o A revisión.',
            ],
            'tabla' => [
                'caption' => 'Fórmulas de diferencia (TesoreriaCrownCierreCalculo)',
                'headers' => ['Tarea', 'Diferencia'],
                'rows' => [
                    ['T / K / L', 'Efectivo rendido + fichas + (pagos Z − ventas cashless) + (pagos − ventas vouchers) + (pagos − ventas online) + (pagos − ventas apuestas) − efectivo habilitado'],
                    ['G / H', 'Efectivo rendido + fichas + transferencia + ctacte − habilitado − (X − NC − fallo mozo)'],
                    ['E', '0'],
                ],
            ],
            'items' => [
                'Negativo = faltante; positivo = excedente; cero = sin diferencia.',
                'Tolerancia: parámetro tesoreria_crown_tolerancia_auditoria (default 50.000). Si |diferencia| > tolerancia → nace en Auditoría aunque hayan elegido conforme.',
                'Estados terminales (dejan cerrar jornada): conforme, revisada, cierre de auditoría.',
                'A revisión / auditoría: caja ya no admite comprobantes nuevos, pero la jornada no cierra.',
                'Cerrar auditoría: permiso auditar-tesoreria-crown-caja (Enc-contaduría); firmante distinto de quien mandó a auditoría.',
                'Ajuste de auditoría (permiso ajustar-tesoreria-crown-caja): corrige importe sin reabrir; doble firma sistema.',
                'Reapertura (permiso reabrir-tesoreria-crown-caja): vuelve la caja a operativa para revertir o cargar; puede reabrir la jornada si no hay otra abierta en la sala.',
            ],
            'parrafos2' => [
                'Ejemplo captura CIP: habilitación 6.659.000 / rendición 6.659.000, cierre Conforme importado de Anita.',
            ],
        ],
        [
            'titulo' => '9. Mesas, drops y propinas',
            'captura_id' => 'mesas_propina',
            'parrafos' => [
                'La mesa es un depósito tipo MESA. Movimientos del día (TesoreriaCrownMesaSupport): A habilitación (+stock fichas), R reposición (+), C crédito (−), F estado final (conteo; propina en cabecera), D drop (conteo caja de drop; no mueve flotante de fichas).',
                'Tesorería confirma con usuario y clave. El borrador no entra al stock. Cada mesa apunta a un código de banca: se usa la última vigencia con fecha ≤ jornada; al confirmar se guarda esa banca.',
            ],
            'tabla' => [
                'caption' => 'Propinas — reglas cerradas (Anexo VIII / II / I)',
                'headers' => ['Dónde', 'Regla'],
                'rows' => [
                    ['Mesa tipo F confirmada', 'Solo monto de propina en el estado final: va al Anexo II y asiento informativo bóveda PROP / Mesas (neutro, sin billetaje).'],
                    ['Bóveda PROP', 'Tipo PROPINA con subtipos Híbrida y Mesas (sin billetaje, informativo/RRHH) y Pública (ingresa billetes). Mesas también nace automático desde el F.'],
                    ['RRHH', 'Tabla `tesoreria_crown_propina_dia` se mantiene para puntos de liquidación; ya no suma al Anexo VIII.'],
                    ['Anexo II pie', 'Propina del día = Σ F del día; % propina = caja_empleado / drop.'],
                    ['Anexo I', 'Sin propina empleados ni depósito propina (van al reporte propinas por casino).'],
                    ['Total caja final VIII', 'Sin propina acumulada. Las fichas / poder público se listan aparte y no entran.'],
                ],
            ],
            'parrafos2' => [
                'En la solapa Mesas del escritorio cada mesa muestra si está sin movimiento, en juego, con estado final o con borrador.',
            ],
        ],
        [
            'titulo' => '10. Slots, soft-count y CashBox',
            'captura_id' => 'slots_softcount',
            'parrafos' => [
                'Solapa Slots / menú Slots: drop de máquinas (tipo Drop = 4) y soft-count del stacker (Contadora = 1, Manual o Corrección en import Anita). Cada movimiento trae número Anita, máquina, total y billetaje.',
                'Al confirmar un soft-count / CashBox el sistema puede asentar INGR en bóveda (TesoreriaCrownSlotBovedaSupport) vinculando el slot a la bóveda. El soft-count alimenta el poder público slot vía subtotal_slot en resultado online.',
                'Fórmula poder slot del día: subtotal_slot − resultado_slots_online − voucher_mesa. Resultado slots = coin_in − coin_out − pago_manual. Voucher de mesa = Σ tesoreria_crown_mesa.voucher.',
            ],
        ],
        [
            'titulo' => '11. Bóveda y stocks de valores',
            'captura_id' => 'boveda_stock',
            'herramientas_grupos' => [
                ['titulo' => 'Bóveda', 'clave' => 'boveda'],
            ],
            'parrafos' => [
                'La bóveda es el lugar de tesorería (depósito tipo BOVEDA). Pre-depósito y caja chica usan la misma pantalla de movimientos. Tipos Anita típicos: INGR entra, OEGR sale, PDEP hacia pre-depósito, HAB/REN automáticos del cajero, y tipos “no mueve saldo”.',
                'Borrador no mueve saldo. Confirmar con usuario/clave. Anular con jornada abierta saca del saldo y conserva el número. USD y EUR quedan en su moneda en el saldo operativo (no se pasan a pesos ahí).',
                'Al conforme de habilitación sale efectivo de bóveda; al conforme de rendición entra. Las fichas no pasan por bóveda.',
            ],
            'tabla' => [
                'caption' => 'Capas de stock',
                'headers' => ['Ámbito', 'Qué guarda', 'Quién lo mueve'],
                'rows' => [
                    ['Bóveda / pre-depósito / caja chica', 'Cantidades por valor (ARS, USD, EUR, fichas en algunos tipos)', 'Movimientos confirmados + HAB/REN automáticos'],
                    ['Mesa', 'Flotante de fichas (A/R/C); F fija apertura del día siguiente; D no mueve flotante', 'Confirmación tesorería'],
                    ['Cajero', 'No tiene stock propio', 'Habilitación sale / rendición entra vía bóveda'],
                    ['Poder público', 'Conversión fichas + poder slot (tabla propia)', 'Generador del día'],
                ],
            ],
            'parrafos2' => [
                'Ejemplo captura CIP: APER apertura, HAB −49.750 y HAB −6.359.000 vinculados a habilitaciones de cajero.',
            ],
        ],
        [
            'titulo' => '12. Poder del público (fichas y slot)',
            'captura_id' => 'poder_publico',
            'parrafos' => [
                'Solapa Stock de fichas / Poder público. El ERP genera y persiste tesoreria_crown_poder_publico (día / mes / acumulado de fichas y de slot). El Anexo I lee esa tabla; ya no depende de que Anita grabe ppublico.',
                'Fichas (l-stockfichm): conversión inicial = conversión final de ayer + asignación inicial de hoy + otros movimientos del día. Conversión final = inicial + variación mesas + variación cajeros. Variación mesas = estado final − (habilitación + reposición − crédito) + canje. Variación cajeros = rendición pago + rendición venta − habilitación.',
                'Poder público día (importe) = Σ (conversión inicial − conversión final) × valor de cada ficha. Si la conversión final sube, el día da negativo (volvió ficha a la sala). Mes y acumulado arrancan del día anterior y suman este día; el 1 del mes el mes arranca en 0.',
                'Slot: ver sección 10. Reserva = cierres de ficha + asignaciones iniciales posteriores. Poder en cantidad = reserva − conversión final − mesas de banca − habilitación del cajero.',
            ],
        ],
        [
            'titulo' => '13. Cotizaciones, monedas y variables',
            'captura_id' => 'cotizacion',
            'parrafos' => [
                'Cotización Crown: por sala + tipo de valor + jornada. Regla de día para USD/EUR: última cotización con venta > 0 y jornada ≤ fecha. Una fila en cero no es cotización. Al firmar conforme se sellan cotiz_dolar / cotiz_euro en el comprobante. Sin cotización de una moneda usada no se cierra la caja.',
                'En Anexo VIII: face USD/EUR de bóveda × cotización del día → pesos que suman a la caja final.',
            ],
            'tabla' => [
                'caption' => 'Variables de entorno y parámetros',
                'headers' => ['Clave', 'Uso'],
                'rows' => [
                    ['EMPRESA=CROWN', 'Gate de instalación del módulo'],
                    ['TESORERIA_CROWN_PXSOL_TOKEN', 'Bearer API PXSol (preferido)'],
                    ['TESORERIA_CROWN_PXSOL_URL', 'Endpoint voucher list v2'],
                    ['TESORERIA_CROWN_PXSOL_COMANDO / _SSH / _REMOTE_*', 'Fallback bridge local/SSH'],
                    ['TESORERIA_CROWN_PXSOL_TIMEOUT', 'Timeout segundos (default 20)'],
                    ['tesoreria_crown_tolerancia_auditoria', 'Parámetro sistema; default 50.000'],
                    ['tesoreria_crown_empresa_id', 'Empresa sueldos/legajos bóveda'],
                    ['tesoreria_crown_avisos_mails', 'Destinatarios del resumen diario de avisos (uno por línea)'],
                    ['tesoreria_crown_avisos_hora', 'Hora de la corrida nocturna (default 07:30)'],
                    ['tesoreria_crown_avisos_fallos_*', 'Cantidad / días / mínimo $ para fallos repetidos'],
                    ['tesoreria_crown_avisos_jornada_abierta_dias', 'Días abiertos antes de avisar (default 2)'],
                    ['AUDIT_CONSOLE', 'Si true, OwenIt audita también comandos Artisan'],
                    ['CAJERO_CROWN_ANITA_*', 'Import de cajeros desde Anita'],
                ],
            ],
        ],
        [
            'titulo' => '14. Kardex y ledger de valores',
            'captura_id' => 'kardex_valores',
            'parrafos' => [
                'El ledger unificado (TesoreriaCrownValorLedgerSupport) concentra el stock físico en movimientos de bóveda (y lugares equivalentes). El Kardex de valores lista movimientos confirmados de bóveda, pre-depósito y caja chica con workbench (QBE, vistas, columnas).',
                'Maestros: tipos de valor (FCOL, FVAL, ARS, USD, EUR) y valores por denominación; tipos de depósito (BOVEDA, CAJA, MESA, PREDEPOSITO, CAJA_CHICA) y depósitos por sala. La conversión de fichas del día se abre desde Valores (ya no hay ABM Fichas aparte); el Poder público enlaza a esa conversión.',
            ],
        ],
        [
            'titulo' => '15. Anexos — fórmulas y lectura',
            'captura_id' => 'anexo1',
            'parrafos' => [
                'Acceso: escritorio de jornada → Anexos (permiso listar-tesoreria-crown-anexo). Pestañas: I, II, resumen de cajeros, III, VII, VIII. Export Pdf/Excel/Csv en la barra de sala. No graban Anita.',
            ],
            'tabla' => [
                'caption' => 'Anexo I (l-anexo1 / l-anexo1new) — esquema',
                'headers' => ['Bloque', 'Fórmula / contenido'],
                'rows' => [
                    ['Recaudación juego', 'Sala (mesas Anexo II) + ruleta + slots (Anexo II máquinas)'],
                    ['+ Contado', 'Gastro al contado + Hotel al contado'],
                    ['+ Otros ingresos', 'Poder público fichas + poder slot + torneos + bingo + online + voucher + apuestas deportivas + bancarios + excedente + recargo (sin propina empleados)'],
                    ['Egresos', 'Compras/gastos (VI conceptual) + fallos/anticipos (VII) + depósitos efectivo + tarjetas + QR + depósitos USD/EUR + ctacte/transf hotel + otros (sin depósito propina)'],
                    ['Variación', 'Ingresos − egresos'],
                    ['Caja inicial', 'Anexo VIII (`caja_final_viii`) de la jornada anterior ERP'],
                    ['Caja final por variación', 'Inicial + variación'],
                    ['Caja final VIII', 'Total Anexo VIII del día (ítem 130)'],
                    ['Diferencia', 'VIII − final por variación'],
                ],
            ],
            'tabla2' => [
                'caption' => 'Anexo II, resumen cajeros, III, VII, VIII',
                'headers' => ['Anexo', 'Fórmula clave'],
                'rows' => [
                    ['II Mesas', 'Recomp. banca = estado_final + drop − habilitación + caja_empleado − pago_manual. Resultado = recomp − reposición + crédito − 2×caja_empleado. % utilidad = resultado/drop; % propina = caja_empleado/drop.'],
                    ['II Máquinas', 'Resultado = ventas cashless (K/L/T) + validadoras (slots ≠ DROP) − (pagos cashless + pagos créditos bóveda tipo 39)'],
                    ['II Gastro/Hotel', 'Cierres fiscales ERP (venta / cierre Z)'],
                    ['Resumen / III', 'Por caja conforme: egresos = efectivo habilitado; ingresos = efectivo + crédito − recargo + débito + QR (+ fallo/ctacte en G/H). Resultado = (ingresos−egresos) + cashless neto + fichas + online + voucher neto + apuestas netas. Orden grupos: E→G→H→K→L→T'],
                    ['VII', 'Fallo = |diferencia| si < 0 + fallo mozo; excedente = diferencia si > 0. Anticipos de sueldo: fuera de alcance (columna siempre 0).'],
                    ['VIII caja final', 'stock_dia/arqueo ERP + slots recuento (≠ DROP sin vínculo bóveda) + drops mesa + resumen final ERP + propina_dia/mesas F + monextr ERP. Fichas/poder público se listan aparte y no entran al total.'],
                ],
            ],
            'parrafos2' => [
                'Diferencia tesorería en VIII: ítem del resumen final (tipos 35 entra / 36 sale, grupo 6); es el saldo acumulado de esos movimientos, no un porcentaje aparte. Signos de resumen: 21/22 pre-depósito, 25/30 fondo, 31/32 caja chica hotel, 35/36 diferencia tesorería, 6/40 caja chica adm., 99/98 cancela dif. final. Dif. final de bóveda = total VIII − saldo final Anexo I (caja por variación). El concepto «diferencia a verificar» (33/34) ya no entra al VIII.',
                'Anexo II selector Mesas/Máquinas/Gastronomía/Todo: cada botón pide su permiso listar-tesoreria-crown-anexo2-*; Sistemas los asigna en Permiso-Rol. El MTD usa solo días con jornada ERP (sin Anita en huecos).',
                'Anexo VII — anticipos: fuera de alcance operativo. No hay maestro/proceso de anticipos de sueldo en Tesorería Crown; la columna se deja en cero a propósito hasta que exista fuente ERP propia.',
            ],
        ],
        [
            'titulo' => '16. Cierre de jornada y buenas prácticas',
            'parrafos' => [
                'Cuando cada caja con movimiento tiene cierre terminal, se cierra la jornada. Queda en consulta: no se habilita, no se rinde, no se edita, no se pide firma y no se revierte sin reapertura.',
                'Si hace falta corregir un importe sin anular papeles: Ajuste de caja. Si hace falta revertir o cargar de nuevo: Reabrir caja. No se reemplazan entre sí.',
            ],
            'items' => [
                'Cargar cotización USD/EUR del día (venta > 0) antes de cerrar cajas con moneda extranjera.',
                'No dejar comprobantes pendientes de firma al final del turno.',
                'Confirmar soft-count / drops / estados finales de mesa antes de leer anexos.',
                'Exportar anexos del día para archivo; no dependen de grabar Anita.',
                'Varias habilitaciones/rendiciones en el mismo turno: cada una con su número y sus dos firmas; el cierre las suma.',
            ],
        ],
        [
            'titulo' => '17. Casos de uso rápidos',
            'parrafos' => [
                'Día de una caja de venta: abrir jornada → asignar T → habilitar y firmar (PIN propio) → operar → rendir y firmar → cerrar caja conforme → repetir → cerrar jornada → anexos.',
                'Error antes de firmar: corregir en borrador; si ya pidieron firmas sin firmar, Modificar cantidades; si ya firmó uno, Volver a borrador.',
                'No conforme del papel: motivo → auditoría; otra rendición conforme para poder cerrar.',
                'PIN olvidado: en pendiente, “El cajero se olvidó el PIN” → tesorería firma → blanqueo → en la próxima firma elige PIN nuevo.',
                'Diferencia chica a revisar: cerrar A revisión; después Revisada o Auditoría.',
                'Diferencia > tolerancia: nace en auditoría; otra persona con auditar-tesoreria-crown-caja firma Cerrar auditoría.',
                'Hotel/gastro: tarea G/H; en rendición tildar cierres ERP (o Anita si faltan) y/o Traer de PXSol; el cierre suma ctacte/transferencia en H.',
                'Avisos: a la mañana Enc-contaduría revisa la bandeja y el mail de resumen; marca Revisado o Descartado.',
            ],
        ],
        [
            'titulo' => '18. Flash Crown',
            'captura_id' => 'flash_crown',
            'parrafos' => [
                'Pantalla: Tesorería Crown → Flash (`caja/flash-crown`, permiso listar-flash-crown). Un registro por sala y fecha de jornada. No confundir con el Flash AGG (`caja/flash`): ese es otro producto.',
                'El cálculo (`FlashCrownCalculoSupport`) arma el día desde la jornada Crown: Anexo II (mesas por juego, gastro/hotel), Anexo I (clientes, totales de apoyo) y `tesoreria_crown_resultado_online` (slots: coin-in/out, soft count, cashless). Cron: `flash-crown:calcular-diario`.',
                'Desde el listado se puede editar, recalcular, validar o quitar validación. El reporte de período (`caja/flash-crown/reporte`) exporta Excel y permite suscripciones de envío.',
            ],
            'tabla' => [
                'caption' => 'Bloques principales del Flash Crown',
                'headers' => ['Bloque', 'Origen'],
                'rows' => [
                    ['Mesas (soft, resultados por juego, caja empleado)', 'Anexo II mesas / jornada'],
                    ['Slots (slot_d/r, soft/hard, coin-in/out, cashless)', 'Resultado online + Anexo II máquinas'],
                    ['AyB / Hotel', 'Anexo II gastronomía (cajas G/H)'],
                    ['Clientes / asistencia', 'Campo de jornada (Anexo I)'],
                    ['Torneos / inscripción / premio', 'Datos de jornada / anexos si hay'],
                ],
            ],
            'items' => [
                'Recalcular: vuelve a leer anexos y resultado online de esa fecha/sala.',
                'Validar: congela el flash del día para distribución/reportes.',
                'Reportes: perfiles de vista, período relativo o fijo, suscripciones.',
                'Si faltan datos ERP (sin jornada, sin resultado online), el flash avisa y deja ceros; no consulta Anita en vivo.',
            ],
            'parrafos2' => [
                'Captura: php artisan manual:capturar-tesoreria-crown --solo=flash-crown && php artisan config:clear && php docs/manual-tesoreria-crown/generar.php',
            ],
        ],
        [
            'titulo' => '19. PIN del cajero',
            'herramientas_grupos' => [
                ['titulo' => 'PIN', 'clave' => 'pin_cajero'],
            ],
            'parrafos' => [
                'Cada cajero firma con un PIN propio (4 a 8 dígitos). No puede ser el código ni el legajo, ni series obvias (1111, 1234, 4321…). El PIN inicial de migración (código/legajo) o un PIN blanqueado no sirve para firmar: en esa misma pantalla el cajero elige uno nuevo y lo repite.',
                'Si se olvidó el PIN, tesorería en el comprobante pendiente abre “El cajero se olvidó el PIN”, firma con usuario y clave, y blanquea. En la próxima firma el cajero elige uno nuevo delante de tesorería. El blanqueo no genera aviso en la bandeja.',
                'El movimiento y el cierre muestran un banner amarillo cuando el cajero asignado todavía no tiene PIN propio. Hay tope de 5 intentos fallidos de PIN (y de usuario/clave de firmante); después hay que esperar.',
            ],
            'items' => [
                'Soporte: TesoreriaCrownFirmaSupport::pinEsInicial, autenticarCajero, blanquearPin.',
                'Ruta de blanqueo: blanquear_pin_tesoreria_crown_movimiento.',
                'No confundir el PIN del cajero con la clave de usuario de tesorería.',
            ],
        ],
        [
            'titulo' => '20. Bandeja de avisos de auditoría',
            'herramientas_grupos' => [
                ['titulo' => 'Avisos', 'clave' => 'avisos'],
            ],
            'parrafos' => [
                'Menú Tesorería Crown → Avisos (caja/tesoreria-crown/aviso). No reemplaza el cierre de caja: concentra hallazgos del día/mes para Enc-contaduría y auditoría.',
                'Estados: Abierto → Revisado o Descartado (con nota). Superado lo marca el sistema cuando la condición deja de cumplirse. Severidades: Alta / Media / Baja. Cada fila enlaza a la jornada o al comprobante (solapa sin menú).',
                'Corrida: php artisan tesoreria-crown:avisos [--desde=] [--hasta=] [--anita] [--ejecutar]; hooks al cerrar caja / firmar comprobante; schedule diario a la hora de tesoreria_crown_avisos_hora (default 07:30) con mail si hay destinatarios.',
            ],
            'tabla' => [
                'caption' => 'Reglas (TesoreriaCrownAvisoSupport::REGLAS)',
                'headers' => ['Regla', 'Sev.', 'Qué mira'],
                'rows' => [
                    ['Diferencia de cierre sobre la tolerancia', 'Alta', '|diferencia| > tolerancia'],
                    ['Comprobante no conforme sin resolver', 'Alta', 'Papel en auditoría abierto'],
                    ['Rendición sin habilitación conforme', 'Alta', 'F sin H conforme'],
                    ['Comprobante modificado después del cierre', 'Alta', 'Edición post-cierre'],
                    ['Misma persona firma las dos partes', 'Alta', 'Entrega = recibe'],
                    ['Reversa / Caja reabierta / Cajero en dos cajas', 'Media', 'Operación sensible del día'],
                    ['Gastro/hotel con X sin cierre de ventas', 'Media', 'X sin fila de cierre'],
                    ['Fallos repetidos / Jornada abierta N días / Diff Anita', 'Media', 'Patrones y control'],
                    ['Anexo VIII ≠ variación del Anexo I', 'Media', 'caja_final_viii vs final por variación'],
                    ['Cotización de otra fecha / Recálculo pendiente', 'Baja', 'ME / invalidación hacia adelante'],
                ],
            ],
            'items' => [
                'Permisos: listar-tesoreria-crown-aviso (ver) y auditar-tesoreria-crown-caja (resolver, firmar auditoría, reabrir en A). Enc-contaduría tiene ambos.',
                'Parámetros en Configuración → Tesorería Crown: mails, hora, fallos cantidad/días/mínimo, días jornada abierta.',
                'Mail: TesoreriaCrownAvisosResumen con abiertos del día y pendientes.',
            ],
        ],
    ],
];
