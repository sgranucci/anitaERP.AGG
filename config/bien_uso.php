<?php

return [
    /*
     * CC contables habilitados en el ABM (códigos del maestro centrocosto).
     */
    'centrocosto_codigos_abm' => array_values(array_filter(array_map(
        'trim',
        explode(',', env('BIEN_USO_CENTROCOSTO_CODIGOS', '89,92'))
    ))),

    /*
     * CC del rol/usuario → CC de bienes visibles (códigos contables).
     * Laboratorio (93) ve bienes del CC 89 (Máquinas), no los de su propio CC.
     */
    'rol_cc_ve_centrocosto_codigos' => [
        env('BIEN_USO_CC_SISTEMAS', '92') => [env('BIEN_USO_CC_SISTEMAS', '92')],
        env('BIEN_USO_CC_LABORATORIO', '93') => [env('BIEN_USO_CC_MAQUINAS', '89')],
    ],

    'roles_sin_restriccion' => array_values(array_filter(array_map(
        'trim',
        explode(',', env('BIEN_USO_ROLES_SIN_RESTRICCION', 'administrador,Enc-contaduría,Op-contaduria,Enc-impuestos,Enc-admin,Ger-administracion'))
    ))),

    'permiso_ver_todos' => 'listar-bien-uso-todos',
];
