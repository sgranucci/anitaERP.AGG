<?php

/**
 * Herramientas del manual — Módulo Caja / tesorería.
 */
$toolbarListado = 'Toolbar del listado (filtros y exportar)';
$columnaAcciones = 'Columna Acciones de la grilla';
$formCabecera = 'Cabecera del formulario';
$formDetalle = 'Grillas y bloques de detalle';
$formFooter = 'Pie del formulario (Guardar, Calcular, Imprimir)';

return [
    'comunes_listado' => [
        [
            'herramienta' => 'Filtros',
            'ubicacion' => $toolbarListado,
            'accion' => 'Panel colapsable con criterios; botón Aplicar filtros / Limpiar filtros.',
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
    'posicion_financiera' => [
        [
            'herramienta' => 'Selector empresa',
            'ubicacion' => $formCabecera,
            'accion' => 'Filtra el informe por empresa asignada al usuario.',
            'permiso' => 'listar-posicion-financiera',
        ],
        [
            'herramienta' => 'Mes / año',
            'ubicacion' => $formCabecera,
            'accion' => 'Define el período mensual a consultar (columnas = días del mes).',
            'permiso' => 'listar-posicion-financiera',
        ],
        [
            'herramienta' => 'Consultar',
            'ubicacion' => $formCabecera,
            'accion' => 'Genera la grilla desde EfePosicionFinancieraSupport (port l-posfinanc.c) + datos ERP.',
            'permiso' => 'listar-posicion-financiera',
        ],
        [
            'herramienta' => 'Exportar PDF / Excel',
            'ubicacion' => 'Toolbar sobre la grilla',
            'accion' => 'Descarga el informe completo del período filtrado.',
            'permiso' => 'listar-posicion-financiera',
        ],
        [
            'herramienta' => 'Confirmar saldo',
            'ubicacion' => 'Panel inferior (mes finalizado)',
            'accion' => 'Graba saldo inicial/final confirmado en ERP; alimenta el saldo inicial del mes siguiente.',
            'permiso' => 'confirmar-saldo-posicion-financiera',
        ],
        [
            'herramienta' => 'Anular confirmación',
            'ubicacion' => 'Panel saldo confirmado',
            'accion' => 'Revoca la confirmación del último día del mes si hubo error.',
            'permiso' => 'confirmar-saldo-posicion-financiera',
        ],
        [
            'herramienta' => 'Auditoría',
            'ubicacion' => 'Enlace en cabecera',
            'accion' => 'Consulta histórico de confirmaciones de saldo por empresa.',
            'permiso' => 'listar-posicion-financiera',
        ],
    ],
    'flash_listado' => [
        [
            'herramienta' => 'Nuevo Flash',
            'ubicacion' => $toolbarListado,
            'accion' => 'Alta de flash diario por empresa y fecha.',
            'permiso' => 'crear-flash-caja',
        ],
        [
            'herramienta' => 'Editar / Consultar',
            'ubicacion' => $columnaAcciones,
            'accion' => 'Abre el formulario del día; permite recalcular o revisar totales.',
            'permiso' => 'editar-flash-caja / listar-flash-caja',
        ],
        [
            'herramienta' => 'Reporte PDF',
            'ubicacion' => $columnaAcciones,
            'accion' => 'Imprime el flash del día en formato estándar.',
            'permiso' => 'listar-flash-caja',
        ],
        [
            'herramienta' => 'Reporte histórico',
            'ubicacion' => 'Menú Flash → Reporte histórico',
            'accion' => 'Serie diaria de totales entre fechas; export PDF/Excel.',
            'permiso' => 'listar-flash-caja',
        ],
        [
            'herramienta' => 'Parámetros Flash',
            'ubicacion' => 'Menú Caja → Flash → Parámetros',
            'accion' => 'ABM de parámetros por empresa/período (días hábiles, metas, etc.).',
            'permiso' => 'listar-flash-parametro',
        ],
    ],
    'flash_form' => [
        [
            'herramienta' => 'Calcular desde ERP/Wigos',
            'ubicacion' => $formFooter,
            'accion' => 'Recalcula todos los bloques del día: slots, ruletas, AyB, estacionamiento, vending, bingo.',
            'permiso' => 'crear-flash-caja / actualizar-flash-caja',
        ],
        [
            'herramienta' => 'Origen de total (ícono lupa)',
            'ubicacion' => 'Junto a cada total numérico',
            'accion' => 'Abre modal con fórmula, cuenta y detalle Wigos/ERP vía API origen-total.',
            'permiso' => 'listar-flash-caja',
        ],
        [
            'herramienta' => 'Desglose Wigos Excel',
            'ubicacion' => 'Toolbar del formulario',
            'accion' => 'Exporta movimientos Wigos del working day para auditoría.',
            'permiso' => 'listar-flash-caja',
        ],
        [
            'herramienta' => 'Guardar',
            'ubicacion' => $formFooter,
            'accion' => 'Persiste el flash del día; puede importarse desde Anita legacy o editarse manualmente.',
            'permiso' => 'crear-flash-caja / actualizar-flash-caja',
        ],
    ],
    'rendicion_maquina' => [
        [
            'herramienta' => 'Nueva rendición',
            'ubicacion' => $toolbarListado,
            'accion' => 'Abre pantalla de carga por empresa, fecha y turno (M/T/N/C).',
            'permiso' => 'crear-rendicion-maquina',
        ],
        [
            'herramienta' => 'Traer Wigos',
            'ubicacion' => $formCabecera,
            'accion' => 'Importa valores del working day según turno; C = cierre completo del día.',
            'permiso' => 'crear-rendicion-maquina / actualizar-rendicion-maquina',
        ],
        [
            'herramienta' => 'Calcular',
            'ubicacion' => $formFooter,
            'accion' => 'Recalcula líneas, impuestos, gastos y totales antes de guardar.',
            'permiso' => 'crear-rendicion-maquina / actualizar-rendicion-maquina',
        ],
        [
            'herramienta' => 'Grilla valores / gastos',
            'ubicacion' => $formDetalle,
            'accion' => 'Carga montos por concepto valormae; turno C concentra impuestos para el Flash.',
            'permiso' => 'crear-rendicion-maquina / actualizar-rendicion-maquina',
        ],
        [
            'herramienta' => 'Imprimir comprobante',
            'ubicacion' => $columnaAcciones.' / pie formulario',
            'accion' => 'PDF de la rendición grabada.',
            'permiso' => 'imprimir-rendicion-maquina',
        ],
        [
            'herramienta' => 'Eliminar',
            'ubicacion' => $columnaAcciones,
            'accion' => 'Baja la rendición si no está cerrada contablemente.',
            'permiso' => 'borrar-rendicion-maquina',
        ],
    ],
    'bingo_terminal' => [
        [
            'herramienta' => 'Jornada bingo',
            'ubicacion' => 'Menú Caja → Bingo → Jornada',
            'accion' => 'Abre/cierra la jornada operativa del bingo por empresa.',
            'permiso' => 'gestionar-jornada-bingo',
        ],
        [
            'herramienta' => 'Habilitación turno',
            'ubicacion' => 'Menú Caja → Bingo → Habilitación turno',
            'accion' => 'Inicia turno en terminal; cierres parcial/definitivo por PV.',
            'permiso' => 'gestionar-habilitacion-turno-bingo',
        ],
        [
            'herramienta' => 'Cargar rendición',
            'ubicacion' => 'Menú Caja → Bingo → Rendición terminal',
            'accion' => 'Registra cartones vendidos, premios y conceptos por turno operativo.',
            'permiso' => 'crear-rendicion-bingo-terminal',
        ],
        [
            'herramienta' => 'Calcular premios',
            'ubicacion' => 'Formulario rendición terminal',
            'accion' => 'Aplica conceptos de rendición y reglas de pozo según configuración.',
            'permiso' => 'crear-rendicion-bingo-terminal',
        ],
    ],
    'bingo_presentacion' => [
        [
            'herramienta' => 'Nueva presentación',
            'ubicacion' => 'Listado caja/rendicionbingo',
            'accion' => 'Presenta en tesorería el cierre del turno bingo ya operado en terminal.',
            'permiso' => 'crear-rendicion-bingo-caja',
        ],
        [
            'herramienta' => 'Consulta cierre',
            'ubicacion' => 'Modal alta presentación',
            'accion' => 'Trae totales del turno pendiente de presentar; valida duplicados.',
            'permiso' => 'crear-rendicion-bingo-caja',
        ],
        [
            'herramienta' => 'Medios de cobro',
            'ubicacion' => 'Formulario presentación',
            'accion' => 'Distribuye el total en cuentas de caja autorizadas.',
            'permiso' => 'crear-rendicion-bingo-caja / actualizar-rendicion-bingo-caja',
        ],
        [
            'herramienta' => 'Imprimir comprobante',
            'ubicacion' => $columnaAcciones,
            'accion' => 'PDF de la presentación en caja.',
            'permiso' => 'listar-rendicion-bingo-caja',
        ],
    ],
];
