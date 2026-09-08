<?php

namespace App\Support\Tesoreria\PosicionBancaria;

use App\Models\Caja\Cuentacaja;
use App\Models\Tesoreria\PosicionBancariaCheque;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Aging de cheques propios para posición bancaria diaria.
 * Réplica la lógica de las hojas Cheques BSA/KSA/RSA:
 *   retenidos  = vencimiento <= posición - 30 días
 *   transito   = posición - 30 < vencimiento <= posición
 *   diferidos  = vencimiento > posición
 *
 * Cruce con conciliación: mismos CHP (empresa × cuentacaja × numero × fecha_cheque);
 * carátula de conciliación = diferidos/tránsito con vencimiento en el mes de corte.
 */
final class PosicionBancariaChequeAgingSupport
{
    public const BANCO_MACRO = 'MACRO';

    public const BANCO_ITAU = 'ITAU';

    public const BANCO_BIND = 'BIND';

    /** @var array<int, array<string, string>> empresa_id => banco_canonico => codigo cuentacaja */
    public const CUENTAS_CANONICAS = [
        1 => [
            self::BANCO_MACRO => '127',
            self::BANCO_ITAU => '1112',
            self::BANCO_BIND => '1118',
        ],
        2 => [
            self::BANCO_MACRO => '226',
            self::BANCO_ITAU => '2112',
            self::BANCO_BIND => '2118',
        ],
        3 => [
            self::BANCO_MACRO => '326',
            self::BANCO_ITAU => '3112',
            self::BANCO_BIND => '3118',
        ],
    ];

    public static function normalizarBanco(?string $label): ?string
    {
        if ($label === null || trim($label) === '') {
            return null;
        }
        $u = strtoupper(trim($label));
        if (str_contains($u, 'MACRO') && ! str_contains($u, 'ITAU')) {
            return self::BANCO_MACRO;
        }
        if (str_contains($u, 'ITAU') || str_contains($u, 'BMA') || $u === 'I') {
            return self::BANCO_ITAU;
        }
        if (str_contains($u, 'BIND') || str_contains($u, 'INDUSTRIAL')) {
            return self::BANCO_BIND;
        }
        if ($u === 'MACRO') {
            return self::BANCO_MACRO;
        }
        if ($u === 'ITAU') {
            return self::BANCO_ITAU;
        }
        if ($u === 'BIND') {
            return self::BANCO_BIND;
        }

        return null;
    }

    public static function resolverCuentacajaId(int $empresaId, string $bancoCanonico): ?int
    {
        $codigo = self::CUENTAS_CANONICAS[$empresaId][$bancoCanonico] ?? null;
        if ($codigo === null) {
            return null;
        }

        $id = Cuentacaja::query()
            ->where('empresa_id', $empresaId)
            ->where('codigo', $codigo)
            ->value('id');

        return $id !== null ? (int) $id : null;
    }

    /**
     * @return Collection<int, PosicionBancariaCheque>
     */
    public function listarActivos(?int $empresaId = null, bool $soloPortfolioPosicion = false): Collection
    {
        $q = PosicionBancariaCheque::query()
            ->where('activo', true)
            ->where(function ($w) {
                $w->whereNull('estado')
                    ->orWhere('estado', '')
                    ->orWhere('estado', ' ');
            });

        if ($soloPortfolioPosicion) {
            $q->where('en_portfolio_posicion', true);
        }

        if ($empresaId !== null) {
            $q->where('empresa_id', $empresaId);
        }

        return $q->orderBy('fecha_cheque')->orderBy('numero_cheque')->get();
    }

    /**
     * @param  Collection<int, PosicionBancariaCheque>|null  $cheques
     * @return array{
     *   fecha: string,
     *   retenidos: array{count:int,importe:float},
     *   transito: array{count:int,importe:float},
     *   diferidos: array{count:int,importe:float},
     *   por_banco: array<string, array{retenidos:float,transito:float,diferidos:float,total:float}>,
     *   por_empresa: array<int, array{retenidos:float,transito:float,diferidos:float,total:float}>
     * }
     */
    public function resumir(
        Carbon $fechaPosicion,
        ?Collection $cheques = null,
        ?int $empresaId = null,
        bool $soloPortfolioPosicion = false,
    ): array {
        $cheques ??= $this->listarActivos($empresaId, $soloPortfolioPosicion);
        $corteRetenidos = $fechaPosicion->copy()->subDays(30)->startOfDay();
        $corteHoy = $fechaPosicion->copy()->startOfDay();

        $retenidos = ['count' => 0, 'importe' => 0.0];
        $transito = ['count' => 0, 'importe' => 0.0];
        $diferidos = ['count' => 0, 'importe' => 0.0];
        $porBanco = [];
        $porEmpresa = [];

        foreach ($cheques as $ch) {
            $venc = $ch->fecha_cheque ? Carbon::parse($ch->fecha_cheque)->startOfDay() : null;
            if ($venc === null) {
                continue;
            }
            $imp = abs((float) $ch->importe);
            $banco = (string) $ch->banco_canonico;
            $emp = (int) $ch->empresa_id;

            if (! isset($porBanco[$banco])) {
                $porBanco[$banco] = ['retenidos' => 0.0, 'transito' => 0.0, 'diferidos' => 0.0, 'total' => 0.0];
            }
            if (! isset($porEmpresa[$emp])) {
                $porEmpresa[$emp] = ['retenidos' => 0.0, 'transito' => 0.0, 'diferidos' => 0.0, 'total' => 0.0];
            }

            if ($venc->lte($corteRetenidos)) {
                $bucket = 'retenidos';
            } elseif ($venc->lte($corteHoy)) {
                $bucket = 'transito';
            } else {
                $bucket = 'diferidos';
            }

            ${$bucket}['count']++;
            ${$bucket}['importe'] = round(${$bucket}['importe'] + $imp, 2);
            $porBanco[$banco][$bucket] = round($porBanco[$banco][$bucket] + $imp, 2);
            $porBanco[$banco]['total'] = round($porBanco[$banco]['total'] + $imp, 2);
            $porEmpresa[$emp][$bucket] = round($porEmpresa[$emp][$bucket] + $imp, 2);
            $porEmpresa[$emp]['total'] = round($porEmpresa[$emp]['total'] + $imp, 2);
        }

        return [
            'fecha' => $fechaPosicion->toDateString(),
            'retenidos' => $retenidos,
            'transito' => $transito,
            'diferidos' => $diferidos,
            'por_banco' => $porBanco,
            'por_empresa' => $porEmpresa,
        ];
    }
}
