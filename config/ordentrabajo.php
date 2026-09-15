<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Impresión de emisión OT (PDF → JetDirect puerto 9100)
    |--------------------------------------------------------------------------
    |
    | No requiere colas CUPS en el L12: el script envía el PDF directo a la IP
    | de la impresora (mismo mecanismo que Laser PDF Monica/Laura).
    |
    | IPs Ferli (host 160.132.0.254):
    |   hp-diego     → 160.132.0.203
    |   HP4250GABY   → 160.132.0.183
    |   P1 / Monica  → 160.132.0.201
    |   hp1300/Laura → 160.132.0.200
    |
    */
    'imprimir_script' => env('OT_IMPRIMIR_SCRIPT', base_path('bin/imprimir-pdf-laser.sh')),
];
