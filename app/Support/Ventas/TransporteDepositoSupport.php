<?php

namespace App\Support\Ventas;

use App\Models\Stock\Depmae;
use App\Models\Ventas\Transporte;

/**
 * Depósito de stock según el reparto (`transporte`).
 * Sin depósito en el reparto → default de ventas.
 * Si el asignado es de otra empresa, busca hermano por código (Bierzo ↔ Surmar).
 */
final class TransporteDepositoSupport
{
    public static function depositoId(?int $transporteId, ?int $empresaId = null): int
    {
        $fallback = self::resolverDepositoEnEmpresa(
            (int) config('facturacion.DEPOSITO_VENTA_ID', 1),
            (int) $empresaId
        );

        $transporteId = (int) $transporteId;
        if ($transporteId <= 0) {
            return $fallback;
        }

        $transporte = Transporte::query()->with('depositos')->find($transporteId);
        $asignadoId = (int) ($transporte?->deposito_id ?? 0);
        if ($asignadoId <= 0) {
            return $fallback;
        }

        return self::resolverDepositoEnEmpresa($asignadoId, (int) $empresaId);
    }

    /**
     * Reparto de la factura: el ingresado en el form; si viene vacío, el del cliente.
     *
     * @param  array<string, mixed>  $data
     */
    public static function transporteIdDesdeFactura(array $data, mixed $cliente = null): int
    {
        $id = (int) ($data['transporte_id'] ?? 0);
        if ($id > 0) {
            return $id;
        }

        if (is_object($cliente)) {
            return (int) ($cliente->transporte_id ?? 0);
        }

        return (int) (is_array($cliente) ? ($cliente['transporte_id'] ?? 0) : 0);
    }

    public static function depositoIdDesdeFactura(array $data, mixed $cliente = null, ?int $empresaId = null): int
    {
        return self::depositoId(self::transporteIdDesdeFactura($data, $cliente), $empresaId);
    }

    public static function tieneDepositoAsignado(?int $transporteId): bool
    {
        $transporteId = (int) $transporteId;
        if ($transporteId <= 0) {
            return false;
        }

        return (int) Transporte::query()->whereKey($transporteId)->value('deposito_id') > 0;
    }

    /**
     * Mapa para el aviso de pantalla (modal factura/remito y factura mostrador).
     *
     * @return array{
     *   default: array<string, mixed>,
     *   por_transporte_id: array<string, array<string, mixed>>
     * }
     */
    public static function mapaAvisosUi(?int $empresaId = null): array
    {
        $empresaId = self::empresaIdUi($empresaId);
        $default = self::depositoParaAviso(self::depositoId(0, $empresaId));
        $default['desde_reparto'] = false;

        $porTransporte = [];
        $filas = Transporte::query()
            ->whereNotNull('deposito_id')
            ->where('deposito_id', '>', 0)
            ->get(['id', 'codigo', 'nombre', 'deposito_id']);

        foreach ($filas as $transporte) {
            $info = self::depositoParaAviso(self::depositoId((int) $transporte->id, $empresaId));
            $info['desde_reparto'] = true;
            $info['transporte_id'] = (int) $transporte->id;
            $info['transporte_codigo'] = trim((string) $transporte->codigo);
            $info['transporte_nombre'] = trim((string) $transporte->nombre);
            $porTransporte[(string) $transporte->id] = $info;
        }

        return [
            'default' => $default,
            'por_transporte_id' => $porTransporte,
        ];
    }

    /**
     * @return array{id: int, codigo: string, nombre: string, desde_reparto: bool}
     */
    private static function depositoParaAviso(int $depositoId): array
    {
        $dep = $depositoId > 0 ? Depmae::query()->find($depositoId) : null;

        return [
            'id' => (int) ($dep->id ?? $depositoId),
            'codigo' => trim((string) ($dep->codigo ?? '')),
            'nombre' => trim((string) ($dep->nombre ?? '')),
            'desde_reparto' => false,
        ];
    }

    private static function empresaIdUi(?int $empresaId): int
    {
        $id = (int) $empresaId;
        if ($id <= 0) {
            $id = (int) session('empresa_id');
        }
        if ($id <= 0) {
            $id = (int) config('cliente.EMPRESA_DEFAULT_ID', 1);
        }

        return $id;
    }

    private static function resolverDepositoEnEmpresa(int $depositoId, int $empresaId): int
    {
        $defaultId = (int) config('facturacion.DEPOSITO_VENTA_ID', 1);
        if ($depositoId <= 0) {
            return $defaultId;
        }
        if ($empresaId <= 0) {
            return $depositoId;
        }

        $dep = Depmae::query()->find($depositoId);
        if ($dep === null) {
            return $defaultId;
        }
        if ($dep->perteneceAEmpresa($empresaId)) {
            return (int) $dep->id;
        }

        $codigo = trim((string) $dep->codigo);
        if ($codigo === '') {
            return $defaultId;
        }

        $hermanoId = (int) Depmae::query()
            ->paraEmpresa($empresaId)
            ->where('codigo', $codigo)
            ->value('id');

        return $hermanoId > 0 ? $hermanoId : $defaultId;
    }
}
