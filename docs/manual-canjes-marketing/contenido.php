<?php

/**
 * Manual de usuario — Canjes Marketing (Ventas → Gastronomía → Canjes).
 * Escrito para quien usa el módulo por primera vez.
 */
return [
    'titulo' => 'Manual de Usuario',
    'subtitulo' => 'Anita ERP — Canjes Marketing',
    'version' => '1.1',
    'fecha' => null,
    'empresa' => null,
    'url_base' => null,
    'secciones' => [
        [
            'titulo' => '1. Para qué sirve este módulo',
            'parrafos' => [
                'Canjes Marketing sirve para entregar un producto a un cliente especial (un cliente VIP) y dejar emitida la factura correspondiente. El descuento ya está definido: usted no lo elige ni cobra el producto en esta pantalla.',
                'Está pensado para el equipo de Marketing. No reemplaza al facturador del salón (mesas, mozos de piso, cobranzas). Aquí solo se registran las entregas de cortesía a beneficiarios VIP.',
                'En el menú se entra por Ventas, después Gastronomía, después Canjes. Ahí hay cinco pantallas. Las tres de siempre son Clientes VIP, Facturador canjes marketing y Listado canjes marketing. Las dos nuevas consultan la base de Emita: VIP Emita por nombre y VIP Emita por alias.',
                'Emita es el sistema donde están los clientes de la sala (cuentas de juego). Anita no copia toda esa base: la consulta cuando usted busca a alguien, y solo guarda en su lista de Clientes VIP a la persona que va a recibir el canje.',
            ],
            'items' => [
                'Clientes VIP: la lista propia de Anita. Ahí se dan de alta, se corrigen y se buscan las personas que ya recibieron o van a recibir un canje.',
                'Facturador canjes marketing: la pantalla del día a día. Se carga el producto, se indica quién lo recibe y se emite la factura.',
                'Listado canjes marketing: el informe de lo ya entregado, para controlar fechas, productos y personas.',
                'VIP Emita por nombre y VIP Emita por alias: consultas a Emita para encontrar a un cliente de la sala aunque todavía no esté en la lista de Anita.',
            ],
        ],
        [
            'titulo' => '2. Gastronomía vs Canjes Marketing',
            'captura_id' => 'flujo',
            'parrafos' => [
                'Antes de operar conviene saber qué hace cada área. Marketing no abre el día de trabajo ni cobra mesas.',
            ],
            'tabla' => [
                'caption' => 'Quién hace cada cosa',
                'headers' => ['Qué hay que hacer', 'Quién lo hace', 'Dónde está en el menú'],
                'rows' => [
                    ['Abrir o cerrar el día de trabajo (jornada)', 'Encargado de gastronomía', 'Ventas → Gastronomía → Jornada'],
                    ['Habilitar el turno de una PC de salón', 'Caja o gastronomía', 'Ventas → Gastronomía → Habilitación de turno'],
                    ['Facturar mesas y cobrar', 'Salón o caja', 'Ventas → Gastronomía → Proceso de facturación'],
                    ['Canjes de premios o tarjeta de fidelidad', 'Salón o caja', 'Facturador de gastronomía'],
                    ['Entregas a clientes VIP y consulta en Emita', 'Marketing', 'Ventas → Gastronomía → Canjes'],
                ],
            ],
            'items' => [
                'Para facturar un canje tiene que haber jornada abierta en la empresa del punto de venta. Si está cerrada, pídale a gastronomía que la abra. Desde Canjes no se puede abrir.',
                'En esta pantalla no hace falta habilitar un turno de caja. Al entrar solo se pide el código y la clave del operador (el mozo).',
                'La factura sale a nombre de Consumidor final. El cliente VIP es quien recibe el producto, no el nombre que figura en la factura.',
            ],
        ],
        [
            'titulo' => '3. Qué tiene que estar listo antes de empezar',
            'parrafos' => [
                'Si falta alguno de estos puntos, la pantalla avisa y no deja facturar. No intente saltearlo: pida ayuda a quien corresponda.',
            ],
            'tabla' => [
                'caption' => 'Antes de usar el facturador',
                'headers' => ['Qué revisar', 'Qué significa en la práctica'],
                'rows' => [
                    ['Su usuario', 'Tiene que poder entrar al facturador de canjes. Para ver la lista VIP hace falta otro permiso. Para consultar Emita desde el menú, el permiso Consultar clientes VIP Emita.'],
                    ['La computadora', 'Esa PC tiene que estar dada de alta como punto de venta de gastronomía. Si dice que no hay punto de venta, avise a sistemas.'],
                    ['La jornada', 'El día de trabajo de gastronomía tiene que estar abierto.'],
                    ['El descuento', 'Ya viene cargado (en general el código 40). Usted no lo cambia.'],
                    ['Su código de operador', 'El mozo con el que entra tiene que existir para la empresa de esa PC. Si la clave no sirve, pídala al encargado.'],
                ],
            ],
        ],
        [
            'titulo' => '4. Clientes VIP — padrón y búsqueda',
            'captura_id' => 'cliente_vip_listado',
            'parrafos' => [
                'Esta pantalla es la lista de beneficiarios que guarda Anita. Menú: Ventas → Gastronomía → Canjes → Clientes VIP.',
                'Úsela cuando ya conoce a la persona y quiere verla, corregir un dato o darla de alta a mano. Si la persona está en la sala pero todavía no está en esta lista, búsquela en VIP Emita por nombre o por alias (capítulos 8 y 9).',
            ],
            'items' => [
                'La caja de arriba busca en todos los campos a la vez. Escriba apellido, nombre, documento o apodo y pulse Enter.',
                'El botón Filtros permite acotar por empresa o por un campo concreto (documento, apellido, nombre, apodo, localidad).',
                'Nuevo registro pide documento, apellido y nombre. El apodo y la localidad son opcionales. Anita asigna sola el número de cliente.',
                'Si está habilitado, Sincronizar Anita trae clientes VIP desde el sistema anterior. No lo use para buscar en Emita: Emita tiene sus propias pantallas.',
                'PDF, Excel y CSV exportan lo que está filtrado en ese momento, no solo la página que se ve.',
            ],
            'tabla' => [
                'caption' => 'Qué puede hacer cada permiso en Clientes VIP',
                'headers' => ['Si su usuario puede…', 'Entonces puede…'],
                'rows' => [
                    ['Ver el listado', 'Entrar y buscar'],
                    ['Crear', 'Dar de alta una persona'],
                    ['Editar', 'Corregir datos'],
                    ['Borrar', 'Eliminar un registro'],
                ],
            ],
        ],
        [
            'titulo' => '5. Facturador canjes marketing',
            'captura_id' => 'facturador_login',
            'parrafos' => [
                'Es la pantalla para entregar el producto. Menú: Ventas → Gastronomía → Canjes → Facturador canjes marketing.',
                'El recorrido de un canje es siempre el mismo. No hay que memorizar atajos para empezar: los botones dicen lo mismo que las teclas.',
            ],
            'flujo' => "1. Entre con su código y su clave\n2. Cargue el producto (código del artículo)\n3. Indique quién lo recibe (cliente VIP)\n4. Pulse Facturar (tecla F8)\n5. Entregue el producto\n6. La pantalla vuelve a pedir el operador para el siguiente canje",
            'items' => [
                'Al abrir aparece una ventana: código de operador, una lupa para buscarlo y la clave. Enter en el código lo valida. Enter en la clave confirma.',
                'Si usted ya tenía una cuenta abierta en esa misma computadora, el sistema la retoma. No abre otra sin necesidad.',
                'El descuento se aplica solo. No lo borre ni lo cambie.',
                'Cuando la factura sale bien, vuelve a la ventana de ingreso para que la siguiente persona empiece de cero.',
            ],
        ],
        [
            'titulo' => '6. Login mozo, cuentas y carga de artículos',
            'captura_id' => 'facturador_pantalla',
            'parrafos' => [
                'Después de entrar verá tres zonas: las cuentas abiertas en esa PC, el recuadro Descuento y cliente VIP, y la carga del producto.',
                'El producto se carga por su código (SKU). Si no lo recuerda, la lupa o la tecla F1 abren el buscador de artículos.',
            ],
            'tabla' => [
                'caption' => 'Qué hace cada control',
                'headers' => ['Lo que ve en pantalla', 'Para qué sirve'],
                'rows' => [
                    ['Cambiar mozo', 'Entra otra persona con su código y clave'],
                    ['Cuentas abiertas', 'Elige otra cuenta, abre una nueva o cierra las que quedaron sin facturar'],
                    ['SKU y Enter', 'Agrega una unidad de ese producto'],
                    ['Lupa o F1', 'Busca el artículo si no tiene el código a mano'],
                    ['Agregar o tecla +', 'Pide la cantidad (y opciones del producto, si las tiene)'],
                    ['Tab en el código', 'Confirma el artículo y pasa al botón Agregar'],
                    ['Lista de ítems', 'Suma o resta cantidad, escribe un comentario o quita la línea'],
                    ['Facturar o F8', 'Abre la ventana para confirmar el descuento y el cliente VIP'],
                ],
            ],
        ],
        [
            'titulo' => '7. Identificar cliente VIP (beneficiario)',
            'parrafos' => [
                'Sin cliente VIP no se puede facturar. Esa persona es quien recibe el producto. Hay varias formas de indicarla. Use la que tenga a mano.',
                'La lupa que está al lado del nombre busca solo en la lista de Anita (Clientes VIP). No consulta Emita. Para buscar en Emita use los botones Por nombre y Por alias, explicados en el capítulo 9.',
            ],
            'tabla' => [
                'caption' => 'Cómo indicar a la persona',
                'headers' => ['Si usted tiene…', 'Haga esto'],
                'rows' => [
                    ['El número de cliente de Anita', 'Escríbalo en Cód. y pulse Enter'],
                    ['El documento', 'Escríbalo en DNI y pulse Enter'],
                    ['Solo el apellido o el nombre, y ya está en Anita', 'Pulse la lupa, busque y elija Elegir'],
                    ['La tarjeta de la sala', 'Si ve el botón Tarjeta Wigos, pase la tarjeta y pulse Aplicar'],
                    ['El nombre o el apodo, y puede no estar en Anita', 'Use Por nombre o Por alias (capítulo 9)'],
                ],
            ],
            'items' => [
                'En la ventana de facturar (F8), Enter en Cód. o en DNI busca a la persona y, si la encuentra, puede facturar enseguida.',
                'Si no aparece en la lupa, no la invente en el momento: búsquela en Emita o pida el alta en Clientes VIP.',
            ],
        ],
        [
            'titulo' => '8. Consultar clientes VIP en Emita',
            'parrafos' => [
                'Estas dos pantallas sirven para mirar la base de Emita sin facturar. Están en el mismo menú: Ventas → Gastronomía → Canjes → VIP Emita por nombre, y VIP Emita por alias.',
                'En la esquina de cada pantalla hay un botón para pasar a la otra. No son dos búsquedas iguales: cada una muestra un grupo distinto de personas.',
                'Escriba al menos 3 letras o números y pulse Consultar. La búsqueda puede tardar unos segundos: aparece un aviso y no hay que cerrar la página. No distingue mayúsculas ni acentos.',
                'Si hay resultados, puede bajarlos a PDF, Excel o CSV. La exportación usa el mismo texto que acaba de consultar. Si hay muchísimos, el archivo trae los primeros 5.000 y lo avisa en el título.',
            ],
            'tabla' => [
                'caption' => 'Cuándo usar cada consulta',
                'headers' => ['Pantalla', 'Cuándo conviene', 'Qué va a ver'],
                'rows' => [
                    ['VIP Emita por nombre', 'Sabe el nombre, el apellido o un apodo de alguien que ya tiene cuenta de juego', 'Solo cuentas con nombre y con fecha de última visita. El texto puede estar en el nombre o en el apodo.'],
                    ['VIP Emita por alias', 'Solo conoce el apodo, o quiere ver apodos que todavía no tienen cuenta', 'Cuentas con apodo y, al final, apodos sueltos (sin cuenta). Esos apodos sueltos también tienen que tener una visita registrada.'],
                ],
            ],
            'items' => [
                'Sala: en qué sala está cargada la persona.',
                'Origen: Wigos si tiene cuenta de juego. Solo alias si es un apodo sin cuenta.',
                'Cuenta Wigos: el número de cuenta. Vacío en los apodos sueltos.',
                'Nombre y apellido: el titular de la cuenta.',
                'Alias: el apodo.',
                'Nivel tarjeta: la categoría vigente de esa cuenta.',
                'VIP: Sí o No, según Emita. Es un dato informativo. No es lo mismo que estar en la lista Clientes VIP de Anita.',
                'Última visita: el día de la última visita que tiene Emita. Si no hay visita, la persona no aparece.',
            ],
        ],
        [
            'titulo' => '9. Elegir un cliente de Emita al facturar',
            'parrafos' => [
                'En el facturador, dentro del recuadro Descuento y cliente VIP, hay dos botones azules: Por nombre y Por alias. Abren la misma búsqueda del capítulo 8, pero desde ahí puede elegir a la persona para el canje.',
                'Escriba al menos 3 caracteres y pulse Consultar (o Enter). Si hay muchas coincidencias, la ventana muestra las primeras y le pide que escriba un texto más preciso.',
                'Cuando encuentre a la persona correcta, pulse Elegir en esa fila. El nombre queda cargado como cliente VIP y puede seguir con F8.',
                'Si esa persona ya estaba en Clientes VIP de Anita, se usa ese registro. Si no estaba, Anita la da de alta con los datos de Emita (documento, nombre, apodo y sala) y la deja lista para facturar. Usted no tiene que ir a la otra pantalla a crearla.',
            ],
            'items' => [
                'Solo se puede elegir una fila que tenga cuenta de juego y nombre. Si la fila dice Sin cliente Wigos, es un apodo suelto: sirve para consultarlo, pero no para facturar el canje.',
                'La lupa de al lado del nombre sigue buscando la lista de Anita. Los botones Por nombre y Por alias son los únicos que van a Emita.',
                'Si Emita no responde, la ventana muestra el error. Espere un momento y vuelva a consultar. No cargue el canje a nombre de otra persona.',
                'Un texto de una o dos letras no busca: el sistema pide al menos 3 caracteres para no traer a media sala.',
            ],
        ],
        [
            'titulo' => '10. Facturación con F8',
            'parrafos' => [
                'En canjes de marketing solo se factura con descuento. La tecla es F8 (o el botón Facturar). No hay cobro ni pantalla para ingresar efectivo o tarjeta.',
            ],
            'items' => [
                'Tiene que haber al menos un producto cargado, un cliente VIP indicado y la jornada abierta.',
                'F8 abre la ventana Facturar canje marketing. Revise que el descuento sea el que ya viene cargado y que el nombre del VIP sea el correcto.',
                'Pulse Facturar. El sistema emite la factura a Consumidor final, guarda la entrega en el listado y muestra el avance (incluyendo la impresión del ticket, si la PC imprime).',
                'Con el descuento habitual el total queda en un centavo. Es una factura de cortesía: no se cobra.',
            ],
            'tabla' => [
                'caption' => 'Teclas que más se usan',
                'headers' => ['Tecla', 'Qué hace'],
                'rows' => [
                    ['F1', 'Abre la búsqueda de productos'],
                    ['Enter', 'En el código de producto agrega una unidad. En Cód. o DNI busca al VIP. En el ingreso, avanza.'],
                    ['+', 'Pide la cantidad'],
                    ['F8', 'Factura con el descuento'],
                    ['Tab', 'Pasa del código de producto al botón Agregar'],
                ],
            ],
        ],
        [
            'titulo' => '11. Diferencia con los canjes del salón',
            'parrafos' => [
                'En el facturador de gastronomía también hay canjes (premios y tarjetas de fidelidad). No son esta pantalla. Si el cliente llega con un cupón de premio, eso lo hace el salón, no Marketing.',
            ],
            'tabla' => [
                'caption' => 'No mezclar las dos operatorias',
                'headers' => ['', 'Canjes de Marketing', 'Canjes del salón'],
                'rows' => [
                    ['Quién recibe', 'Un cliente VIP', 'Alguien con cupón o tarjeta de fidelidad'],
                    ['Descuento', 'El de marketing (ya cargado)', 'El de premio o fidelidad'],
                    ['Cómo se factura', 'Siempre con F8, sin cobrar', 'Con las reglas del facturador de salón'],
                    ['Quién opera', 'El operador de marketing en esta PC', 'El turno de salón'],
                ],
            ],
        ],
        [
            'titulo' => '12. Listado canjes marketing',
            'captura_id' => 'listado_marketing',
            'parrafos' => [
                'Sirve para ver qué se entregó. Menú: Ventas → Gastronomía → Canjes → Listado canjes marketing.',
                'Primero elija empresa, fechas y, si hace falta, la sala. Pulse Consultar. Al entrar, las fechas suelen venir con el mes en curso.',
            ],
            'items' => [
                'Cada fila es un producto entregado: fecha, empresa, cliente VIP, operador, producto, cantidad, costo, precio de venta y sala.',
                'Los totales de arriba corresponden a todo el filtro, no solo a la página que está viendo.',
                'Los nombres en azul abren el cliente, el operador, el producto o la factura en otra solapa, si su usuario tiene permiso.',
                'PDF, Excel y CSV salen con el mismo filtro. El PDF es apaisado, para que entren las columnas.',
            ],
        ],
        [
            'titulo' => '13. Cuando algo no sale',
            'parrafos' => [
                'La mayoría de los avisos se resuelven sin llamar a sistemas. Lea el mensaje y use esta tabla.',
            ],
            'tabla' => [
                'caption' => 'Aviso en pantalla y qué hacer',
                'headers' => ['Qué aparece', 'Qué hacer'],
                'rows' => [
                    ['No hay punto de venta para esta PC', 'Avisar a sistemas para dar de alta la computadora'],
                    ['Jornada cerrada', 'Pedir a gastronomía que abra la jornada'],
                    ['Cliente VIP no encontrado', 'Probar la lupa de Anita, o Por nombre / Por alias en Emita'],
                    ['Cargue al menos un artículo', 'Agregar el producto antes de F8'],
                    ['Clave incorrecta', 'Verificar el código y la clave con el encargado'],
                    ['Indique al menos 3 caracteres', 'Escribir un nombre o apodo más largo y volver a consultar'],
                    ['Sin cliente Wigos', 'Esa fila es solo un apodo. Buscar la cuenta por nombre o pedir el alta a mano'],
                    ['No se pudo consultar Emita', 'Esperar un momento y reintentar. Si sigue, avisar a sistemas'],
                    ['Error de factura electrónica', 'Reintentar una vez. Si se repite, avisar a sistemas'],
                ],
            ],
        ],
        [
            'titulo' => '14. Rutina de un canje, paso a paso',
            'parrafos' => [
                'Siga este orden la primera vez. Cuando ya lo hizo un par de veces, el mismo recorrido sale solo.',
            ],
            'items' => [
                'Confirme con gastronomía que la jornada está abierta.',
                'Abra Facturador canjes marketing en la computadora del puesto.',
                'Entre con su código y su clave.',
                'Cargue el producto con el código, la lupa o F1.',
                'Indique quién lo recibe: código, DNI, lupa de Anita, tarjeta, o Por nombre / Por alias si hay que buscarlo en Emita.',
                'Si lo eligió desde Emita, controle que el nombre de la fila sea el de la persona que está delante.',
                'Pulse F8, revise descuento y nombre, y facture.',
                'Entregue el producto.',
                'Si quedó una cuenta abierta sin facturar, ciérrela antes de irse.',
                'Para controlar el día, abra el listado y filtre la fecha de hoy.',
            ],
        ],
    ],
];
