<?php

/**
 * Manual especial — Plataforma de Inteligencia Artificial anitaERP.
 * Alineado conceptualmente con SAP Joule / AI Agent Hub / Document AI
 * y buenas prácticas de gobernanza (HITL, auditoría, least privilege).
 */
return [
    'titulo' => 'Manual de Plataforma IA',
    'subtitulo' => 'Anita ERP — Inteligencia Artificial embebida (estilo SAP / mejores sistemas)',
    'version' => '1.1',
    'fecha' => null,
    'empresa' => null,
    'url_base' => null,
    'secciones' => [

        [
            'titulo' => '1. Propósito y principios (por qué esta IA no es un chatbot suelto)',
            'parrafos' => [
                'Este manual describe la plataforma de Inteligencia Artificial integrada en anitaERP. No se trata de un asistente genérico que “habla con la base de datos”: es un conjunto de capacidades embebidas en procesos reales (facturas, caja, stock, conciliación, árboles de aprobación, consultas operativas), con gobernanza, permisos y auditoría.',
                'La referencia de producto es la arquitectura de los mejores ERP cloud (en particular SAP Business AI / Joule y el AI Agent Hub): Context (contexto de negocio), Build (skills tipadas), Governance (política, kill-switch, log de decisiones) y, de forma selectiva, Agentic (planes ante eventos, siempre con humano en el circuito — HITL).',
                'Regla de oro de anitaERP: la IA propone; el ERP decide. Ninguna skill escribe sola en maestros críticos salvo que la política de auto-aplicar lo autorice explícitamente (hoy: solo extracción de factura proveedor con score alto). Explicar, sugerir y consultar están permitidos; grabar sin humano es la excepción.',
            ],
            'items' => [
                'Context: cada skill recibe datos tipados (PDF, IDs, empresa, usuario), no un prompt libre del operador como única fuente de verdad.',
                'Build: skills registradas en un catálogo (AiSkillRegistry) con flags por entorno.',
                'Governance: AiPolicy + tabla ai_decision + panel Gobernanza IA + permisos por rol.',
                'Agentic selectivo: auditores determinísticos disparan ai_agente_evento con un plan HITL; el humano marca visto / descartado / resuelto.',
                'Least privilege: el panel FAB exige ejecutar-consulta-ia; el mayor contable vía IA exige además consulta-ia-contable.',
            ],
            'tabla' => [
                'caption' => 'Equivalencia conceptual SAP ↔ anitaERP',
                'headers' => ['Concepto SAP / mercado', 'Componente anitaERP', 'Qué garantiza'],
                'rows' => [
                    ['Joule / copiloto en proceso', 'Skills embebidas + panel FAB', 'IA dentro del flujo, no en silo'],
                    ['AI Agent Hub / skill catalog', 'config/ai.php + AiSkillRegistry', 'Discovery, on/off, umbrales'],
                    ['Grounding / business context', 'Maestros ERP + Support de dominio', 'Respuestas ancladas a datos reales'],
                    ['Human-in-the-loop', 'Preview/confirmar + cola ai_agente_evento', 'Humano cierra el circuito'],
                    ['AI decision audit', 'Tabla ai_decision + KPIs', 'Trazabilidad de sugerencias'],
                    ['Document AI', 'PDF+IA precarga / mail / batch / portal', 'Ingesta documental gobernada'],
                    ['Knowledge / help RAG', 'AiManualRagSupport sobre docs/manual-*', 'Ayuda desde manuales oficiales'],
                    ['Tool calling / MCP', 'API /api/ai/mcp (Bearer)', 'Exposición controlada a sistemas externos'],
                ],
            ],
        ],

        [
            'titulo' => '2. Arquitectura de la plataforma',
            'parrafos' => [
                'La plataforma vive bajo App\\Services\\Ai y App\\Support\\Ai. El punto de entrada de modelos es AiGateway (drivers Ollama u HTTP). AiPolicy aplica kill-switch, flags por skill, permisos y umbral de auto-aplicar. AiDecisionLogger persiste cada decisión relevante en ai_decision.',
                'Las features de negocio (precarga PDF, mail, batch, conciliación, panel) no llaman al LLM “a pelo”: invocan una skill o un Support de consulta. Así se mantiene el mismo contrato aunque cambie el modelo (qwen, otro HTTP, etc.).',
                'Configuración central: config/ai.php, alimentada por variables AI_* en .env. Tras cambiar .env hay que ejecutar php artisan config:clear (y config:cache en despliegues que lo usen).',
            ],
            'tabla' => [
                'caption' => 'Capas y responsabilidades',
                'headers' => ['Capa', 'Clases / artefactos', 'Responsabilidad'],
                'rows' => [
                    ['Gateway', 'AiGateway, OllamaAiDriver, HttpAiDriver', 'Llamada al modelo, JSON, timeouts'],
                    ['Política', 'AiPolicy', 'Habilitado, kill-switch, permiso, auto-aplicar'],
                    ['Skills', 'AiSkillInterface, *Skill, AiSkillRegistry', 'Unidad de trabajo tipada'],
                    ['Auditoría', 'AiDecision, AiDecisionLogger', 'Sugerida / confirmada / auto_aplicada / descartada'],
                    ['Consulta NL', 'AiConsultaOperativa*Support', 'Router reglas→LLM + grounding SQL'],
                    ['Eventos', 'AiAgenteEvento*, AiAgenteOperativoSupport', 'Planes HITL ante hallazgos'],
                    ['RAG', 'AiManualRagSupport, ai:indexar-manuales', 'Búsqueda léxica en manuales'],
                    ['MCP', 'AiMcpBridgeSupport, AiMcpController', 'tools/list y tools/call'],
                ],
            ],
            'tabla2' => [
                'caption' => 'Interruptores globales (.env)',
                'headers' => ['Variable', 'Efecto', 'Valor típico producción'],
                'rows' => [
                    ['AI_HABILITADO', 'Enciende el hub de skills', 'true'],
                    ['AI_KILL_SWITCH', 'Corta modelos de emergencia (sin redeploy)', 'false'],
                    ['AI_DRIVER', 'ollama | http', 'ollama'],
                    ['AI_DECISION_LOG_PERSISTIR', 'Graba filas en ai_decision', 'true'],
                    ['AI_AGENTE_EVENTO_HABILITADO', 'Permite registrar eventos HITL', 'true'],
                    ['AI_RAG_MANUALES_HABILITADO', 'Intent consultar_manual / RAG', 'true'],
                    ['AI_MCP_HABILITADO', 'API MCP HTTP', 'true solo si hay token'],
                    ['AI_MCP_TOKEN', 'Bearer secreto del puente MCP', '(secreto largo)'],
                ],
            ],
        ],

        [
            'titulo' => '3. Catálogo de Skills (Build)',
            'parrafos' => [
                'Cada skill es una unidad mínima: arma contexto → llama al modelo si corresponde → valida contra maestros/reglas → devuelve sugerencia tipada (AiSkillResult) con score 0..1 y flag autoAplicable según política.',
                'Las skills de escritura (factura, remito, pares bancarios) nunca deben considerarse “cerradas” hasta la confirmación humana o el camino de auto-aplicar autorizado. Las skills de explicación (árbol, diferencias gastronomía) son solo lectura.',
            ],
            'tabla' => [
                'caption' => 'Skills registradas',
                'headers' => ['Skill (clave)', 'Proceso', 'Permiso típico', 'Auto-aplicar'],
                'rows' => [
                    ['extraer_factura_proveedor', 'Precarga PDF / mail / batch / portal', 'crear-precarga-proveedores', 'Sí si score ≥ umbral (.env)'],
                    ['extraer_comprobante_iva_caja', 'Ingresos/egresos IVA PDF', 'crear-ingresos-egresos-caja', 'No (umbral 0)'],
                    ['emparejar_remito_recepcion', 'Recepción proveedor OCR/IA', 'ocr-recepcion-proveedor', 'No'],
                    ['sugerir_pares_conciliacion_bancaria', 'Conciliación bancaria', 'ejecutar-conciliacion-bancaria', 'No (solo sugiere)'],
                    ['explicar_contexto_arbol_aprobacion', 'Árbol OC / portales', '(lectura / hash público)', 'No aplica'],
                    ['explicar_diferencias_…_gastronomia', 'Conciliación turno gastronomía', 'gestionar-habilitacion-turno-gastronomia', 'No aplica'],
                    ['consultar_contexto_operativo', 'Panel FAB / NL', 'ejecutar-consulta-ia', 'No aplica'],
                    ['sugerir_pedido_consumo_sector', 'Pedido por consumo CC+depósito (también vía intent FAB)', 'ejecutar-consulta-ia', 'No (siempre HITL)'],
                ],
            ],
            'items' => [
                'Para apagar una skill sin deploy: AI_SKILL_<NOMBRE>=false en .env y config:clear.',
                'El umbral AI_SKILL_EXTRAER_FACTURA_AUTO_SCORE=0 desactiva por completo el auto-aplicar de facturas (recomendado si aún se está calibrando el modelo).',
                'Score alto no implica verdad absoluta: CAE faltante, OC solo del mail o advertencias fuerzan revisión humana aunque el score sea bueno.',
            ],
        ],

        [
            'titulo' => '4. Document AI — facturas de proveedor',
            'herramientas_grupos' => [
                ['clave' => 'document_ai', 'titulo' => 'Canales Document AI'],
            ],
            'parrafos' => [
                'El circuito Document AI replica el valor de soluciones tipo SAP Document Information Extraction / Invoice Management: el PDF se interpreta, se ancla a una orden de compra, se contrastan conceptos y se deja una precarga PENDIENTE en la grilla para que Compras/Contabilidad complete el circuito Anita.',
                'Hay cuatro canales de entrada que convergen al mismo pipeline PDF+IA (preview → confirmar → Facturas_scan): (1) modal manual en precarga, (2) portal proveedores interno, (3) ingesta por correo IMAP, (4) carpeta caliente batch.',
                'Convención: la OC del PDF manda; la del asunto/nombre de archivo es fallback. Si no hay OC, el sistema pide OC manual (6 dígitos). Discrepancias de suma, falta de CAE o advertencias marcan la precarga “para revisar”.',
            ],
            'tabla' => [
                'caption' => 'Canales y flags',
                'headers' => ['Canal', 'Config / comando', 'Notas operativas'],
                'rows' => [
                    ['Modal precarga', 'COMPROBANTE_PROVEEDOR_PDF_IA_*', 'HITL: el operador ve preview y confirma'],
                    ['Portal proveedores', 'compras/portal-proveedores (+ /pagos, /retenciones)', 'MVP interno: facturas, OP, certificados; sin auth de proveedor'],
                    ['Mail IMAP', 'PRECARGA_MAIL_* + compras:ingestar-facturas-mail', 'Schedule cada N min; label Gmail recomendado'],
                    ['Batch carpeta', 'PRECARGA_BATCH_IA_* + compras:ingestar-facturas-batch-ia', 'PDF en entrada_ia → precarga'],
                ],
            ],
            'parrafos2' => [
                'Casilla de correo: lo ideal es una casilla de Sistemas (facturas@…). En Gmail provisorio se usa el label “anitaERP Facturas” (PRECARGA_MAIL_CARPETA) más el filtro de candidato (PDF + OC o palabras como factura/comprobante). Los mails que no matchean no se marcan ni se mueven.',
                'Auto-aplicar (mail/batch): si el score ≥ AI_SKILL_EXTRAER_FACTURA_AUTO_SCORE (ej. 0.92), hay OC del PDF, hay CAE y no hay advertencias graves, la precarga se confirma y ai_decision queda en acción auto_aplicada. En el modal interactivo no se confirma solo: se destaca el botón “Aplicar automático” para que el humano pulse una vez.',
            ],
        ],

        [
            'titulo' => '5. Panel de consulta operativa (copiloto)',
            'herramientas_grupos' => [
                ['clave' => 'panel_fab', 'titulo' => 'Panel FAB (esquina)'],
            ],
            'parrafos' => [
                'El panel flotante (FAB) es el copiloto operativo: lenguaje natural + botón Herramientas (consultas tipadas por módulo). El router primero aplica reglas; si no alcanza, un LLM solo clasifica intent/params (no inventa saldos). El grounding lo hace AiConsultaOperativaSupport contra maestros y movimientos del ERP.',
                'Visibilidad: permiso ejecutar-consulta-ia. Sin ese permiso el FAB no aparece. Además, cada intent exige el permiso del módulo (artículos, OC, CT, etc.). Los intents contables (mayor, saldo de cuenta, asiento) requieren también consulta-ia-contable, para que un rol de gastronomía no consulte un mayor sensible aunque tuviera el panel.',
                'UI: el chat prioriza el resultado (tabla scrolleable); Excel queda fijo en la cabecera tras una consulta exportable. Los atajos no se listan todos a la vez: se agrupan en Herramientas (Compras, Contable, Stock…). Los KPIs de Compras se ejecutan al instante al tocar el botón.',
                'Ejemplos de frases: “saldo insumo muzarella”, “kardex 12345 este mes”, “CT proveedor 001234”, “mayor cuenta caja y bancos empresa biyemas julio”, “mayor cuenta 214010013 CC 85 empresa 1 este mes”, “mayor de la OC 221022”, “pedido consumo CC 93 depósito 12 últimos 60 días”, “qué hago con desvíos de conciliación”, “cómo cargo una orden de compra” (RAG), “cómo hago la carga masiva de solicitudes de pago” (RAG).',
            ],
            'tabla' => [
                'caption' => 'Intents principales',
                'headers' => ['Intent', 'Qué responde', 'Permiso extra'],
                'rows' => [
                    ['articulo_saldo / articulo_kardex', 'Saldo y movimientos (entrada/salida por signo de cantidad)', 'listar/editar-articulos'],
                    ['proveedor / proveedor_ctacte', 'Ficha y cuenta corriente', 'listar-proveedor / CT'],
                    ['cliente / cliente_ctacte', 'Ficha y CT cliente', 'listar-clientes / CT'],
                    ['ordencompra / arbol_oc', 'Estado OC y árbol', 'listar/editar-ordencompra'],
                    ['mayor_cuenta / saldo_cuenta / asiento', 'Mayor con filtros (cuenta, CC, empresa, fechas, OC) / saldo / asiento', 'módulo + consulta-ia-contable'],
                    ['compras_kpi_resumen + OC/RQ KPIs', 'Mediciones: pendientes firma, vencidas, lead time, top proveedores, RQ sin OC', 'listar-ordencompra / RQ / proveedor'],
                    ['pedido_consumo_sector', 'Proyecta pedido por consumo (CC + depósito) → borrador RQ compra o sala', 'artículos / crear-requisicion(-sala)'],
                    ['plan_agente', 'Plan HITL sugerido ante un evento', '(panel)'],
                    ['consultar_manual', 'Pasajes de manuales (RAG)', 'panel + RAG habilitado'],
                ],
            ],
            'items' => [
                'Exportar: el panel puede exportar tablas (Excel) respetando el mismo intent y permisos.',
                'Typos: coincidencia flexible en artículos (ej. muzarella/mozarella) y filtro solo_insumo para gastronomía.',
                'El mayor se arma desde asientos ERP; admite filtros combinables (cuenta, centro de costo, empresa, rango de fechas y/o OC). No es texto plano del LLM.',
                'KPIs de Compras: números desde ERP (OC/RQ/recepción/comprobantes). Frases: «resumen operativo de compras», «OC pendientes de firma», «OC vencidas sin recepción», «lead time OC», «top proveedores», «requisiciones sin OC».',
                'Pedido por consumo: el depósito de consumo es obligatorio; qty = consumo_diario×cobertura − stock − pendientes. Con stock en depósito origen → RQ sala; si no → RQ compra. Confirmar con botón HITL (no auto-graba).',
            ],
        ],

        [
            'titulo' => '6. Gobernanza IA y KPIs',
            'herramientas_grupos' => [
                ['clave' => 'gobernanza', 'titulo' => 'Pantalla Gobernanza IA'],
            ],
            'parrafos' => [
                'Ruta: configuracion/ai-decisiones. Permiso: listar-ai-decisiones (menú Configuración → Gobernanza IA). Aquí se miden aceptación, auto-aplicadas, descartadas y errores, y se ve la salud operativa (flags, mail, batch, cola HITL, RAG, MCP).',
                'Cada fila de ai_decision representa un ciclo de sugerencia: skill, score, driver, payload recortado, usuario, empresa, entidad. Las acciones típicas son: sugerida → confirmada | editada | auto_aplicada | descartada | error.',
                'KPIs año 1 recomendados (estilo business case SAP): % de precargas aceptadas sin edición, tiempo medio de carga vs. carga manual, tasa de auto_aplicada estable, backlog de eventos HITL bajo el umbral acordado.',
            ],
            'tabla' => [
                'caption' => 'Acciones en ai_decision',
                'headers' => ['Acción', 'Significado'],
                'rows' => [
                    ['sugerida', 'La IA generó preview; aún no hay cierre humano'],
                    ['confirmada', 'El operador aceptó sin cambios materiales'],
                    ['editada', 'Hubo OC manual, para-revisar u otra corrección'],
                    ['auto_aplicada', 'Política permitió confirmar sin HITL (factura alta confianza)'],
                    ['descartada', 'Preview cerrado sin confirmar / evento descartado'],
                    ['error', 'Fallo controlado de skill o canal'],
                ],
            ],
        ],

        [
            'titulo' => '7. Agentes por evento y cola HITL',
            'herramientas_grupos' => [
                ['clave' => 'hitl', 'titulo' => 'Cola de agentes'],
            ],
            'parrafos' => [
                'Los “agentes” de anitaERP no reemplazan auditores: los auditores siguen siendo determinísticos (WSAPOC apócrifas, Z con transmisión faltante, anomalías de conciliación). Cuando hay hallazgo, AiAgenteEventoDispatcherSupport deja una fila en ai_agente_evento con resumen, severidad y plan_json (pasos sugeridos).',
                'Esto se alinea al patrón “event-driven agent with human approval” de los mejores sistemas: el agente propone un plan; el humano decide. El plan no ejecuta por sí solo conciliaciones ni suspensiones.',
                'Cola operable: configuracion/ai-agente-eventos (también embebida en Gobernanza). Estados: pendiente → visto → resuelto | descartado. Cada transición puede vincular/cerrar un registro en ai_decision.',
            ],
            'tabla' => [
                'caption' => 'Eventos conocidos',
                'headers' => ['Evento', 'Origen típico', 'Qué hacer (humano)'],
                'rows' => [
                    ['desvio_conciliacion', 'Conciliación bancaria', 'Revisar pares / extracto; marcar resuelto'],
                    ['factura_apocrifa', 'Auditoría ARCA/WSAPOC', 'Validar CUIT/proveedor; seguir procedimiento'],
                    ['z_transmision_faltante', 'Informe Z gastronomía', 'Completar transmisión / jornada'],
                    ['deuda_proveedor / deuda_cliente', 'Plan vía panel', 'Usar CT y acuerdos; el plan es guía'],
                    ['firma_oc / stock_insumo', 'Plan vía panel', 'Árbol OC o kardex según pasos'],
                    ['planear_pedido_consumo', 'Panel / auditor futuro stock bajo', 'Abrir pedido_consumo_sector (CC+depósito) y confirmar RQ'],
                ],
            ],
            'parrafos2' => [
                'Whitelist: AI_AGENTE_EVENTO_PERMITIDOS (CSV). Si un evento no está listado, no se registra. Kill-switch o flag en false cortan el puente sin romper el auditor.',
            ],
        ],

        [
            'titulo' => '8. RAG de manuales (conocimiento oficial)',
            'parrafos' => [
                'A diferencia de un chatbot que “alucina procedimientos”, el RAG de anitaERP indexa los manuales oficiales en docs/manual-* (el mismo corpus del Centro de ayuda). El retrieval es léxico (tokens + score); no usa embeddings ni vector DB en esta versión — decisión deliberada para operar on-prem sin infra extra.',
                'Comando: php artisan ai:indexar-manuales. Genera storage/app/ai/manual_rag_index.json. Conviene reindexar tras publicar o actualizar un manual (incluido este).',
                'En el panel, frases como “cómo cargo una OC”, “manual de gastronomía cierres”, “ayuda recepción proveedor” o “carga masiva de solicitudes de pago / SP” disparan el intent consultar_manual. La respuesta cita módulo, sección, extracto y link al manual web.',
                'Corpus vigente incluye, entre otros: Compras, Stock (recuento / recepción-movstock), Gastronomía, Ventas, Canjes, Vending, Contable, Plataforma IA y Solicitudes de pago (listado, filtros, madre/hijas, cuotas, informe y carga masiva CSV Anita). Los operadores consultan esos textos desde el Centro de ayuda; no hace falta un botón Manual en cada index.',
            ],
            'tabla' => [
                'caption' => 'Variables RAG',
                'headers' => ['Variable', 'Rol'],
                'rows' => [
                    ['AI_RAG_MANUALES_HABILITADO', 'Prende/apaga el intent y la búsqueda'],
                    ['AI_RAG_MANUALES_INDEX', 'Ruta relativa bajo storage/app'],
                    ['AI_RAG_MANUALES_TOP_K', 'Cantidad de fragmentos a devolver'],
                ],
            ],
            'items' => [
                'Fase 2 opcional: embeddings locales vía Ollama si el léxico se queda corto en sinónimos.',
                'El RAG no sustituye permisos: ver un extracto del manual no otorga permiso para ejecutar la pantalla descrita.',
            ],
        ],

        [
            'titulo' => '9. MCP HTTP — exposición controlada hacia afuera',
            'parrafos' => [
                'MCP (Model Context Protocol) es el estándar emergente para que asistentes externos descubran y llamen “tools”. anitaERP implementa un subconjunto HTTP productivo: listar tools y llamarlas con Bearer token — equivalente práctico a exponer el Agent Hub hacia integraciones, sin abrir stdio ni el protocolo completo de Cursor.',
                'Endpoints: POST /api/ai/mcp/tools/list y POST /api/ai/mcp/tools/call. Header Authorization: Bearer seguido del valor de AI_MCP_TOKEN. Si AI_MCP_HABILITADO=false, falta token o el kill-switch está activo, responde 503/401.',
                'tools/call con name=consultar_contexto_operativo_nl y arguments.pregunta ejecuta el mismo router/grounding del panel (el token MCP actúa como canal autorizado). Las skills de escritura siguen devolviendo sugerencias: no saltan el HITL salvo la política de auto-aplicar ya vigente en mail/batch.',
            ],
            'tabla' => [
                'caption' => 'Ejemplo de uso (operación)',
                'headers' => ['Paso', 'Detalle'],
                'rows' => [
                    ['1', 'Configurar AI_MCP_HABILITADO=true y AI_MCP_TOKEN largo en .env'],
                    ['2', 'php artisan config:clear'],
                    ['3', 'POST /api/ai/mcp/tools/list con Bearer'],
                    ['4', 'POST /api/ai/mcp/tools/call JSON { "name": "consultar_contexto_operativo_nl", "arguments": { "pregunta": "…" } }'],
                    ['5', 'Auditar en Gobernanza (origen mcp en payload)'],
                ],
            ],
            'items' => [
                'Trate el token MCP como secreto de integración (rotar si se filtra).',
                'No habilite MCP en internet abierta sin VPN/firewall; es un puente de poder sobre datos ERP.',
                'No es el conector stdio de Cursor; para eso haría falta un adaptador adicional (fuera de alcance actual).',
            ],
        ],

        [
            'titulo' => '10. Permisos y roles (seguridad operativa)',
            'parrafos' => [
                'La seguridad sigue el modelo de roles de anitaERP (permiso_rol), no Gates genéricos de Laravel. El helper can(slug) lee el rol en sesión. El rol administrador bypasea chequeos como en el resto del ERP.',
                'Asignación recomendada: administrador (todo); Enc/Op contaduría e impuestos (ejecutar-consulta-ia + consulta-ia-contable); Enc/Op logística (ejecutar-consulta-ia); Enc-compras (ejecutar-consulta-ia + KPIs/OC/RQ; no Op-Compras salvo política local); gastronomía sin ejecutar-consulta-ia o sin consulta-ia-contable para aislar mayor.',
            ],
            'tabla' => [
                'caption' => 'Permisos IA dedicados',
                'headers' => ['Slug', 'Para qué'],
                'rows' => [
                    ['ejecutar-consulta-ia', 'Ver FAB y APIs de consulta operativa'],
                    ['consulta-ia-contable', 'Mayor / saldo cuenta / asiento vía IA'],
                    ['listar-ai-decisiones', 'Gobernanza IA + cola HITL'],
                    ['crear-precarga-proveedores', 'Skill factura / Document AI'],
                    ['(permisos de módulo)', 'Cada intent/skill de dominio'],
                ],
            ],
            'parrafos2' => [
                'Tras cambiar permisos en ABM de roles, limpie caché de permisos / reingrese sesión si el entorno cachea slugs (SuitecrmPermiso::flushCachePermisos en migraciones).',
            ],
        ],

        [
            'titulo' => '11. Operación diaria y runbook',
            'parrafos' => [
                'Checklist de salud (también visible en Gobernanza): plataforma ON, kill-switch OFF, mail y batch según política, schedule Laravel (cron * * * * * php artisan schedule:run), queue worker activo para jobs de mail/PDF, log de ingesta reciente, backlog HITL bajo control.',
                'Incidentes típicos: (1) Ollama caído → skills fallan con error controlado; kill-switch si el modelo responde basura. (2) Mail no procesa → verificar label, UNREAD, filtro candidato, credenciales IMAP app password. (3) Auto-aplicar demasiado agresivo → bajar umbral a 0. (4) MCP 401 → token/config:clear.',
            ],
            'tabla' => [
                'caption' => 'Comandos útiles',
                'headers' => ['Comando', 'Uso'],
                'rows' => [
                    ['php artisan config:clear', 'Tras tocar AI_* o PRECARGA_*'],
                    ['php artisan ai:indexar-manuales', 'Rebuild índice RAG'],
                    ['php artisan compras:ingestar-facturas-mail --dry-run', 'Simular casilla sin encolar'],
                    ['php artisan compras:ingestar-facturas-mail', 'Corrida real mail'],
                    ['php docs/manual-ia/generar.php', 'Regenerar PDF/Word de este manual'],
                    ['php artisan schedule:list', 'Ver jobs cada 5 min (mail/batch)'],
                ],
            ],
        ],

        [
            'titulo' => '12. Buenas prácticas (alineación con “mejores sistemas”)',
            'items' => [
                'Separe canales: casilla/carpeta solo de facturas; no mezcle INBOX personal.',
                'Mida antes de auto-aplicar: deje umbral 0 hasta que la tasa de aceptación en Gobernanza sea estable.',
                'Prefiera skills estrechas a un mega-prompt: más fácil auditar y apagar.',
                'Documente excepciones de negocio en el plan HITL; no pida al LLM que “arregle” asientos solo.',
                'Trate MCP y mail credentials como secretos; rote tokens.',
                'Reindexe RAG cuando cambien procedimientos; el copiloto debe citar el manual vigente.',
                'No otorgue ejecutar-consulta-ia a roles que no deban ver datos transversales; use consulta-ia-contable como segundo candado.',
                'Mantenga human-in-the-loop en conciliación bancaria y apócrifas: el costo de un falso positivo supera el beneficio de auto-ejecutar.',
            ],
            'parrafos' => [
                'En síntesis: anitaERP adopta el mismo contrato que los ERP premium — IA embebida, gobernada, auditable y acotada — sin depender de un cloud propietario. Este manual es la guía operativa y de diseño para administradores, contabilidad, compras e integración.',
            ],
        ],

        [
            'titulo' => '13. Glosario rápido',
            'tabla' => [
                'caption' => 'Términos',
                'headers' => ['Término', 'Definición en anitaERP'],
                'rows' => [
                    ['Skill', 'Unidad tipada de IA (extraer, sugerir, explicar, consultar)'],
                    ['HITL', 'Human-in-the-loop: humano confirma o cierra el plan'],
                    ['ai_decision', 'Auditoría de cada sugerencia/resolución'],
                    ['ai_agente_evento', 'Hallazgo + plan pendiente de acción humana'],
                    ['Grounding', 'Anclar la respuesta a datos ERP, no al modelo'],
                    ['RAG', 'Retrieval Augmented Generation sobre manuales'],
                    ['MCP', 'Puente HTTP tools/list|call con Bearer'],
                    ['Kill-switch', 'Corte de emergencia de llamadas a modelos'],
                    ['Auto-aplicar', 'Confirmar precarga sin preview humano (política)'],
                    ['FAB', 'Floating Action Button del panel de consulta'],
                ],
            ],
        ],
    ],
];
