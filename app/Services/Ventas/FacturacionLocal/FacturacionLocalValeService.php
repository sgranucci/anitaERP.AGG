<?php

namespace App\Services\Ventas\FacturacionLocal;

use App\Models\Ventas\LocalVenta;
use App\Models\Ventas\ValeClienteLocal;
use App\Models\Ventas\Venta;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;

final class FacturacionLocalValeService
{
    /**
     * @param  array{cliente_id?:int,tipo_documento?:string,nro_documento?:string,nombre?:string,observacion?:string}  $titular
     */
    public function crearVale(
        LocalVenta $local,
        float $importe,
        ?Venta $ventaOrigen = null,
        ?int $turnoId = null,
        array $titular = [],
    ): ValeClienteLocal {
        if ($importe <= 0) {
            throw new InvalidArgumentException('El importe del vale debe ser mayor a cero.');
        }

        return ValeClienteLocal::query()->create([
            'local_venta_id' => $local->id,
            'cliente_id' => ($titular['cliente_id'] ?? null) ?: null,
            'tipo_documento' => $titular['tipo_documento'] ?? null,
            'nro_documento' => $titular['nro_documento'] ?? null,
            'nombre' => $titular['nombre'] ?? null,
            'tipo' => ValeClienteLocal::TIPO_VAL,
            'importe_original' => round($importe, 2),
            'saldo' => round($importe, 2),
            'estado' => ValeClienteLocal::ESTADO_ACTIVO,
            'venta_origen_id' => $ventaOrigen?->id,
            'turno_operativo_local_id' => $turnoId,
            'usuario_id' => Auth::id(),
            'observacion' => $titular['observacion'] ?? null,
        ]);
    }

    public function aplicar(ValeClienteLocal $vale, float $importe, ?Venta $venta = null): ValeClienteLocal
    {
        if ($vale->estado !== ValeClienteLocal::ESTADO_ACTIVO) {
            throw new InvalidArgumentException('El vale no está activo.');
        }
        if ($importe <= 0 || $importe > (float) $vale->saldo + 0.009) {
            throw new InvalidArgumentException('Importe de aplicación inválido para el vale.');
        }

        $vale->saldo = round((float) $vale->saldo - $importe, 2);
        if ($vale->saldo <= 0.009) {
            $vale->saldo = 0;
            $vale->estado = ValeClienteLocal::ESTADO_APLICADO;
        }
        if ($venta) {
            $vale->venta_aplicacion_id = $venta->id;
        }
        $vale->save();

        return $vale;
    }

    /**
     * @return list<ValeClienteLocal>
     */
    public function buscarActivos(?int $clienteId, ?string $tipoDoc, ?string $nroDoc): array
    {
        $q = ValeClienteLocal::query()
            ->where('estado', ValeClienteLocal::ESTADO_ACTIVO)
            ->where('saldo', '>', 0)
            ->orderByDesc('id');

        if ($clienteId && $clienteId > 0) {
            $q->where('cliente_id', $clienteId);
        } elseif ($nroDoc) {
            $q->where('nro_documento', $nroDoc);
            if ($tipoDoc) {
                $q->where('tipo_documento', $tipoDoc);
            }
        } else {
            return [];
        }

        return $q->limit(20)->get()->all();
    }
}
