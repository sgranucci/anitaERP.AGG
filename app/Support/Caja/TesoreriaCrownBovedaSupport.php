<?php

namespace App\Support\Caja;

use App\Models\Admin\Permiso;
use App\Models\Caja\TesoreriaCrownBoveda;
use App\Models\Caja\TesoreriaCrownBovedaTipo;
use App\Models\Caja\TesoreriaCrownDeposito;
use App\Models\Caja\TesoreriaCrownJornada;
use App\Models\Caja\TesoreriaCrownValor;
use App\Models\Seguridad\Usuario;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class TesoreriaCrownBovedaSupport
{
    /** @var list<string> */
    public const LUGARES = ['BOVEDA', 'PREDEPOSITO', 'CAJA_CHICA'];

    public static function efecto(string $signo): int
    {
        return match ($signo) {
            '0' => 1,
            '1' => -1,
            default => 0,
        };
    }

    public static function efectoTexto(string $signo): string
    {
        return match ($signo) {
            '0' => 'Entra',
            '1' => 'Sale',
            default => 'No mueve saldo',
        };
    }

    public static function estado(string $codigo): string
    {
        return match ($codigo) {
            TesoreriaCrownBoveda::BORRADOR => 'Borrador',
            TesoreriaCrownBoveda::CONFIRMADO => 'Confirmado',
            TesoreriaCrownBoveda::ANULADO => 'Anulado',
            default => $codigo,
        };
    }

    public static function puedeConfirmar(Usuario $usuario): bool
    {
        $roles = $usuario->roles()->get(['rol.id', 'rol.nombre']);
        if ($roles->contains(fn ($rol) => (string) $rol->nombre === 'administrador')) {
            return true;
        }

        return Permiso::query()
            ->where('slug', 'editar-tesoreria-crown-boveda')
            ->whereHas('roles', fn ($q) => $q->whereIn('rol.id', $roles->pluck('id')))
            ->exists();
    }

    /**
     * @return Collection<int, TesoreriaCrownDeposito>
     */
    public static function lugares(int $salaId): Collection
    {
        return TesoreriaCrownDeposito::query()
            ->with('tipoDeposito')
            ->where('sala_id', $salaId)
            ->where('activo', true)
            ->whereHas('tipoDeposito', fn ($q) => $q->whereIn('codigo', self::LUGARES))
            ->orderBy('orden')
            ->orderBy('codigo')
            ->get();
    }

    /**
     * @return Collection<int, TesoreriaCrownBovedaTipo>
     */
    public static function tiposActivos(int $salaId): Collection
    {
        return TesoreriaCrownBovedaTipo::query()
            ->where('sala_id', $salaId)
            ->where('activo', true)
            ->orderBy('codigo')
            ->get();
    }

    /**
     * Saldo en cantidad por valor, de los movimientos confirmados hasta esa fecha de jornada.
     *
     * @return array<int, float>
     */
    public static function saldoCantidades(int $depositoId, string $hastaYmd): array
    {
        $filas = DB::table('tesoreria_crown_boveda_linea as l')
            ->join('tesoreria_crown_boveda as b', 'b.id', '=', 'l.boveda_id')
            ->join('tesoreria_crown_boveda_tipo as t', 't.id', '=', 'b.tipo_id')
            ->join('tesoreria_crown_jornada as j', 'j.id', '=', 'b.jornada_id')
            ->where('b.deposito_id', $depositoId)
            ->where('b.estado', TesoreriaCrownBoveda::CONFIRMADO)
            ->whereDate('j.fecha', '<=', $hastaYmd)
            ->get(['l.valor_id', 'l.cantidad', 't.signo']);

        $saldos = [];
        foreach ($filas as $fila) {
            $efecto = self::efecto((string) $fila->signo);
            if ($efecto === 0) {
                continue;
            }
            $valorId = (int) $fila->valor_id;
            $saldos[$valorId] = ($saldos[$valorId] ?? 0.0) + ((float) $fila->cantidad * $efecto);
        }

        return $saldos;
    }

    /**
     * @param  array<int, float>  $cantidades
     * @return list<array{titulo: string, filas: list<array{valor: TesoreriaCrownValor, cantidad: float, monto: float}>, monto: float}>
     */
    public static function saldoAgrupado(int $salaId, array $cantidades): array
    {
        if ($cantidades === []) {
            return [];
        }
        $valores = TesoreriaCrownValor::query()
            ->with('tipoValor')
            ->where('sala_id', $salaId)
            ->whereIn('id', array_keys($cantidades))
            ->get()
            ->keyBy('id');
        $grupos = [];
        foreach ($cantidades as $valorId => $cantidad) {
            if (abs($cantidad) < 0.0001) {
                continue;
            }
            $valor = $valores->get((int) $valorId);
            if ($valor === null) {
                continue;
            }
            $titulo = (string) ($valor->tipoValor->nombre ?? 'Valor');
            $monto = $cantidad * (float) $valor->denominacion;
            $grupos[$titulo]['titulo'] = $titulo;
            $grupos[$titulo]['filas'][] = [
                'valor' => $valor,
                'cantidad' => $cantidad,
                'monto' => $monto,
            ];
            $grupos[$titulo]['monto'] = ($grupos[$titulo]['monto'] ?? 0.0) + $monto;
        }

        return array_values($grupos);
    }

    /**
     * @return array{lugares: Collection<int, TesoreriaCrownDeposito>, saldos: array<int, list<array{titulo: string, monto: float}>>, movimientos: Collection<int, TesoreriaCrownBoveda>}
     */
    public static function resumenJornada(TesoreriaCrownJornada $jornada): array
    {
        $lugares = self::lugares((int) $jornada->sala_id);
        $fecha = $jornada->fecha->format('Y-m-d');
        $saldos = [];
        foreach ($lugares as $lugar) {
            if ((string) ($lugar->tipoDeposito->codigo ?? '') !== 'BOVEDA') {
                continue;
            }
            $grupos = self::saldoAgrupado((int) $jornada->sala_id, self::saldoCantidades((int) $lugar->id, $fecha));
            $saldos[(int) $lugar->id] = array_map(
                fn (array $grupo) => ['titulo' => $grupo['titulo'], 'monto' => $grupo['monto']],
                $grupos
            );
        }

        return [
            'lugares' => $lugares,
            'saldos' => $saldos,
            'movimientos' => TesoreriaCrownBoveda::query()
                ->with(['tipo', 'deposito'])
                ->where('jornada_id', $jornada->id)
                ->where('estado', '<>', TesoreriaCrownBoveda::ANULADO)
                ->orderBy('numero')
                ->get(),
        ];
    }
}
