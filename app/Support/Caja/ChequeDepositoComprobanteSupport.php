<?php

namespace App\Support\Caja;

use App\Models\Caja\Cheque;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * Boleta PDF de depósito de cheques de terceros (uno o varios).
 */
final class ChequeDepositoComprobanteSupport
{
    /**
     * @param  array<int, mixed>|string|null  $raw
     * @return list<int>
     */
    public static function parseIds(array|string|null $raw): array
    {
        if (is_array($raw)) {
            $parts = $raw;
        } else {
            $parts = preg_split('/[,\s]+/', trim((string) $raw)) ?: [];
        }

        return array_values(array_unique(array_filter(
            array_map('intval', $parts),
            static fn ($id) => $id > 0
        )));
    }

    /**
     * @param  list<int>|array<int, mixed>  $ids
     */
    public static function url(array $ids): ?string
    {
        $ids = self::parseIds($ids);
        if ($ids === []) {
            return null;
        }

        return route('comprobante_deposito_cheque', ['ids' => implode(',', $ids)]);
    }

    /**
     * @param  list<int>  $ids
     * @return array{
     *     cheques: Collection<int, Cheque>,
     *     totales: list<array{moneda:string, monto:float, cantidad:int}>,
     *     total_cantidad: int,
     *     cuenta_deposito: string,
     *     fecha_deposito: string,
     *     nro_boleta: string,
     *     empresa: string,
     *     usuario: string,
     *     deposito_homogeneo: bool
     * }
     */
    public static function armar(array $ids, EmpresaRepositoryInterface $empresaRepository): array
    {
        $ids = self::parseIds($ids);
        if ($ids === []) {
            throw new InvalidArgumentException('No hay cheques para el comprobante de depósito.');
        }

        $query = Cheque::query()
            ->with(['bancos', 'monedas', 'empresas', 'cuentacajaDeposito', 'clientes'])
            ->whereIn('id', $ids)
            ->where('origen', 'R')
            ->whereNotNull('fecha_deposito');
        $empresaRepository->aplicarFiltroEmpresasAsignadas($query, 'empresa_id');
        $cheques = $query->orderBy('id')->get();

        if ($cheques->isEmpty()) {
            throw new InvalidArgumentException('No se encontraron cheques depositados para el comprobante.');
        }

        foreach ($cheques as $cheque) {
            $cheque->nombreempresa = $cheque->empresas->nombre ?? '';
        }

        $totalesMap = [];
        foreach ($cheques as $cheque) {
            $moneda = trim((string) ($cheque->monedas->abreviatura ?? ''));
            if ($moneda === '') {
                $moneda = '$';
            }
            if (! isset($totalesMap[$moneda])) {
                $totalesMap[$moneda] = ['moneda' => $moneda, 'monto' => 0.0, 'cantidad' => 0];
            }
            $totalesMap[$moneda]['monto'] = round($totalesMap[$moneda]['monto'] + (float) $cheque->monto, 2);
            $totalesMap[$moneda]['cantidad']++;
        }

        $fechas = $cheques->map(static fn (Cheque $c) => self::fechaDmy($c->fecha_deposito))->unique()->values();
        $cuentas = $cheques->map(static function (Cheque $c) {
            $cc = $c->cuentacajaDeposito;
            if (! $cc) {
                return '';
            }

            return trim((string) ($cc->codigo ?? '').' — '.(string) ($cc->nombre ?? ''), ' —');
        })->unique()->values();
        $boletas = $cheques->map(static fn (Cheque $c) => trim((string) ($c->nro_boleta_deposito ?? '')))
            ->filter()
            ->unique()
            ->values();
        $empresas = $cheques->map(static fn (Cheque $c) => trim((string) ($c->empresas->nombre ?? '')))
            ->filter()
            ->unique()
            ->values();

        $homogeneo = $fechas->count() <= 1 && $cuentas->count() <= 1;

        $usuario = auth()->user();
        $usuarioNombre = '';
        if ($usuario) {
            $usuarioNombre = trim((string) ($usuario->nombre ?? ''));
            if ($usuarioNombre === '') {
                $usuarioNombre = trim((string) ($usuario->usuario ?? ''));
            }
        }

        return [
            'cheques' => $cheques,
            'totales' => array_values($totalesMap),
            'total_cantidad' => $cheques->count(),
            'cuenta_deposito' => $cuentas->implode(' / '),
            'fecha_deposito' => $fechas->implode(' / '),
            'nro_boleta' => $boletas->implode(' / '),
            'empresa' => $empresas->implode(' / '),
            'usuario' => $usuarioNombre,
            'deposito_homogeneo' => $homogeneo,
        ];
    }

    /**
     * Número de cheque bancario para impresión: nunca incluye el nro. interno Anita.
     */
    public static function numeroChequeImpresion(Cheque $cheque): string
    {
        $nro = trim((string) ($cheque->numerocheque ?? ''));
        $interno = trim((string) ($cheque->nro_interno_anita ?? ''));
        if ($nro === '') {
            return '';
        }
        if ($interno !== '' && $interno !== '0') {
            $quoted = preg_quote($interno, '/');
            $nro = preg_replace('/[\s\/\-|]+'.$quoted.'\s*$/', '', $nro) ?? $nro;
            $nro = preg_replace('/^'.$quoted.'[\s\/\-|]+/', '', $nro) ?? $nro;
            $nro = trim($nro);
        }

        return $nro;
    }

    public static function fechaDmy(mixed $fecha): string
    {
        if ($fecha instanceof \DateTimeInterface) {
            return $fecha->format('d/m/Y');
        }
        $f = trim((string) $fecha);
        if ($f === '') {
            return '';
        }
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $f, $m)) {
            return $m[3].'/'.$m[2].'/'.$m[1];
        }

        return $f;
    }

    public static function nombreArchivo(int $cantidad, string $fechaDepositoDmy): string
    {
        $fecha = preg_replace('/[^\d]/', '', $fechaDepositoDmy) ?: date('Ymd');
        if ($cantidad === 1) {
            return 'deposito_cheque_'.$fecha.'.pdf';
        }

        return 'deposito_cheques_'.$fecha.'_'.$cantidad.'ch.pdf';
    }
}
