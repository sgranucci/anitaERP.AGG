<?php

namespace App\Services\Caja;

use App\ApiAnita;
use App\Models\Caja\CajeroCrown;
use App\Models\Caja\TesoreriaCrownDeposito;
use App\Models\Caja\TesoreriaCrownJornada;
use App\Models\Caja\TesoreriaCrownMesa;
use App\Models\Caja\TesoreriaCrownMesaJuego;
use App\Models\Caja\TesoreriaCrownMesaLinea;
use App\Models\Caja\TesoreriaCrownNumerador;
use App\Models\Caja\TesoreriaCrownValor;
use App\Support\Caja\TesoreriaCrownMesaSupport;
use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Trae el día de mesas desde cncadmin.
 * a-fichammae.c → fichammae + fichammov (A/R/C/F).
 * a-efemesa.c → efemesa + rendmbill origen M + fcanjemesa (drop D / propina P).
 */
class TesoreriaCrownMesaAnitaImportService
{
    /** @var array<string, string> */
    private const MONEDA = ['1' => 'ARS', '2' => 'USD', '3' => 'EUR'];

    /** @var array<string, string> */
    private const FICHA_TIPO = [
        'A' => 'A',
        'R' => 'R',
        'C' => 'C',
        'F' => 'F',
    ];

    /**
     * @param  list<int>  $excepto
     * @return array{
     *     crear: list<array<string, mixed>>,
     *     omitir: list<string>,
     *     avisos: list<string>,
     *     pruebas: list<array<string, mixed>>,
     *     mesa1: array<string, mixed>
     * }
     */
    public function plan(string $fechaYmd, array $excepto = []): array
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            throw new RuntimeException('La importación de mesas solo corre en Crown.');
        }
        $fechaInt = (int) str_replace('-', '', $fechaYmd);
        $jornada = TesoreriaCrownJornada::query()->whereDate('fecha', $fechaYmd)->first();
        if ($jornada === null) {
            throw new RuntimeException('No hay jornada del '.$fechaYmd.'.');
        }
        $excepto = array_fill_keys($excepto, true);
        $depositos = TesoreriaCrownDeposito::query()
            ->where('sala_id', $jornada->sala_id)
            ->whereHas('tipoDeposito', fn ($q) => $q->where('codigo', 'MESA'))
            ->get()
            ->keyBy(function (TesoreriaCrownDeposito $deposito) {
                return (int) ltrim((string) $deposito->codigo, 'Mm');
            });
        $fichas = $this->fichasPorCodigo((int) $jornada->sala_id);
        $billetes = $this->billetesPorClave((int) $jornada->sala_id);
        $cajeros = CajeroCrown::query()
            ->where('sala_id', $jornada->sala_id)
            ->get()
            ->keyBy(fn (CajeroCrown $cajero) => (int) $cajero->codigo);
        $juegos = TesoreriaCrownMesaJuego::query()
            ->whereIn('deposito_id', $depositos->pluck('id'))
            ->get()
            ->keyBy('deposito_id');

        $crear = [];
        $omitir = [];
        $avisos = [];

        $cabFicha = $this->consultar(
            'fichmm_nro_oper,fichmm_tipo_oper,fichmm_mesajuego,fichmm_cajero,fichmm_hora,fichmm_propina',
            'SELECT fichmm_nro_oper, fichmm_tipo_oper, fichmm_mesajuego, fichmm_cajero, fichmm_hora, fichmm_propina FROM fichammae WHERE fichmm_fecha = '.$fechaInt.' ORDER BY fichmm_mesajuego, fichmm_nro_oper'
        );
        $numFicha = array_map(fn ($f) => (int) $f->fichmm_nro_oper, $cabFicha);
        $linFicha = $this->porOperacion($this->enLotes($numFicha, function (string $lista) {
            return $this->consultar(
                'fichmv_nro_oper,fichmv_codigo,fichmv_cantidad',
                "SELECT fichmv_nro_oper, fichmv_codigo, fichmv_cantidad FROM fichammov WHERE fichmv_nro_oper IN ($lista)"
            );
        }), 'fichmv_nro_oper');

        foreach ($cabFicha as $fila) {
            $mesaNro = (int) $fila->fichmm_mesajuego;
            if (isset($excepto[$mesaNro])) {
                continue;
            }
            $tipoAnita = strtoupper(trim((string) $fila->fichmm_tipo_oper));
            $tipo = self::FICHA_TIPO[$tipoAnita] ?? null;
            if ($tipo === null) {
                $avisos[] = 'Mesa '.$mesaNro.' operación '.$fila->fichmm_nro_oper.': tipo de ficha '.$tipoAnita.' no se importa.';

                continue;
            }
            $deposito = $depositos->get($mesaNro);
            if (! $deposito instanceof TesoreriaCrownDeposito) {
                throw new RuntimeException('No está el depósito de la mesa '.$mesaNro.'.');
            }
            $numero = (int) $fila->fichmm_nro_oper;
            if (TesoreriaCrownMesa::query()->where('sala_id', $jornada->sala_id)->where('numero', $numero)->exists()) {
                $omitir[] = $numero.' (mesa '.$mesaNro.' '.$tipo.') ya está.';

                continue;
            }
            $lineas = [];
            foreach ($linFicha[$numero] ?? [] as $linea) {
                if (abs((float) $linea->fichmv_cantidad) < 0.0001) {
                    continue;
                }
                $codigo = (int) $linea->fichmv_codigo;
                $valorId = $fichas[$codigo] ?? null;
                if ($valorId === null) {
                    throw new RuntimeException('No está la ficha '.$codigo.' (operación '.$numero.').');
                }
                $lineas[$valorId] = ($lineas[$valorId] ?? 0) + (float) $linea->fichmv_cantidad;
            }
            $crear[] = $this->filaMovimiento(
                $jornada,
                $deposito,
                $juegos->get($deposito->id),
                $cajeros->get((int) $fila->fichmm_cajero),
                $tipo,
                $numero,
                $this->hora((string) $fila->fichmm_hora),
                $fechaYmd,
                'Anita fichammae '.$numero,
                $lineas,
                $mesaNro,
                $tipo === 'F' ? round((float) ($fila->fichmm_propina ?? 0), 2) : 0.0
            );
        }

        $cabDrop = $this->consultar(
            'efem_nro_oper,efem_tipo_oper,efem_mesajuego,efem_cajero,efem_hora,efem_voucher,efem_total_peso',
            'SELECT efem_nro_oper, efem_tipo_oper, efem_mesajuego, efem_cajero, efem_hora, efem_voucher, efem_total_peso FROM efemesa WHERE efem_fecha = '.$fechaInt.' ORDER BY efem_mesajuego, efem_nro_oper'
        );
        $numDrop = array_map(fn ($f) => (int) $f->efem_nro_oper, $cabDrop);
        $linBill = $this->porOperacion($this->enLotes($numDrop, function (string $lista) {
            return $this->consultar(
                'renmb_nro_oper,renmb_valor,renmb_cantidad,renmb_cod_mon',
                "SELECT renmb_nro_oper, renmb_valor, renmb_cantidad, renmb_cod_mon FROM rendmbill WHERE renmb_origen = 'M' AND renmb_nro_oper IN ($lista)"
            );
        }), 'renmb_nro_oper');
        $linCanje = $this->porOperacion($this->enLotes($numDrop, function (string $lista) {
            return $this->consultar(
                'fcanjm_nro_oper,fcanjm_codigo,fcanjm_cantidad',
                "SELECT fcanjm_nro_oper, fcanjm_codigo, fcanjm_cantidad FROM fcanjemesa WHERE fcanjm_nro_oper IN ($lista)"
            );
        }), 'fcanjm_nro_oper');

        foreach ($cabDrop as $fila) {
            $mesaNro = (int) $fila->efem_mesajuego;
            if (isset($excepto[$mesaNro])) {
                continue;
            }
            $tipoAnita = strtoupper(trim((string) $fila->efem_tipo_oper));
            $tipo = match ($tipoAnita) {
                'P' => 'P',
                'F', 'I', 'C' => 'D',
                default => null,
            };
            if ($tipo === null) {
                $avisos[] = 'Mesa '.$mesaNro.' operación '.$fila->efem_nro_oper.': tipo drop '.$tipoAnita.' no se importa.';

                continue;
            }
            $deposito = $depositos->get($mesaNro);
            if (! $deposito instanceof TesoreriaCrownDeposito) {
                throw new RuntimeException('No está el depósito de la mesa '.$mesaNro.'.');
            }
            $numero = (int) $fila->efem_nro_oper;
            if (TesoreriaCrownMesa::query()->where('sala_id', $jornada->sala_id)->where('numero', $numero)->exists()) {
                $omitir[] = $numero.' (mesa '.$mesaNro.' '.$tipo.') ya está.';

                continue;
            }
            $lineas = [];
            foreach ($linBill[$numero] ?? [] as $linea) {
                if (abs((float) $linea->renmb_cantidad) < 0.0001) {
                    continue;
                }
                $moneda = self::MONEDA[trim((string) $linea->renmb_cod_mon)] ?? '';
                $clave = $moneda.'|'.number_format((float) $linea->renmb_valor, 2, '.', '');
                $valor = $billetes[$clave] ?? null;
                if (! $valor instanceof TesoreriaCrownValor) {
                    throw new RuntimeException('No está la denominación '.$clave.' (drop '.$numero.').');
                }
                $lineas[(int) $valor->id] = ($lineas[(int) $valor->id] ?? 0) + (float) $linea->renmb_cantidad;
            }
            foreach ($linCanje[$numero] ?? [] as $linea) {
                if (abs((float) $linea->fcanjm_cantidad) < 0.0001) {
                    continue;
                }
                $codigo = (int) $linea->fcanjm_codigo;
                $valorId = $fichas[$codigo] ?? null;
                if ($valorId === null) {
                    throw new RuntimeException('No está la ficha de canje '.$codigo.' (drop '.$numero.').');
                }
                $lineas[$valorId] = ($lineas[$valorId] ?? 0) + (float) $linea->fcanjm_cantidad;
            }
            $voucher = round((float) $fila->efem_voucher, 2);
            $comentario = 'Anita efemesa '.$numero;
            if ($voucher > 0.0001) {
                $comentario = mb_substr($comentario.' voucher '.number_format($voucher, 2, ',', '.'), 0, 80);
            }
            $crear[] = $this->filaMovimiento(
                $jornada,
                $deposito,
                $juegos->get($deposito->id),
                $cajeros->get((int) $fila->efem_cajero),
                $tipo,
                $numero,
                $this->hora((string) $fila->efem_hora),
                $fechaYmd,
                $comentario,
                $lineas,
                $mesaNro,
                0.0,
                $voucher
            );
        }

        usort($crear, fn (array $a, array $b) => [$a['mesa'], $a['numero']] <=> [$b['mesa'], $b['numero']]);

        $pruebas = TesoreriaCrownMesa::query()
            ->with(['lineas.valor.tipoValor', 'deposito'])
            ->where('jornada_id', $jornada->id)
            ->whereHas('deposito', fn ($q) => $q->where('codigo', 'M1'))
            ->where('numero', '<', 1000)
            ->orderBy('numero')
            ->get();
        $mesa1 = $this->compararMesa1($pruebas, $cabFicha, $linFicha, $fichas);

        return [
            'crear' => $crear,
            'omitir' => $omitir,
            'avisos' => $avisos,
            'pruebas' => $pruebas->map(fn (TesoreriaCrownMesa $m) => [
                'id' => (int) $m->id,
                'numero' => (int) $m->numero,
                'tipo' => (string) $m->tipo,
                'comentario' => (string) $m->comentario,
                'lineas' => $m->lineas->count(),
            ])->all(),
            'mesa1' => $mesa1,
            'jornada_id' => (int) $jornada->id,
            'sala_id' => (int) $jornada->sala_id,
        ];
    }

    /**
     * @param  array{crear: list<array<string, mixed>>, pruebas: list<array<string, mixed>>, sala_id: int}  $plan
     */
    public function aplicar(array $plan, bool $reemplazarPrueba = false): int
    {
        $altas = 0;
        DB::transaction(function () use ($plan, $reemplazarPrueba, &$altas) {
            if ($reemplazarPrueba && $plan['pruebas'] !== []) {
                $ids = array_column($plan['pruebas'], 'id');
                TesoreriaCrownMesaLinea::query()->whereIn('mesa_id', $ids)->get()->each->delete();
                TesoreriaCrownMesa::query()->whereIn('id', $ids)->get()->each->delete();
            }
            $maxNumero = 0;
            foreach ($plan['crear'] as $fila) {
                if (TesoreriaCrownMesa::query()->where('sala_id', $fila['sala_id'])->where('numero', $fila['numero'])->exists()) {
                    continue;
                }
                $movimiento = TesoreriaCrownMesa::query()->create([
                    'sala_id' => $fila['sala_id'],
                    'empresa_id' => $fila['empresa_id'],
                    'jornada_id' => $fila['jornada_id'],
                    'deposito_id' => $fila['deposito_id'],
                    'cajero_crown_id' => $fila['cajero_id'],
                    'banca_id' => $fila['banca_id'],
                    'tipo' => $fila['tipo'],
                    'numero' => $fila['numero'],
                    'estado' => TesoreriaCrownMesa::CONFIRMADO,
                    'comentario' => $fila['comentario'],
                    'propina' => round((float) ($fila['propina'] ?? 0), 2),
                    'voucher' => round((float) ($fila['voucher'] ?? 0), 2),
                    'confirmado_at' => $fila['fecha'].' '.$fila['hora'].':00',
                ]);
                foreach ($fila['lineas'] as $linea) {
                    TesoreriaCrownMesaLinea::query()->create([
                        'mesa_id' => $movimiento->id,
                        'valor_id' => $linea['valor_id'],
                        'cantidad' => $linea['cantidad'],
                    ]);
                }
                $maxNumero = max($maxNumero, (int) $fila['numero']);
                $altas++;
            }
            if ($maxNumero > 0) {
                $this->alinearNumerador((int) $plan['sala_id'], $maxNumero);
            }
        });

        return $altas;
    }

    private function alinearNumerador(int $salaId, int $maxNumero): void
    {
        $fila = TesoreriaCrownNumerador::query()
            ->where('sala_id', $salaId)
            ->where('tipo', 'M')
            ->lockForUpdate()
            ->first();
        if ($fila === null) {
            TesoreriaCrownNumerador::query()->create([
                'sala_id' => $salaId,
                'tipo' => 'M',
                'ultimo' => $maxNumero,
            ]);

            return;
        }
        if ((int) $fila->ultimo < $maxNumero) {
            $fila->ultimo = $maxNumero;
            $fila->save();
        }
    }

    /**
     * @param  \Illuminate\Support\Collection<int, TesoreriaCrownMesa>  $pruebas
     * @param  list<object>  $cabFicha
     * @param  array<int, list<object>>  $linFicha
     * @param  array<int, int>  $fichas
     * @return array{coincide: bool, detalle: list<string>}
     */
    private function compararMesa1($pruebas, array $cabFicha, array $linFicha, array $fichas): array
    {
        $detalle = [];
        $anita = [];
        foreach ($cabFicha as $fila) {
            if ((int) $fila->fichmm_mesajuego !== 1) {
                continue;
            }
            $tipo = self::FICHA_TIPO[strtoupper(trim((string) $fila->fichmm_tipo_oper))] ?? null;
            if ($tipo === null) {
                continue;
            }
            $suma = [];
            foreach ($linFicha[(int) $fila->fichmm_nro_oper] ?? [] as $linea) {
                if (abs((float) $linea->fichmv_cantidad) < 0.0001) {
                    continue;
                }
                $valorId = $fichas[(int) $linea->fichmv_codigo] ?? null;
                if ($valorId === null) {
                    continue;
                }
                $suma[$valorId] = ($suma[$valorId] ?? 0) + (float) $linea->fichmv_cantidad;
            }
            $anita[$tipo][] = ['numero' => (int) $fila->fichmm_nro_oper, 'lineas' => $suma];
        }
        if ($pruebas->isEmpty()) {
            return ['coincide' => true, 'detalle' => ['No hay movimientos de prueba en la mesa 1.']];
        }
        foreach ($pruebas as $mov) {
            $detalle[] = 'ERP n='.$mov->numero.' tipo '.$mov->tipo.' «'.$mov->comentario.'» con '.$mov->lineas->count().' renglones.';
            $candidatos = $anita[$mov->tipo] ?? [];
            if ($candidatos === []) {
                $detalle[] = '  Anita no tiene tipo '.$mov->tipo.' en la mesa 1 ese día.';

                continue;
            }
            $erp = [];
            foreach ($mov->lineas as $linea) {
                $erp[(int) $linea->valor_id] = (float) $linea->cantidad;
            }
            $ok = false;
            foreach ($candidatos as $cand) {
                if ($this->mismasCantidades($erp, $cand['lineas'])) {
                    $ok = true;
                    $detalle[] = '  Coincide con Anita '.$cand['numero'].'.';
                    break;
                }
            }
            if (! $ok) {
                $cand = $candidatos[0];
                $detalle[] = '  No coincide con Anita '.$cand['numero'].' (Anita tiene '.count($cand['lineas']).' fichas distintas; ERP tiene '.count($erp).').';
            }
        }

        $coincide = ! str_contains(implode("\n", $detalle), 'No coincide')
            && ! str_contains(implode("\n", $detalle), 'no tiene tipo');

        return ['coincide' => $coincide, 'detalle' => $detalle];
    }

    /**
     * @param  array<int, float>  $a
     * @param  array<int, float>  $b
     */
    private function mismasCantidades(array $a, array $b): bool
    {
        if (count($a) !== count($b)) {
            return false;
        }
        foreach ($a as $id => $cantidad) {
            if (abs(($b[$id] ?? 0) - $cantidad) > 0.001) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<int, float>  $lineas
     * @return array<string, mixed>
     */
    private function filaMovimiento(
        TesoreriaCrownJornada $jornada,
        TesoreriaCrownDeposito $deposito,
        ?TesoreriaCrownMesaJuego $juego,
        ?CajeroCrown $cajero,
        string $tipo,
        int $numero,
        string $hora,
        string $fechaYmd,
        string $comentario,
        array $lineas,
        int $mesaNro,
        float $propina = 0.0,
        float $voucher = 0.0
    ): array {
        $banca = null;
        if ($juego !== null) {
            $banca = TesoreriaCrownMesaSupport::bancaVigente(
                (int) $jornada->sala_id,
                (int) $juego->banca_codigo,
                $fechaYmd
            );
        }
        $detalle = [];
        foreach ($lineas as $valorId => $cantidad) {
            $detalle[] = [
                'valor_id' => (int) $valorId,
                'cantidad' => round((float) $cantidad, 2),
            ];
        }

        return [
            'mesa' => $mesaNro,
            'deposito' => trim($deposito->codigo.' '.$deposito->nombre),
            'deposito_id' => (int) $deposito->id,
            'cajero_id' => $cajero?->id,
            'cajero' => $cajero ? trim($cajero->codigo.' '.$cajero->nombre) : 'sin cajero',
            'banca_id' => $banca?->id,
            'tipo' => $tipo,
            'tipo_nombre' => TesoreriaCrownMesaSupport::tipo($tipo),
            'numero' => $numero,
            'propina' => round($propina, 2),
            'voucher' => round($voucher, 2),
            'hora' => $hora,
            'comentario' => mb_substr($comentario, 0, 80),
            'lineas' => $detalle,
            'renglones' => count($detalle),
            'sala_id' => (int) $jornada->sala_id,
            'empresa_id' => (int) $jornada->empresa_id,
            'jornada_id' => (int) $jornada->id,
            'fecha' => $fechaYmd,
        ];
    }

    /**
     * @return array<int, int>
     */
    private function fichasPorCodigo(int $salaId): array
    {
        $porSerie = [];
        foreach (TesoreriaCrownValor::query()->where('sala_id', $salaId)->get(['id', 'serie']) as $valor) {
            $serie = strtoupper(trim((string) $valor->serie));
            if ($serie !== '') {
                $porSerie[$serie] = (int) $valor->id;
            }
        }
        $out = [];
        foreach ($this->consultar('fichm_codigo,fichm_serie', 'SELECT fichm_codigo, fichm_serie FROM fichamae') as $fila) {
            $serie = strtoupper(trim((string) $fila->fichm_serie));
            if ($serie !== '' && isset($porSerie[$serie])) {
                $out[(int) $fila->fichm_codigo] = $porSerie[$serie];
            }
        }

        return $out;
    }

    /**
     * @return array<string, TesoreriaCrownValor>
     */
    private function billetesPorClave(int $salaId): array
    {
        $out = [];
        foreach (TesoreriaCrownValor::query()->with('tipoValor')->where('sala_id', $salaId)->get() as $valor) {
            $codigo = (string) ($valor->tipoValor->codigo ?? '');
            if (! in_array($codigo, ['ARS', 'USD', 'EUR'], true)) {
                continue;
            }
            $out[$codigo.'|'.number_format((float) $valor->denominacion, 2, '.', '')] = $valor;
        }

        return $out;
    }

    /**
     * @param  list<object>  $filas
     * @return array<int, list<object>>
     */
    private function porOperacion(array $filas, string $campo): array
    {
        $out = [];
        foreach ($filas as $fila) {
            $out[(int) $fila->{$campo}][] = $fila;
        }

        return $out;
    }

    /**
     * @param  list<int>  $numeros
     * @param  callable(string): list<object>  $consulta
     * @return list<object>
     */
    private function enLotes(array $numeros, callable $consulta): array
    {
        $out = [];
        foreach (array_chunk(array_values(array_unique($numeros)), 40) as $lote) {
            if ($lote === []) {
                continue;
            }
            $lista = implode(',', array_map('intval', $lote));
            foreach ($consulta($lista) as $fila) {
                $out[] = $fila;
            }
        }

        return $out;
    }

    private function hora(string $hora): string
    {
        $hora = trim($hora);
        if (preg_match('/^(\d{2}:\d{2})/', $hora, $coincide) === 1) {
            return $coincide[1];
        }

        return '00:00';
    }

    /**
     * @return list<object>
     */
    private function consultar(string $campos, string $sql): array
    {
        $api = new ApiAnita;
        $payload = [
            'acc' => 'customSql',
            'campos' => $campos,
            'customSql' => $sql,
            'sistema' => config('caja_anita.sistema'),
        ];
        $ifx = trim((string) config('caja_anita.ifx_server', ''));
        if ($ifx !== '') {
            $payload['ifx_server'] = $ifx;
        }
        $path = rtrim(trim((string) config('caja_anita.path_sistema', '')), '/');
        if ($path !== '') {
            $payload['path_sistema'] = $path;
        }
        $raw = $api->apiCall($payload);
        $error = ApiAnita::extraerMensajeError(is_string($raw) ? $raw : null);
        if ($error !== null && $error !== '') {
            throw new RuntimeException($error);
        }
        $filas = json_decode(is_string($raw) ? $raw : '[]');

        return is_array($filas) ? $filas : [];
    }
}
