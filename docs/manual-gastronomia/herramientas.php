<?php

/**
 * Herramientas del manual — Módulo Gastronomía.
 */
$barraSuperior = 'Barra superior del POS o card-header';
$panelEstado = 'Panel de estado del turno / jornada';
$columnaAcciones = 'Columna Acciones de la grilla';
$toolbarListado = 'Toolbar del listado (filtros y exportar)';

return [
    'comunes_listado' => [
        [
            'herramienta' => 'Filtros',
            'ubicacion' => $toolbarListado,
            'accion' => 'Panel colapsable con criterios de búsqueda; botón Aplicar filtros / Limpiar.',
            'permiso' => 'Permiso listar-* del recurso',
        ],
        [
            'herramienta' => 'Búsqueda rápida',
            'ubicacion' => 'Campo filtro_valor en cabecera',
            'accion' => 'Enter o lupa busca en todos los campos cuando el panel está cerrado.',
            'permiso' => 'listar-*',
        ],
        [
            'herramienta' => 'PDF / Excel / CSV',
            'ubicacion' => 'Barra exportar sobre la grilla',
            'accion' => 'Exporta el listado completo según filtros activos (no solo la página visible).',
            'permiso' => 'listar-*',
        ],
        [
            'herramienta' => 'Paginación',
            'ubicacion' => 'Pie de la tarjeta',
            'accion' => 'Navega entre páginas conservando filtros en la URL.',
            'permiso' => 'listar-*',
        ],
    ],
    'jornada' => [
        [
            'herramienta' => 'Selector de empresa',
            'ubicacion' => 'Formulario superior',
            'accion' => 'Filtra jornada e historial por empresa asignada al usuario.',
            'permiso' => 'gestionar-jornada-gastronomia',
        ],
        [
            'herramienta' => 'Abrir jornada',
            'ubicacion' => 'Tarjeta verde «Abrir jornada»',
            'accion' => 'Registra fecha de jornada y observación; habilita facturación en todas las terminales de la empresa.',
            'permiso' => 'gestionar-jornada-gastronomia',
        ],
        [
            'herramienta' => 'Cerrar jornada',
            'ubicacion' => 'Tarjeta roja «Cerrar jornada»',
            'accion' => 'Valida turnos y cuentas pendientes; opcionalmente concilia Informe Z Waitry y graba cierre tótem.',
            'permiso' => 'gestionar-jornada-gastronomia',
        ],
        [
            'herramienta' => 'Preview / Informe Z Waitry',
            'ubicacion' => 'Dentro de tarjeta de cierre (si tótems habilitados)',
            'accion' => 'Consulta órdenes Waitry, muestra total Sistema vs Informe Z por tótem; botón Actualizar lectura Waitry.',
            'permiso' => 'gestionar-jornada-gastronomia',
        ],
        [
            'herramienta' => 'Ir a Saneamiento',
            'ubicacion' => 'Enlace en errores de cierre',
            'accion' => 'Abre saneamiento de turnos en pestaña nueva para resolver cuentas bloqueantes.',
            'permiso' => 'ejecutar-saneamiento-turno-gastronomia (consulta) / gestionar-jornada',
        ],
        [
            'herramienta' => 'Comprobante PDF tótem',
            'ubicacion' => 'Columna Comprobante del historial',
            'accion' => 'Reimprime PDF de ingresos tótem Waitry del cierre de jornada.',
            'permiso' => 'gestionar-jornada-gastronomia',
        ],
        [
            'herramienta' => 'Anular último cierre',
            'ubicacion' => 'Botón sobre historial',
            'accion' => 'Reabre la última jornada cerrada si no hubo rendición en caja.',
            'permiso' => 'anular-cierre-jornada-gastronomia',
        ],
    ],
    'habilitacion_turno' => [
        [
            'herramienta' => 'Estado del turno',
            'ubicacion' => $panelEstado,
            'accion' => 'Muestra si hay turno habilitado, nombre del turno maestro, PC, totales y badges de cuentas pendientes.',
            'permiso' => 'gestionar-habilitacion-turno-gastronomia',
        ],
        [
            'herramienta' => 'Habilitar turno',
            'ubicacion' => 'Formulario de habilitación',
            'accion' => 'Inicia turno operativo en la terminal actual con turno maestro y monto inicial.',
            'permiso' => 'gestionar-habilitacion-turno-gastronomia',
        ],
        [
            'herramienta' => 'Conciliación por medio',
            'ubicacion' => 'Enlaces en totales del turno',
            'accion' => 'Abre grilla de facturas filtradas por cuenta de caja / medio de pago.',
            'permiso' => 'gestionar-habilitacion-turno-gastronomia',
        ],
        [
            'herramienta' => 'Informe mozo PDF',
            'ubicacion' => 'Botón en toolbar',
            'accion' => 'Genera PDF de ventas del turno agrupadas por mozo.',
            'permiso' => 'gestionar-habilitacion-turno-gastronomia',
        ],
    ],
    'cierre_turno' => [
        [
            'herramienta' => 'Pestaña Cierre parcial',
            'ubicacion' => 'Tabs amarillo «Cierre parcial»',
            'accion' => 'Registra arqueo intermedio; imprime comprobante; no cierra el turno.',
            'permiso' => 'gestionar-habilitacion-turno-gastronomia',
        ],
        [
            'herramienta' => 'Pestaña Cierre definitivo',
            'ubicacion' => 'Tabs rojo «Cierre definitivo»',
            'accion' => 'Cierra el turno; ingresa montos contados y observación; valida cuentas pendientes en último turno del día.',
            'permiso' => 'gestionar-habilitacion-turno-gastronomia',
        ],
        [
            'herramienta' => 'Comprobante parcial PDF',
            'ubicacion' => 'Tras confirmar cierre parcial',
            'accion' => 'Abre/descarga comprobante del parcial registrado.',
            'permiso' => 'gestionar-habilitacion-turno-gastronomia',
        ],
        [
            'herramienta' => 'Anular último cierre',
            'ubicacion' => 'Toolbar (si aplica)',
            'accion' => 'Revierte cierre definitivo erróneo en la misma PC y jornada abierta.',
            'permiso' => 'anular-cierre-turno-gastronomia',
        ],
    ],
    'proceso_facturacion' => [
        [
            'herramienta' => 'Modo Mesas / Cuentas libres',
            'ubicacion' => $barraSuperior,
            'accion' => 'Alterna selección por plano de mesas o lista de cuentas libres numeradas.',
            'permiso' => 'facturar-gastronomia',
        ],
        [
            'herramienta' => 'Abrir mesa / cuenta',
            'ubicacion' => 'Clic en mesa libre o botón +',
            'accion' => 'Modal con cubiertos y mozo; crea cuenta gastronómica abierta.',
            'permiso' => 'facturar-gastronomia',
        ],
        [
            'herramienta' => 'Carga de ítems (SKU)',
            'ubicacion' => 'Panel consumo',
            'accion' => 'Ingreso por código catálogo (prefijo V), cantidad, opcionales de fórmula si aplica.',
            'permiso' => 'facturar-gastronomia',
        ],
        [
            'herramienta' => 'Cliente factura',
            'ubicacion' => 'Bloque cliente + lupa consulta',
            'accion' => 'Define receptor fiscal del comprobante (independiente del cliente interno del descuento).',
            'permiso' => 'facturar-gastronomia',
        ],
        [
            'herramienta' => 'Cobranza',
            'ubicacion' => 'Grilla medios de pago',
            'accion' => 'Agrega filas con cuenta caja, moneda, importe; F5 carga efectivo automático.',
            'permiso' => 'facturar-gastronomia',
        ],
        [
            'herramienta' => 'F5 Facturar (efectivo)',
            'ubicacion' => 'Atajo teclado / botón',
            'accion' => 'Emite factura con medio efectivo configurado. Bloqueado si hay canje premio/fidelidad pendiente.',
            'permiso' => 'facturar-gastronomia',
        ],
        [
            'herramienta' => 'F8 Facturar con descuento',
            'ubicacion' => 'Atajo teclado / botón',
            'accion' => 'Obligatorio para invitaciones y canjes Wigos; valida descuento y cliente interno.',
            'permiso' => 'facturar-gastronomia',
        ],
        [
            'herramienta' => 'Canje premio / fidelidad / ticket tarjeta',
            'ubicacion' => 'Iconos en toolbar POS',
            'accion' => 'Flujos especiales de cupón Wigos, tarjeta fidelidad o CTG en cobranza.',
            'permiso' => 'facturar-gastronomia',
        ],
        [
            'herramienta' => 'Importar Waitry',
            'ubicacion' => 'Panel órdenes Waitry',
            'accion' => 'Lista órdenes pendientes del tótem e importa a cuenta gastronómica.',
            'permiso' => 'facturar-gastronomia',
        ],
        [
            'herramienta' => 'Cierre parcial / Cerrar turno',
            'ubicacion' => $barraSuperior,
            'accion' => 'Acceso rápido a cierres de turno desde el POS (misma lógica que Habilitación).',
            'permiso' => 'gestionar-habilitacion-turno-gastronomia',
        ],
    ],
    'saneamiento_turno' => [
        [
            'herramienta' => 'Diagnosticar',
            'ubicacion' => 'Formulario filtros',
            'accion' => 'Carga panel por terminal: turnos, cuentas pendientes, sugerencias de acción.',
            'permiso' => 'listar-saneamiento-turno-gastronomia',
        ],
        [
            'herramienta' => 'Cerrar cuentas pendientes',
            'ubicacion' => 'Botón en diagnóstico por terminal',
            'accion' => 'Cierra sin facturar cuentas con ítems; exige confirmación CERRAR-N.',
            'permiso' => 'ejecutar-saneamiento-turno-gastronomia',
        ],
        [
            'herramienta' => 'Cierre remoto de turno',
            'ubicacion' => 'Acción en terminal con turno habilitado',
            'accion' => 'Cierra turno de otra PC sin estar físicamente en ella.',
            'permiso' => 'ejecutar-saneamiento-turno-gastronomia',
        ],
        [
            'herramienta' => 'Exportar informe PDF',
            'ubicacion' => 'Botón junto a Diagnosticar',
            'accion' => 'Genera PDF del diagnóstico actual para auditoría.',
            'permiso' => 'ejecutar-saneamiento-turno-gastronomia',
        ],
    ],
    'facturas_dia' => [
        [
            'herramienta' => 'Ver comprobante',
            'ubicacion' => $columnaAcciones . ' (ícono factura)',
            'accion' => 'Abre detalle de la venta emitida.',
            'permiso' => 'ver-factura-gastronomia',
        ],
        [
            'herramienta' => 'Reimprimir ticket',
            'ubicacion' => $columnaAcciones,
            'accion' => 'Reenvía ticket fiscal/comercial a impresora configurada.',
            'permiso' => 'ver-factura-gastronomia',
        ],
        [
            'herramienta' => 'Nota de crédito',
            'ubicacion' => $columnaAcciones,
            'accion' => 'Genera NC total o parcial según modal.',
            'permiso' => 'generar-nota-credito-gastronomia',
        ],
        [
            'herramienta' => 'Cambiar medio de pago',
            'ubicacion' => $columnaAcciones,
            'accion' => 'Corrige cuentas de caja de la cobranza post-emisión.',
            'permiso' => 'cambiar-medio-pago-gastronomia-facturas-dia',
        ],
        [
            'herramienta' => 'Canjes / tickets tarjeta',
            'ubicacion' => 'Iconos en fila (regalo, tarjeta, ticket)',
            'accion' => 'Modales con detalle de canje premio, fidelidad o CTG de esa factura.',
            'permiso' => 'ver-factura-gastronomia',
        ],
    ],
    'cierres_turno' => [
        [
            'herramienta' => 'Ver cierre',
            'ubicacion' => $columnaAcciones,
            'accion' => 'Detalle del cierre parcial o definitivo.',
            'permiso' => 'listar-gastronomia-cierres-turno',
        ],
        [
            'herramienta' => 'Comprobante PDF',
            'ubicacion' => $columnaAcciones,
            'accion' => 'Descarga comprobante de arqueo parcial o cierre definitivo.',
            'permiso' => 'listar-gastronomia-cierres-turno',
        ],
        [
            'herramienta' => 'Canjes del turno',
            'ubicacion' => 'Modales en detalle',
            'accion' => 'Lista canjes premio, fidelidad y tickets tarjeta del turno asociado.',
            'permiso' => 'listar-gastronomia-cierres-turno',
        ],
    ],
    'informe_gerente' => [
        [
            'herramienta' => 'Filtros empresa / jornada',
            'ubicacion' => 'Cabecera del informe',
            'accion' => 'Selecciona contexto y regenera tablas y gráficos.',
            'permiso' => 'consultar-informe-gerente-gastronomia',
        ],
        [
            'herramienta' => 'Top 10 artículos',
            'ubicacion' => 'Tarjetas superiores',
            'accion' => 'Ranking por cantidad vendida y por importe neto.',
            'permiso' => 'consultar-informe-gerente-gastronomia',
        ],
        [
            'herramienta' => 'Gráficos',
            'ubicacion' => 'Mitad inferior',
            'accion' => 'Distribución por categoría, medios de pago y comparativas Chart.js.',
            'permiso' => 'consultar-informe-gerente-gastronomia',
        ],
    ],
    'articulos_vendidos' => [
        [
            'herramienta' => 'Ver facturas del artículo',
            'ubicacion' => $columnaAcciones,
            'accion' => 'Modal con comprobantes que incluyeron el SKU en el rango filtrado.',
            'permiso' => 'listar-gastronomia-articulos-vendidos',
        ],
        [
            'herramienta' => 'Ver movimientos stock',
            'ubicacion' => $columnaAcciones,
            'accion' => 'Modal con movimientos de inventario vinculados a esas ventas.',
            'permiso' => 'listar-gastronomia-articulos-vendidos',
        ],
    ],
    'configuracion_pv' => [
        [
            'herramienta' => 'Nuevo / Editar PV',
            'ubicacion' => 'Listado configuracion-puntoventa-gastronomia',
            'accion' => 'Alta de terminal: identificador PC, PV fiscal, lista precios, depósitos, tipos transacción, flags Waitry.',
            'permiso' => 'crear-configuracion-puntoventa-gastronomia / editar-*',
        ],
        [
            'herramienta' => 'Selects por empresa',
            'ubicacion' => 'Formulario (API interna)',
            'accion' => 'Carga combos dependientes al cambiar empresa.',
            'permiso' => 'editar-configuracion-puntoventa-gastronomia',
        ],
    ],
    'waitry' => [
        [
            'herramienta' => 'ABM Tótems Waitry',
            'ubicacion' => 'ventas/totem-waitry-gastronomia',
            'accion' => 'Alta de kiosco: layout Waitry, mesa, ubicación, Informe Z habilitado, layouts adicionales.',
            'permiso' => 'listar-totem-waitry-gastronomia',
        ],
        [
            'herramienta' => 'Cuentas externas',
            'ubicacion' => 'POS → pestaña Cuentas externas',
            'accion' => 'Lista órdenes Waitry sin facturar; clic importa a cuenta gastronómica.',
            'permiso' => 'facturar-gastronomia',
        ],
        [
            'herramienta' => 'Importar por nº monitor',
            'ubicacion' => 'POS → botón junto a Cuentas externas',
            'accion' => 'Trae orden Waitry por orderId cuando no aparece en el listado.',
            'permiso' => 'facturar-gastronomia',
        ],
        [
            'herramienta' => 'Cobro TOTEM automático',
            'ubicacion' => 'Grilla cobranza (cuenta importada ya pagada en kiosco)',
            'accion' => 'Asigna cuenta caja TOTEM; no permite edición manual del medio.',
            'permiso' => 'facturar-gastronomia',
        ],
        [
            'herramienta' => 'Preview Informe Z',
            'ubicacion' => 'Jornada → panel cierre',
            'accion' => 'Muestra total Sistema vs Informe Z por tótem; guardar borrador o definitivo.',
            'permiso' => 'gestionar-jornada-gastronomia',
        ],
        [
            'herramienta' => 'Actualizar lectura Waitry',
            'ubicacion' => 'Jornada → botón amarillo antes de cerrar',
            'accion' => 'Reconsulta órdenes Waitry y congela último ticket leído sin perder montos Z ya cargados.',
            'permiso' => 'gestionar-jornada-gastronomia',
        ],
        [
            'herramienta' => 'Comprobante cierre tótem',
            'ubicacion' => 'Historial jornada → columna Comprobante',
            'accion' => 'PDF de ingresos Waitry del cierre de jornada.',
            'permiso' => 'gestionar-jornada-gastronomia',
        ],
        [
            'herramienta' => 'Comandas en factura',
            'ubicacion' => 'Facturas del día → detalle venta',
            'accion' => 'Panel con comandas Waitry vinculadas a la emisión (si aplica).',
            'permiso' => 'ver-factura-gastronomia',
        ],
    ],
    'wigos_canjes' => [
        [
            'herramienta' => 'Canje premio (cupón)',
            'ubicacion' => 'POS → ícono regalo',
            'accion' => 'Valida cupón Wigos, carga ítems premio y descuento; exige F8 para emitir.',
            'permiso' => 'facturar-gastronomia',
        ],
        [
            'herramienta' => 'Canje fidelidad (tarjeta)',
            'ubicacion' => 'POS → ícono tarjeta',
            'accion' => 'Lee tarjeta Wigos, valida categoría y artículo; un canje por DNI/día; F8 obligatorio.',
            'permiso' => 'facturar-gastronomia',
        ],
        [
            'herramienta' => 'Categorías fidelidad',
            'ubicacion' => 'ventas/categoria-fidelidad-gastronomia',
            'accion' => 'Mapea levelCode Wigos → categoría ERP y artículos canjeables por lista de precios.',
            'permiso' => 'listar-categoria-fidelidad-gastronomia',
        ],
        [
            'herramienta' => 'Consulta canjes en factura',
            'ubicacion' => 'Facturas del día → iconos regalo / tarjeta',
            'accion' => 'Detalle de cupón, SKU, mozo, DNI y fecha de canje.',
            'permiso' => 'ver-factura-gastronomia',
        ],
        [
            'herramienta' => 'Canjes en cierre turno',
            'ubicacion' => 'Habilitación turno / Cierres turno',
            'accion' => 'Modales de canjes premio y fidelidad del turno operativo.',
            'permiso' => 'gestionar-habilitacion-turno-gastronomia',
        ],
        [
            'herramienta' => 'Diagnóstico Wigos',
            'ubicacion' => 'Consola servidor',
            'accion' => 'Comando php artisan wigos:probar-conexion [--cupon=] [--trackdata=] desde host de producción.',
            'permiso' => 'Administrador sistema',
        ],
    ],
];
