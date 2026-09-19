<?php

namespace App\Queries\Stock;

interface Articulo_MovimientoQueryInterface
{
    public function generaDatosRepStockOt($estado, $mventa_id,
                                            $desdearticulo, $hastaarticulo,
                                            $desdelinea_id, $hastalinea_id,
                                            $desdecategoria_id, $hastacategoria_id,
                                            $desdelote, $hastalote, $deposito_id);

    /**
     * OT en planta (sin tarea de cierre) que aún no tienen movimiento de stock.
     *
     * @param  list<int>  $ordentrabajoIdsYaIncluidos
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    public function generaDatosOtEnProduccion(
        $estado,
        $mventa_id,
        $desdearticulo,
        $hastaarticulo,
        $desdelinea_id,
        $hastalinea_id,
        $desdecategoria_id,
        $hastacategoria_id,
        $desdelote,
        $hastalote,
        array $ordentrabajoIdsYaIncluidos
    );

    public function leeStockPorLote($lote, $articulo_id, $combinacion_id);

    /**
     * Movimientos de lotes/OT con saldo potencial para un artículo+combinación (picking Ferli).
     *
     * @return \Illuminate\Support\Collection<int, object>
     */
    public function leeMovimientosLotesArticuloCombinacion(int $articuloId, int $combinacionId, ?int $moduloId = null, ?string $texto = null);

    public function buscaLoteImportacion($lotestock_id);
}

