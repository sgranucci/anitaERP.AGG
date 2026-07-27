<?php

/**
 * Herramientas del manual — Solicitudes de pago.
 */
$barraListado = 'Barra superior de la tarjeta (card-header)';
$columnaAcciones = 'Columna Acciones de cada fila';
$sobreGrilla = 'Barra de exportación sobre la tabla';
$footerForm = 'Pie del formulario (card-footer)';
$solapas = 'Solapas del formulario';
$barraInforme = 'Formulario de filtros del informe';

return [
    'comunes_listado' => [
        [
            'herramienta' => 'Manual / Guía del módulo',
            'ubicacion' => 'Esquina superior derecha del listado (ícono libro)',
            'accion' => 'Abre este manual de usuario en una pestaña nueva.',
            'permiso' => 'Usuario autenticado',
        ],
        [
            'herramienta' => 'Nuevo registro',
            'ubicacion' => $barraListado,
            'accion' => 'Abre el formulario de alta de una solicitud de pago.',
            'permiso' => 'crear-solicitud-pago',
        ],
        [
            'herramienta' => 'Filtros',
            'ubicacion' => $barraListado.' — botón Filtros',
            'accion' => 'Despliega panel: campo/condición/valor, estado, tratamiento, madre/hija y fechas.',
            'permiso' => 'listar-solicitud-pago',
        ],
        [
            'herramienta' => 'Búsqueda rápida',
            'ubicacion' => 'Campo superior + lupa (panel cerrado)',
            'accion' => 'Busca en código, detalle, beneficiario y observación (tolera tipeo).',
            'permiso' => 'listar-solicitud-pago',
        ],
        [
            'herramienta' => 'Limpiar filtros',
            'ubicacion' => 'Junto al botón Filtros (si hay criterios activos)',
            'accion' => 'Borra criterios y la memoria de filtros de la sesión.',
            'permiso' => 'listar-solicitud-pago',
        ],
        [
            'herramienta' => 'PDF / Excel / CSV',
            'ubicacion' => $sobreGrilla,
            'accion' => 'Exporta el listado con los filtros activos (no solo la página visible).',
            'permiso' => 'listar-solicitud-pago',
        ],
    ],
    'sp_listado' => [
        [
            'herramienta' => 'Código (link azul)',
            'ubicacion' => 'Columna Código',
            'accion' => 'Abre la SP en edición. Badges Madre/Hija indican el vínculo familiar.',
            'permiso' => 'editar-solicitud-pago',
        ],
        [
            'herramienta' => 'SP madre (link)',
            'ubicacion' => 'Columna SP madre',
            'accion' => 'Abre la solicitud madre del plan.',
            'permiso' => 'editar-solicitud-pago',
        ],
        [
            'herramienta' => 'Cuotas (contador)',
            'ubicacion' => 'Columna Cuotas',
            'accion' => 'Abre la madre en la solapa Cuotas (generadas/total).',
            'permiso' => 'editar-solicitud-pago',
        ],
        [
            'herramienta' => 'Plan / cuotas (sitemap)',
            'ubicacion' => $columnaAcciones.' — ícono sitemap',
            'accion' => 'Modal con el plan: avance, cuotas, hijas y estados. Links en solapa consulta sin menú.',
            'permiso' => 'listar-solicitud-pago',
        ],
        [
            'herramienta' => 'Editar',
            'ubicacion' => $columnaAcciones.' — lápiz',
            'accion' => 'Abre el formulario completo de la SP.',
            'permiso' => 'editar-solicitud-pago',
        ],
        [
            'herramienta' => 'Eliminar',
            'ubicacion' => $columnaAcciones.' — papelera',
            'accion' => 'Borra la SP (confirmar).',
            'permiso' => 'borrar-solicitud-pago',
        ],
    ],
    'sp_formulario' => [
        [
            'herramienta' => 'Consulta concepto / proveedor / cuenta',
            'ubicacion' => 'Campos con lupa en Datos y Cuentas',
            'accion' => 'Resuelve por código o abre modal de búsqueda.',
            'permiso' => 'crear/actualizar-solicitud-pago',
        ],
        [
            'herramienta' => 'Solapa Cuentas',
            'ubicacion' => $solapas,
            'accion' => 'Carga el asiento Debe/Haber. Debe balancear para grabar.',
            'permiso' => 'actualizar-solicitud-pago',
        ],
        [
            'herramienta' => 'Solapa Cuotas',
            'ubicacion' => $solapas,
            'accion' => 'Plan de la madre (manual o Excel). En hijas no es obligatoria.',
            'permiso' => 'actualizar-solicitud-pago',
        ],
        [
            'herramienta' => 'Importar Excel de cuotas',
            'ubicacion' => 'Solapa Cuotas',
            'accion' => 'Carga nro, vencimiento y monto desde archivo.',
            'permiso' => 'actualizar-solicitud-pago',
        ],
        [
            'herramienta' => 'Reenviar al árbol / correo',
            'ubicacion' => 'Barra superior del formulario',
            'accion' => 'Reinicia notificaciones o reenvía el mail del nivel pendiente (no Pagada).',
            'permiso' => 'actualizar-solicitud-pago',
        ],
        [
            'herramienta' => 'Pagar (IE) / Marcar pagada',
            'ubicacion' => 'Barra superior (estado Autorizada)',
            'accion' => 'Inicia pago por IE o marca pagada sin IE.',
            'permiso' => 'actualizar-solicitud-pago',
        ],
        [
            'herramienta' => 'Cerrar solapa',
            'ubicacion' => $footerForm.' (modo consulta)',
            'accion' => 'Cierra la pestaña y vuelve al listado/informe de origen.',
            'permiso' => 'listar-solicitud-pago',
        ],
        [
            'herramienta' => 'Guardar / Actualizar',
            'ubicacion' => $footerForm,
            'accion' => 'Persiste cabecera, cuentas, cuotas (madre) y archivos.',
            'permiso' => 'actualizar-solicitud-pago',
        ],
    ],
    'sp_informe' => [
        [
            'herramienta' => 'Consultar',
            'ubicacion' => $barraInforme,
            'accion' => 'Ejecuta el informe con empresa, fechas, sectores, estado y tratamiento.',
            'permiso' => 'listar-informe-solicitudpago',
        ],
        [
            'herramienta' => 'Tratamiento Familia / Con plan',
            'ubicacion' => $barraInforme,
            'accion' => 'Incluye madres e hijas o solo planes, según la opción elegida.',
            'permiso' => 'listar-informe-solicitudpago',
        ],
        [
            'herramienta' => 'Links a SP / proveedor',
            'ubicacion' => 'Columnas Código, Referencia, Proveedor',
            'accion' => 'Abren ABM en solapa consulta sin menú.',
            'permiso' => 'listar-informe-solicitudpago (+ editar si corresponde)',
        ],
        [
            'herramienta' => 'PDF / Excel / CSV',
            'ubicacion' => 'Toolbar de exportación',
            'accion' => 'Exporta el resultado completo del filtro aplicado.',
            'permiso' => 'listar-informe-solicitudpago',
        ],
    ],
];
