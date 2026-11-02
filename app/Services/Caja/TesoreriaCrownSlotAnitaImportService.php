<?php

namespace App\Services\Caja;

use App\ApiAnita;
use App\Models\Caja\TesoreriaCrownJornada;
use App\Models\Caja\TesoreriaCrownNumerador;
use App\Models\Caja\TesoreriaCrownSlot;
use App\Models\Caja\TesoreriaCrownSlotLinea;
use App\Models\Caja\TesoreriaCrownValor;
use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Trae el drop y el recuento de slots de un día desde cncadmin.
 * a-efeslot.c y a-recslot.c graban efeslot y rendmbill con origen S.
 */
class TesoreriaCrownSlotAnitaImportService
{
    /** @var array<string, string> */
    private const MONEDA = ['1' => 'ARS', '2' => 'USD', '3' => 'EUR'];

    /**
     * @return array{jornada_id: int, sala_id: int, empresa_id: int, crear: list<array<string, mixed>>, ya: int, avisos: list<string>}
     */
    public function plan(string $fechaYmd): array
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            throw new RuntimeException('La importación de slots solo corre en Crown.');
        }
        $jornada = TesoreriaCrownJornada::query()->whereDate('fecha', $fechaYmd)->first();
        if ($jornada === null) {
            throw new RuntimeException('No hay jornada del '.$fechaYmd.'.');
        }
        $fechaInt = (int) str_replace('-', '', $fechaYmd);
        $cabeceras = $this->consultar(
            'efes_nro_oper,efes_maquina,efes_hora,efes_total,efes_tipo_reg,efes_estado',
            'SELECT efes_nro_oper, efes_maquina, efes_hora, efes_total, efes_tipo_reg, efes_estado'
            .' FROM efeslot WHERE efes_fecha = '.$fechaInt.' ORDER BY efes_maquina, efes_nro_oper'
        );
        if ($cabeceras === []) {
            return [
                'jornada_id' => (int) $jornada->id,
                'sala_id' => (int) $jornada->sala_id,
                'empresa_id' => (int) $jornada->empresa_id,
                'crear' => [],
                'ya' => 0,
                'avisos' => [],
            ];
        }

        $numeros = [];
        $maquinas = [];
        foreach ($cabeceras as $fila) {
            $numeros[] = (int) $fila->efes_nro_oper;
            $codigo = trim((string) $fila->efes_maquina);
            if ($codigo !== '') {
                $maquinas[$codigo] = $codigo;
            }
        }
        $ya = array_fill_keys(
            TesoreriaCrownSlot::query()
                ->where('sala_id', $jornada->sala_id)
                ->whereIn('numero', $numeros)
                ->pluck('numero')
                ->map(fn ($n) => (int) $n)
                ->all(),
            true
        );
        $billetes = $this->porOperacion($this->enLotes($numeros, function (string $lista) {
            return $this->consultar(
                'renmb_nro_oper,renmb_valor,renmb_cantidad,renmb_cod_mon',
                "SELECT renmb_nro_oper, renmb_valor, renmb_cantidad, renmb_cod_mon FROM rendmbill WHERE renmb_origen = 'S' AND renmb_nro_oper IN ($lista)"
            );
        }), 'renmb_nro_oper');
        $billeteValor = $this->billetesPorClave((int) $jornada->sala_id);
        $nombres = $this->nombresMaquina(array_values($maquinas));
        $avisos = $nombres['avisos'];

        $crear = [];
        $omitidos = 0;
        foreach ($cabeceras as $fila) {
            $numero = (int) $fila->efes_nro_oper;
            if (isset($ya[$numero])) {
                $omitidos++;

                continue;
            }
            $codigo = trim((string) $fila->efes_maquina);
            $lineas = [];
            foreach ($billetes[$numero] ?? [] as $linea) {
                if (abs((float) $linea->renmb_cantidad) < 0.0001) {
                    continue;
                }
                $moneda = self::MONEDA[trim((string) $linea->renmb_cod_mon)] ?? '';
                $clave = $moneda.'|'.number_format((float) $linea->renmb_valor, 2, '.', '');
                $valor = $billeteValor[$clave] ?? null;
                if (! $valor instanceof TesoreriaCrownValor) {
                    $avisos[] = 'Operación '.$numero.': no está la denominación '.$clave.'.';

                    continue;
                }
                $lineas[(int) $valor->id] = ($lineas[(int) $valor->id] ?? 0) + (float) $linea->renmb_cantidad;
            }
            $renglones = [];
            foreach ($lineas as $valorId => $cantidad) {
                $renglones[] = ['valor_id' => $valorId, 'cantidad' => $cantidad];
            }
            $tipo = trim((string) $fila->efes_tipo_reg);
            $estado = strtoupper(trim((string) $fila->efes_estado));
            $crear[] = [
                'numero' => $numero,
                'maquina' => mb_substr($codigo, 0, 10),
                'maquina_nombre' => mb_substr($nombres['nombres'][$codigo] ?? '', 0, 30),
                'tipo' => array_key_exists($tipo, TesoreriaCrownSlot::TIPOS) ? $tipo : TesoreriaCrownSlot::DROP,
                'tipo_nombre' => TesoreriaCrownSlot::tipo(array_key_exists($tipo, TesoreriaCrownSlot::TIPOS) ? $tipo : TesoreriaCrownSlot::DROP),
                'estado' => $estado === TesoreriaCrownSlot::BORRADOR ? TesoreriaCrownSlot::BORRADOR : TesoreriaCrownSlot::RECIBIDO,
                'total' => round((float) $fila->efes_total, 2),
                'hora' => $this->hora((string) $fila->efes_hora),
                'lineas' => $renglones,
            ];
        }

        return [
            'jornada_id' => (int) $jornada->id,
            'sala_id' => (int) $jornada->sala_id,
            'empresa_id' => (int) $jornada->empresa_id,
            'crear' => $crear,
            'ya' => $omitidos,
            'avisos' => $avisos,
        ];
    }

    /**
     * @param  array{jornada_id: int, sala_id: int, empresa_id: int, crear: list<array<string, mixed>>}  $plan
     */
    public function aplicar(array $plan): int
    {
        $altas = 0;
        DB::transaction(function () use ($plan, &$altas) {
            $maxNumero = 0;
            foreach ($plan['crear'] as $fila) {
                $slot = TesoreriaCrownSlot::query()->create([
                    'sala_id' => $plan['sala_id'],
                    'empresa_id' => $plan['empresa_id'],
                    'jornada_id' => $plan['jornada_id'],
                    'numero' => $fila['numero'],
                    'maquina' => $fila['maquina'],
                    'maquina_nombre' => $fila['maquina_nombre'] !== '' ? $fila['maquina_nombre'] : null,
                    'tipo' => $fila['tipo'],
                    'estado' => $fila['estado'],
                    'total' => $fila['total'],
                    'hora' => $fila['hora'],
                ]);
                foreach ($fila['lineas'] as $linea) {
                    TesoreriaCrownSlotLinea::query()->create([
                        'slot_id' => $slot->id,
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
            ->where('tipo', 'S')
            ->lockForUpdate()
            ->first();
        if ($fila === null) {
            TesoreriaCrownNumerador::query()->create([
                'sala_id' => $salaId,
                'tipo' => 'S',
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
     * @return array<string, TesoreriaCrownValor>
     */
    private function billetesPorClave(int $salaId): array
    {
        $out = [];
        $valores = TesoreriaCrownValor::query()
            ->with('tipoValor')
            ->where('sala_id', $salaId)
            ->whereHas('tipoValor', fn ($q) => $q->whereIn('codigo', ['ARS', 'USD', 'EUR']))
            ->get();
        foreach ($valores as $valor) {
            $codigo = (string) ($valor->tipoValor->codigo ?? '');
            $out[$codigo.'|'.number_format((float) $valor->denominacion, 2, '.', '')] = $valor;
        }

        return $out;
    }

    /**
     * @param  list<string>  $codigos
     * @return array{nombres: array<string, string>, avisos: list<string>}
     */
    private function nombresMaquina(array $codigos): array
    {
        if ($codigos === []) {
            return ['nombres' => [], 'avisos' => []];
        }
        try {
            $filas = $this->enLotesTexto($codigos, function (string $lista) {
                return $this->consultar(
                    'maq_maquina,maq_desc',
                    'SELECT maq_maquina, maq_desc FROM maquina WHERE TRIM(maq_maquina) IN ('.$lista.')'
                );
            });
        } catch (RuntimeException $e) {
            return ['nombres' => [], 'avisos' => ['No se leyeron los nombres de máquina: '.$e->getMessage()]];
        }
        $nombres = [];
        foreach ($filas as $fila) {
            $nombres[trim((string) $fila->maq_maquina)] = trim((string) $fila->maq_desc);
        }

        return ['nombres' => $nombres, 'avisos' => []];
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
            $lista = implode(',', array_map('intval', $lote));
            foreach ($consulta($lista) as $fila) {
                $out[] = $fila;
            }
        }

        return $out;
    }

    /**
     * @param  list<string>  $codigos
     * @param  callable(string): list<object>  $consulta
     * @return list<object>
     */
    private function enLotesTexto(array $codigos, callable $consulta): array
    {
        $out = [];
        foreach (array_chunk(array_values(array_unique($codigos)), 40) as $lote) {
            $lista = implode(',', array_map(
                fn (string $codigo) => "'".str_replace("'", '', $codigo)."'",
                $lote
            ));
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
