<?php

namespace App\Services\Caja;

use App\ApiAnita;
use App\Models\Caja\TesoreriaCrownJornada;
use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Cantidad de público por día (tabla ingreso, a-ingreso.c) a tesoreria_crown_jornada.clientes.
 * l-resmesa.c y el Anexo I la leen de ahí: Cantidad de público / Clientes.
 */
class TesoreriaCrownPublicoAnitaImportService
{
    /**
     * @return array{filas: list<array{fecha: string, jornada_id: ?int, actual: ?int, anita: int, accion: string}>, actualizar: int, omitir: int}
     */
    public function plan(string $desde, string $hasta): array
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            throw new RuntimeException('La importación de público solo corre en Crown.');
        }
        $desdeInt = (int) str_replace('-', '', $desde);
        $hastaInt = (int) str_replace('-', '', $hasta);
        if ($desdeInt <= 0 || $hastaInt < $desdeInt) {
            throw new RuntimeException('Rango de fechas inválido.');
        }

        $anita = [];
        foreach ($this->consultar(
            'ingr_fecha,ingr_cantidad',
            'SELECT ingr_fecha, ingr_cantidad FROM ingreso WHERE ingr_fecha BETWEEN '.$desdeInt.' AND '.$hastaInt.' ORDER BY ingr_fecha'
        ) as $fila) {
            $f = (string) (int) $fila->ingr_fecha;
            $anita[substr($f, 0, 4).'-'.substr($f, 4, 2).'-'.substr($f, 6, 2)] = (int) $fila->ingr_cantidad;
        }

        $jornadas = TesoreriaCrownJornada::query()
            ->whereDate('fecha', '>=', $desde)
            ->whereDate('fecha', '<=', $hasta)
            ->get()
            ->keyBy(fn (TesoreriaCrownJornada $j) => $j->fecha->format('Y-m-d'));

        $filas = [];
        $actualizar = 0;
        $omitir = 0;
        foreach ($anita as $fecha => $cantidad) {
            $jornada = $jornadas->get($fecha);
            $actual = $jornada ? (int) ($jornada->clientes ?? 0) : null;
            if ($jornada === null) {
                $accion = 'sin jornada';
                $omitir++;
            } elseif ($actual === $cantidad) {
                $accion = 'igual';
                $omitir++;
            } else {
                $accion = 'actualizar';
                $actualizar++;
            }
            $filas[] = [
                'fecha' => $fecha,
                'jornada_id' => $jornada ? (int) $jornada->id : null,
                'actual' => $actual,
                'anita' => $cantidad,
                'accion' => $accion,
            ];
        }

        return ['filas' => $filas, 'actualizar' => $actualizar, 'omitir' => $omitir];
    }

    /**
     * @param  array{filas: list<array{jornada_id: ?int, anita: int, accion: string}>}  $plan
     */
    public function aplicar(array $plan): int
    {
        $cambios = 0;
        DB::transaction(function () use ($plan, &$cambios) {
            foreach ($plan['filas'] as $fila) {
                if ($fila['accion'] !== 'actualizar') {
                    continue;
                }
                $jornada = TesoreriaCrownJornada::query()->find($fila['jornada_id']);
                if ($jornada === null) {
                    continue;
                }
                $jornada->clientes = $fila['anita'];
                $jornada->save();
                $cambios++;
            }
        });

        return $cambios;
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
