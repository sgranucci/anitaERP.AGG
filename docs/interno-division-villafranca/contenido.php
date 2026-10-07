<?php

/**
 * Ejemplar completo para el NAS. Incluye el manual de la mesa de ayuda
 * y la división Villafranca (coeficiente, reparto 101, COT, SENASA, remito Z).
 * No registrar este archivo en el Centro de ayuda.
 */
$base = require dirname(__DIR__).'/manual-facturacion-bierzo/contenido.php';

$base['titulo'] = 'Manual completo';
$base['subtitulo'] = 'El Bierzo — Facturación, remitos, COT, certificados y división Villafranca';
$base['version'] = '1.0';

$insertar = [];

$insertar['2. Antes de facturar: el cliente'] = [[
    'titulo' => 'Coeficiente de división del cliente',
    'parrafos' => [
        'En El Bierzo el cliente puede tener un coeficiente de división. Se carga en Ventas → Coeficientes y se elige en la ficha del cliente. El porcentaje de división (0 a 100) es la parte de la venta que va a Villafranca cuando el reparto es de tipo Divide. El resto queda en El Bierzo. La tasa del coeficiente es el IVA de la factura Villafranca, no la tasa del artículo.',
        'El coeficiente extra de la ficha es de solo lectura. Sale de la instalación (CLIENTE_COEFICIENTE_EXTRA) y el valor de fábrica es 1,05. Multiplica el precio del lado Villafranca en un reparto Divide. No es el 1,10 del reparto 101: ese 1,10 es fijo del tipo Solo Remito y no se lee del cliente.',
        'El selector del coeficiente en el cliente se ve con el permiso cargar-coeficiente-cliente. Si el coeficiente tiene código Anita mayor a cero, al guardar el cliente se actualiza climae en Anita Villafranca (/usr2/villafranca).',
        'Si el transporte es Divide y el cliente no tiene coeficiente, no hay aviso: sale una sola factura de El Bierzo al 100 %.',
    ],
    'tabla' => [
        'caption' => 'Datos del coeficiente',
        'headers' => ['Dato', 'Efecto'],
        'rows' => [
            ['Porc. de división', 'Porcentaje que va a Villafranca en un reparto Divide. 30 deja 70 en Bierzo y 30 en Villafranca.'],
            ['Tasa', 'IVA de la factura Villafranca. Si el artículo es exento, el neto sigue exento.'],
            ['Coeficiente extra (1,05)', 'Precio Villafranca del Divide. No aplica al reparto 101.'],
            ['1,10 del reparto 101', 'Precio de la factura Villafranca cuando el transporte es Solo Remito. Ignora el porcentaje del cliente.'],
            ['Artículo NO DIVIDE', 'Esa línea queda entera en Bierzo y no entra a la factura Villafranca.'],
        ],
    ],
]];

$insertar['5. Facturar un pedido'] = [[
    'titulo' => 'División al facturar el pedido',
    'parrafos' => [
        'La división no mira el código «101». Mira el tipo de expreso del transporte. Completo y Normal facturan una sola factura de El Bierzo. Divide (valor 3) parte con el porcentaje del cliente. Solo Remito (valor 4) manda el 100 % de la factura a Villafranca: es el circuito del reparto 101. Solo se parte en tipos de transacción 001 y 201.',
        'En un Divide, kilos, piezas y cajas de cada lado se redondean a un decimal. Bierzo = cantidad por (100 − porcentaje) / 100, al precio del pedido, con el descuento de pie. Villafranca = cantidad por el porcentaje / 100, precio por el extra del cliente (1,05 por defecto), sin descuento de pie, vencimiento el mismo día de la factura, punto de venta 00015 y el mismo número que la factura de Bierzo. Ejemplo visible: FAC A-00010-00001234 en Bierzo y FAC A-00015-00001234 en Villafranca. La de Villafranca no pide CAE: es modo manual y se graba en Anita /usr2/villafranca. En esa factura no hay percepciones de ingresos brutos, ni percepción de IVA, ni abasto. El IVA es la tasa del coeficiente.',
        'El operador no ve la factura Villafranca en el mensaje de éxito ni en los botones del pedido. El remito del ERP, en un Divide, lleva las cantidades de Bierzo y queda facturado contra esa factura. Si el porcentaje es 100 en un Divide, no hay factura de Bierzo: el remito lleva el total y la factura sale en el 00015.',
        'Al grabar el transporte en Anita, el tipo Solo Remito se manda como expr_tipo_expr = 3. En el ERP tiene que seguir en 4. Si alguien lo deja en 3, deja de ser un 101 y pasa al circuito del coeficiente (punto 00015 y número copiado).',
    ],
    'tabla' => [
        'caption' => 'Dos circuitos de división',
        'headers' => ['Dato', 'Divide (coeficiente del cliente)', 'Solo Remito (reparto 101)'],
        'rows' => [
            ['Qué parte las cantidades', 'porcentajedivision del cliente', 'Siempre 100 % a Villafranca'],
            ['Precio Villafranca', 'Precio del pedido × extra del cliente (1,05)', 'Precio del pedido × 1,10'],
            ['Factura El Bierzo', 'Sí, si el porcentaje es menor a 100', 'No. En Bierzo queda el remito'],
            ['Punto de venta Villafranca', '00015', '00001 de la empresa Villafranca'],
            ['Número', 'Copia el de la factura Bierzo', 'Numerador propio, sucursal 1, en Anita Villafranca'],
            ['Qué muestra el OK', 'La factura de Bierzo y su remito', 'El remito. La factura Villafranca no se muestra'],
        ],
    ],
], [
    'titulo' => 'Reparto 101',
    'parrafos' => [
        'Cualquier transporte con tipo de expreso Solo Remito se factura como el 101, aunque el código no sea 101. Usa el punto de venta 00001 de Villafranca (id 9), el numerador FAC de sucursal 1 en /usr2/villafranca y el coeficiente 1,10. El 00001 de remito de Bierzo es otro punto, de otra empresa.',
        'Secuencia al facturar el pedido: se reserva el número de factura en Anita Villafranca; el remito de Bierzo sale con el 100 % de kilos, piezas y cajas al precio de lista (sin el 1,10) y en pendmae queda la referencia penm_ref_tipo FAC, letra, sucursal 1 y ese número; después se emite la factura en el 00001 con el número ya reservado, precios por 1,10, sin descuento de pie y vencimiento igual a la fecha. El remito queda vinculado a esa factura en el ERP. En Anita Bierzo no hay factura hermana: por eso el remito se considera huérfano del lado Bierzo.',
        'Facturar un remito cuyo transporte es Solo Remito repite el criterio: punto 00001, sucursal 1, precio por 1,10, y conserva el número de remito que ya tenía.',
        'En el PDF del pedido, neto, recargo y total aparecen solo en este tipo, cuando ya hay factura Villafranca. Neto = total de esa factura / 1,10. Un Divide no imprime ese bloque. En el PDF del remito el ajuste es el 10 % sobre el neto de las líneas.',
    ],
    'items' => [
        'Para otro reparto del mismo tipo: Ventas → Transportes, tipo de expreso Solo Remito (4). El código Anita es el número de reparto; el sistema no lo compara con 101.',
        'No hace falta otro punto de venta ni otra clave. Todos los Solo Remito comparten el 00001, el numerador de sucursal 1 y el 1,10. Cambiar esas claves mueve todos, no uno solo.',
        'El cliente no necesita coeficiente. Si lo tiene, en este transporte se ignora el porcentaje. El precio igual se multiplica por 1,10.',
        'Tiene que existir el punto Villafranca Reparto 101, código 00001, y en /usr2/villafranca un compemis de FAC por cada letra (A, B, …) con sucursal 1. El punto de remito de Bierzo tiene que tener numerador REM R.',
        'Prueba: el OK muestra el remito. En pendmae de Bierzo, penm_ref_tipo es FAC, sucursal 1. En Villafranca la factura queda en 00001 con precios por 1,10.',
    ],
]];

$agregarParrafos = [
    '7. Pedido del cliente DESPACHO y stock del depósito' => [
        'Este pedido no entra en la división ni en el reparto 101: no hay factura de Bierzo ni de Villafranca. El stock se mueve entre depósitos del ERP. El destino es el depósito cargado en el transporte, no el punto de venta 00015 ni el 00001.',
    ],
    '9. Remito Z (asignar kilos con F5)' => [
        'El origen de esos kilos es Anita Villafranca (/usr2/villafranca), tablas comprob y compaux del día, filtradas por el código del reparto. Facturas y notas de débito suman. Notas de crédito restan. No entran remitos ni cobranzas. Se excluyen el SKU vacío, los que empiezan con «texto» y el código 0000000000903. El porcentaje del cuadro es lo que se descuenta, a un decimal: 0 deja el 100 % de esos kilos. El remito que se guarda es un REM letra R de El Bierzo. F5 no crea la factura de Villafranca: esa factura ya está del otro lado.',
    ],
    '12. Asignar un remito a una factura ya emitida' => [
        'La consulta es de una empresa por vez para no mezclar el punto 00015 de Villafranca con El Bierzo. Un pedido 101 ya facturado quedó vinculado a la factura Villafranca y no aparece como huérfano. Sí aparecen remitos de F5, o de un Solo Remito, que todavía no tienen factura.',
    ],
    '13. COT electrónico (ARBA)' => [
        'El COT no lee Anita Villafranca. Toma remitos de Anita Bierzo y del ERP. Una factura que solo exista en Villafranca no entra, salvo que el remito esté en Bierzo. No parte kilos: el importe es el de la factura o del remito ya emitido, sin IVA. Excluye el remito marcado penm_ref_tipo Z, que es el que se arma con F5. El remito del reparto 101 sí puede presentarse: el COT sale de ese remito de Bierzo, no de la factura Villafranca. La referencia FAC del 101 no lo saca de la lista.',
    ],
    '14. Certificado sanitario SENASA' => [
        'El certificado tampoco lee Anita Villafranca. El recorte de cantidades aplica solo si el transporte es Divide. Entra la parte Bierzo: kilos, cajas y piezas por (100 − porcentaje) / 100, sin el redondeo a un decimal de la factura. Un 30 % de división deja el 70 % en el certificado. El 30 % no sale en otro XML. Porcentaje 100 en un Divide: la línea no entra. Sin coeficiente, o coeficiente 0, entran los kilos enteros del pedido.',
        'El reparto 101 (Solo Remito) no recorta el certificado y no aplica el 1,10. Si el pedido está en el ERP, los kilos entran enteros. El destino SENASA es la zona de venta, no un destino llamado Villafranca. Si el pedido ya está en el ERP, mandan el reparto y las cantidades del ERP (ya con el recorte del Divide). Cambiar el expreso solo en Anita no mueve el certificado.',
    ],
];

$secciones = $base['secciones'];
$salida = [];
foreach ($secciones as $sec) {
    $titulo = (string) ($sec['titulo'] ?? '');
    if (isset($agregarParrafos[$titulo])) {
        $sec['parrafos2'] = array_merge($sec['parrafos2'] ?? [], $agregarParrafos[$titulo]);
    }
        if ($titulo === '16. Errores frecuentes de facturación') {
        $sec['tabla2'] = [
            'caption' => 'Mensajes de la división',
            'headers' => ['Mensaje', 'Causa'],
            'rows' => [
                ['No está configurado el punto de venta Villafranca sucursal 1 para el reparto 101.', 'Transporte Solo Remito y no se resuelve el punto 00001.'],
                ['No se pudo reservar FAC {letra} sucursal 1 en Anita Villafranca.', 'Falta compemis o falla el numerador en /usr2/villafranca.'],
                ['El punto de venta de remito no tiene numerador configurado en Anita.', 'Falta REM R en el Anita de Bierzo.'],
                ['No pudo actualizar el numerador del remito en Anita.', 'El número del remito no coincidió con el ya reservado.'],
                ['No hay comprobantes del día en Villafranca para ese reparto.', 'F5 no encontró FAC, ND o NC de hoy para ese código.'],
            ],
        ];
    }
    if ($titulo === '17. Permisos') {
        $sec['tabla']['rows'][] = ['cargar-coeficiente-cliente', 'Ver y cambiar el coeficiente de división en la ficha del cliente.'];
    }
    $salida[] = $sec;
    foreach ($insertar[$titulo] ?? [] as $extra) {
        $salida[] = $extra;
    }
}

$salida[] = [
    'titulo' => 'Reportes, nota de crédito e IVA',
    'parrafos' => [
        'Los reportes de kilos por pedido, por categoría y por vendedor suman las cantidades del pedido, sin prorratear Villafranca. El listado de pedidos oculta las facturas de los puntos de división al contar facturas por reparto.',
        'La nota de crédito no emite las dos patas solas. Se hace de mostrador, en el punto que elija el operador. Si ese punto es de Villafranca, Anita graba en /usr2/villafranca, sin descuento de pie, con vencimiento igual a la fecha y sin ingresos brutos. Si la factura Villafranca tiene origen en la de Bierzo, la nota de crédito hereda esa factura de Bierzo. El 101 no tiene factura de Bierzo que heredar, salvo que el origen ya esté cargado.',
        'El libro de IVA ventas lista el ERP de la empresa elegida y no lee /usr2/villafranca. La factura dividida entra en el libro de Villafranca si el punto tiene el tilde de IVA ventas. La migración del punto 00001 no marca ese tilde.',
    ],
    'tabla' => [
        'caption' => 'Claves fijas en config/facturacion.php (caso EL BIERZO)',
        'headers' => ['Clave', 'Valor'],
        'rows' => [
            ['PUNTOVENTA_DIVISION_ID', '5, sucursal 00015, factura del Divide'],
            ['PUNTOVENTA_DIVISION_REPARTO_101_ID / CODIGO', '9 y 00001, factura del Solo Remito'],
            ['VILLAFRANCA_NUMERADOR_SUCURSAL', '1'],
            ['COEFICIENTE_EXTRA_REPARTO_101', '1.10'],
            ['Factura Bierzo', '00008 prueba, 00010 producción'],
            ['Remito Bierzo', '00099 prueba, 00001 producción (no es el 00001 de Villafranca)'],
            ['Path Anita Villafranca', '/usr2/villafranca'],
        ],
    ],
];

$n = 1;
foreach ($salida as $i => $sec) {
    $limpio = preg_replace('/^\d+\.\s*/', '', (string) $sec['titulo']) ?? (string) $sec['titulo'];
    $salida[$i]['titulo'] = $n.'. '.$limpio;
    $n++;
}

$base['secciones'] = $salida;

return $base;
