<?php

namespace App\Support\Caja;

use App\Models\Caja\TesoreriaCrownValor;
use Illuminate\Support\Collection;

class TesoreriaCrownComprobanteSupport
{
    public const HABILITACION = 'H';

    public const RENDICION = 'F';

    public const BORRADOR = 'B';

    public const PENDIENTE = 'P';

    public const CONFORME = 'C';

    public const NO_CONFORME = 'N';

    public const AUDITORIA = 'A';

    public const REVISION = 'R';

    public const REVISADA = 'T';

    public const AJUSTE = 'J';

    public const CIERRE = 'F';

    /** @var array<string, string> */
    public const TIPOS = [
        self::HABILITACION => 'Habilitación',
        self::RENDICION => 'Rendición',
    ];

    /** @var array<string, string> */
    public const ESTADOS = [
        self::BORRADOR => 'Borrador',
        self::PENDIENTE => 'Pendiente de firma',
        self::CONFORME => 'Conforme',
        self::NO_CONFORME => 'No conforme',
        self::AUDITORIA => 'Auditoría',
        self::REVISION => 'A revisión',
        self::REVISADA => 'Revisada',
        self::AJUSTE => 'Ajuste',
        self::CIERRE => 'Cierre de auditoría',
    ];

    public static function tipo(string $codigo): string
    {
        return self::TIPOS[$codigo] ?? $codigo;
    }

    public static function estado(string $codigo): string
    {
        return self::ESTADOS[$codigo] ?? $codigo;
    }

    /**
     * Habilitación: solo efectivo, como a-transfefe (carga_billetes; las fichas quedan fuera de la carga).
     * Moneda 1, 2 y 3 (pesos, dólares, euros) y, dentro de cada una, denominación de mayor a menor.
     * Rendición: el mismo efectivo, y las fichas en el orden de carga de fichamae.
     *
     * @return list<array{codigo: string, titulo: string, filas: Collection<int, TesoreriaCrownValor>}>
     */
    public static function gruposEdicion(int $salaId, string $tipo): array
    {
        $valores = TesoreriaCrownValor::query()
            ->with('tipoValor')
            ->where('sala_id', $salaId)
            ->where('activo', true)
            ->get();

        $grupos = [];
        foreach (['ARS' => 'Pesos', 'USD' => 'Dólares', 'EUR' => 'Euros'] as $codigo => $titulo) {
            $filas = self::deCodigo($valores, $codigo)
                ->sort(function (TesoreriaCrownValor $a, TesoreriaCrownValor $b): int {
                    $porValor = (float) $b->denominacion <=> (float) $a->denominacion;

                    return $porValor !== 0 ? $porValor : strcmp((string) $a->serie, (string) $b->serie);
                })
                ->values();
            if ($filas->isNotEmpty()) {
                $grupos[] = [
                    'codigo' => $codigo,
                    'titulo' => (string) ($filas->first()->tipoValor->nombre ?? $titulo),
                    'filas' => $filas,
                ];
            }
        }

        if ($tipo === self::HABILITACION) {
            return $grupos;
        }

        foreach (['FVAL' => 'Fichas', 'FCOL' => 'Fichas de color'] as $codigo => $titulo) {
            $filas = self::deCodigo($valores, $codigo)
                ->sort(function (TesoreriaCrownValor $a, TesoreriaCrownValor $b): int {
                    $porOrden = (int) $a->orden <=> (int) $b->orden;

                    return $porOrden !== 0
                        ? $porOrden
                        : ((float) $b->denominacion <=> (float) $a->denominacion);
                })
                ->values();
            if ($filas->isNotEmpty()) {
                $grupos[] = [
                    'codigo' => $codigo,
                    'titulo' => (string) ($filas->first()->tipoValor->nombre ?? $titulo),
                    'filas' => $filas,
                ];
            }
        }

        return $grupos;
    }

    /**
     * @param  Collection<int, TesoreriaCrownValor>  $valores
     * @return Collection<int, TesoreriaCrownValor>
     */
    private static function deCodigo(Collection $valores, string $codigo): Collection
    {
        return $valores
            ->filter(fn (TesoreriaCrownValor $valor) => ($valor->tipoValor->codigo ?? '') === $codigo)
            ->values();
    }
}
