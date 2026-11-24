<?php

declare(strict_types=1);

return [
    /*
    | Complemento Anita (retmov / retimov) al armar el TXT de compras.
    | Apagado: solo pagoproveedor_retencion del ERP (incluye OP anuladas + NC del AOP).
    */
    'complemento_anita' => filter_var(
        env('SICORE_COMPLEMENTO_ANITA', false),
        FILTER_VALIDATE_BOOLEAN
    ),
];
