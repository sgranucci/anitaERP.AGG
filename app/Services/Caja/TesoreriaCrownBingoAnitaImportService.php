<?php

namespace App\Services\Caja;

use App\ApiAnita;
use App\Models\Caja\TesoreriaCrownBingo;
use App\Models\Configuracion\Sala;
use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\Ventas\GastronomiaSalaSupport;
use RuntimeException;

/**
 * Trae movbingo (a-movbingo.c) de cncadmin a tesoreria_crown_bingo.
 */
class TesoreriaCrownBingoAnitaImportService
{
    /**
     * @return array{crear: int, actualizar: int, omitir: int, filas: list<array<string, mixed>>}
     */
    public function plan(string $desdeYmd, string $hastaYmd): array
    {
        [$sala, $desde, $hasta] = $this->contexto($desdeYmd, $hastaYmd);
        $existentes = TesoreriaCrownBingo::query()
            ->where('sala_id', $sala->id)
            ->whereDate('fecha', '>=', $desdeYmd)
            ->whereDate('fecha', '<=', $hastaYmd)
            ->get()
            ->keyBy(fn (TesoreriaCrownBingo $f) => $f->fecha->format('Y-m-d'));

        $crear = 0;
        $actualizar = 0;
        $omitir = 0;
        $filas = [];
        foreach ($this->consultar($desde, $hasta) as $fila) {
            $fechaInt = (int) ($fila->f ?? 0);
            if ($fechaInt < $desde || $fechaInt > $hasta) {
                continue;
            }
            $fecha = sprintf(
                '%04d-%02d-%02d',
                intdiv($fechaInt, 10000),
                intdiv($fechaInt % 10000, 100),
                $fechaInt % 100
            );
            $carton = round((float) ($fila->c ?? 0), 2);
            $premio = round((float) ($fila->p ?? 0), 2);
            $rse = round((float) ($fila->r ?? 0), 2);
            $resultado = TesoreriaCrownBingo::calcularResultado($carton, $premio, $rse);
            $hora = trim((string) ($fila->h ?? ''));
            if (strlen($hora) > 5) {
                $hora = substr($hora, 0, 5);
            }
            $datos = [
                'empresa_id' => (int) $sala->empresa_id,
                'sala_id' => (int) $sala->id,
                'fecha' => $fecha,
                'hora' => $hora !== '' ? $hora : null,
                'tot_carton' => $carton,
                'tot_premio' => $premio,
                'tot_rse' => $rse,
                'resultado' => $resultado,
            ];
            $actual = $existentes->get($fecha);
            if ($actual === null) {
                $accion = 'crear';
                $crear++;
            } elseif (
                abs((float) $actual->tot_carton - $carton) > 0.001
                || abs((float) $actual->tot_premio - $premio) > 0.001
                || abs((float) $actual->tot_rse - $rse) > 0.001
            ) {
                $accion = 'actualizar';
                $actualizar++;
            } else {
                $accion = 'omitir';
                $omitir++;
            }
            $filas[] = [
                'accion' => $accion,
                'id' => $actual?->id,
                'datos' => $datos,
            ];
        }

        return compact('crear', 'actualizar', 'omitir', 'filas');
    }

    /**
     * @param  array{filas: list<array<string, mixed>>}  $plan
     * @return array{altas: int, cambios: int}
     */
    public function aplicar(array $plan, ?int $usuarioId = null): array
    {
        $altas = 0;
        $cambios = 0;
        foreach ($plan['filas'] as $fila) {
            $datos = $fila['datos'];
            if ($usuarioId) {
                $datos['usuario_id'] = $usuarioId;
            }
            if ($fila['accion'] === 'crear') {
                TesoreriaCrownBingo::query()->create($datos);
                $altas++;
            } elseif ($fila['accion'] === 'actualizar' && $fila['id']) {
                $row = TesoreriaCrownBingo::query()->find($fila['id']);
                if ($row) {
                    $row->fill($datos)->save();
                    $cambios++;
                }
            }
        }

        return ['altas' => $altas, 'cambios' => $cambios];
    }

    /**
     * @return array{0: Sala, 1: int, 2: int}
     */
    private function contexto(string $desdeYmd, string $hastaYmd): array
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            throw new RuntimeException('La importación de bingo solo corre en Crown.');
        }
        $codigo = strtoupper(trim((string) config('caja_anita.sala_codigo', 'CIP')));
        $salaId = GastronomiaSalaSupport::idPorCodigo($codigo);
        $sala = $salaId ? Sala::query()->find($salaId) : null;
        if ($sala === null) {
            throw new RuntimeException('No está la sala '.$codigo.'.');
        }
        $desde = (int) str_replace('-', '', $desdeYmd);
        $hasta = (int) str_replace('-', '', $hastaYmd);
        if ($desde <= 0 || $hasta < $desde) {
            throw new RuntimeException('Rango de fechas inválido.');
        }

        return [$sala, $desde, $hasta];
    }

    /**
     * @return list<object>
     */
    private function consultar(int $desde, int $hasta): array
    {
        $api = new ApiAnita;
        $payload = [
            'acc' => 'customSql',
            'campos' => 'f,h,c,p,r,res',
            'customSql' => 'SELECT movb_fecha f, movb_hora h, movb_tot_carton c, movb_tot_premio p,'
                .' movb_tot_rse r, movb_resultado res FROM movbingo'
                .' WHERE movb_fecha BETWEEN '.$desde.' AND '.$hasta
                .' ORDER BY 1',
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
