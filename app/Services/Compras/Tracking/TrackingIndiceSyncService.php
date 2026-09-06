<?php

namespace App\Services\Compras\Tracking;

use App\Models\Compras\Comprobante_Proveedor;
use App\Support\Compras\Tracking\TrackingPagoEstado;
use App\Support\Compras\Tracking\TrackingPdfReferencia;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Puebla `comprobante_tracking_indice` con lo que el puente Anita sabe y el ERP no.
 *
 * El listado del tracking necesita tres datos que no salen de columnas propias:
 * si hay PDF y dónde, cuándo se cargó de verdad el comprobante y si está pagado.
 * Los tres requieren ir al Anita, que responde en cientos de milisegundos por
 * lote: aceptable en un proceso batch, inviable por fila en una grilla.
 *
 * Por eso el índice se sincroniza por lotes (nocturno o a demanda) y la grilla
 * lo lee como una tabla más, con índices propios para filtrar y ordenar.
 */
class TrackingIndiceSyncService
{
    /** Fecha de escaneo en el Anita: el único dato real para lo importado. */
    public const FECHACARGA_SCAN_ANITA = 'scan_anita';

    /** Recepción del mail que originó la precarga IA. */
    public const FECHACARGA_PRECARGA = 'precarga';

    /** Alta en el ERP: sólo sirve para lo cargado nativamente. */
    public const FECHACARGA_ERP = 'erp';

    private const RELACIONES = [
        'proveedores',
        'tipotransaccion_compras',
        'empresas',
        'comprobante_proveedor_archivos',
        'precarga_comprobante_proveedores',
    ];

    public function __construct(
        private readonly TrackingPdfResolverService $pdf,
        private readonly TrackingPagoResolverService $pago,
    ) {}

    /**
     * Sincroniza en lotes todos los comprobantes que cumplan el filtro.
     *
     * @param  callable(int, int): void|null  $progreso  recibe (procesados, total)
     * @param  int|null  $limite  corta el recorrido después de N comprobantes
     * @param  bool  $soloPagos  no resuelve el PDF (ver sincronizarLote)
     * @return array{procesados: int, con_pdf: int, sin_pdf: int, con_op: int, con_deuda: int}
     */
    public function sincronizar(
        int $tamanoLote = 200,
        ?int $comprobanteId = null,
        bool $soloFaltantes = false,
        ?callable $progreso = null,
        ?int $limite = null,
        bool $soloPagos = false,
    ): array {
        $query = Comprobante_Proveedor::query()->with(self::RELACIONES)->orderBy('id');

        if ($comprobanteId !== null && $comprobanteId > 0) {
            $query->where('id', $comprobanteId);
        }

        if ($soloFaltantes) {
            $query->whereNotExists(
                fn ($q) => $q->selectRaw('1')
                    ->from('comprobante_tracking_indice as i')
                    ->whereColumn('i.comprobante_proveedor_id', 'comprobante_proveedor.id')
            );
        }

        // Sin fila en el índice no hay nada que actualizar: el pase de pagos
        // completa lo ya indexado, no lo crea.
        if ($soloPagos) {
            $query->whereExists(
                fn ($q) => $q->selectRaw('1')
                    ->from('comprobante_tracking_indice as i')
                    ->whereColumn('i.comprobante_proveedor_id', 'comprobante_proveedor.id')
            );
        }

        $total = $query->clone()->count();
        if ($limite !== null) {
            $total = min($total, $limite);
            $tamanoLote = min($tamanoLote, $limite);
        }

        $stats = ['procesados' => 0, 'con_pdf' => 0, 'sin_pdf' => 0, 'con_op' => 0, 'con_deuda' => 0];

        $query->chunkById($tamanoLote, function (Collection $lote) use (&$stats, $total, $progreso, $limite, $soloPagos) {
            $resultado = $this->sincronizarLote($lote, $soloPagos);

            foreach ($resultado as $clave => $valor) {
                $stats[$clave] += $valor;
            }

            if ($progreso !== null) {
                $progreso($stats['procesados'], $total);
            }

            // `false` corta el recorrido de chunkById.
            return $limite === null || $stats['procesados'] < $limite;
        });

        return $stats;
    }

    /**
     * Resuelve un lote y lo graba en el índice.
     *
     * Con `$soloPagos` se saltea la resolución del PDF y se actualizan nada más
     * que las columnas de pago. Es lo que hace útil el pase corto: el costo de
     * la sincronización está casi todo en `scanfactura`, que hay que consultar
     * comprobante por comprobante para encontrar el escaneo, mientras que el
     * estado de pago sale de dos consultas por lote. Sobre el histórico
     * completo la diferencia es de horas a minutos.
     *
     * @param  Collection<int, Comprobante_Proveedor>  $lote
     * @return array{procesados: int, con_pdf: int, sin_pdf: int, con_op: int, con_deuda: int}
     */
    public function sincronizarLote(Collection $lote, bool $soloPagos = false): array
    {
        if ($lote->isEmpty()) {
            return ['procesados' => 0, 'con_pdf' => 0, 'sin_pdf' => 0, 'con_op' => 0, 'con_deuda' => 0];
        }

        $pdfs = $soloPagos ? [] : $this->pdf->resolverLote($lote);
        $pagos = $this->pago->resolverLote($lote);

        $filas = [];
        $stats = ['procesados' => 0, 'con_pdf' => 0, 'sin_pdf' => 0, 'con_op' => 0, 'con_deuda' => 0];
        $ahora = now();

        foreach ($lote as $comprobante) {
            $id = (int) $comprobante->id;
            $referencia = $pdfs[$id] ?? null;
            $pago = $pagos[$id] ?? TrackingPagoEstado::sinDato();

            $fila = [
                'comprobante_proveedor_id' => $id,
                'pago_estado' => $pago->estado,
                'pago_origen' => $pago->origen !== '' ? $pago->origen : null,
                'pago_monto' => $pago->monto,
                'pago_pagado' => $pago->pagado,
                'pago_saldo' => $pago->saldo,
                'pago_fecha' => $pago->fechaPago,
                'pago_op_referencia' => $pago->opReferencia,
                'pago_op_cantidad' => $pago->opCantidad,
                'pago_op_id' => $pago->opId,
                'updated_at' => $ahora,
            ];

            if (! $soloPagos) {
                [$fechaCarga, $origenFecha] = $this->fechaCargaEfectiva($comprobante, $referencia);

                $fila += [
                    'pdf_origen' => $referencia?->origen,
                    'pdf_documento_id' => $referencia?->documentoId,
                    'pdf_archivo_id' => $referencia?->archivoId,
                    'pdf_ruta' => $referencia?->ruta,
                    'pdf_disponible' => $referencia !== null,
                    'fechacarga_efectiva' => $fechaCarga,
                    'fechacarga_origen' => $origenFecha,
                    'sincronizado_at' => $ahora,
                    'created_at' => $ahora,
                ];
            }

            $filas[] = $fila;

            $stats['procesados']++;
            $referencia !== null ? $stats['con_pdf']++ : $stats['sin_pdf']++;
            if ($pago->opReferencia !== null) {
                $stats['con_op']++;
            }
            if (in_array($pago->estado, TrackingPagoEstado::conDeuda(), true)) {
                $stats['con_deuda']++;
            }
        }

        // El pase de pagos no toca `sincronizado_at`: ese sello dice cuándo se
        // resolvió el PDF, y es lo que distingue «sin PDF» de «sin resolver».
        $actualizables = [
            'pago_estado', 'pago_origen', 'pago_monto', 'pago_pagado', 'pago_saldo', 'pago_fecha',
            'pago_op_referencia', 'pago_op_cantidad', 'pago_op_id', 'updated_at',
        ];

        if (! $soloPagos) {
            $actualizables = array_merge($actualizables, [
                'pdf_origen', 'pdf_documento_id', 'pdf_archivo_id', 'pdf_ruta', 'pdf_disponible',
                'fechacarga_efectiva', 'fechacarga_origen', 'sincronizado_at',
            ]);
        }

        DB::table('comprobante_tracking_indice')->upsert(
            $filas,
            ['comprobante_proveedor_id'],
            $actualizables
        );

        return $stats;
    }

    /**
     * Fecha en que el comprobante entró realmente al circuito, y de dónde sale.
     *
     * `created_at` no sirve para el histórico: la importación masiva le puso a
     * casi veinte mil comprobantes la misma fecha, la del día de la migración.
     * Con eso, la búsqueda «cargados entre fechas» —la que más se usaba en el
     * informe viejo— colapsaría todo el histórico en un solo día. La fecha de
     * escaneo del Anita es la que refleja el momento real de carga.
     *
     * @return array{0: string|null, 1: string|null}
     */
    private function fechaCargaEfectiva(
        Comprobante_Proveedor $comprobante,
        ?TrackingPdfReferencia $referencia,
    ): array {
        $candidatos = [
            [$referencia?->fechaScan, self::FECHACARGA_SCAN_ANITA],
            [$comprobante->precarga_comprobante_proveedores?->fecharecepcionemail, self::FECHACARGA_PRECARGA],
            [$comprobante->precarga_comprobante_proveedores?->created_at, self::FECHACARGA_PRECARGA],
            [$comprobante->fecharecepcion, self::FECHACARGA_ERP],
            [$comprobante->created_at, self::FECHACARGA_ERP],
        ];

        foreach ($candidatos as [$valor, $origen]) {
            $fecha = self::normalizarFecha($valor);
            if ($fecha !== null) {
                return [$fecha, $origen];
            }
        }

        return [null, null];
    }

    /**
     * Recorta a fecha ISO y descarta lo que no es una fecha usable.
     *
     * Se descarta por los dos extremos y no sólo por abajo:
     *
     * - Hay precargas con `fecharecepcionemail` en la fecha cero de MySQL
     *   ('0000-00-00'), que no es NULL y por lo tanto pasa cualquier chequeo de
     *   nulidad: sin validar el rango la grilla muestra «30/11/-0001».
     * - Y hay `scanfactura.ifecha` tipeadas a mano con el año equivocado, que
     *   llegan a 2102. Una fecha de carga futura no existe, y además rompe la
     *   búsqueda por rango: el comprobante no aparece en ningún período real.
     *
     * Devolver null hace que se pruebe el candidato siguiente, así que el
     * comprobante termina con la fecha de la precarga o del alta en el ERP en
     * vez de quedarse sin fecha.
     */
    private static function normalizarFecha(mixed $valor): ?string
    {
        if ($valor === null) {
            return null;
        }

        $fecha = substr(trim((string) $valor), 0, 10);
        if (! preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $fecha, $partes)) {
            return null;
        }

        [, $anio, $mes, $dia] = $partes;

        // El ERP arrancó bastante después de 2000; nada anterior es un dato real.
        if ((int) $anio < 2000 || ! checkdate((int) $mes, (int) $dia, (int) $anio)) {
            return null;
        }

        // Un día de tolerancia por diferencia de zona horaria con el Informix.
        return $fecha <= now()->addDay()->format('Y-m-d') ? $fecha : null;
    }

    /**
     * @return array<string, string>
     */
    public static function etiquetasOrigenFecha(): array
    {
        return [
            self::FECHACARGA_SCAN_ANITA => 'Escaneo Anita',
            self::FECHACARGA_PRECARGA => 'Recepción precarga',
            self::FECHACARGA_ERP => 'Alta en ERP',
        ];
    }
}
