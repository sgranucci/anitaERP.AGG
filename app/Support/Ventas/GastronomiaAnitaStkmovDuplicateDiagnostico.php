<?php

declare(strict_types=1);

namespace App\Support\Ventas;

/**
 * Diagnóstico de colisiones stkmov ERP ↔ Informix (gastronomía).
 *
 * Esquema stkv_nro_orden en Informix (índice único por comprobante + orden):
 * - Platos (FacturacionService::grabaAnita): 1, 2, 3… por renglón de factura.
 * - Insumos fórmula (GastronomiaInsumoStkmovAnitaService): (numeroitem_plato × 1000) + secuencia.
 *
 * Causas habituales de «duplicate value in UNIQUE INDEX» (Informix 239):
 *
 * 1. Backfill sobre comprobante ya sincronizado en emisión, sin borrar stkmov previo.
 *    Mitigación: GastronomiaReplicarVentasAnitaErpService::liberarCabeceraAnitaSiExiste().
 *
 * 2. Emisión en vivo + auditoría/backfill concurrentes (p. ej. 01:00 con POS abierto).
 *    Mitigación: auditoría @ 06:30; no marcar faltante si falla lectura bridge.
 *
 * 3. Rollback parcial tras fallo en grabaAnita (stkmov insert falla, borraAnita/revert falla
 *    con el mismo error 239 en delete). Queda cabecera o stkmov huérfano; reintento duplica.
 *    Ver logs: gastronomia.emitir_factura.fallo + gastronomia.revertir_anita.fallo.
 *
 * 4. Falso «sin cabecera» cuando el bridge devolvía {"Error":…} y decodificarListaFilas
 *    interpretaba lista vacía → backfill innecesario. Mitigación: ApiAnita::parsearRespuestaLista().
 */
final class GastronomiaAnitaStkmovDuplicateDiagnostico
{
    public const MULTIPLICADOR_ORDEN_INSUMO = 1000;
}
