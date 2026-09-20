<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Purga de la tabla audits
    |--------------------------------------------------------------------------
    |
    | audits es la tabla más grande de la base (4,9 GB) y crece ~1 M filas/mes.
    | Esta purga borra por fecha, en lotes, con retención distinta según el modelo.
    |
    | La retención por defecto es corta (6 meses) a propósito: la mayoría del volumen
    | es ruido operativo. Por eso TODO lo que respalde plata, un documento fiscal o un
    | permiso tiene que estar listado abajo con retención larga; si no, se purga a los
    | 6 meses. Al agregar un modelo nuevo auditable, decidir en qué grupo cae.
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

    /** Retención por defecto para cualquier modelo no listado abajo. */
    'retencion_meses' => max(1, (int) env('AUDITS_PURGA_RETENCION_MESES', 6)),

    /** Filas por lote. Un DELETE único de millones de filas bloquea e infla el binlog. */
    'lote' => max(100, (int) env('AUDITS_PURGA_LOTE', 5000)),

    /** Tope de filas por corrida, para que una purga agendada no se desmadre. */
    'max_por_corrida' => max(1000, (int) env('AUDITS_PURGA_MAX', 500000)),

    /** Pausa en milisegundos entre lotes, para no ahogar la réplica ni el disco. */
    'pausa_ms' => max(0, (int) env('AUDITS_PURGA_PAUSA_MS', 200)),

    /**
     * Retención en meses por modelo. La clave es el auditable_type completo, tal como
     * se guarda en la tabla.
     */
    'retencion_por_modelo' => [

        /*
         * 10 años — respaldo fiscal, societario y de seguridad.
         *
         * Para varias de estas tablas el audit es la ÚNICA traza que queda de un borrado
         * físico, porque la migración 2026_08_18_153000 les quitó los softdeletes.
         */
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

        // Comprobantes de venta y cobranzas: documentación respaldatoria ante AFIP.
        App\Models\Ventas\Venta::class => 120,
        App\Models\Caja\Cobranza::class => 120,

        // Factura de compra: es el documento que respalda cada pago.
        App\Models\Compras\Comprobante_Proveedor::class => 120,

        // Usuarios y permisos: quién habilitó a quién y cuándo.
        App\Models\Seguridad\Usuario::class => 120,

        // Árbol de aprobaciones: respalda quién autorizó cada compra y cada pago. Va
        // también la configuración del árbol, porque cambiar un umbral de aprobación
        // habilita un pago igual que firmarlo.
        App\Models\Configuracion\Arbolaprobacion_Movimiento::class => 120,
        App\Models\Configuracion\Arbolaprobacion::class => 120,
        App\Models\Configuracion\Arbolaprobacion_Nivel::class => 120,
        App\Models\Configuracion\Arbolaprobacion_CuentaExcepcion::class => 120,
        App\Models\Configuracion\Arbolaprobacion_OcTrigger::class => 120,

        // Prevención de lavado (UIF): requisito regulatorio.
        App\Models\Uif\Cliente_Uif::class => 120,
        App\Models\Uif\Cliente_Premio_Uif::class => 120,
        App\Models\Uif\Cliente_Riesgo_Uif::class => 120,

        /*
         * 3 años — presupuestario y aprobaciones de inversión: se consultan entre
         * ejercicios, pero no son respaldo fiscal.
         */
        App\Models\Presupuesto\Capex::class => 36,
        App\Models\Presupuesto\Capex_Partida::class => 36,
        App\Models\Presupuesto\Capex_Partida_Monto::class => 36,
        App\Models\Presupuesto\Partidagasto::class => 36,

        /*
         * 3 meses — alta rotación. Todos estos ya pasaron a auditar solo
         * updated/deleted, así que lo que queda para purgar es el backlog de audits
         * de 'created' que se venían escribiendo de más.
         */
        App\Models\Ventas\CuentaGastronomia::class => 3,
        App\Models\Ventas\CuentaGastronomiaLinea::class => 3,
        App\Models\Ventas\WaitryComandaEnvio::class => 3,
        App\Models\Presupuesto\Partidagasto_Monto::class => 3,
        App\Models\Presupuesto\Partidagasto_Estado::class => 3,
        App\Models\Ventas\Venta_Impuesto::class => 3,
        App\Models\Ventas\Venta_Emision::class => 3,
        App\Models\Caja\Cobranza_Estado::class => 3,
        App\Models\Compras\Requisicion_Estado::class => 3,

        // auditable_type es un string guardado: si un modelo cambia de namespace, las
        // filas viejas quedan con el nombre anterior y hay que listarlo aparte o nunca
        // se purgan. CuentaGastronomia se movió de Stock a Ventas.
        'App\Models\Stock\CuentaGastronomia' => 3,
        'App\Models\Stock\CuentaGastronomiaLinea' => 3,
    ],

    /**
     * Retención por patrón, para familias enteras de modelos. Se evalúa recién si el
     * auditable_type no está en la lista exacta de arriba, y gana el primer patrón que
     * coincida.
     *
     * Existe porque la lista exacta no protege lo que se agregue mañana: hay 208
     * auditable_type distintos en la tabla y varios sensibles son chicos y fáciles de
     * pasar por alto (Pais_Uif, Localidad_Uif, Rol). Con la retención por defecto en 6
     * meses, olvidarse de uno lo purga en silencio.
     */
    'retencion_por_patron' => [
        // Todo el régimen UIF (Cliente, Pais, Provincia, Localidad, Factorriesgo, Puntaje).
        '/Uif$/' => 120,

        // Cuentas corrientes de clientes y proveedores: saldos y aplicaciones.
        '/Cuentacorriente/' => 120,

        /*
         * Familia Proveedor: además del CBU y el CUIT en Proveedor, acá entra
         * Proveedor_Formapago, que define CÓMO se le paga. Cambiar eso desvía plata igual
         * que cambiar el CBU, así que el rastro se guarda 10 años.
         *
         * El \\ inicial ancla el patrón al nombre de la clase y no al namespace: así
         * Listaprecio_Proveedor_Articulo y Comprobante_Proveedor NO caen acá (el
         * comprobante tiene su propia regla exacta arriba).
         */
        '/\\\\Proveedor(_|$)/' => 120,

        // Roles y permisos.
        '/\\\\(Rol|Permiso)(_|$)/' => 120,
    ],

];
