<?php

namespace App\Support\Caja;

use App\Models\Caja\Cheque;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Consulta de cheques de terceros en cartera (origen R, disponibles para entregar).
 */
final class ChequeCarteraConsultaSupport
{
    /**
     * @param  array{
     *   consulta?:string,
     *   empresa_id?:int|null,
     *   limite?:int
     * }  $opts
     * @return list<array<string, mixed>>
     */
    public static function consultar(array $opts = []): array
    {
        $texto = trim((string) ($opts['consulta'] ?? ''));
        $empresaId = isset($opts['empresa_id']) ? (int) $opts['empresa_id'] : 0;
        $limite = max(1, min(200, (int) ($opts['limite'] ?? 80)));

        $query = self::queryCartera();
        if ($empresaId > 0) {
            $query->where('empresa_id', $empresaId);
        } else {
            app(EmpresaRepositoryInterface::class)->aplicarFiltroEmpresasAsignadas($query);
        }

        if ($texto !== '') {
            $like = '%'.$texto.'%';
            $query->where(function (Builder $q) use ($texto, $like) {
                $q->where('numerocheque', 'like', $like)
                    ->orWhere('nro_interno_anita', 'like', $like)
                    ->orWhere('entregado', 'like', $like)
                    ->orWhere('anombrede', 'like', $like)
                    ->orWhere('cuentalibradora', 'like', $like);
                if (ctype_digit($texto)) {
                    $q->orWhere('nro_interno_anita', (int) $texto)
                        ->orWhere('id', (int) $texto)
                        ->orWhere('monto', (float) $texto);
                }
            });
        }

        /** @var Collection<int, Cheque> $filas */
        $filas = $query
            ->with(['bancos:id,codigo,nombre', 'monedas:id,abreviatura,nombre', 'empresas:id,nombre', 'clientes:id,codigo,nombre'])
            ->orderByDesc('fechapago')
            ->orderByDesc('id')
            ->limit($limite)
            ->get();

        return $filas->map(static fn (Cheque $c) => self::serializar($c))->all();
    }

    public static function findEnCarteraPorId(int $id, ?int $empresaId = null): ?Cheque
    {
        if ($id <= 0) {
            return null;
        }
        $q = self::queryCartera()->whereKey($id);
        if ($empresaId && $empresaId > 0) {
            $q->where('empresa_id', $empresaId);
        }

        return $q->with(['bancos', 'monedas', 'empresas', 'clientes'])->first();
    }

    public static function findEnCarteraPorNroInterno(int $nroInterno, ?int $empresaId = null): ?Cheque
    {
        if ($nroInterno <= 0) {
            return null;
        }
        $q = self::queryCartera()->where('nro_interno_anita', $nroInterno);
        if ($empresaId && $empresaId > 0) {
            $q->where('empresa_id', $empresaId);
        }

        return $q->with(['bancos', 'monedas', 'empresas', 'clientes'])->first();
    }

    /**
     * @return Builder<Cheque>
     */
    public static function queryCartera(): Builder
    {
        return Cheque::query()
            ->where('origen', 'R')
            ->whereNull('pagoproveedor_id')
            ->where(function (Builder $q) {
                $q->whereIn('estado', [' ', 'N'])
                    ->orWhereNull('estado')
                    ->orWhere('estado', '');
            });
    }

    /**
     * @return array<string, mixed>
     */
    public static function serializar(Cheque $c): array
    {
        $banco = $c->bancos;
        $moneda = $c->monedas;
        $cliente = $c->clientes;

        return [
            'id' => (int) $c->id,
            'nro_interno_anita' => $c->nro_interno_anita !== null ? (int) $c->nro_interno_anita : null,
            'numerocheque' => (string) ($c->numerocheque ?? ''),
            'fechapago' => $c->fechapago ? (string) $c->fechapago : '',
            'fechaemision' => $c->fechaemision ? (string) $c->fechaemision : '',
            'monto' => (float) ($c->monto ?? 0),
            'cotizacion' => (float) ($c->cotizacion ?? 1),
            'moneda_id' => (int) ($c->moneda_id ?? 1),
            'moneda_abrev' => (string) ($moneda->abreviatura ?? ''),
            'banco_id' => (int) ($c->banco_id ?? 0),
            'banco_codigo' => (string) ($banco->codigo ?? ''),
            'banco_nombre' => (string) ($banco->nombre ?? ''),
            'sucursalpago' => (string) ($c->sucursalpago ?? ''),
            'cuentalibradora' => (string) ($c->cuentalibradora ?? ''),
            'empresa_id' => (int) ($c->empresa_id ?? 0),
            'empresa_nombre' => (string) ($c->empresas->nombre ?? ''),
            'cliente_id' => $c->cliente_id ? (int) $c->cliente_id : null,
            'cliente_nombre' => (string) ($cliente->nombre ?? $c->entregado ?? ''),
            'entregado' => (string) ($c->entregado ?? ''),
            'estado' => (string) ($c->estado ?? ' '),
            'etiqueta' => trim(
                ($c->nro_interno_anita ? '#'.$c->nro_interno_anita.' · ' : '')
                .($c->numerocheque ?: 's/n')
                .' · '.number_format((float) $c->monto, 2, ',', '.')
            ),
        ];
    }
}
