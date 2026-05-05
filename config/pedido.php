<?php
// Constantes de pedidos
if (config('app.empresa') == 'EL BIERZO')
    return [
        'impresora_default' => 'BIE_PS_229',
        ];
else
    return [];