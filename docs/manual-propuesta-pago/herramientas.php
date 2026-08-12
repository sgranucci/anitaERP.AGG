<?php

/**
 * Catálogo de herramientas por pantalla (manual propuesta de pagos).
 */
return [
    'comunes_listado' => [
        [
            'herramienta' => 'Filtros / Consultar',
            'ubicacion' => 'Cabecera del listado',
            'accion' => 'Restringe propuestas por empresa, estado u otros criterios',
            'permiso' => 'listar-propuesta-pago',
        ],
        [
            'herramienta' => 'Exportar PDF/Excel/CSV',
            'ubicacion' => 'Toolbar de exportación',
            'accion' => 'Baja el listado completo con los filtros activos',
            'permiso' => 'listar-propuesta-pago',
        ],
        [
            'herramienta' => 'Manual',
            'ubicacion' => 'Toolbar',
            'accion' => 'Abre este manual en una solapa',
            'permiso' => 'listar-propuesta-pago',
        ],
    ],
    'pp_listado' => [
        [
            'herramienta' => 'Nueva propuesta',
            'ubicacion' => 'Card tools',
            'accion' => 'Alta de lote desde deuda por vencimiento',
            'permiso' => 'crear-propuesta-pago',
        ],
        [
            'herramienta' => 'Cockpit / Cash',
            'ubicacion' => 'Card tools',
            'accion' => 'Va al workbench o a la posición de caja',
            'permiso' => 'listar-propuesta-pago',
        ],
        [
            'herramienta' => 'Config Premium/Light',
            'ubicacion' => 'Card tools',
            'accion' => 'Modo de aprobación por empresa',
            'permiso' => 'editar-configuracion-propuesta-pago',
        ],
        [
            'herramienta' => 'Editar (lápiz)',
            'ubicacion' => 'Columna acciones',
            'accion' => 'Abre la propuesta',
            'permiso' => 'editar-propuesta-pago',
        ],
    ],
    'config' => [
        [
            'herramienta' => 'Modo Premium / Light',
            'ubicacion' => 'Ficha por empresa',
            'accion' => 'Activa o desactiva el árbol PP',
            'permiso' => 'editar-configuracion-propuesta-pago',
        ],
        [
            'herramienta' => 'Permite OP sin propuesta',
            'ubicacion' => 'Ficha por empresa',
            'accion' => 'Habilita bypass de OP unitaria',
            'permiso' => 'editar-configuracion-propuesta-pago',
        ],
    ],
    'pp_formulario' => [
        [
            'herramienta' => 'Rango de vencimientos',
            'ubicacion' => 'Cabecera alta',
            'accion' => 'Define qué deuda entra al armar el lote',
            'permiso' => 'crear-propuesta-pago',
        ],
        [
            'herramienta' => 'Grilla Incluir / Monto',
            'ubicacion' => 'Cuerpo',
            'accion' => 'Selecciona y ajusta qué se paga',
            'permiso' => 'actualizar-propuesta-pago',
        ],
        [
            'herramienta' => 'Rearmar líneas',
            'ubicacion' => 'Checkbox al editar borrador',
            'accion' => 'Reemplaza la grilla desde la deuda',
            'permiso' => 'actualizar-propuesta-pago',
        ],
    ],
    'pp_aprobacion' => [
        [
            'herramienta' => 'Enviar a aprobación',
            'ubicacion' => 'Footer (Premium)',
            'accion' => 'Dispara árbol PP y deja EN_APROBACION',
            'permiso' => 'enviar-aprobacion-propuesta-pago',
        ],
        [
            'herramienta' => 'Autorizar (light)',
            'ubicacion' => 'Footer (Light)',
            'accion' => 'Autoriza el lote sin firmantes',
            'permiso' => 'enviar-aprobacion-propuesta-pago',
        ],
        [
            'herramienta' => 'Reabrir',
            'ubicacion' => 'Footer (AUTORIZADA sin OP)',
            'accion' => 'Vuelve a BORRADOR y limpia autorizado',
            'permiso' => 'actualizar-propuesta-pago',
        ],
        [
            'herramienta' => 'Auditoría',
            'ubicacion' => 'Header',
            'accion' => 'Ve firmas, estados, OP y lotes',
            'permiso' => 'listar-propuesta-pago',
        ],
    ],
    'pp_ejecucion' => [
        [
            'herramienta' => 'Ejecutar → OP',
            'ubicacion' => 'Footer',
            'accion' => 'Genera OP por proveedor+medio con retenciones',
            'permiso' => 'ejecutar-propuesta-pago',
        ],
        [
            'herramienta' => 'Generar lote bancario',
            'ubicacion' => 'Footer',
            'accion' => 'Arma snapshot de transferencias',
            'permiso' => 'ejecutar-propuesta-pago',
        ],
        [
            'herramienta' => 'Exportar archivo bancario',
            'ubicacion' => 'Footer',
            'accion' => 'Descarga CSV/driver convenio',
            'permiso' => 'listar-propuesta-pago',
        ],
        [
            'herramienta' => 'Marcar enviado banco',
            'ubicacion' => 'Footer',
            'accion' => 'Sella el lote y bloquea OP',
            'permiso' => 'ejecutar-propuesta-pago',
        ],
        [
            'herramienta' => 'Reabrir parcial / Delta',
            'ubicacion' => 'Footer',
            'accion' => 'Gestiona pendientes post-ejecución',
            'permiso' => 'actualizar / crear-propuesta-pago',
        ],
    ],
    'clearing' => [
        [
            'herramienta' => 'Correr clearing',
            'ubicacion' => 'Formulario superior',
            'accion' => 'Reprocesa sugerencias de una propuesta',
            'permiso' => 'ejecutar-propuesta-pago',
        ],
        [
            'herramienta' => 'OK / X',
            'ubicacion' => 'Grilla de sugerencias',
            'accion' => 'Confirma o rechaza un match',
            'permiso' => 'ejecutar-propuesta-pago',
        ],
        [
            'herramienta' => 'Forzar match',
            'ubicacion' => 'Pie de pantalla',
            'accion' => 'Vincula OP con transferencia o movimiento a mano',
            'permiso' => 'ejecutar-propuesta-pago',
        ],
    ],
    'cockpit' => [
        [
            'herramienta' => 'Filtro tipo PP/SP/IE',
            'ubicacion' => 'Cabecera',
            'accion' => 'Reduce la grilla operativa',
            'permiso' => 'según tipo',
        ],
        [
            'herramienta' => 'Abrir documento',
            'ubicacion' => 'Columna acciones',
            'accion' => 'Edita propuesta, SP o IE',
            'permiso' => 'según documento',
        ],
        [
            'herramienta' => 'Clearing',
            'ubicacion' => 'Card tools / accesos',
            'accion' => 'Va al workbench de conciliación bancaria',
            'permiso' => 'listar/ejecutar-propuesta-pago',
        ],
    ],
    'proyeccion' => [
        [
            'herramienta' => 'Consultar',
            'ubicacion' => 'Formulario de filtros',
            'accion' => 'Genera la proyección con los criterios indicados',
            'permiso' => 'listar-reporte-proyeccion-pagos',
        ],
        [
            'herramienta' => 'Columnas',
            'ubicacion' => 'Card tools',
            'accion' => 'Elige, ordena y guarda columnas (presets Ejecutiva / Tesorería / Análisis / Cash flow)',
            'permiso' => 'listar-reporte-proyeccion-pagos',
        ],
        [
            'herramienta' => 'F1 / lupa proveedor',
            'ubicacion' => 'Campo códigos de proveedor',
            'accion' => 'Consulta y elige proveedores',
            'permiso' => 'listar-reporte-proyeccion-pagos',
        ],
        [
            'herramienta' => 'Agrupación / Orden',
            'ubicacion' => 'Filtros',
            'accion' => 'Agrupa por proveedor, tramo, concepto cash flow, etc. y ordena la grilla',
            'permiso' => 'listar-reporte-proyeccion-pagos',
        ],
        [
            'herramienta' => 'Colapsar grupo',
            'ubicacion' => 'Cabecera de grupo en la tabla',
            'accion' => 'Oculta o muestra el detalle del grupo',
            'permiso' => 'listar-reporte-proyeccion-pagos',
        ],
        [
            'herramienta' => 'Exportar PDF / Excel / CSV',
            'ubicacion' => 'Toolbar de exportación',
            'accion' => 'Descarga el informe completo con los mismos filtros y columnas',
            'permiso' => 'listar-reporte-proyeccion-pagos',
        ],
        [
            'herramienta' => 'Links azules',
            'ubicacion' => 'Celdas de proveedor, comprobante, OC, requisición, concepto, cuenta',
            'accion' => 'Abre el ABM en solapa (vista consulta) según permiso',
            'permiso' => 'según documento',
        ],
        [
            'herramienta' => 'Limpiar',
            'ubicacion' => 'Card tools',
            'accion' => 'Vuelve a la pantalla sin filtros aplicados',
            'permiso' => 'listar-reporte-proyeccion-pagos',
        ],
    ],
];
