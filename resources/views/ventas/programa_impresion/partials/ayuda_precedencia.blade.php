<div class="alert alert-info mb-3" role="note">
    <div class="font-weight-bold mb-1">C&oacute;mo se elige el programa al imprimir</div>
    <p class="mb-2 small">
        Primero se miran los programas de la <strong>empresa del comprobante</strong>
        (y los de &laquo;todas las empresas&raquo;). Entre esos, gana la regla m&aacute;s espec&iacute;fica:
    </p>
    <ol class="mb-2 small pl-3">
        <li><strong>Provincia de entrega</strong></li>
        <li><strong>Transporte / reparto</strong></li>
        <li><strong>Default</strong> (el resto de casos de esa empresa)</li>
    </ol>
    <p class="mb-1 small">
        <strong>Ejemplo El Bierzo:</strong> el programa <em>Est&aacute;ndar</em> lleva empresa El Bierzo + regla Default.
        El de <em>Tucum&aacute;n</em> lleva la misma empresa + provincia Tucum&aacute;n.
        Un recorrete especial lleva la misma empresa + ese reparto.
        No hace falta listar todos los repartos en el est&aacute;ndar: Default cubre &laquo;todo lo que no matche&oacute; otra regla&raquo;.
    </p>
    <p class="mb-2 small">
        Si un reparto especial entrega en Tucum&aacute;n, gana la provincia.
        En AGG o en la nube, cada empresa (Biyemas, Kandiko, etc.) tiene sus propios programas.
    </p>
    <p class="mb-0 small">
        <strong>Impresora:</strong> en las copias de papel dej&aacute; <em>Impresora del usuario</em>
        para que cada operador (Omard, Daniela, etc.) imprima en su cola desde la sesi&oacute;n.
        Si fij&aacute;s una impresora en la copia, todos salen por esa misma cola.
        NAS / archivo sigue con salida fija del programa.
    </p>
</div>
