<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Purga de la tabla audits
    |--------------------------------------------------------------------------
    |
    | audits es la tabla más grande de la base (4,9 GB) y crece ~1,2 M filas/mes.
    | Esta purga borra por fecha, en lotes, con retención distinta según el modelo:
    | un audit de pagoproveedor respalda un documento fiscal, uno de una comanda de
    | Waitry es ruido operativo. Una retención plana obliga a elegir entre tirar lo
    | que importa o guardar lo que no.
    |
    | Arranca DESHABILITADA: activar con AUDITS_PURGA_HABILITADA=true + config:clear.
    |
    | OJO: el DELETE de InnoDB no devuelve el espacio al sistema operativo, deja
    | páginas libres que se reusan. Esto frena el crecimiento; para recuperar los
    | GB ya ocupados hace falta OPTIMIZE TABLE (reconstruye y bloquea) o pasar la
    | tabla a particiones por rango de created_at y usar DROP PARTITION.
    |
    */

    'habilitada' => filter_var(env('AUDITS_PURGA_HABILITADA', false), FILTER_VALIDATE_BOOLEAN),

    /** Retención por defecto, para cualquier modelo no listado abajo. */
    'retencion_meses' => max(1, (int) env('AUDITS_PURGA_RETENCION_MESES', 24)),

    /** Filas por lote. Un DELETE único de millones de filas bloquea e infla el binlog. */
    'lote' => max(100, (int) env('AUDITS_PURGA_LOTE', 5000)),

    /** Tope de filas por corrida, para que una purga agendada no se desmadre. */
    'max_por_corrida' => max(1000, (int) env('AUDITS_PURGA_MAX', 500000)),

    /** Pausa en milisegundos entre lotes, para no ahogar la réplica ni el disco. */
    'pausa_ms' => max(0, (int) env('AUDITS_PURGA_PAUSA_MS', 200)),

    /**
     * Retención en meses por modelo. La clave es el auditable_type completo.
     *
     * Respaldo de documentos fiscales y societarios: 10 años. AFIP puede pedir
     * documentación respaldatoria por varios ejercicios y estos audits son la
     * única traza de un borrado físico (esas tablas ya no tienen softdeletes).
     */
    'retencion_por_modelo' => [
        App\Models\Compras\Pagoproveedor::class => 120,
        App\Models\Compras\Pagoproveedor_Retencion::class => 120,
        App\Models\Compras\Pagoproveedor_Comprobante::class => 120,
        App\Models\Compras\Pagoproveedor_Estado::class => 120,
        App\Models\Compras\Proveedor_Cuentacorriente::class => 120,
        App\Models\Compras\PropuestaPago::class => 120,
        App\Models\Contable\Asiento::class => 120,
        App\Models\Contable\Asiento_Movimiento::class => 120,
        App\Models\Caja\Caja_Movimiento::class => 120,
        App\Models\Caja\Caja_Movimiento_Cuentacaja::class => 120,
        App\Models\Caja\Caja_Movimiento_Estado::class => 120,

        // Prevención de lavado (UIF): se conserva largo por requisito regulatorio.
        App\Models\Uif\Cliente_Premio_Uif::class => 120,
        App\Models\Uif\Cliente_Riesgo_Uif::class => 120,

        // Ruido operativo de alta rotación: con unos meses alcanza.
        App\Models\Ventas\CuentaGastronomia::class => 6,
        App\Models\Ventas\CuentaGastronomiaLinea::class => 6,
        App\Models\Ventas\WaitryComandaEnvio::class => 3,

        // auditable_type es un string guardado: si un modelo cambia de namespace, las filas
        // viejas quedan con el nombre anterior y hay que listarlo aparte o nunca se purgan.
        // CuentaGastronomia se movió de Stock a Ventas.
        'App\Models\Stock\CuentaGastronomia' => 6,
        'App\Models\Stock\CuentaGastronomiaLinea' => 6,
    ],

];
