<?php

namespace App\Support\Sala;

use App\Models\Sala\RequisicionSala;
use App\Models\Stock\Depmae;
use App\Services\Stock\TransferenciaMercaderiaService;

/**
 * Evalúa si la transferencia automática al laboratorio (tras aprobar) tendría saldo suficiente.
 */
final class RequisicionSalaTransferenciaLaboratorioPreflightSupport
{
    /**
     * @return array{
     *     aplica: bool,
     *     viable: bool|null,
     *     controla_stock: bool|null,
     *     mensaje_resumen: string,
     *     lineas_detalle: list<array<string, mixed>>,
     *     deposito_laboratorio_id: int|null,
     *     deposito_origen_es_centro_consumo: bool,
     *     mensaje_informativo: string
     * }
     */
    public static function evaluar(?RequisicionSala $req, ?string $estadoTrasAprobar): array
    {
        $sinAplicar = [
            'aplica' => false,
            'viable' => null,
            'controla_stock' => null,
            'mensaje_resumen' => '',
            'lineas_detalle' => [],
            'deposito_laboratorio_id' => null,
            'deposito_origen_es_centro_consumo' => false,
            'mensaje_informativo' => '',
        ];

        if ($req === null || ! RequisicionSalaLineasLaboratorioSupport::generaraTransferenciaLaboratorioAlAprobar($req, $estadoTrasAprobar)) {
            return $sinAplicar;
        }

        $depositoLabId = self::resolverDepositoLaboratorioId();
        if ($depositoLabId <= 0) {
            return [
                'aplica' => true,
                'viable' => false,
                'controla_stock' => null,
                'mensaje_resumen' => 'No está configurado el depósito de laboratorio. La transferencia automática no podrá realizarse.',
                'lineas_detalle' => [],
                'deposito_laboratorio_id' => null,
                'deposito_origen_es_centro_consumo' => false,
                'mensaje_informativo' => '',
            ];
        }

        $depositoOrigenId = (int) ($req->deposito_id ?? 0);
        if ($depositoOrigenId <= 0) {
            return [
                'aplica' => true,
                'viable' => false,
                'controla_stock' => null,
                'mensaje_resumen' => 'La requisición no tiene depósito de origen definido. La transferencia automática no podrá realizarse.',
                'lineas_detalle' => [],
                'deposito_laboratorio_id' => $depositoLabId,
                'deposito_origen_es_centro_consumo' => false,
                'mensaje_informativo' => '',
            ];
        }

        if ($depositoOrigenId === $depositoLabId) {
            return [
                'aplica' => true,
                'viable' => true,
                'controla_stock' => null,
                'mensaje_resumen' => '',
                'lineas_detalle' => [],
                'deposito_laboratorio_id' => $depositoLabId,
                'deposito_origen_es_centro_consumo' => false,
                'mensaje_informativo' => '',
            ];
        }

        $lineas = RequisicionSalaLineasLaboratorioSupport::payloadLineasTransferencia($req);
        $eval = app(TransferenciaMercaderiaService::class)->evaluarSaldosTransferenciaDesdePayload(
            $depositoOrigenId,
            $depositoLabId,
            (int) ($req->empresa_id ?? 0),
            $lineas
        );

        $controlaStock = isset($eval['controla_stock']) ? (bool) $eval['controla_stock'] : null;
        $esCentroConsumo = $controlaStock === false;

        return [
            'aplica' => true,
            'viable' => (bool) ($eval['viable'] ?? false),
            'controla_stock' => $controlaStock,
            'mensaje_resumen' => (string) ($eval['mensaje_resumen'] ?? ''),
            'lineas_detalle' => is_array($eval['lineas_detalle'] ?? null) ? $eval['lineas_detalle'] : [],
            'deposito_laboratorio_id' => $depositoLabId,
            'deposito_origen_es_centro_consumo' => $esCentroConsumo,
            'mensaje_informativo' => $esCentroConsumo
                ? self::mensajeCentroConsumo($depositoOrigenId)
                : '',
        ];
    }

    private static function mensajeCentroConsumo(int $depositoOrigenId): string
    {
        $deposito = Depmae::query()->find($depositoOrigenId);
        $nombre = $deposito
            ? Depmae::etiquetaDesdePartes($deposito->codigo, $deposito->nombre, $deposito->id)
            : 'centro de consumo';

        return 'El depósito de origen ('.$nombre.') es centro de consumo: no se valida saldo y la transferencia se registrará igual.';
    }

    private static function resolverDepositoLaboratorioId(): int
    {
        $codigo = trim((string) config('sala.requisicion_deposito_laboratorio_codigo', '406'));
        if ($codigo === '') {
            return 0;
        }
        $id = Depmae::query()->where('codigo', $codigo)->value('id');

        return $id ? (int) $id : 0;
    }
}
