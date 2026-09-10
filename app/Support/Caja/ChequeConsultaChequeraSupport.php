<?php

namespace App\Support\Caja;

use App\Models\Caja\Cheque;
use App\Models\Caja\Chequera;
use App\Models\Caja\Cuentacaja;

/**
 * Etiquetas y consulta de chequeras para el lookup de cheques emitidos.
 */
final class ChequeConsultaChequeraSupport
{
    public static function tipoChequeNombre(string $tipo): string
    {
        return strtoupper(trim($tipo)) === 'D' ? 'Cheque diferido' : 'Cheque al día';
    }

    public static function tipoChequeCorto(string $tipo): string
    {
        return strtoupper(trim($tipo)) === 'D' ? 'Diferido' : 'Al día';
    }

    public static function tipoChequeraNombre(string $tipo): string
    {
        return strtoupper(trim($tipo)) === 'E' ? 'Electrónica' : 'Física';
    }

    public static function estadoNombre(string $estado): string
    {
        return strtoupper(trim($estado)) === 'T' ? 'Terminada' : 'Activa';
    }

    public static function nroTexto(int|string|null $n): string
    {
        $n = (int) preg_replace('/\D/', '', (string) $n);
        if ($n <= 0) {
            return '';
        }

        return number_format($n, 0, ',', '.');
    }

    public static function rangoTexto(int|string|null $desde, int|string|null $hasta): string
    {
        $d = self::nroTexto($desde);
        $h = self::nroTexto($hasta);
        if ($d === '' && $h === '') {
            return '';
        }
        if ($d === '') {
            return $h;
        }
        if ($h === '') {
            return $d;
        }

        return $d.' – '.$h;
    }

    public static function etiquetaCompacta(string $codigo, string $tipocheque): string
    {
        $tipo = self::tipoChequeCorto($tipocheque);
        $codigo = trim($codigo);

        return $codigo === '' ? $tipo : $tipo.' · '.$codigo;
    }

    public static function etiquetaCompleta(
        string $codigo,
        string $tipocheque,
        int|string|null $desde = null,
        int|string|null $hasta = null
    ): string {
        $base = self::etiquetaCompacta($codigo, $tipocheque);
        $rango = self::rangoTexto($desde, $hasta);

        return $rango === '' ? $base : $base.' · '.$rango;
    }

    public static function disponibles(?int $ultimo, int $desde, int $hasta): ?int
    {
        if ($hasta <= 0 || $hasta < $desde) {
            return null;
        }
        $piso = max($desde - 1, (int) $ultimo);

        return max(0, $hasta - $piso);
    }

    /**
     * @param  array<string, mixed>  $opts
     * @return list<array<string, mixed>>
     */
    public static function consultar(array $opts): array
    {
        $cuentacajaId = (int) ($opts['cuentacaja_id'] ?? 0);
        $consulta = trim((string) ($opts['consulta'] ?? ''));
        $preferirDiferido = (bool) ($opts['preferir_diferido'] ?? false);
        $incluirTerminadas = (bool) ($opts['incluir_terminadas'] ?? false);

        $q = Chequera::query()->with('cuentacajas');
        if ($cuentacajaId <= 0) {
            return [];
        }
        $q->where('cuentacaja_id', $cuentacajaId);
        if (! $incluirTerminadas) {
            $q->where(function ($w) {
                $w->where('estado', 'A')->orWhereNull('estado');
            });
        }
        if ($consulta !== '') {
            $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $consulta).'%';
            $q->where(function ($w) use ($like, $consulta) {
                $w->where('codigo', 'like', $like)
                    ->orWhere('desdenumerocheque', 'like', $like)
                    ->orWhere('hastanumerocheque', 'like', $like);
                $tipo = mb_strtolower($consulta);
                if (str_contains($tipo, 'dif')) {
                    $w->orWhere('tipocheque', 'D');
                }
                if (str_contains($tipo, 'día') || str_contains($tipo, 'dia') || str_contains($tipo, 'al d')) {
                    $w->orWhere('tipocheque', 'N')->orWhere('tipocheque', 'C');
                }
            });
        }

        $rows = $q->orderBy('codigo')->get();
        $ultimos = self::ultimosNumeros($rows->pluck('id')->all());

        $out = [];
        foreach ($rows as $ch) {
            $out[] = self::serializar($ch, $preferirDiferido, $ultimos[(int) $ch->id] ?? null);
        }

        usort($out, static function (array $a, array $b): int {
            $cmp = ((int) $b['preferida']) <=> ((int) $a['preferida']);
            if ($cmp !== 0) {
                return $cmp;
            }

            return strcmp((string) $a['codigo'], (string) $b['codigo']);
        });

        return $out;
    }

    /**
     * @param  array<int, int|string>  $ids
     * @return array<int, int>
     */
    public static function ultimosNumeros(array $ids): array
    {
        $ids = array_values(array_filter(array_map('intval', $ids), static fn ($id) => $id > 0));
        if ($ids === []) {
            return [];
        }

        return Cheque::query()
            ->whereIn('chequera_id', $ids)
            ->where('origen', 'E')
            ->selectRaw('chequera_id, MAX(CAST(numerocheque AS UNSIGNED)) as ultimo')
            ->groupBy('chequera_id')
            ->pluck('ultimo', 'chequera_id')
            ->map(static fn ($v) => (int) $v)
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public static function serializar(Chequera $ch, bool $preferirDiferido = false, ?int $ultimo = null): array
    {
        $tipo = (string) ($ch->tipocheque ?? 'N');
        $codigo = (string) ($ch->codigo ?? '');
        $desde = (int) preg_replace('/\D/', '', (string) ($ch->desdenumerocheque ?? ''));
        $hasta = (int) preg_replace('/\D/', '', (string) ($ch->hastanumerocheque ?? ''));
        $cuenta = $ch->cuentacajas;

        return [
            'id' => (int) $ch->id,
            'codigo' => $codigo,
            'tipocheque' => $tipo,
            'tipo_nombre' => self::tipoChequeNombre($tipo),
            'tipo_corto' => self::tipoChequeCorto($tipo),
            'tipochequera' => (string) ($ch->tipochequera ?? 'F'),
            'tipochequera_nombre' => self::tipoChequeraNombre((string) ($ch->tipochequera ?? 'F')),
            'estado' => (string) ($ch->estado ?? 'A'),
            'estado_nombre' => self::estadoNombre((string) ($ch->estado ?? 'A')),
            'desde' => $desde,
            'hasta' => $hasta,
            'rango' => self::rangoTexto($desde, $hasta),
            'ultimo' => (int) ($ultimo ?? 0),
            'ultimo_texto' => self::nroTexto($ultimo ?? 0),
            'disponibles' => self::disponibles($ultimo, $desde, $hasta),
            'fechauso' => (string) ($ch->fechauso ?? ''),
            'cuentacaja_id' => (int) ($ch->cuentacaja_id ?: 0),
            'cuenta_codigo' => (string) ($cuenta->codigo ?? ''),
            'cuenta_nombre' => (string) ($cuenta->nombre ?? ''),
            'etiqueta' => self::etiquetaCompacta($codigo, $tipo),
            'etiqueta_completa' => self::etiquetaCompleta($codigo, $tipo, $desde, $hasta),
            'preferida' => $preferirDiferido ? $tipo === 'D' : $tipo !== 'D',
        ];
    }

    /**
     * @return array{id:int,codigo:string,nombre:string}|null
     */
    public static function cuentaResumen(int $cuentacajaId): ?array
    {
        if ($cuentacajaId <= 0) {
            return null;
        }
        $cuenta = Cuentacaja::query()->find($cuentacajaId);
        if (! $cuenta) {
            return null;
        }

        return [
            'id' => (int) $cuenta->id,
            'codigo' => (string) $cuenta->codigo,
            'nombre' => (string) $cuenta->nombre,
        ];
    }
}
