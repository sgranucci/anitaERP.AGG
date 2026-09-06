<?php

/**
 * Manual de usuario — Circuito de suscripciones (SaaS y tarjeta corporativa).
 */
return [
    'titulo' => 'Manual de Usuario',
    'subtitulo' => 'Anita ERP — Circuito de suscripciones',
    'version' => '1.0',
    'fecha' => null,
    'empresa' => null,
    'url_base' => null,
    'secciones' => [
        [
            'titulo' => '1. Qué problema resuelve',
            'parrafos' => [
                'Los servicios que se pagan con tarjeta corporativa —software, licencias, herramientas de marketing— se contratan en un clic y se renuevan solos. Sin un circuito propio terminan como cargos en el resumen que nadie autorizó, que nadie sabe quién usa y que no se pueden dar de baja porque se perdió el rastro de quién los pidió.',
                'El módulo pone una orden de compra detrás de cada suscripción y después cruza el resumen del emisor contra esas órdenes. Lo que no cierra queda a la vista.',
            ],
            'items' => [
                'Cada suscripción es una OC abierta (contrato) sin recepción, con cuenta contable y tope autorizado.',
                'La autoriza el gerente del sector por un árbol propio, distinto al de órdenes de compra.',
                'Todos los meses se importa el resumen de la tarjeta y se concilia contra las suscripciones vigentes.',
                'El indicador de cobertura mide qué proporción del gasto real tiene una orden detrás.',
            ],
        ],
        [
            'titulo' => '2. El circuito en seis pasos',
            'parrafos' => [
                'El recorrido completo, desde que alguien pide una herramienta hasta que el gasto queda imputado.',
            ],
            'tabla' => [
                'caption' => 'Pasos del circuito',
                'headers' => ['Paso', 'Quién', 'Qué pasa'],
                'rows' => [
                    ['1. Alta', 'Solicitante / Compras', 'Se carga la suscripción: servicio, proveedor, área, centro de costo, cuenta contable, tarjeta, monto y tolerancia. Se puede guardar como borrador.'],
                    ['2. Envío', 'Solicitante / Compras', 'Al enviar se genera la OC abierta y entra al árbol de Suscripciones.'],
                    ['3. Autorización', 'Gerente del sector', 'Autoriza o rechaza con comentario, desde la bandeja del módulo, desde Mis aprobaciones o desde el enlace del mail.'],
                    ['4. Vigencia', 'Sistema', 'La suscripción queda vigente hasta la fecha de renovación, con avisos a los 60, 30 y 15 días.'],
                    ['5. Conciliación', 'Administración', 'Se importa el resumen de la tarjeta y cada cargo se cruza contra las suscripciones vigentes.'],
                    ['6. Imputación', 'Administración', 'Los cargos conciliados generan el movimiento en Ingresos y egresos.'],
                ],
            ],
        ],
        [
            'titulo' => '3. Estados',
            'parrafos' => [
                'El estado de la suscripción resume dónde está en el circuito. No es el estado de la OC: es la lectura de negocio.',
            ],
            'tabla' => [
                'caption' => 'Estados de la suscripción',
                'headers' => ['Estado', 'Significa'],
                'rows' => [
                    ['Borrador', 'Cargada pero todavía no enviada al gerente.'],
                    ['Pendiente', 'Enviada y esperando la autorización del gerente del sector.'],
                    ['Vigente', 'Autorizada y dentro del período de vigencia.'],
                    ['Desvío', 'Vigente, pero con un cargo por encima del tope autorizado sin resolver.'],
                    ['Vencida', 'Pasó la fecha de renovación sin revalidar.'],
                    ['Rechazada / cerrada', 'El gerente la rechazó o la OC se cerró.'],
                ],
            ],
        ],
        [
            'titulo' => '4. Tope autorizado y tolerancia',
            'parrafos' => [
                'El monto del período es lo que se espera pagar. La tolerancia es cuánto se admite que suba sin volver a preguntar: los proveedores de software ajustan precios, cambian el tipo de cambio y suman usuarios, y no tiene sentido escalar cada centavo.',
                'Tope autorizado = monto del período × (1 + tolerancia). Un cargo por debajo del tope se concilia solo. Uno por encima queda marcado como desvío y vuelve al mismo gerente para que revalide.',
                'Pagar menos de lo previsto no genera desvío: solo el exceso rompe la tolerancia.',
            ],
        ],
        [
            'titulo' => '5. Conciliación mensual',
            'parrafos' => [
                'Se abre un período por empresa y mes, y se importa el resumen del emisor en CSV, XLS, XLSX u ODS. El archivo necesita al menos las columnas fecha, comercio y monto; si trae los últimos cuatro dígitos de la tarjeta, el cruce es más preciso. El separador del CSV se detecta solo.',
                'Reimportar el mismo archivo no duplica: cada línea se identifica por fecha, comercio, tarjeta e importe. Desde la pantalla se puede exportar el papel de trabajo del período en PDF, Excel o CSV.',
            ],
            'tabla' => [
                'caption' => 'Resultado del cruce',
                'headers' => ['Estado del cargo', 'Qué hacer'],
                'rows' => [
                    ['Conciliado', 'Nada. Queda listo para imputar.'],
                    ['Desvío', 'Enviar a revalidar: vuelve al gerente por el árbol de Suscripciones.'],
                    ['Sin identificar', 'Asociar a una suscripción existente, marcar A regularizar si es gasto real sin orden, o descartar si no es una suscripción.'],
                    ['En re-aprobación', 'Esperando la respuesta del gerente. Cuando autoriza, el cargo pasa a conciliado.'],
                    ['A regularizar', 'Hay que emitir la suscripción o dar de baja el servicio.'],
                ],
            ],
        ],
        [
            'titulo' => '6. Cómo aprende el cruce',
            'parrafos' => [
                'El emisor informa el comercio con su propia nomenclatura: "ADOBE *CREATIVE CLOU 4085078188 IE" para lo que en la orden figura como Adobe. El sistema limpia la pasarela de cobro, los números de trámite y los sufijos societarios, y compara contra el nombre del servicio y la razón social del proveedor.',
                'Cuando alguien asocia un cargo a mano, ese comercio queda guardado como alias. Al mes siguiente el mismo cargo se resuelve solo. Es el mecanismo que hace que la conciliación sea cada vez más rápida.',
            ],
            'items' => [
                'Coincidencia de texto entre el comercio y el servicio o el proveedor.',
                'La misma tarjeta suma confianza; una tarjeta distinta descarta la coincidencia.',
                'Un importe dentro de la tolerancia también suma.',
            ],
        ],
        [
            'titulo' => '7. Quién hace qué',
            'parrafos' => [
                'El circuito reparte roles por etapa. R = responsable de hacerlo, A = aprueba, C = consultado, I = informado.',
            ],
            'tabla' => [
                'caption' => 'Responsables por etapa',
                'headers' => ['Etapa', 'Área usuaria', 'Gerente', 'Compras', 'Ctas. a pagar'],
                'rows' => [
                    ['1. Relevamiento', 'R', 'I', 'C', ''],
                    ['2. Alta / OC abierta', 'C', 'I', 'R', 'I'],
                    ['3. Aprobación', 'I', 'A', 'R', 'I'],
                    ['4. Aplicación del gasto', '', 'I', 'C', 'R'],
                    ['5. Conciliación mensual', 'C', 'A', 'C', 'R'],
                    ['6. Renovación o baja', 'R', 'A', 'C', 'I'],
                ],
            ],
        ],
        [
            'titulo' => '8. Configuración previa',
            'parrafos' => [
                'Dos cosas hay que dejar cargadas antes de usar el módulo. Sin la primera no se puede enviar nada a aprobación. Se cargan desde el submenu Suscripciones del módulo de Compras.',
            ],
            'items' => [
                'Aprobadores: se agregan de a uno los centros de costo que participan, cada uno con su gerente. Es el nivel único del árbol.',
                'Tarjetas corporativas: etiqueta, últimos cuatro dígitos y responsable. Para poder imputar hacen falta además la cuenta de caja y el tipo de transacción de egreso.',
            ],
        ],
        [
            'titulo' => '9. Qué mirar cuando ya está en marcha',
            'parrafos' => [
                'Los reportes del módulo están pensados para tres preguntas concretas.',
            ],
            'items' => [
                'Cobertura: si baja, hay gasto de tarjeta que se está escapando del circuito.',
                'Gasto recurrente sin orden: comercios que aparecen mes a mes sin ninguna suscripción detrás. Son las candidatas a suscripción fantasma.',
                'Suscripciones sin dueño: nadie a quien preguntarle si el servicio sigue en uso.',
                'Compromiso contra presupuesto: qué proporción del presupuesto anual de cada cuenta ya está tomada por gasto recurrente.',
                'Vencimientos a 60 días: la ventana para decidir antes de que se renueve solo.',
            ],
        ],
    ],
];
