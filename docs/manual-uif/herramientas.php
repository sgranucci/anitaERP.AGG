<?php

/**
 * Catálogo de herramientas del módulo UIF.
 * Referenciado desde contenido.php del manual.
 */
return [
    'clientes_listado' => [
        [
            'herramienta' => 'Filtros',
            'ubicacion' => 'Toolbar del listado',
            'accion' => 'Abre el panel de criterios (nombre, documento, CUIT, empresa, etc.).',
            'permiso' => 'listar-cliente-uif',
        ],
        [
            'herramienta' => 'Nuevo',
            'ubicacion' => 'Toolbar',
            'accion' => 'Abre el alta de cliente UIF.',
            'permiso' => 'crear-cliente-uif',
        ],
        [
            'herramienta' => 'Exportar PDF / Excel / CSV',
            'ubicacion' => 'Toolbar de exportación',
            'accion' => 'Baja el listado con los filtros activos (no solo la página visible).',
            'permiso' => 'listar-cliente-uif',
        ],
        [
            'herramienta' => 'Editar',
            'ubicacion' => 'Columna acciones',
            'accion' => 'Abre la ficha del cliente (solapas de datos, UIF, archivos, premios).',
            'permiso' => 'editar-cliente-uif',
        ],
        [
            'herramienta' => 'Manual',
            'ubicacion' => 'Toolbar',
            'accion' => 'Abre este manual en una pestaña nueva.',
            'permiso' => '(logueado)',
        ],
    ],
    'clientes_form' => [
        [
            'herramienta' => 'Datos personales / domicilio',
            'ubicacion' => 'Solapas del formulario',
            'accion' => 'Completa documento, CUIT, domicilio, localidad, provincia, país.',
            'permiso' => 'crear-cliente-uif / actualizar-cliente-uif',
        ],
        [
            'herramienta' => 'Datos UIF (PEP, SO, riesgo)',
            'ubicacion' => 'Solapa cumplimiento',
            'accion' => 'Define PEP, sujeto obligado, actividad y calcula riesgo.',
            'permiso' => 'actualizar-cliente-uif',
        ],
        [
            'herramienta' => 'Archivos adjuntos',
            'ubicacion' => 'Solapa archivos',
            'accion' => 'Adjunta DNI/PDF y documentación del cliente (galería Abrir/Descargar/Quitar).',
            'permiso' => 'actualizar-cliente-uif',
        ],
        [
            'herramienta' => 'Premios del cliente',
            'ubicacion' => 'Solapa premios',
            'accion' => 'Lista premios del cliente y permite exportar PDF/Excel/CSV de esa persona.',
            'permiso' => 'listar-cliente-premio-uif',
        ],
    ],
    'premios_listado' => [
        [
            'herramienta' => 'Filtros / Nuevo',
            'ubicacion' => 'Toolbar',
            'accion' => 'Busca premios por cliente, fechas, monto, sala; alta de premio.',
            'permiso' => 'listar-cliente-premio-uif / crear-cliente-premio-uif',
        ],
        [
            'herramienta' => 'Foto',
            'ubicacion' => 'Columna foto',
            'accion' => 'Muestra o abre la foto del pago asociada al premio.',
            'permiso' => 'listar-cliente-premio-uif',
        ],
        [
            'herramienta' => 'Exportar',
            'ubicacion' => 'Toolbar',
            'accion' => 'PDF / Excel / CSV del listado filtrado.',
            'permiso' => 'listar-cliente-premio-uif',
        ],
    ],
    'premios_form' => [
        [
            'herramienta' => 'Cliente',
            'ubicacion' => 'Formulario',
            'accion' => 'Vincula el premio a un cliente UIF existente (consulta por código/documento).',
            'permiso' => 'crear-cliente-premio-uif',
        ],
        [
            'herramienta' => 'Monto / juego / entrega',
            'ubicacion' => 'Formulario',
            'accion' => 'Define importe, moneda, juego UIF, fecha de entrega, posición y TITO.',
            'permiso' => 'crear-cliente-premio-uif',
        ],
        [
            'herramienta' => 'Foto y archivos',
            'ubicacion' => 'Solapa archivos',
            'accion' => 'Adjunta foto del pago y documentación del premio.',
            'permiso' => 'actualizar-cliente-premio-uif',
        ],
    ],
    'informe_consulta' => [
        [
            'herramienta' => 'Empresa',
            'ubicacion' => 'Formulario de consulta',
            'accion' => 'Filtra premios por empresa de la sala (BSA/KSA/RSA según asignación).',
            'permiso' => 'exportar-operacion-uif',
        ],
        [
            'herramienta' => 'Período',
            'ubicacion' => 'Formulario',
            'accion' => 'Mes/año AAAA-MM de fecha de entrega del premio.',
            'permiso' => 'exportar-operacion-uif',
        ],
        [
            'herramienta' => 'Importe mayor a',
            'ubicacion' => 'Formulario',
            'accion' => 'Umbral de monto (default LIMITE_INFORME_UIF).',
            'permiso' => 'exportar-operacion-uif',
        ],
        [
            'herramienta' => 'Consultar',
            'ubicacion' => 'Footer del formulario',
            'accion' => 'Muestra el listado de premios reportables del mes.',
            'permiso' => 'exportar-operacion-uif',
        ],
    ],
    'informe_export' => [
        [
            'herramienta' => 'Excel',
            'ubicacion' => 'Toolbar del resultado',
            'accion' => 'Descarga el informe de datos (columnas legacy Anita Web).',
            'permiso' => 'exportar-operacion-uif',
        ],
        [
            'herramienta' => 'PDF',
            'ubicacion' => 'Toolbar del resultado',
            'accion' => 'Descarga el mismo informe en PDF (legal landscape).',
            'permiso' => 'exportar-operacion-uif',
        ],
        [
            'herramienta' => 'Generar XML (ZIP)',
            'ubicacion' => 'Toolbar',
            'accion' => 'Genera un XML por operación y descarga el ZIP a la PC.',
            'permiso' => 'exportar-operacion-uif',
        ],
        [
            'herramienta' => 'Volver a descargar ZIP',
            'ubicacion' => 'Toolbar (si ya hay XML)',
            'accion' => 'Re-empaqueta los XML ya generados del período/empresa.',
            'permiso' => 'exportar-operacion-uif',
        ],
        [
            'herramienta' => 'Editar premio',
            'ubicacion' => 'Fila del resultado',
            'accion' => 'Abre el premio para corregir datos antes de reexportar.',
            'permiso' => 'editar-cliente-premio-uif',
        ],
    ],
    'congelados' => [
        [
            'herramienta' => 'Nuevo / Editar / Borrar',
            'ubicacion' => 'ABM congelados',
            'accion' => 'Mantiene la nómina de personas restringidas.',
            'permiso' => 'crear/editar/borrar-cliente-congelado-uif',
        ],
        [
            'herramienta' => 'Importar',
            'ubicacion' => 'Toolbar',
            'accion' => 'Carga masiva de congelados desde archivo.',
            'permiso' => 'importar-cliente-congelado-uif',
        ],
    ],
    'conciliacion' => [
        [
            'herramienta' => 'Filtros año/mes/empresa',
            'ubicacion' => 'Cabecera',
            'accion' => 'Define el período a conciliar.',
            'permiso' => 'listar-conciliacion-wigos-uif',
        ],
        [
            'herramienta' => 'Cargar planillas',
            'ubicacion' => 'Formulario',
            'accion' => 'Sube archivos Wigos del mes.',
            'permiso' => 'cargar-conciliacion-wigos-uif',
        ],
        [
            'herramienta' => 'Conciliar',
            'ubicacion' => 'Acción principal',
            'accion' => 'Cruza planillas vs ERP y arma el unificado.',
            'permiso' => 'conciliar-conciliacion-wigos-uif',
        ],
        [
            'herramienta' => 'Exportar Excel',
            'ubicacion' => 'Toolbar / alerta de descarga',
            'accion' => 'Libro Titos + PM + UNIFICADO.',
            'permiso' => 'exportar-conciliacion-wigos-uif',
        ],
    ],
];
