<?php

namespace App\Support\Caja;

use App\Models\Admin\Permiso;
use App\Models\Caja\CajeroCrown;
use App\Models\Caja\TesoreriaCrownBanca;
use App\Models\Caja\TesoreriaCrownDeposito;
use App\Models\Caja\TesoreriaCrownJornada;
use App\Models\Caja\TesoreriaCrownMesa;
use App\Models\Caja\TesoreriaCrownMesaJuego;
use App\Models\Caja\TesoreriaCrownValor;
use App\Models\Seguridad\Usuario;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class TesoreriaCrownMesaSupport
{
    /** @var array<string, string> */
    public const TIPOS = [
        'A' => 'Habilitación',
        'R' => 'Reposición',
        'C' => 'Crédito',
        'F' => 'Estado final',
        'D' => 'Drop',
        'P' => 'Propina',
    ];

    /** Habilitación y reposición suman fichas a la mesa. El crédito las saca. El estado final y el drop son conteos. */
    public const EFECTO = [
        'A' => 1,
        'R' => 1,
        'C' => -1,
        'F' => 0,
        'D' => 0,
        'P' => 0,
    ];

    public static function tipo(string $codigo): string
    {
        return self::TIPOS[$codigo] ?? $codigo;
    }

    public static function estado(string $codigo): string
    {
        return match ($codigo) {
            TesoreriaCrownMesa::BORRADOR => 'Borrador',
            TesoreriaCrownMesa::CONFIRMADO => 'Confirmado',
            TesoreriaCrownMesa::ANULADO => 'Anulado',
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
            ->where('slug', 'editar-tesoreria-crown-mesa')
            ->whereHas('roles', fn ($q) => $q->whereIn('rol.id', $roles->pluck('id')))
            ->exists();
    }

    /**
     * La banca que regía en esa fecha. Una vigencia posterior no pisa los días anteriores.
     */
    public static function bancaVigente(int $salaId, int $codigo, string $fechaYmd): ?TesoreriaCrownBanca
    {
        if ($codigo <= 0) {
            return null;
        }

        return TesoreriaCrownBanca::query()
            ->where('sala_id', $salaId)
            ->where('codigo', $codigo)
            ->whereDate('vigencia_desde', '<=', $fechaYmd)
            ->orderByDesc('vigencia_desde')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Piso del día: cada mesa con la banca de esa fecha y el estado de sus movimientos, sin recalcular el stock ficha por ficha.
     *
     * @return array{mesas: list<array{mesa: TesoreriaCrownDeposito, banca: ?TesoreriaCrownBanca, movimientos: Collection, estado: string, drop: bool, borradores: int}>, movimientos: Collection}
     */
    public static function tablero(TesoreriaCrownJornada $jornada): array
    {
        $fecha = $jornada->fecha->format('Y-m-d');
        $mesas = self::mesas((int) $jornada->sala_id);
        if ($mesas->isEmpty()) {
            return ['mesas' => [], 'movimientos' => collect()];
        }
        $juegos = TesoreriaCrownMesaJuego::query()
            ->whereIn('deposito_id', $mesas->pluck('id'))
            ->get()
            ->keyBy('deposito_id');
        $bancas = TesoreriaCrownBanca::query()
            ->where('sala_id', $jornada->sala_id)
            ->whereDate('vigencia_desde', '<=', $fecha)
            ->orderByDesc('vigencia_desde')
            ->orderByDesc('id')
            ->get()
            ->unique('codigo')
            ->keyBy(fn (TesoreriaCrownBanca $banca) => (int) $banca->codigo);
        $movimientos = TesoreriaCrownMesa::query()
            ->with(['deposito', 'cajero'])
            ->where('jornada_id', $jornada->id)
            ->where('estado', '<>', TesoreriaCrownMesa::ANULADO)
            ->orderBy('numero')
            ->get();
        $porMesa = $movimientos->groupBy('deposito_id');
        $filas = [];
        foreach ($mesas as $mesa) {
            $juego = $juegos->get($mesa->id);
            $codigoBanca = $juego ? (int) $juego->banca_codigo : 0;
            $movs = $porMesa->get($mesa->id, collect());
            $confirmados = $movs->where('estado', TesoreriaCrownMesa::CONFIRMADO);
            $borradores = $movs->where('estado', TesoreriaCrownMesa::BORRADOR)->count();
            $tieneFinal = $confirmados->contains(fn ($movimiento) => $movimiento->tipo === 'F');
            $enJuego = $confirmados->contains(fn ($movimiento) => in_array($movimiento->tipo, ['A', 'R', 'C'], true));
            $tieneDrop = $confirmados->contains(fn ($movimiento) => $movimiento->tipo === 'D');
            $estado = 'vacia';
            if ($borradores > 0) {
                $estado = 'borrador';
            } elseif ($tieneFinal) {
                $estado = 'final';
            } elseif ($enJuego || $tieneDrop) {
                $estado = 'juego';
            }
            $filas[] = [
                'mesa' => $mesa,
                'banca' => $codigoBanca > 0 ? $bancas->get($codigoBanca) : null,
                'movimientos' => $movs,
                'estado' => $estado,
                'drop' => $tieneDrop,
                'borradores' => $borradores,
            ];
        }

        return [
            'mesas' => $filas,
            'movimientos' => $movimientos,
        ];
    }

    /**
     * @return Collection<int, TesoreriaCrownDeposito>
     */
    public static function mesas(int $salaId): Collection
    {
        return TesoreriaCrownDeposito::query()
            ->with('tipoDeposito')
            ->where('sala_id', $salaId)
            ->where('activo', true)
            ->whereHas('tipoDeposito', fn ($q) => $q->where('codigo', 'MESA'))
            ->orderBy('orden')
            ->orderBy('codigo')
            ->get();
    }

    public static function cajeroPorNumero(int $salaId, string $numero): CajeroCrown|string|null
    {
        $numero = trim($numero);
        if ($numero === '') {
            return null;
        }
        $numero = (int) preg_replace('/\D/', '', $numero);
        if ($numero <= 0) {
            return 'Ingresá el número de cajero.';
        }
        $base = CajeroCrown::query()->where('sala_id', $salaId)->where('activo', true);
        $candidato = (clone $base)->where('codigo', $numero)->first();
        if ($candidato === null) {
            $porLegajo = (clone $base)->where('legajo', $numero)->orderBy('codigo')->get();
            if ($porLegajo->count() > 1) {
                return 'Hay varios cajeros con ese legajo. Ingresá el código de cajero.';
            }
            $candidato = $porLegajo->first();
        }

        return $candidato ?? 'No hay un cajero activo con ese número.';
    }

    /**
     * @return array{banca: ?TesoreriaCrownBanca, apertura: array<int, float>, saldo: array<int, float>, estado_final: array<int, float>, drop: array<int, float>}
     */
    public static function stock(int $depositoId, int $salaId, string $fechaYmd): array
    {
        $juego = TesoreriaCrownMesaJuego::query()->where('deposito_id', $depositoId)->first();
        $banca = $juego ? self::bancaVigente($salaId, (int) $juego->banca_codigo, $fechaYmd) : null;
        $apertura = self::cantidadesDelMovimiento(self::ultimoEstadoFinalAntes($depositoId, $fechaYmd));
        if ($apertura === [] && $banca !== null) {
            $apertura = self::cantidadesDeBanca((int) $banca->id);
        }
        $saldo = $apertura;
        foreach (self::movimientosDelDia($depositoId, $fechaYmd, ['A', 'R', 'C']) as $movimiento) {
            $efecto = self::EFECTO[$movimiento->tipo] ?? 0;
            foreach (self::cantidadesDelMovimiento($movimiento) as $valorId => $cantidad) {
                $saldo[$valorId] = ($saldo[$valorId] ?? 0.0) + ($cantidad * $efecto);
            }
        }
        $cierre = self::ultimoEstadoFinalDelDia($depositoId, $fechaYmd);

        return [
            'banca' => $banca,
            'apertura' => $apertura,
            'saldo' => $saldo,
            'estado_final' => self::cantidadesDelMovimiento($cierre),
            'drop' => self::sumarTipo($depositoId, $fechaYmd, 'D'),
        ];
    }

    /**
     * @param  array<int, float>  $cantidades
     * @return list<array{valor: TesoreriaCrownValor, cantidad: float, monto: float}>
     */
    public static function filas(int $salaId, array $cantidades): array
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
        $filas = [];
        foreach ($cantidades as $valorId => $cantidad) {
            if (abs($cantidad) < 0.0001) {
                continue;
            }
            $valor = $valores->get((int) $valorId);
            if ($valor === null) {
                continue;
            }
            $filas[] = [
                'valor' => $valor,
                'cantidad' => $cantidad,
                'monto' => $cantidad * (float) $valor->denominacion,
            ];
        }

        return $filas;
    }

    /**
     * @return array<int, array{codigo: string, titulo: string, filas: mixed}>
     */
    public static function grupos(int $salaId, string $tipo): array
    {
        $grupos = TesoreriaCrownComprobanteSupport::gruposEdicion($salaId, TesoreriaCrownComprobanteSupport::RENDICION);
        if (in_array($tipo, ['D', 'P'], true)) {
            return $grupos;
        }

        return array_values(array_filter(
            $grupos,
            fn (array $grupo) => in_array($grupo['codigo'], ['FVAL', 'FCOL'], true)
        ));
    }

    private static function ultimoEstadoFinalAntes(int $depositoId, string $fechaYmd): ?TesoreriaCrownMesa
    {
        return TesoreriaCrownMesa::query()
            ->where('deposito_id', $depositoId)
            ->where('tipo', 'F')
            ->where('estado', TesoreriaCrownMesa::CONFIRMADO)
            ->whereHas('jornada', fn ($q) => $q->whereDate('fecha', '<', $fechaYmd))
            ->orderByDesc('numero')
            ->first();
    }

    private static function ultimoEstadoFinalDelDia(int $depositoId, string $fechaYmd): ?TesoreriaCrownMesa
    {
        return self::movimientosDelDia($depositoId, $fechaYmd, ['F'])->sortByDesc('numero')->first();
    }

    /**
     * @param  list<string>  $tipos
     * @return Collection<int, TesoreriaCrownMesa>
     */
    private static function movimientosDelDia(int $depositoId, string $fechaYmd, array $tipos): Collection
    {
        return TesoreriaCrownMesa::query()
            ->with('lineas')
            ->where('deposito_id', $depositoId)
            ->where('estado', TesoreriaCrownMesa::CONFIRMADO)
            ->whereIn('tipo', $tipos)
            ->whereHas('jornada', fn ($q) => $q->whereDate('fecha', $fechaYmd))
            ->get();
    }

    /**
     * @return array<int, float>
     */
    private static function sumarTipo(int $depositoId, string $fechaYmd, string $tipo): array
    {
        $total = [];
        foreach (self::movimientosDelDia($depositoId, $fechaYmd, [$tipo]) as $movimiento) {
            foreach (self::cantidadesDelMovimiento($movimiento) as $valorId => $cantidad) {
                $total[$valorId] = ($total[$valorId] ?? 0.0) + $cantidad;
            }
        }

        return $total;
    }

    /**
     * @return array<int, float>
     */
    private static function cantidadesDelMovimiento(?TesoreriaCrownMesa $movimiento): array
    {
        if ($movimiento === null) {
            return [];
        }
        $movimiento->loadMissing('lineas');
        $cantidades = [];
        foreach ($movimiento->lineas as $linea) {
            $cantidades[(int) $linea->valor_id] = (float) $linea->cantidad;
        }

        return $cantidades;
    }

    /**
     * @return array<int, float>
     */
    private static function cantidadesDeBanca(int $bancaId): array
    {
        return DB::table('tesoreria_crown_banca_linea')
            ->where('banca_id', $bancaId)
            ->pluck('cantidad', 'valor_id')
            ->map(fn ($cantidad) => (float) $cantidad)
            ->all();
    }
}
