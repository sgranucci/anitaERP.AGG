<?php

namespace App\Services\Caja;

use App\ApiAnita;
use App\Models\Caja\TesoreriaCrownBoveda;
use App\Models\Caja\TesoreriaCrownBovedaLinea;
use App\Models\Caja\TesoreriaCrownBovedaTipo;
use App\Models\Caja\TesoreriaCrownDeposito;
use App\Models\Caja\TesoreriaCrownJornada;
use App\Models\Caja\TesoreriaCrownValor;
use App\Support\Caja\TesoreriaCrownBovedaMonedaExtranjeraPesosSupport;
use App\Support\Caja\TesoreriaCrownBovedaSupport;
use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Trae efeboveda + rendmbill (origen B) de cncadmin a la bóveda de la jornada.
 */
class TesoreriaCrownBovedaAnitaImportService
{
    /** @var array<string, string> */
    private const MONEDA = ['1' => 'ARS', '2' => 'USD', '3' => 'EUR'];

    /**
     * @return array{crear: list<array<string, mixed>>, omitir: list<string>, corte: ?int}
     */
    public function plan(string $fechaYmd, string $corteComentario): array
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            throw new RuntimeException('La importación de bóveda solo corre en Crown.');
        }
        $fechaInt = (int) str_replace('-', '', $fechaYmd);
        $jornada = TesoreriaCrownJornada::query()->whereDate('fecha', $fechaYmd)->first();
        if ($jornada === null) {
            throw new RuntimeException('No hay jornada del '.$fechaYmd.'.');
        }
        $deposito = TesoreriaCrownBovedaSupport::lugares((int) $jornada->sala_id)
            ->first(fn (TesoreriaCrownDeposito $lugar) => (string) ($lugar->tipoDeposito->codigo ?? '') === 'BOVEDA');
        if ($deposito === null) {
            throw new RuntimeException('La sala no tiene depósito de bóveda.');
        }

        $cabeceras = $this->consultar(
            'efeb_nro_oper,efeb_cod_trans,efeb_hora,efeb_legajo,efeb_comentario',
            'SELECT efeb_nro_oper, efeb_cod_trans, efeb_hora, efeb_legajo, efeb_comentario FROM efeboveda WHERE efeb_fecha = '.$fechaInt.' ORDER BY efeb_nro_oper'
        );
        // Corte vacío = importa todo el día (histórico / MTD sin pruebas ERP).
        $corte = trim($corteComentario);
        $indiceCorte = -1;
        if ($corte !== '') {
            $indiceCorte = null;
            foreach ($cabeceras as $i => $fila) {
                if (trim((string) $fila->efeb_comentario) === $corte) {
                    $indiceCorte = $i;
                    break;
                }
            }
            if ($indiceCorte === null) {
                throw new RuntimeException('En esa fecha no está el comentario de corte «'.$corte.'».');
            }
        }

        $tipos = TesoreriaCrownBovedaTipo::query()
            ->where('sala_id', $jornada->sala_id)
            ->get()
            ->keyBy(fn (TesoreriaCrownBovedaTipo $tipo) => (int) $tipo->codigo);
        $valores = [];
        foreach (TesoreriaCrownBovedaSupport::filasEfectivo((int) $jornada->sala_id) as $valor) {
            $codigo = (string) ($valor->tipoValor->codigo ?? '');
            $valores[$codigo.'|'.number_format((float) $valor->denominacion, 2, '.', '')] = $valor;
        }

        $siguientes = array_slice($cabeceras, $indiceCorte + 1);
        $numeros = array_map(fn ($fila) => (int) $fila->efeb_nro_oper, $siguientes);
        $lineas = $numeros === [] ? [] : $this->lineasDe($numeros);
        $numerosMonedaExtranjera = array_map(
            fn ($fila) => (int) $fila->efeb_nro_oper,
            array_filter($siguientes, fn ($fila) => isset(TesoreriaCrownBovedaMonedaExtranjeraPesosSupport::MONEDAS[(int) $fila->efeb_cod_trans]))
        );
        $pesosAnita = $this->importePesosAnita($numerosMonedaExtranjera);
        $porOperacion = [];
        foreach ($lineas as $linea) {
            $porOperacion[(int) $linea->renmb_nro_oper][] = $linea;
        }

        $crear = [];
        $omitir = [];
        $usadosManual = [];
        foreach ($siguientes as $fila) {
            $numero = (int) $fila->efeb_nro_oper;
            if (TesoreriaCrownBoveda::query()->where('sala_id', $jornada->sala_id)->where('numero', $numero)->exists()) {
                $omitir[] = $numero.' ya está en la bóveda.';
                continue;
            }
            $tipo = $tipos->get((int) $fila->efeb_cod_trans);
            if ($tipo === null) {
                throw new RuntimeException('No está el tipo de transacción '.$fila->efeb_cod_trans.' (operación '.$numero.').');
            }
            $detalle = [];
            foreach ($porOperacion[$numero] ?? [] as $linea) {
                if (abs((float) $linea->renmb_cantidad) < 0.0001) {
                    continue;
                }
                $moneda = self::MONEDA[trim((string) $linea->renmb_cod_mon)] ?? '';
                $clave = $moneda.'|'.number_format((float) $linea->renmb_valor, 2, '.', '');
                $valor = $valores[$clave] ?? null;
                if (! $valor instanceof TesoreriaCrownValor) {
                    throw new RuntimeException('No está la denominación '.$clave.' (operación '.$numero.').');
                }
                $detalle[] = [
                    'valor_id' => (int) $valor->id,
                    'cantidad' => round((float) $linea->renmb_cantidad, 2),
                    'moneda' => $moneda,
                    'denominacion' => (float) $valor->denominacion,
                ];
            }
            $manual = $this->cargadoAMano($jornada, (int) $tipo->id, $detalle, $usadosManual);
            if ($manual !== null) {
                $usadosManual[] = (int) $manual->id;
                $omitir[] = $numero.' ya está cargado a mano en el ERP (movimiento '.$manual->numero.').';
                continue;
            }
            $hora = trim((string) $fila->efeb_hora);
            if (preg_match('/^(\d{2}:\d{2})/', $hora, $coincide) === 1) {
                $hora = $coincide[1];
            }
            $crear[] = [
                'numero' => $numero,
                'tipo' => trim($tipo->abreviatura.' '.$tipo->nombre),
                'tipo_id' => (int) $tipo->id,
                'hora' => $hora,
                'legajo' => (int) $fila->efeb_legajo > 0 ? (int) $fila->efeb_legajo : null,
                'comentario' => mb_substr(trim((string) $fila->efeb_comentario), 0, 80),
                'lineas' => $detalle,
                'importe_pesos' => $pesosAnita[$numero] ?? null,
                'jornada_id' => (int) $jornada->id,
                'sala_id' => (int) $jornada->sala_id,
                'empresa_id' => (int) $jornada->empresa_id,
                'deposito_id' => (int) $deposito->id,
                'fecha' => $fechaYmd,
            ];
        }

        return [
            'crear' => $crear,
            'omitir' => $omitir,
            'corte' => $indiceCorte >= 0 && isset($cabeceras[$indiceCorte])
                ? (int) $cabeceras[$indiceCorte]->efeb_nro_oper
                : null,
        ];
    }

    /**
     * @param  array{crear: list<array<string, mixed>>, omitir: list<string>, corte: ?int}  $plan
     */
    public function aplicar(array $plan): int
    {
        $altas = 0;
        DB::transaction(function () use ($plan, &$altas) {
            foreach ($plan['crear'] as $fila) {
                $movimiento = TesoreriaCrownBoveda::query()->create([
                    'sala_id' => $fila['sala_id'],
                    'empresa_id' => $fila['empresa_id'],
                    'jornada_id' => $fila['jornada_id'],
                    'deposito_id' => $fila['deposito_id'],
                    'tipo_id' => $fila['tipo_id'],
                    'numero' => $fila['numero'],
                    'estado' => TesoreriaCrownBoveda::CONFIRMADO,
                    'comentario' => $fila['comentario'] !== '' ? $fila['comentario'] : null,
                    'hora' => $fila['hora'] !== '' ? $fila['hora'] : null,
                    'legajo' => $fila['legajo'],
                    'confirmado_at' => $fila['fecha'].' '.($fila['hora'] !== '' ? $fila['hora'] : '00:00').':00',
                    'importe_pesos' => $fila['importe_pesos'] ?? null,
                ]);
                foreach ($fila['lineas'] as $linea) {
                    TesoreriaCrownBovedaLinea::query()->create([
                        'boveda_id' => $movimiento->id,
                        'valor_id' => $linea['valor_id'],
                        'cantidad' => $linea['cantidad'],
                    ]);
                }
                $altas++;
            }
        });

        return $altas;
    }

    /**
     * Movimiento de bóveda dado de alta en el ERP (no viene de un comprobante de caja) con el
     * mismo tipo y las mismas líneas que la operación de Anita. Evita duplicar lo ya operado.
     *
     * @param  list<array{valor_id: int, cantidad: float}>  $detalle
     * @param  list<int>  $usados
     */
    private function cargadoAMano(TesoreriaCrownJornada $jornada, int $tipoId, array $detalle, array $usados): ?TesoreriaCrownBoveda
    {
        $firma = $this->firmaLineas($detalle);
        $candidatos = TesoreriaCrownBoveda::query()
            ->with('lineas')
            ->where('jornada_id', $jornada->id)
            ->where('tipo_id', $tipoId)
            ->whereNull('comprobante_id')
            ->whereNull('revierte_id')
            ->whereNotIn('id', $usados)
            ->get();
        foreach ($candidatos as $candidato) {
            $lineas = $candidato->lineas
                ->map(fn ($l) => ['valor_id' => (int) $l->valor_id, 'cantidad' => (float) $l->cantidad])
                ->all();
            if ($this->firmaLineas($lineas) === $firma) {
                return $candidato;
            }
        }

        return null;
    }

    /**
     * @param  list<array{valor_id: int, cantidad: float}>  $lineas
     */
    private function firmaLineas(array $lineas): string
    {
        $suma = [];
        foreach ($lineas as $linea) {
            $suma[(int) $linea['valor_id']] = round(($suma[(int) $linea['valor_id']] ?? 0) + (float) $linea['cantidad'], 2);
        }
        $suma = array_filter($suma, fn ($cantidad) => abs($cantidad) > 0.0001);
        ksort($suma);

        return json_encode($suma);
    }

    /**
     * Pesos con que Anita valorizó cada retiro de moneda extranjera: monemov de la operación
     * de bóveda (origen B) × cotización de la partida consumida (FIFO de lee_monextr).
     *
     * @param  list<int>  $numeros  efeb_nro_oper
     * @return array<int, float>  numero => pesos (positivo)
     */
    public function importePesosAnita(array $numeros): array
    {
        $numeros = array_values(array_unique(array_filter(array_map('intval', $numeros))));
        if ($numeros === []) {
            return [];
        }
        $filas = $this->consultar(
            'id,mon,cot,monto',
            "SELECT monev_id, monev_cod_mon, monev_cotizacion, monev_monto FROM monemov WHERE monev_origen = 'B' AND monev_id IN (".implode(',', $numeros).')'
        );
        $out = [];
        foreach ($filas as $fila) {
            if (trim((string) $fila->mon) === '1') {
                continue;
            }
            $id = (int) $fila->id;
            $out[$id] = ($out[$id] ?? 0.0) + (float) $fila->monto * (float) $fila->cot;
        }

        return array_map(fn (float $pesos) => round(abs($pesos), 2), $out);
    }

    /**
     * @param  list<int>  $numeros
     * @return list<object>
     */
    private function lineasDe(array $numeros): array
    {
        $lista = implode(',', array_map('intval', $numeros));

        return $this->consultar(
            'renmb_nro_oper,renmb_offset,renmb_valor,renmb_cantidad,renmb_cod_mon',
            "SELECT renmb_nro_oper, renmb_offset, renmb_valor, renmb_cantidad, renmb_cod_mon FROM rendmbill WHERE renmb_origen = 'B' AND renmb_nro_oper IN ($lista) ORDER BY renmb_nro_oper, renmb_offset"
        );
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
