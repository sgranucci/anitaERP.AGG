<?php

namespace App\Services\Ventas\FacturacionLocal;

use App\Models\Ventas\LocalVenta;
use App\Models\Ventas\TurnoLocal;
use App\Models\Ventas\TurnoOperativoLocal;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;

final class FacturacionLocalTurnoService
{
    public function turnoAbierto(int $localVentaId, ?string $identificadorPc = null): ?TurnoOperativoLocal
    {
        $q = TurnoOperativoLocal::query()
            ->where('local_venta_id', $localVentaId)
            ->where('estado', TurnoOperativoLocal::ESTADO_ABIERTO)
            ->orderByDesc('id');

        if ($identificadorPc !== null && trim($identificadorPc) !== '') {
            $q->where(function ($w) use ($identificadorPc) {
                $w->where('identificador_pc', $identificadorPc)
                    ->orWhereNull('identificador_pc')
                    ->orWhere('identificador_pc', '');
            });
        }

        return $q->first();
    }

    /**
     * @param  array{fondo_inicial?:float,observacion?:string,identificador_pc?:string,turno_local_id?:int}  $datos
     */
    public function abrir(LocalVenta $local, array $datos = []): TurnoOperativoLocal
    {
        if (! $local->activo) {
            throw new InvalidArgumentException('El local no está activo.');
        }

        $abierto = $this->turnoAbierto((int) $local->id, $datos['identificador_pc'] ?? null);
        if ($abierto) {
            throw new InvalidArgumentException('Ya hay un turno abierto para este local (#' . $abierto->id . ').');
        }

        $usuarioId = (int) Auth::id();
        if ($usuarioId <= 0) {
            throw new InvalidArgumentException('Usuario no autenticado.');
        }

        $turnoLocalId = (int) ($datos['turno_local_id'] ?? 0);
        if ($turnoLocalId <= 0) {
            throw new InvalidArgumentException('Debe elegir un turno (Mañana/Tarde/Noche).');
        }

        $empresaId = (int) ($local->empresa_id ?? 0);
        $turnoMaestro = TurnoLocal::query()
            ->where('id', $turnoLocalId)
            ->where('activo', true)
            ->when($empresaId > 0, fn ($q) => $q->where('empresa_id', $empresaId))
            ->first();
        if (! $turnoMaestro) {
            throw new InvalidArgumentException('El turno elegido no es válido para este local.');
        }

        return TurnoOperativoLocal::query()->create([
            'local_venta_id' => $local->id,
            'turno_local_id' => $turnoMaestro->id,
            'identificador_pc' => trim((string) ($datos['identificador_pc'] ?? '')) ?: null,
            'estado' => TurnoOperativoLocal::ESTADO_ABIERTO,
            'usuario_apertura_id' => $usuarioId,
            'apertura_en' => now(),
            'fondo_inicial' => (float) ($datos['fondo_inicial'] ?? 0),
            'observacion_apertura' => trim((string) ($datos['observacion'] ?? '')) ?: null,
            'monto_facturacion_turno' => 0,
        ]);
    }

    /**
     * @param  list<array{cuentacaja_id:int,esperado:float,contado:float,diferencia?:float}>  $mediosContado
     */
    public function cerrar(
        TurnoOperativoLocal $turno,
        array $mediosContado = [],
        ?string $observacion = null,
        ?float $sobranteFaltante = null,
    ): TurnoOperativoLocal {
        if (! $turno->estaAbierto()) {
            throw new InvalidArgumentException('El turno ya está cerrado.');
        }

        $usuarioId = (int) Auth::id();
        if ($usuarioId <= 0) {
            throw new InvalidArgumentException('Usuario no autenticado.');
        }

        $diff = $sobranteFaltante;
        if ($diff === null && $mediosContado !== []) {
            $diff = 0.;
            foreach ($mediosContado as $m) {
                $diff += (float) ($m['contado'] ?? 0) - (float) ($m['esperado'] ?? 0);
            }
            $diff = round($diff, 2);
        }

        $turno->fill([
            'estado' => TurnoOperativoLocal::ESTADO_CERRADO,
            'usuario_cierre_id' => $usuarioId,
            'cierre_en' => now(),
            'medios_contado_cierre_json' => $mediosContado,
            'observacion_cierre' => trim((string) ($observacion ?? '')) ?: null,
            'sobrante_faltante' => $diff,
        ]);
        $turno->save();

        return $turno->fresh();
    }

    public function sumarFacturacion(TurnoOperativoLocal $turno, float $importe): void
    {
        $turno->monto_facturacion_turno = round((float) $turno->monto_facturacion_turno + $importe, 2);
        $turno->save();
    }
}
