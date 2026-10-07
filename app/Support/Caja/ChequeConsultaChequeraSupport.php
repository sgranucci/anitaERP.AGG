<?php

namespace App\Support\Caja;

use App\Models\Caja\Cheque;
use App\Models\Caja\Chequera;
use App\Models\Caja\Cuentacaja;
use App\Support\Database\SqlDialectSupport;

/**
 * Etiquetas, consulta y numeración ERP de chequeras (talonario).
 *
 * El próximo número con chequera asociada usa el rango ERP (`desdenumerocheque` /
 * `hastanumerocheque` + MAX emitidos), igual que OP por propuesta.
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
     * Mayor número ya emitido que cae dentro del rango (aunque el cheque
     * haya quedado asociado a otra chequera de la misma cuenta).
     *
     * @param  list<int>  $numeros
     */
    public static function ultimoDentroDeRango(array $numeros, int $desde, int $hasta): int
    {
        $ultimo = 0;
        foreach ($numeros as $n) {
            $n = (int) $n;
            if ($n >= $desde && $n <= $hasta && $n > $ultimo) {
                $ultimo = $n;
            }
        }

        return $ultimo;
    }

    /**
     * @throws \RuntimeException
     */
    public static function proximoNumeroEnRango(int $desde, int $hasta, int $ultimo, string $codigoChequera = ''): string
    {
        if ($desde <= 0) {
            $desde = 1;
        }
        if ($hasta <= 0) {
            $hasta = 99999999;
        }
        $sig = max($desde, $ultimo + 1);
        if ($sig > $hasta) {
            $codigo = trim($codigoChequera) !== '' ? trim($codigoChequera) : 's/n';
            $ultimoTxt = $ultimo > 0 ? (string) $ultimo : 'ninguno';

            throw new \RuntimeException(
                'La chequera '.$codigo.' ('.$desde.'-'.$hasta.') no tiene más números. '
                .'El último usado es '.$ultimoTxt.'. '
                .'Hay que dar de alta la chequera nueva antes de emitir otro eCheq.'
            );
        }

        return (string) $sig;
    }

    /**
     * Corta la grabación si el número no entra en la chequera elegida.
     *
     * @throws \RuntimeException
     */
    public static function assertNumeroDentroDeChequera(Chequera $chequera, string $numero): void
    {
        $n = (int) preg_replace('/\D/', '', $numero);
        $desde = (int) preg_replace('/\D/', '', (string) ($chequera->desdenumerocheque ?? ''));
        $hasta = (int) preg_replace('/\D/', '', (string) ($chequera->hastanumerocheque ?? ''));
        if ($desde <= 0 || $hasta <= 0 || $n < $desde || $n > $hasta) {
            $codigo = trim((string) ($chequera->codigo ?? '')) ?: ('#'.$chequera->id);

            throw new \RuntimeException(
                'El eCheq '.$n.' está fuera de la chequera '.$codigo
                .' ('.$desde.'-'.$hasta.'). '
                .'Ese número no se puede emitir: la chequera activa se terminó o el talonario elegido es otro.'
            );
        }
    }

    /**
     * Próximo número del talonario ERP (mismo criterio que OP por propuesta).
     *
     * Cuenta los números ya emitidos en la cuenta de tesorería que caen dentro
     * del rango. Un número cargado en otra chequera igual consume este talonario.
     * Un número fuera de rango no agota la chequera ni habilita seguir por Anita.
     *
     * @throws \InvalidArgumentException|\RuntimeException
     */
    public static function siguienteNumero(int $chequeraId, ?Chequera $chequera = null, bool $lock = false): string
    {
        if ($chequeraId <= 0) {
            throw new \InvalidArgumentException('Chequera inválida.');
        }

        if ($lock) {
            $chequera = Chequera::query()->whereKey($chequeraId)->lockForUpdate()->first();
        } elseif ($chequera === null || (int) $chequera->id !== $chequeraId) {
            $chequera = Chequera::query()->find($chequeraId);
        }

        if ($chequera === null) {
            throw new \RuntimeException('Chequera #'.$chequeraId.' no encontrada.');
        }

        $desde = (int) preg_replace('/\D/', '', (string) ($chequera->desdenumerocheque ?: '1'));
        if ($desde <= 0) {
            $desde = 1;
        }
        $hasta = (int) preg_replace('/\D/', '', (string) ($chequera->hastanumerocheque ?: '99999999'));
        if ($hasta <= 0) {
            $hasta = 99999999;
        }

        $numeros = self::numerosEmitidosCuenta((int) $chequera->cuentacaja_id);
        $ultimoEnRango = self::ultimoDentroDeRango($numeros, $desde, $hasta);
        $ultimo = $ultimoEnRango > 0 ? $ultimoEnRango : ($desde - 1);

        return self::proximoNumeroEnRango($desde, $hasta, $ultimo, (string) ($chequera->codigo ?? ''));
    }

    /**
     * @return list<int>
     */
    private static function numerosEmitidosCuenta(int $cuentacajaId): array
    {
        if ($cuentacajaId <= 0) {
            return [];
        }

        $cast = SqlDialectSupport::castEntero('numerocheque');

        return Cheque::query()
            ->where('origen', 'E')
            ->where('cuentacaja_id', $cuentacajaId)
            ->whereRaw($cast.' > 0')
            ->selectRaw($cast.' as nro')
            ->pluck('nro')
            ->map(static fn ($n) => (int) $n)
            ->all();
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
            // La que se está usando (último número más alto) queda primera,
            // aunque esté agotada: así el aviso de "se terminó" no se saltea
            // eligiendo otro talonario viejo que todavía tenga números.
            $cmp = ((int) ($b['ultimo'] ?? 0)) <=> ((int) ($a['ultimo'] ?? 0));
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

        $chequeras = Chequera::query()
            ->whereIn('id', $ids)
            ->get(['id', 'cuentacaja_id', 'desdenumerocheque', 'hastanumerocheque']);
        if ($chequeras->isEmpty()) {
            return [];
        }

        $numerosPorCuenta = [];
        $out = [];
        foreach ($chequeras as $ch) {
            $cuentaId = (int) $ch->cuentacaja_id;
            if (! array_key_exists($cuentaId, $numerosPorCuenta)) {
                $numerosPorCuenta[$cuentaId] = self::numerosEmitidosCuenta($cuentaId);
            }
            $desde = (int) preg_replace('/\D/', '', (string) ($ch->desdenumerocheque ?? ''));
            $hasta = (int) preg_replace('/\D/', '', (string) ($ch->hastanumerocheque ?? ''));
            $ultimo = self::ultimoDentroDeRango($numerosPorCuenta[$cuentaId], $desde, $hasta);
            if ($ultimo > 0) {
                $out[(int) $ch->id] = $ultimo;
            }
        }

        return $out;
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
            'tipochequera' => (string) ($ch->tipochequera
                ?: \App\Support\Caja\ChequePropioInstrumentoSupport::negociableDefault()),
            'tipochequera_nombre' => self::tipoChequeraNombre((string) ($ch->tipochequera
                ?: \App\Support\Caja\ChequePropioInstrumentoSupport::negociableDefault())),
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
