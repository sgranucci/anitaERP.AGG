<?php

/**
 * Catálogo de herramientas por pantalla (manual conciliación bancaria).
 */
return [
    'comunes_listado' => [
        [
            'herramienta' => 'Consultar / Conciliar',
            'ubicacion' => 'Pie del formulario',
            'accion' => 'Ejecuta la corrida del período (mayor vs extracto)',
            'permiso' => 'listar-conciliacion-bancaria',
        ],
        [
            'herramienta' => 'Export Excel',
            'ubicacion' => 'Card tools (con resultado)',
            'accion' => 'Baja la conciliación del período con filtros activos',
            'permiso' => 'listar-conciliacion-bancaria',
        ],
        [
            'herramienta' => 'Manual de usuario',
            'ubicacion' => 'Centro de ayuda / este documento',
            'accion' => 'Abre la guía operativa completa (premium v2)',
            'permiso' => 'listar-conciliacion-bancaria',
        ],
    ],
    'index' => [
        [
            'herramienta' => 'Tablero',
            'ubicacion' => 'Card tools',
            'accion' => 'Avance multi-cuenta y estados de cierre',
            'permiso' => 'listar-conciliacion-bancaria-tablero',
        ],
        [
            'herramienta' => 'Aging',
            'ubicacion' => 'Card tools',
            'accion' => 'Pendientes por antigüedad + forecast',
            'permiso' => 'listar-conciliacion-bancaria-aging',
        ],
        [
            'herramienta' => 'Worklist',
            'ubicacion' => 'Card tools',
            'accion' => 'Cola de excepciones (asignar / resolver)',
            'permiso' => 'listar-conciliacion-bancaria-excepciones',
        ],
        [
            'herramienta' => 'Reglas',
            'ubicacion' => 'Card tools',
            'accion' => 'Configuración de mapas, residuales y tolerancias',
            'permiso' => 'editar-configuracion-conciliacion-bancaria',
        ],
        [
            'herramienta' => 'Manual (workspace)',
            'ubicacion' => 'Card tools',
            'accion' => 'Abre el workspace de dos columnas',
            'permiso' => 'listar-conciliacion-bancaria',
        ],
        [
            'herramienta' => 'Alinear Excel Contaduría',
            'ubicacion' => 'Pie del formulario',
            'accion' => 'Dry-run o aplica el Excel Contaduría del mes',
            'permiso' => 'alinear-conciliacion-bancaria-excel',
        ],
        [
            'herramienta' => 'Consulta cuenta de caja',
            'ubicacion' => 'F1 / lupa',
            'accion' => 'Elige la cuenta operativa (código + descripción)',
            'permiso' => 'listar-conciliacion-bancaria',
        ],
    ],
    'manual' => [
        [
            'herramienta' => 'Modo Contaduría / Tesorería',
            'ubicacion' => 'Header del workspace',
            'accion' => 'Cambia preferencias de la barra (se guarda por empresa)',
            'permiso' => 'listar-conciliacion-bancaria',
        ],
        [
            'herramienta' => 'Traer del banco',
            'ubicacion' => 'Toolbar',
            'accion' => 'Baja movimientos Interbanking del día filtrado',
            'permiso' => 'listar-conciliacion-bancaria',
        ],
        [
            'herramienta' => 'Importar extracto',
            'ubicacion' => 'Toolbar',
            'accion' => 'Excel / CSV / OFX / CAMT.053 / MT940 (cuenta EXT-…)',
            'permiso' => 'listar-conciliacion-bancaria',
        ],
        [
            'herramienta' => 'Sugerir par (S)',
            'ubicacion' => 'Toolbar / teclado',
            'accion' => 'Marca candidato por importe',
            'permiso' => 'listar-conciliacion-bancaria',
        ],
        [
            'herramienta' => 'Conciliar (Enter)',
            'ubicacion' => 'Toolbar / teclado',
            'accion' => 'Confirma 1:1 o grupo multi-match',
            'permiso' => 'listar-conciliacion-bancaria',
        ],
        [
            'herramienta' => 'Adjuntar evidencia',
            'ubicacion' => 'Panel evidencia',
            'accion' => 'Adjunta PDF/imagen/Excel al cierre o aprobación',
            'permiso' => 'ejecutar-conciliacion-bancaria',
        ],
        [
            'herramienta' => 'Cerrar período',
            'ubicacion' => 'Toolbar',
            'accion' => 'Snapshot de cierre + evidencia pendiente',
            'permiso' => 'ejecutar-conciliacion-bancaria',
        ],
        [
            'herramienta' => 'Aprobar',
            'ubicacion' => 'Toolbar (tras cerrado)',
            'accion' => 'Acepta el cierre formal (SoD: otro usuario)',
            'permiso' => 'aprobar-conciliacion-bancaria',
        ],
        [
            'herramienta' => 'Reabrir',
            'ubicacion' => 'Toolbar',
            'accion' => 'Vuelve el período a editable',
            'permiso' => 'ejecutar-conciliacion-bancaria',
        ],
    ],
    'tablero' => [
        [
            'herramienta' => 'Empresas (checkboxes)',
            'ubicacion' => 'Filtros',
            'accion' => 'Elige el alcance multi-empresa',
            'permiso' => 'listar-conciliacion-bancaria-tablero',
        ],
        [
            'herramienta' => 'Pdf / Excel / Csv',
            'ubicacion' => 'Toolbar compacta',
            'accion' => 'Exporta el tablero con filtros activos',
            'permiso' => 'listar-conciliacion-bancaria-tablero',
        ],
        [
            'herramienta' => 'Ir a Manual / Aging',
            'ubicacion' => 'Acciones de fila',
            'accion' => 'Abre el workspace o el aging de esa cuenta',
            'permiso' => 'listar-conciliacion-bancaria',
        ],
    ],
    'aging' => [
        [
            'herramienta' => 'Consulta cuenta de caja',
            'ubicacion' => 'F1 / lupa',
            'accion' => 'Selecciona la cuenta a analizar',
            'permiso' => 'listar-conciliacion-bancaria-aging',
        ],
        [
            'herramienta' => 'Forecast',
            'ubicacion' => 'Resumen superior',
            'accion' => 'Importe proyectado por ventana 0–7 / 8–30 / 31–60 / 61+',
            'permiso' => 'listar-conciliacion-bancaria-aging',
        ],
        [
            'herramienta' => 'Ir a Worklist',
            'ubicacion' => 'Enlace del resumen',
            'accion' => 'Sincroniza excepciones del aging a la cola',
            'permiso' => 'listar-conciliacion-bancaria-excepciones',
        ],
        [
            'herramienta' => 'Pdf / Excel / Csv',
            'ubicacion' => 'Toolbar compacta',
            'accion' => 'Exporta el aging consultado',
            'permiso' => 'listar-conciliacion-bancaria-aging',
        ],
    ],
    'worklist' => [
        [
            'herramienta' => 'Consultar',
            'ubicacion' => 'Filtros',
            'accion' => 'Sync aging → worklist y lista excepciones',
            'permiso' => 'listar-conciliacion-bancaria-excepciones',
        ],
        [
            'herramienta' => 'Asignar',
            'ubicacion' => 'Fila',
            'accion' => 'Asigna usuario operativo (pasa a en curso)',
            'permiso' => 'listar-conciliacion-bancaria-excepciones',
        ],
        [
            'herramienta' => 'Resolver / Descartar',
            'ubicacion' => 'Fila',
            'accion' => 'Cierra la excepción con resolución y nota',
            'permiso' => 'listar-conciliacion-bancaria-excepciones',
        ],
    ],
    'config' => [
        [
            'herramienta' => 'Solapas de mapas',
            'ubicacion' => 'Tabs del formulario',
            'accion' => 'Edita reglas, P.C.C., conceptos, cód.op, patrones, tipo comp., doc. gastos, residuales',
            'permiso' => 'editar-configuracion-conciliacion-bancaria',
        ],
        [
            'herramienta' => 'Agrega renglón',
            'ubicacion' => 'Cada grilla',
            'accion' => 'Suma una fila al mapa activo',
            'permiso' => 'actualizar-configuracion-conciliacion-bancaria',
        ],
        [
            'herramienta' => 'Lupa cuenta / concepto IVA',
            'ubicacion' => 'Doc. gastos y Residuales',
            'accion' => 'Consulta maestros por código (F1)',
            'permiso' => 'editar-configuracion-conciliacion-bancaria',
        ],
        [
            'herramienta' => 'Guardar',
            'ubicacion' => 'Pie del formulario',
            'accion' => 'Persiste reglas, mapas y residuales juntos',
            'permiso' => 'actualizar-configuracion-conciliacion-bancaria',
        ],
    ],
];
