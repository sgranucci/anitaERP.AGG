<?php

/**
 * Manual de usuario — Módulo UIF (clientes, premios, informe, congelados, Wigos).
 * Audiencia: Enc-Uif, Op-Uif, cajeros/tesorería con permisos UIF.
 */
return [
    'titulo' => 'Manual de Usuario',
    'subtitulo' => 'Anita ERP — Módulo UIF · Clientes, premios e informes',
    'version' => '1.0',
    'fecha' => null,
    'empresa' => null,
    'url_base' => null,
    'secciones' => [
        [
            'titulo' => '1. Introducción',
            'parrafos' => [
                'Este manual describe el Módulo UIF de Anita ERP: el circuito operativo para registrar clientes y premios sujetos a control de prevención de lavado de activos, generar el informe mensual de datos, exportar Excel/PDF/XML y conciliar planillas Wigos.',
                'Menú: Módulo UIF → Clientes UIF · Premios UIF · Informe datos clientes UIF · Clientes congelados · Conciliación Wigos · Tablas UIF. Las acciones visibles dependen de los permisos de su usuario (roles Enc-Uif y Op-Uif para el informe; cajeros/tesorería para alta de clientes y premios según asignación).',
                'Desde el Centro de ayuda o el botón Manual de las pantallas principales puede volver a este documento en cualquier momento.',
            ],
            'items' => [
                'Primero se identifica al cliente (documento, datos personales, PEP, sujeto obligado, domicilio).',
                'Después se registra cada premio (monto, juego, fecha de entrega, posición, foto/adjuntos).',
                'Al cierre del mes, Enc-Uif / Op-Uif consultan el informe por empresa e importe y bajan Excel, PDF o XML.',
                'Los clientes congelados y la conciliación Wigos complementan el control operativo.',
            ],
        ],
        [
            'titulo' => '2. Visión del circuito UIF',
            'captura_id' => 'flujo_uif',
            'parrafos' => [
                'El circuito recomendado es una secuencia clara. Cada paso tiene dueño y deja rastro auditable en el ERP (y, cuando aplica, en Anita / Wigos).',
            ],
            'tabla' => [
                'caption' => 'Del cliente al informe regulatorio',
                'headers' => ['Paso', 'Pantalla', 'Quién', 'Resultado'],
                'rows' => [
                    ['1. Identificar', 'Clientes UIF → Nuevo / Editar', 'Cajero / Op-Uif', 'Cliente con documento y datos de cumplimiento'],
                    ['2. Registrar premio', 'Premios UIF → Nuevo', 'Cajero / Op-Uif', 'Premio vinculado al cliente (monto, juego, foto)'],
                    ['3. Controlar', 'Clientes congelados', 'Enc-Uif', 'Bloqueo operativo de personas restringidas'],
                    ['4. Informar mes', 'Informe datos clientes UIF', 'Enc-Uif / Op-Uif', 'Listado de premios ≥ importe en el período'],
                    ['5. Exportar', 'Excel / PDF / XML (ZIP)', 'Enc-Uif / Op-Uif', 'Archivo para archivo interno o presentación'],
                    ['6. Conciliar Wigos', 'Conciliación Wigos', 'Enc-Uif / Impuestos', 'Cruce de planillas Titos / PM / unificado'],
                ],
            ],
            'items' => [
                'El origen (BSA / KSA / RSA) lo define la PC de caja o la empresa elegida al cargar.',
                'No invente un cliente “genérico”: el informe y el XML usan los datos reales del ABM.',
                'El Excel del informe es el equivalente al viejo informe_datos_x_clientes_uif de Anita Web.',
            ],
        ],
        [
            'titulo' => '3. Roles y permisos',
            'captura_id' => 'roles_uif',
            'parrafos' => [
                'No todos ven los mismos botones. Si falta una acción, suele ser permiso o rol — no un error de la pantalla.',
            ],
            'tabla' => [
                'caption' => 'Roles operativos del módulo',
                'headers' => ['Rol', 'Qué hace habitualmente'],
                'rows' => [
                    ['Enc-Uif', 'Supervisa clientes/premios, informe mensual Excel/PDF/XML, congelados, conciliación Wigos y tablas maestras.'],
                    ['Op-Uif', 'Opera el informe de datos (consulta + Excel/PDF/XML) y apoya la carga UIF según menús asignados.'],
                    ['Cajero UIF (permiso)', 'Alta de clientes y premios en sala; no necesariamente ve el informe mensual.'],
                    ['Enc-sistemas / administrador', 'Acceso técnico de soporte; no reemplaza al Enc-Uif en el día a día.'],
                ],
            ],
            'tabla2' => [
                'caption' => 'Permisos más usados',
                'headers' => ['Permiso (slug)', 'Para qué sirve'],
                'rows' => [
                    ['listar-cliente-uif / crear-cliente-uif / editar-cliente-uif', 'ABM de clientes UIF'],
                    ['listar-cliente-premio-uif / crear-cliente-premio-uif', 'ABM de premios'],
                    ['exportar-operacion-uif', 'Informe datos: consulta, Excel, PDF y XML'],
                    ['listar-cliente-congelado-uif / importar-cliente-congelado-uif', 'Congelados'],
                    ['listar-conciliacion-wigos-uif / cargar-… / conciliar-…', 'Conciliación Wigos'],
                    ['supervisor-uif', 'Avisos de alta de cliente a supervisores'],
                ],
            ],
        ],
        [
            'titulo' => '4. Clientes UIF',
            'captura_id' => 'clientes_listado',
            'herramientas_grupos' => [
                ['titulo' => 'Listado y filtros', 'clave' => 'clientes_listado'],
                ['titulo' => 'Alta / edición', 'clave' => 'clientes_form'],
            ],
            'parrafos' => [
                'Pantalla: Módulo UIF → Clientes UIF.',
                'Use los filtros inteligentes (panel Filtros) para buscar por nombre, documento, CUIT, empresa/origen o estado. El listado pagina de a 10 y exporta PDF/Excel/CSV con los mismos criterios.',
                'Al crear o editar complete los datos personales, domicilio, nacimiento, PEP, sujeto obligado, actividad/profesión y residencia en el exterior o paraíso fiscal. El sistema calcula riesgo con factores y puntajes UIF.',
                'Adjunte el documento (DNI/PDF) y archivos del cliente en la solapa de archivos. Un cliente incompleto genera problemas en el informe y en el XML.',
            ],
            'items' => [
                'La barra superior indica el origen de la PC (BSA/KSA/RSA) o las empresas a las que puede operar.',
                'Si el cliente ya existe en Anita, el ERP puede sincronizar datos/fotos según la configuración de importación.',
                'Tras el alta, Enc-Uif puede recibir un aviso de verificación (módulo de avisos).',
            ],
        ],
        [
            'titulo' => '5. Premios UIF',
            'captura_id' => 'premios_listado',
            'herramientas_grupos' => [
                ['titulo' => 'Listado de premios', 'clave' => 'premios_listado'],
                ['titulo' => 'Alta / edición de premio', 'clave' => 'premios_form'],
            ],
            'parrafos' => [
                'Pantalla: Módulo UIF → Premios UIF (también se llega desde la ficha del cliente).',
                'Cada premio exige cliente, monto, moneda, juego UIF, fecha de entrega, sala/empresa y, cuando corresponde, posición y número TITO. La foto del pago (pago_*) y los adjuntos quedan asociados al premio.',
                'El informe mensual toma estos premios filtrando por fecha de entrega, empresa de la sala e importe mínimo.',
            ],
            'items' => [
                'Verifique que el monto y la fecha de entrega sean los reales: definen si entra o no en el informe.',
                'Si reclasifica ruleta / bien de uso, use el proceso documentado del módulo (no edite a mano campos inconsistentes).',
                'Desde el cliente puede exportar solo los premios de esa persona (PDF/Excel/CSV).',
            ],
        ],
        [
            'titulo' => '6. Informe de datos de clientes UIF',
            'captura_id' => 'informe_consulta',
            'herramientas_grupos' => [
                ['titulo' => 'Consulta del período', 'clave' => 'informe_consulta'],
                ['titulo' => 'Exportaciones', 'clave' => 'informe_export'],
            ],
            'parrafos' => [
                'Pantalla: Módulo UIF → Informe datos clientes UIF. Disponible para Enc-Uif y Op-Uif (no para roles de tesorería).',
                'Elija empresa, período (AAAA-MM) e importe mayor a (default del parámetro LIMITE_INFORME_UIF). Pulse Consultar para ver los premios reportables del mes.',
                'Desde el resultado puede bajar Excel (mismo formato que el informe histórico de Anita Web: columnas Id, Nombre, documento, domicilio, PEP, premio, juego, fechas, usuario de alta, posición, etc.), PDF con las mismas columnas, o generar el ZIP de XML para presentación.',
            ],
            'items' => [
                'El título del Excel incluye la razón social y el período (ej. Periodo: 1/2026).',
                'Si no hay premios ≥ importe en el mes/empresa, el sistema avisa y no genera archivo vacío útil.',
                'El XML se guarda por empresa/período; puede volver a descargar el ZIP sin regenerar si ya existe.',
            ],
            'tabla' => [
                'caption' => 'Qué exportar según el uso',
                'headers' => ['Formato', 'Uso típico'],
                'rows' => [
                    ['Excel', 'Archivo interno / revisión Enc-Uif / cruce con planillas'],
                    ['PDF', 'Impresión o archivo documental del mes'],
                    ['XML (ZIP)', 'Presentación / carga en sistemas de cumplimiento'],
                ],
            ],
        ],
        [
            'titulo' => '7. Clientes congelados',
            'captura_id' => 'congelados_listado',
            'herramientas_grupos' => [
                ['titulo' => 'ABM e importación', 'clave' => 'congelados'],
            ],
            'parrafos' => [
                'Pantalla: Módulo UIF → Clientes congelados.',
                'Mantiene la nómina de personas restringidas. Puede cargar de a uno o importar el listado. Al operar un cliente/premio, el sistema puede alertar o impedir según las reglas vigentes.',
            ],
            'items' => [
                'Mantenga el listado actualizado: un congelado desactualizado genera falsos negativos o bloqueos incorrectos.',
                'Use la importación masiva cuando reciba nóminas oficiales; revise el log de errores.',
            ],
        ],
        [
            'titulo' => '8. Conciliación Wigos',
            'captura_id' => 'conciliacion_wigos',
            'herramientas_grupos' => [
                ['titulo' => 'Carga y conciliación', 'clave' => 'conciliacion'],
            ],
            'parrafos' => [
                'Pantalla: Módulo UIF → Conciliación Wigos.',
                'Permite cargar planillas Wigos del mes, conciliar contra los datos del ERP y exportar el libro Excel (Titos + PM + UNIFICADO). Filtre por año/mes/empresa antes de operar.',
            ],
            'items' => [
                'Cargue primero las planillas del período y recién después ejecute Conciliar.',
                'El Excel unificado es la salida operativa para Impuestos / Enc-Uif.',
            ],
        ],
        [
            'titulo' => '9. Tablas maestras UIF',
            'parrafos' => [
                'Bajo Módulo UIF → Tablas UIF están los catálogos: actividades, países, PEP, sujetos obligados, provincias, localidades, profesiones, estados civiles, juegos, montos, frecuencias, inusualidades, factores de riesgo, puntajes y nivel socioeconómico.',
                'Solo Enc-Uif (o roles con permiso de cada ABM) debería modificarlos. Un cambio en PEP/SO/juego impacta informes y XML futuros.',
            ],
            'items' => [
                'No borre códigos usados históricamente: prefiera inactivar o dejar de usar.',
                'Los puntajes y factores alimentan el cálculo de riesgo del cliente.',
            ],
        ],
        [
            'titulo' => '10. Buenas prácticas y problemas frecuentes',
            'parrafos' => [
                'Antes de cerrar el mes, revise clientes con datos incompletos y premios sin foto cuando el proceso lo exija. El informe refleja exactamente lo grabado.',
            ],
            'tabla' => [
                'caption' => 'Síntoma → causa habitual → qué hacer',
                'headers' => ['Síntoma', 'Causa habitual', 'Qué hacer'],
                'rows' => [
                    ['No veo el menú Informe datos', 'Rol sin exportar-operacion-uif', 'Pedir Enc-Uif u Op-Uif (no tesorería)'],
                    ['Excel vacío / sin filas', 'Período, empresa o importe incorrectos', 'Revisar AAAA-MM, empresa de la sala e importe'],
                    ['Falta un premio en el informe', 'Fecha entrega fuera del mes o monto bajo el límite', 'Corregir premio o bajar el importe de corte'],
                    ['XML con datos incompletos', 'Cliente sin domicilio/PEP/documento', 'Completar ficha del cliente y regenerar'],
                    ['Origen BSA/KSA/RSA incorrecto', 'PC de caja o empresa mal asignada', 'Revisar identificador PC / empresas del usuario'],
                    ['No descarga el ZIP', 'Permiso o XML aún no generado', 'Usar Generar XML o Volver a descargar ZIP'],
                ],
            ],
            'parrafos2' => [
                'Para regenerar este manual (PDF/Word) tras actualizar capturas: php docs/manual-uif/generar.php. Para capturas reales: php artisan manual:capturar-uif-interno --usuario=admin.',
            ],
        ],
    ],
];
