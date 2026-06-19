<?php

/**
 * Contenido del manual de usuario — Anita ERP / Módulo Gastronomía.
 */
return [
    'titulo' => 'Manual de Usuario',
    'subtitulo' => 'Anita ERP — Módulo de Gastronomía',
    'version' => '1.1',
    'fecha' => null,
    'empresa' => null,
    'url_base' => null,
    'secciones' => [
        [
            'titulo' => '1. Introducción',
            'parrafos' => [
                'El módulo de Gastronomía de Anita ERP gestiona la operación de salón: apertura de jornada, turnos por terminal, cuentas de mesa, facturación con cobranza inmediata, cierres parciales y totales de caja, y herramientas de consulta para gerencia y auditoría.',
                'Este manual describe el flujo mínimo para operar un día completo (jornada → habilitar turno → facturar → cierre parcial → cierre total → cierre de jornada), las restricciones que el sistema aplica al facturar, y las pantallas de consulta disponibles.',
                'La configuración previa (mesas, mozos, puntos de venta gastronómicos, turnos maestros, descuentos, áreas de comanda, tótems Waitry) se administra en Ventas → Gastronomía → Tablas. Sin esa configuración, el proceso de facturación no podrá resolver la terminal ni los datos operativos.',
                'En el despliegue AGG están activas las integraciones Waitry (kioscos/tótems, conciliación Informe Z al cierre de jornada) y Wigos (canjes de premios por cupón y canje diario de fidelidad por tarjeta). Ver capítulo dedicado.',
                'Los canjes para clientes VIP (equipo Marketing) tienen manual propio: menú Canjes o enlace desde el índice de ayuda (Manual Canjes Marketing).',
            ],
        ],
        [
            'titulo' => '2. Conceptos clave',
            'captura_id' => 'flujo',
            'parrafos' => [
                'Antes de operar conviene distinguir tres niveles temporales que el sistema maneja de forma independiente:',
            ],
            'tabla' => [
                'caption' => 'Niveles operativos',
                'headers' => ['Concepto', 'Alcance', 'Descripción'],
                'rows' => [
                    ['Jornada', 'Empresa', 'Día de turno abierto para toda la empresa. Una sola jornada abierta por empresa. Define venta.fechajornada en los comprobantes.'],
                    ['Fecha de factura', 'Comprobante', 'Siempre el día calendario real (hoy). Puede diferir de la fecha de jornada en turnos que cruzan medianoche.'],
                    ['Turno operativo', 'Terminal (PC)', 'Ventana de caja habilitada en un equipo concreto. Cada PC puede habilitar, hacer parciales y cerrar su turno dentro de la jornada abierta.'],
                    ['Cuenta gastronómica', 'Mesa o cuenta libre', 'Consumos cargados antes de emitir la factura. Estados: abierta, cerrada (sin facturar), facturada.'],
                ],
            ],
            'items' => [
                'Identificador de PC: cada terminal se reconoce por IP del cliente o por hostname/configuración fija (config/gastronomia.php). Debe coincidir con configuracion_puntoventa_gastronomia.identificador_pc.',
                'Modo caja directo: si GASTRONOMIA_REQUIERE_HABILITACION_TURNO=false, no hay habilitación ni cierre de turno; solo se exige jornada abierta (si está configurada).',
            ],
        ],
        [
            'titulo' => '3. Flujo mínimo de trabajo diario',
            'captura_id' => 'flujo',
            'parrafos' => [
                'El circuito operativo estándar (modo con habilitación de turno) sigue este orden. Omitir un paso obligatorio bloqueará la facturación o el cierre con un mensaje explícito en pantalla.',
            ],
            'items' => [
                'Abrir jornada (supervisor/encargado) — Ventas → Gastronomía → Jornada. Indique la fecha de jornada (día de turno) y confirme. Sin jornada abierta las terminales no emiten comprobantes.',
                'Habilitar turno en cada PC que facture — Ventas → Gastronomía → Habilitación de turno. Elija empresa, turno maestro (mañana/tarde/noche) y monto inicial de caja si aplica.',
                'Facturar cuentas — Ventas → Gastronomía → Proceso de facturación. Abra mesas o cuentas libres, cargue consumos, cobre y emita (F5 efectivo / otros medios / F8 con descuento).',
                'Cierre parcial (opcional, durante el turno) — Desde Habilitación de turno o desde el POS. Genera comprobante de arqueo intermedio sin cerrar el turno.',
                'Cierre total de turno — En Habilitación de turno, pestaña Cierre definitivo. Obligatorio en el último turno del día si quedan cuentas abiertas con consumos sin facturar (bloquea hasta facturar o saneamiento).',
                'Cerrar jornada — Ventas → Gastronomía → Jornada. Requiere todos los turnos habilitados cerrados y sin cuentas abiertas con ítems. Si hay tótems Waitry, concilie Informe Z antes de confirmar.',
            ],
            'tabla' => [
                'caption' => 'Rutas del flujo operativo',
                'headers' => ['Paso', 'Ruta', 'Permiso principal'],
                'rows' => [
                    ['Jornada', 'ventas/gastronomia/jornada', 'gestionar-jornada-gastronomia'],
                    ['Habilitación / cierres turno', 'ventas/gastronomia/habilitacion-turno', 'gestionar-habilitacion-turno-gastronomia'],
                    ['Facturación POS', 'ventas/gastronomia/proceso-facturacion', 'facturar-gastronomia'],
                    ['Saneamiento (excepcional)', 'ventas/gastronomia/saneamiento-turno', 'ejecutar-saneamiento-turno-gastronomia'],
                ],
            ],
        ],
        [
            'titulo' => '4. Apertura y cierre de jornada',
            'herramientas_clave' => 'jornada',
            'parrafos' => [
                'La pantalla Jornada gastronomía centraliza el ciclo de la empresa. La fecha de jornada es la que se graba en venta.fechajornada; la fecha de factura de cada comprobante sigue siendo el día calendario actual.',
                'Al abrir: seleccione empresa, fecha de jornada (no puede ser anterior a la última registrada) y observación opcional. No puede abrir si hay turnos habilitados sin cerrar de la jornada anterior.',
                'Al cerrar: el sistema valida que no queden turnos habilitados, que no haya cuentas abiertas con consumos sin facturar (debe facturarlas o cerrarlas desde Saneamiento), y — si está habilitado — permite cargar el Informe Z de tótems Waitry para conciliar cobros del kiosco.',
                'Las cuentas abiertas sin ítems (mesas abiertas y abandonadas) se descartan automáticamente al cerrar la jornada; no bloquean el cierre.',
            ],
            'items' => [
                'Historial: lista jornadas con fechas de apertura/cierre, usuarios y — si aplica — rango de órdenes Waitry y comprobante PDF de cierre tótem.',
                'Anular último cierre: disponible si la jornada se cerró por error y aún no se presentó rendición en caja. Reabre la jornada y elimina el registro de cierre Waitry de esa jornada.',
                'Eliminar jornada: solo si no tiene movimientos asociados (uso excepcional).',
            ],
        ],
        [
            'titulo' => '5. Habilitación de turno y cierres',
            'herramientas_grupos' => [
                ['titulo' => 'Pantalla de habilitación', 'clave' => 'habilitacion_turno'],
                ['titulo' => 'Cierre parcial y cierre definitivo', 'clave' => 'cierre_turno'],
            ],
            'parrafos' => [
                'Cada terminal con configuración de punto de venta gastronómico debe habilitar un turno operativo antes de facturar (salvo modo caja directo).',
                'El panel de estado muestra turno activo, totales acumulados, cuentas pendientes (abiertas con ítems, vacías, cerradas sin facturar) y accesos a conciliación por medio de pago, notas de crédito e invitaciones.',
            ],
            'items' => [
                'Habilitar: elija turno maestro (desde ventas/turno-gastronomia), confirme monto de habilitación de caja si el negocio lo exige.',
                'Cierre parcial: registra un arqueo intermedio (número de parcial incremental). Imprime comprobante PDF. El turno sigue habilitado para seguir facturando.',
                'Cierre definitivo: cierra el turno en la terminal. En el último turno del día bloquea si hay cuentas abiertas con consumos. Permite ingresar montos contados por medio de pago y observaciones.',
                'Anular último cierre de turno: operación administrativa (permiso anular-cierre-turno-gastronomia). Solo en la misma PC, jornada abierta, sin rendición en caja y siendo el último cierre de esa terminal.',
                'Informe por mozo PDF: desde la pantalla de habilitación, exporta ventas del turno desglosadas por mozo.',
            ],
        ],
        [
            'titulo' => '6. Proceso de facturación (POS)',
            'herramientas_clave' => 'proceso_facturacion',
            'parrafos' => [
                'El facturador gastronómico es la pantalla principal del cajero/mozo en salón. Permite trabajar en modo Mesas (plano de mesas) o Cuentas libres (cuentas numeradas sin mesa física).',
                'Flujo típico: seleccionar mesa libre → modal de apertura (cubiertos y mozo si están configurados como obligatorios) → cargar ítems por SKU o catálogo → asignar cliente de factura si corresponde → cargar cobranza → emitir.',
            ],
            'items' => [
                'Atajos: F5 factura con efectivo predeterminado; F8 facturar con descuento (obligatorio para canjes premio/fidelidad); tecla + abre cuenta libre si está habilitado.',
                'Waitry: importar órdenes pendientes del tótem cuando la terminal tiene integración habilitada.',
                'Canjes: cupón premio Wigos, tarjeta fidelidad, ticket tarjeta CTG en cobranza — cada uno con reglas propias (ver sección Restricciones).',
                'Cierre parcial / cerrar turno: botones en barra superior del POS (si hay turno habilitado).',
                'Barra de cuenta activa: muestra mesa/cuenta, mozo, cubiertos y estado (abierta/cerrada).',
            ],
            'tabla' => [
                'caption' => 'Datos obligatorios al abrir cuenta (configurables)',
                'headers' => ['Variable .env', 'Default', 'Efecto'],
                'rows' => [
                    ['GASTRONOMIA_CUBIERTOS_OBLIGATORIO_AL_ABRIR', 'true', 'Exige cubiertos > 0 al abrir mesa/cuenta nueva'],
                    ['GASTRONOMIA_MOZO_OBLIGATORIO_AL_ABRIR', 'true', 'Exige seleccionar mozo al abrir'],
                    ['GASTRONOMIA_CUENTAS_LIBRES_HABILITADAS', 'true', 'Habilita modo cuentas libres y atajo +'],
                ],
            ],
        ],
        [
            'titulo' => '7. Restricciones de facturación',
            'parrafos' => [
                'Antes de emitir, el sistema ejecuta validaciones en servidor (preflight). Si alguna falla, muestra el mensaje en pantalla y no graba comprobante ni cobranza.',
            ],
            'tabla' => [
                'caption' => 'Validaciones principales antes de emitir',
                'headers' => ['Condición', 'Mensaje / comportamiento'],
                'rows' => [
                    ['Jornada obligatoria sin jornada abierta', 'No hay jornada abierta para esta empresa'],
                    ['Turno no habilitado en PC', 'Debe habilitar turno en esta terminal'],
                    ['Sin configuración PV para la PC', 'No hay configuración de punto de venta gastronomía para este equipo'],
                    ['Sin tipo transacción / lista precios / depósitos', 'Configure en configuracion-puntoventa-gastronomia'],
                    ['Sin PV CAE/CAEA válido', 'Configure punto de venta fiscal o CAEA vigente'],
                    ['Cuenta sin líneas o no abierta', 'La cuenta no tiene consumos / no está abierta'],
                    ['Orden Waitry ya facturada', 'Bloqueo de duplicado por waitry_order_id'],
                    ['Descuento sin cliente interno', 'Indique cliente interno del descuento (invitación/CC)'],
                    ['Canje premio/fidelidad pendiente + F5', 'Debe usar F8 Facturar con descuento'],
                    ['Cobranza distinta al total', 'Medios de pago deben coincidir con total (salvo cortesía $0,01)'],
                    ['CTG o TOTEM manual', 'Esas cuentas de caja solo se cargan por flujo automático (ticket/cobro Waitry)'],
                    ['Receptor factura inválido', 'Cliente, CF o datos manuales incompletos según condición IVA'],
                ],
            ],
            'items' => [
                'Factura de cortesía (descuento 100 %): total $0,01, sin cobranza, IVA exento. Típico en canjes premio y fidelidad.',
                'Cliente de factura ≠ cliente interno del descuento: son campos independientes; el interno no se copia al bloque de facturación.',
                'Sincronización Anita: por defecto replica venta en Informix legacy; si falla, el POS muestra error y no confirma emisión.',
            ],
        ],
        [
            'titulo' => '8. Cuentas pendientes y saneamiento',
            'herramientas_clave' => 'saneamiento_turno',
            'parrafos' => [
                'Si al cerrar turno o jornada quedan cuentas abiertas con consumos, el sistema bloquea y dirige a Saneamiento de turnos.',
                'Saneamiento es una herramienta administrativa: diagnostica por terminal, permite cerrar cuentas sin facturar (con confirmación CERRAR-N), cierre remoto de turnos, extensión de horarios de cierre y creación retroactiva de turnos para cuadrar facturas huérfanas.',
            ],
            'items' => [
                'Cuentas abiertas con ítems (badge naranja): bloquean cierre del último turno del día y cierre de jornada.',
                'Cuentas abiertas vacías (badge celeste): informativas; se auto-descartan al cerrar.',
                'Cuentas cerradas sin facturar (badge gris): estado terminal de auditoría; no bloquean.',
                'Bucket «sin PV configurada»: agrupa cuentas huérfanas cuya terminal ya no existe en configuración.',
            ],
        ],
        [
            'titulo' => '9. Facturas del día',
            'herramientas_clave' => 'facturas_dia',
            'herramientas_incluir_listado' => true,
            'parrafos' => [
                'Listado de comprobantes emitidos desde gastronomía en la jornada/filtros seleccionados. Ruta: ventas/gastronomia/facturas-dia.',
                'Permite consultar detalle, reimprimir ticket, ver medios de pago, tickets tarjeta canjeados, canjes premio/fidelidad asociados, y — con permiso — generar nota de crédito o cambiar medio de pago post-emisión.',
            ],
            'items' => [
                'Filtros: empresa, fecha jornada, búsqueda por número/cliente/mesa.',
                'Exportación PDF/Excel/CSV respeta filtros activos.',
                'Modo consulta: middleware modo.consulta restringe acciones de modificación según rol.',
            ],
        ],
        [
            'titulo' => '10. Cierres de turno (consulta)',
            'herramientas_clave' => 'cierres_turno',
            'herramientas_incluir_listado' => true,
            'parrafos' => [
                'Historial de cierres parciales y definitivos registrados en el sistema. Ruta: ventas/gastronomia/cierres-turno.',
                'Desde cada registro puede abrir el comprobante PDF, ver detalle del cierre, consultar canjes premio/fidelidad y tickets tarjeta del turno asociado.',
            ],
        ],
        [
            'titulo' => '11. Informe gerente',
            'herramientas_clave' => 'informe_gerente',
            'parrafos' => [
                'Dashboard gerencial por empresa y fecha de jornada. Ruta: ventas/gastronomia/informe-gerente.',
                'Muestra total de ventas netas de la jornada, top 10 artículos por cantidad y por valor, distribución por categoría, medios de pago y gráficos comparativos.',
            ],
            'items' => [
                'Seleccione empresa y fecha de jornada en la cabecera y presione Generar.',
                'Útil para control de cierre y comparación entre turnos sin acceder al POS.',
            ],
        ],
        [
            'titulo' => '12. Artículos vendidos',
            'herramientas_clave' => 'articulos_vendidos',
            'herramientas_incluir_listado' => true,
            'parrafos' => [
                'Listado analítico de unidades vendidas por artículo en un rango de fechas jornada. Ruta: ventas/gastronomia/articulos-vendidos.',
                'Desde cada fila puede desplegar las facturas que incluyeron el artículo y, en otra pestaña, los movimientos de stock generados.',
            ],
            'items' => [
                'Filtros inteligentes: SKU, descripción, categoría, empresa, rango de fechas jornada.',
                'Exportación PDF/Excel/CSV con logos y estilo unificado del ERP.',
            ],
        ],
        [
            'titulo' => '13. Waitry y canjes Wigos (AGG)',
            'herramientas_grupos' => [
                ['titulo' => 'Integración Waitry', 'clave' => 'waitry'],
                ['titulo' => 'Canjes Wigos', 'clave' => 'wigos_canjes'],
            ],
            'captura_id' => 'totem_waitry',
            'parrafos' => [
                'Este capítulo documenta funcionalidades habilitadas en AGG: kioscos Waitry conectados al POS Anita, conciliación de cobros al cierre de jornada, y canjes vía sistema Wigos (premios y programa de fidelidad). Si WAITRY_HABILITADO o WIGOS_HABILITADO están en false, las pantallas y botones correspondientes no aparecen.',
            ],
            'items' => [
                'Waitry — configuración: cada tótem se registra en ventas/totem-waitry-gastronomia (empresa, ubicación, layout Waitry, mesa asociada, flag Informe Z). La terminal de facturación debe tener waitry_habilitado en configuracion-puntoventa-gastronomia.',
                'Waitry — facturación POS: pestaña «Cuentas externas» lista órdenes pendientes (getOrdersPOS). Importe la orden a una cuenta gastronómica; si el tótem ya cobró, la cobranza queda en cuenta TOTEM (no editable manualmente).',
                'Waitry — importar por ID: botón «Importar por nº monitor» para traer una orden concreta cuando no figura en el listado.',
                'Waitry — cierre jornada: al cerrar la jornada el sistema lee órdenes Waitry desde la apertura hasta el cierre, calcula total Sistema por tótem (lo cobrado en Waitry que Anita aún no presentó) y lo compara con el Informe Z del Posnet/kiosco. Use «Actualizar lectura Waitry» si hubo ventas en tótem después de la carga inicial.',
                'Waitry — comprobante: tras cerrar jornada queda PDF de ingresos tótem y registro de rango de order IDs Waitry en el historial.',
                'Wigos — canje premio (cupón): ícono regalo en POS → escanear cupón → validación SQL Wigos (spVoucherGiftData) → abre cuenta libre si hace falta → aplica ítems y descuento → obligatorio F8 (factura cortesía $0,01). Un cupón por cuenta; no mezclar con canje fidelidad.',
                'Wigos — canje fidelidad (tarjeta): ícono tarjeta → pasar/escanear trackdata → validación HTTP AccountInfoJSON → elegir artículo de la categoría → F8 obligatorio. Un canje por DNI por día calendario.',
                'Wigos — consulta post-emisión: en Facturas del día, iconos de regalo/tarjeta muestran detalle del canje asociado a la factura.',
            ],
            'tabla' => [
                'caption' => 'Resumen de flujos AGG (Waitry vs Wigos)',
                'headers' => ['Herramienta', 'Origen', 'Facturación', 'Cobranza'],
                'rows' => [
                    ['Orden Waitry pendiente', 'Tótem / QR celular', 'F5 o medios normales', 'TOTEM auto si ya pagó en kiosco; sino efectivo/tarjeta'],
                    ['Canje premio cupón', 'Wigos SQL', 'Solo F8', 'Sin cobranza ($0,01 cortesía)'],
                    ['Canje fidelidad tarjeta', 'Wigos HTTP', 'Solo F8', 'Sin cobranza ($0,01 cortesía)'],
                    ['Ticket tarjeta CTG', 'Anita Informix', 'F5 normal', 'Medio CTG en grilla cobranza'],
                ],
            ],
            'tabla2' => [
                'caption' => 'Variables de entorno relevantes (AGG)',
                'headers' => ['Variable', 'Uso'],
                'rows' => [
                    ['WAITRY_HABILITADO', 'Activa integración Waitry en el entorno'],
                    ['GASTRONOMIA_CIERRE_TOTEM_JORNADA_HABILITADO', 'Conciliación Informe Z al cerrar jornada'],
                    ['GASTRONOMIA_CUENTACAJA_TOTEM_CODIGO', 'Cuenta caja para cobros ya realizados en tótem (default TOTEM)'],
                    ['WIGOS_HABILITADO', 'Consulta cupones premio vía SQL Server'],
                    ['WIGOS_ACCOUNT_INFO_HABILITADO', 'Consulta tarjeta fidelidad vía HTTP'],
                    ['GASTRONOMIA_CANJE_PREMIO_DESCUENTO_CODIGO', 'Descuento obligatorio al facturar canje premio (default 10)'],
                    ['GASTRONOMIA_CANJE_FIDELIDAD_DESCUENTO_CODIGO', 'Descuento obligatorio al facturar canje fidelidad (default 10)'],
                ],
            ],
        ],
        [
            'titulo' => '14. Configuración previa (resumen)',
            'herramientas_clave' => 'configuracion_pv',
            'parrafos' => [
                'Antes del primer día de operación, el administrador debe completar las tablas maestras. Todas siguen el patrón ABM estándar del ERP (listado, crear, editar).',
            ],
            'tabla' => [
                'caption' => 'Tablas maestras gastronomía',
                'headers' => ['Recurso', 'Ruta', 'Uso'],
                'rows' => [
                    ['Configuración PV gastronomía', 'ventas/configuracion-puntoventa-gastronomia', 'Terminal: empresa, identificador PC, PV CAE/CAEA, lista precios, depósitos, tipos transacción'],
                    ['Mesas', 'ventas/mesa-gastronomia', 'Plano de salón y numeración'],
                    ['Ubicaciones', 'ventas/ubicaciones-gastronomia', 'Sectores del local'],
                    ['Mozos', 'ventas/mozo-gastronomia', 'Personal de salón'],
                    ['Turnos maestros', 'ventas/turno-gastronomia', 'Mañana/tarde/noche con orden'],
                    ['Descuentos', 'ventas/descuento-gastronomia', 'Invitaciones, cortesías, % cabecera'],
                    ['Áreas comanda', 'ventas/area-comanda-gastronomia', 'Ruteo a cocina/barra'],
                    ['Tótems Waitry', 'ventas/totem-waitry-gastronomia', 'Integración kiosco'],
                    ['Categorías fidelidad', 'ventas/categoria-fidelidad-gastronomia', 'Canje tarjeta Wigos'],
                ],
            ],
        ],
        [
            'titulo' => '15. Permisos principales',
            'parrafos' => [
                'Los ítems de menú y botones visibles dependen del rol. El encargado de gastronomía suele tener permisos de gestión; cajero/mozo los de facturación y consulta.',
            ],
            'tabla' => [
                'caption' => 'Permisos frecuentes',
                'headers' => ['Permiso (slug)', 'Descripción'],
                'rows' => [
                    ['gestionar-jornada-gastronomia', 'Abrir/cerrar/anular jornada'],
                    ['gestionar-habilitacion-turno-gastronomia', 'Habilitar turno, cierre parcial y definitivo'],
                    ['anular-cierre-turno-gastronomia', 'Anular último cierre de turno en la PC'],
                    ['facturar-gastronomia', 'Proceso de facturación POS'],
                    ['ver-factura-gastronomia', 'Consultar facturas del día y detalle'],
                    ['generar-nota-credito-gastronomia', 'NC desde facturas del día'],
                    ['cambiar-medio-pago-gastronomia-facturas-dia', 'Corregir medios post-emisión'],
                    ['listar-gastronomia-cierres-turno', 'Consultar historial de cierres'],
                    ['consultar-informe-gerente-gastronomia', 'Informe gerente'],
                    ['listar-gastronomia-articulos-vendidos', 'Artículos vendidos'],
                    ['ejecutar-saneamiento-turno-gastronomia', 'Acciones correctivas en saneamiento'],
                ],
            ],
        ],
    ],
];
