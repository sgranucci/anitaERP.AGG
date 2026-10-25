<?php

namespace App\Services\Caja;

use App\ApiAnita;
use App\Models\Caja\TesoreriaCrownCotizacion;
use App\Models\Caja\TesoreriaCrownDeposito;
use App\Models\Caja\TesoreriaCrownTipoDeposito;
use App\Models\Caja\TesoreriaCrownTipoValor;
use App\Models\Caja\TesoreriaCrownValor;
use App\Models\Configuracion\Sala;
use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\Ventas\GastronomiaSalaSupport;
use RuntimeException;

/**
 * Lee Cipolletti (cncadmin) y arma el catálogo de una sola sala.
 * La cotización sale de shared.cotizacion de ese casino, no de los lotes de monextr.
 */
class TesoreriaCrownAnitaSyncService
{
    /**
     * @return array<string, mixed>
     */
    public function armarPlan(): array
    {
        $contexto = $this->contexto();
        $salaId = $contexto['sala_id'];
        $tiposValor = $this->tiposValor($salaId);
        $tiposDeposito = $this->tiposDeposito($salaId);

        $fichas = $this->planFichas($this->consultar(
            'fichm_codigo,fichm_serie,fichm_desc_color,fichm_valor,fichm_orden_carga,fichm_venta_caja,fichm_ficha_canje',
            'SELECT fichm_codigo, fichm_serie, fichm_desc_color, fichm_valor, fichm_orden_carga, fichm_venta_caja, fichm_ficha_canje FROM fichamae'
        ), $salaId, $tiposValor);

        $denominaciones = $this->planDenominaciones($this->consultar(
            'denom_valor,denom_cod_mon,denom_tipo',
            'SELECT denom_valor, denom_cod_mon, denom_tipo FROM denominacion'
        ), $salaId, $tiposValor);

        $cajas = $this->planDepositos($this->consultar(
            'caja_caja,caja_desc',
            'SELECT caja_caja, caja_desc FROM caja'
        ), $salaId, $contexto['empresa_id'], $tiposDeposito['CAJA'] ?? null, 'caja_caja', 'caja_desc');

        $mesas = $this->planMesas($this->consultar(
            'mesaj_mesajuego,mesaj_desc_tipoj',
            'SELECT mesaj_mesajuego, mesaj_desc_tipoj FROM mesajuego'
        ), $salaId, $contexto['empresa_id'], $tiposDeposito['MESA'] ?? null);

        $cotizaciones = $this->planCotizaciones($this->consultar(
            'cot_fecha,cot_cambio2,cot_cambio3',
            'SELECT cot_fecha, cot_cambio2, cot_cambio3 FROM cotizacion WHERE cot_fecha >= 20260101',
            'shared',
        ), $salaId, $contexto['empresa_id'], $tiposValor);

        return [
            'sala_codigo' => $contexto['sala_codigo'],
            'sala_id' => $salaId,
            'empresa_id' => $contexto['empresa_id'],
            'fichas' => $fichas,
            'denominaciones' => $denominaciones,
            'cajas' => $cajas,
            'mesas' => $mesas,
            'cotizaciones' => $cotizaciones,
        ];
    }

    /**
     * @param  array<string, mixed>  $plan
     * @return array<string, int>
     */
    public function aplicar(array $plan): array
    {
        $this->contexto();
        $altas = 0;
        $cambios = 0;

        foreach (['fichas', 'denominaciones'] as $grupo) {
            foreach ($plan[$grupo]['filas'] as $fila) {
                if ($fila['accion'] === 'crear') {
                    TesoreriaCrownValor::query()->create($fila['datos']);
                    $altas++;
                } elseif ($fila['accion'] === 'actualizar') {
                    $valor = TesoreriaCrownValor::query()->find($fila['id']);
                    if ($valor) {
                        $valor->fill($fila['datos'])->save();
                        $cambios++;
                    }
                }
            }
        }

        foreach (['cajas', 'mesas'] as $grupo) {
            foreach ($plan[$grupo]['filas'] as $fila) {
                if ($fila['accion'] === 'crear') {
                    TesoreriaCrownDeposito::query()->create($fila['datos']);
                    $altas++;
                } elseif ($fila['accion'] === 'actualizar') {
                    $deposito = TesoreriaCrownDeposito::query()->find($fila['id']);
                    if ($deposito) {
                        $deposito->fill($fila['datos'])->save();
                        $cambios++;
                    }
                }
            }
        }

        foreach ($plan['cotizaciones']['filas'] as $fila) {
            if ($fila['accion'] === 'crear') {
                TesoreriaCrownCotizacion::query()->create($fila['datos']);
                $altas++;
            } elseif ($fila['accion'] === 'actualizar') {
                $cotizacion = TesoreriaCrownCotizacion::query()->find($fila['id']);
                if ($cotizacion) {
                    $cotizacion->fill($fila['datos'])->save();
                    $cambios++;
                }
            }
        }

        return ['altas' => $altas, 'cambios' => $cambios];
    }

    /**
     * @return array{sala_id: int, sala_codigo: string, empresa_id: int}
     */
    private function contexto(): array
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            throw new RuntimeException('La sincronización de tesorería Crown solo corre en Crown.');
        }

        $codigo = strtoupper(trim((string) config('caja_anita.sala_codigo', '')));
        $salaId = GastronomiaSalaSupport::idPorCodigo($codigo);
        if (! $salaId) {
            throw new RuntimeException('No está la sala '.$codigo.'.');
        }
        $sala = Sala::query()->find($salaId);
        if (! $sala) {
            throw new RuntimeException('No está la sala '.$codigo.'.');
        }

        return [
            'sala_id' => (int) $sala->id,
            'sala_codigo' => $codigo,
            'empresa_id' => (int) $sala->empresa_id,
        ];
    }

    /**
     * @return array<string, int>
     */
    private function tiposValor(int $salaId): array
    {
        $tipos = TesoreriaCrownTipoValor::query()
            ->where('sala_id', $salaId)
            ->pluck('id', 'codigo');
        foreach (['FCOL', 'FVAL', 'ARS', 'USD', 'EUR'] as $codigo) {
            if (! isset($tipos[$codigo])) {
                throw new RuntimeException('La sala no tiene el tipo de valor '.$codigo.'.');
            }
        }

        return $tipos->map(fn ($id) => (int) $id)->all();
    }

    /**
     * @return array<string, int>
     */
    private function tiposDeposito(int $salaId): array
    {
        $tipos = TesoreriaCrownTipoDeposito::query()
            ->where('sala_id', $salaId)
            ->pluck('id', 'codigo');
        foreach (['CAJA', 'MESA'] as $codigo) {
            if (! isset($tipos[$codigo])) {
                throw new RuntimeException('La sala no tiene el tipo de depósito '.$codigo.'.');
            }
        }

        return $tipos->map(fn ($id) => (int) $id)->all();
    }

    /**
     * @param  list<object>  $filas
     * @param  array<string, int>  $tipos
     * @return array{leer: int, crear: int, actualizar: int, omitir: int, filas: list<array<string, mixed>>, avisos: list<string>}
     */
    private function planFichas(array $filas, int $salaId, array $tipos): array
    {
        $plan = $this->planVacio(count($filas));
        foreach ($filas as $fila) {
            $serie = strtoupper(trim((string) ($fila->fichm_serie ?? '')));
            if ($serie === '') {
                $plan['omitir']++;

                continue;
            }
            $color = strtoupper(trim((string) ($fila->fichm_venta_caja ?? ''))) === 'S'
                || strtoupper(trim((string) ($fila->fichm_ficha_canje ?? ''))) === 'S';
            $tipoId = $color ? $tipos['FCOL'] : $tipos['FVAL'];
            $datos = [
                'sala_id' => $salaId,
                'tipo_valor_id' => $tipoId,
                'serie' => $serie,
                'descripcion' => trim((string) ($fila->fichm_desc_color ?? '')),
                'denominacion' => (float) ($fila->fichm_valor ?? 0),
                'orden' => (int) ($fila->fichm_orden_carga ?? 0),
                'activo' => true,
            ];
            $this->sumarValor($plan, $datos);
        }

        return $plan;
    }

    /**
     * @param  list<object>  $filas
     * @param  array<string, int>  $tipos
     * @return array{leer: int, crear: int, actualizar: int, omitir: int, filas: list<array<string, mixed>>, avisos: list<string>}
     */
    private function planDenominaciones(array $filas, int $salaId, array $tipos): array
    {
        $plan = $this->planVacio(count($filas));
        $orden = ['ARS' => 0, 'USD' => 0, 'EUR' => 0];
        foreach ($filas as $fila) {
            $moneda = trim((string) ($fila->denom_cod_mon ?? ''));
            $codigo = match ($moneda) {
                '1' => 'ARS',
                '2' => 'USD',
                '3' => 'EUR',
                default => '',
            };
            if ($codigo === '') {
                $plan['omitir']++;
                $plan['avisos'][] = 'Denominación con moneda '.$moneda.' sin tipo.';

                continue;
            }
            $valor = (float) ($fila->denom_valor ?? 0);
            if ($valor <= 0) {
                $plan['omitir']++;

                continue;
            }
            $orden[$codigo]++;
            $marca = trim((string) ($fila->denom_tipo ?? ''));
            $clase = match ($marca) {
                '0' => 'Billete',
                '1' => 'Moneda',
                default => 'Denominación',
            };
            $datos = [
                'sala_id' => $salaId,
                'tipo_valor_id' => $tipos[$codigo],
                'serie' => $this->serieImporte($valor),
                'descripcion' => $clase.' '.$this->serieImporte($valor),
                'denominacion' => $valor,
                'orden' => $orden[$codigo],
                'activo' => true,
            ];
            $this->sumarValor($plan, $datos);
        }

        return $plan;
    }

    /**
     * @param  list<object>  $filas
     * @return array{leer: int, crear: int, actualizar: int, omitir: int, filas: list<array<string, mixed>>, avisos: list<string>}
     */
    private function planDepositos(array $filas, int $salaId, int $empresaId, ?int $tipoId, string $campoCodigo, string $campoNombre): array
    {
        $plan = $this->planVacio(count($filas));
        if (! $tipoId) {
            $plan['avisos'][] = 'Falta el tipo de depósito CAJA.';

            return $plan;
        }
        $orden = 0;
        foreach ($filas as $fila) {
            $nombre = trim((string) ($fila->{$campoNombre} ?? ''));
            $codigo = trim((string) ($fila->{$campoCodigo} ?? ''));
            if ($nombre === '' || $codigo === '') {
                $plan['omitir']++;

                continue;
            }
            $orden++;
            $this->sumarDeposito($plan, [
                'sala_id' => $salaId,
                'empresa_id' => $empresaId,
                'tipo_deposito_id' => $tipoId,
                'codigo' => $codigo,
                'nombre' => $nombre,
                'orden' => $orden,
                'activo' => true,
                'caja_id' => null,
            ]);
        }

        return $plan;
    }

    /**
     * @param  list<object>  $filas
     * @return array{leer: int, crear: int, actualizar: int, omitir: int, filas: list<array<string, mixed>>, avisos: list<string>}
     */
    private function planMesas(array $filas, int $salaId, int $empresaId, ?int $tipoId): array
    {
        $plan = $this->planVacio(count($filas));
        if (! $tipoId) {
            $plan['avisos'][] = 'Falta el tipo de depósito MESA.';

            return $plan;
        }
        foreach ($filas as $fila) {
            $numero = (int) ($fila->mesaj_mesajuego ?? 0);
            $desc = trim((string) ($fila->mesaj_desc_tipoj ?? ''));
            if ($numero <= 0) {
                $plan['omitir']++;

                continue;
            }
            $this->sumarDeposito($plan, [
                'sala_id' => $salaId,
                'empresa_id' => $empresaId,
                'tipo_deposito_id' => $tipoId,
                'codigo' => 'M'.$numero,
                'nombre' => trim($numero.' '.$desc),
                'orden' => $numero,
                'activo' => true,
                'caja_id' => null,
            ]);
        }

        return $plan;
    }

    /**
     * @param  list<object>  $filas
     * @param  array<string, int>  $tipos
     * @return array{leer: int, crear: int, actualizar: int, omitir: int, filas: list<array<string, mixed>>, avisos: list<string>}
     */
    private function planCotizaciones(array $filas, int $salaId, int $empresaId, array $tipos): array
    {
        $plan = $this->planVacio(count($filas));
        $columnas = [
            'cot_cambio2' => 'USD',
            'cot_cambio3' => 'EUR',
        ];
        foreach ($filas as $fila) {
            $fecha = (int) ($fila->cot_fecha ?? 0);
            if ($fecha < 20260101) {
                $plan['omitir']++;

                continue;
            }
            $jornada = sprintf('%04d-%02d-%02d', intdiv($fecha, 10000), intdiv($fecha % 10000, 100), $fecha % 100);
            $alguna = false;
            foreach ($columnas as $columna => $codigo) {
                $tipoId = (int) ($tipos[$codigo] ?? 0);
                $tasa = (float) ($fila->{$columna} ?? 0);
                if ($tipoId <= 0 || $tasa <= 0) {
                    continue;
                }
                $alguna = true;
                $this->sumarCotizacion($plan, $salaId, $empresaId, $jornada, $tipoId, $tasa);
            }
            if (! $alguna) {
                $plan['omitir']++;
            }
        }

        return $plan;
    }

    /**
     * @param  array{leer: int, crear: int, actualizar: int, omitir: int, filas: list<array<string, mixed>>, avisos: list<string>}  $plan
     */
    private function sumarCotizacion(array &$plan, int $salaId, int $empresaId, string $jornada, int $tipoId, float $tasa): void
    {
        $datos = [
            'empresa_id' => $empresaId,
            'sala_id' => $salaId,
            'jornada' => $jornada,
            'tipo_valor_id' => $tipoId,
            'compra' => $tasa,
            'venta' => $tasa,
        ];
        $existente = TesoreriaCrownCotizacion::query()
            ->where('sala_id', $salaId)
            ->whereDate('jornada', $jornada)
            ->where('tipo_valor_id', $tipoId)
            ->first();
        if (! $existente) {
            $plan['crear']++;
            $plan['filas'][] = ['accion' => 'crear', 'datos' => $datos];

            return;
        }
        if (abs((float) $existente->compra - $tasa) > 0.000001 || abs((float) $existente->venta - $tasa) > 0.000001) {
            $plan['actualizar']++;
            $plan['filas'][] = ['accion' => 'actualizar', 'id' => $existente->id, 'datos' => $datos];

            return;
        }
        $plan['omitir']++;
    }

    /**
     * @param  array{leer: int, crear: int, actualizar: int, omitir: int, filas: list<array<string, mixed>>, avisos: list<string>}  $plan
     * @param  array<string, mixed>  $datos
     */
    private function sumarValor(array &$plan, array $datos): void
    {
        $existente = TesoreriaCrownValor::query()
            ->where('sala_id', $datos['sala_id'])
            ->where('tipo_valor_id', $datos['tipo_valor_id'])
            ->where('serie', $datos['serie'])
            ->first();
        if (! $existente) {
            $plan['crear']++;
            $plan['filas'][] = ['accion' => 'crear', 'datos' => $datos];

            return;
        }
        $cambia = $existente->descripcion !== $datos['descripcion']
            || abs((float) $existente->denominacion - (float) $datos['denominacion']) > 0.001
            || (int) $existente->orden !== (int) $datos['orden'];
        if ($cambia) {
            unset($datos['activo']);
            $plan['actualizar']++;
            $plan['filas'][] = ['accion' => 'actualizar', 'id' => $existente->id, 'datos' => $datos];

            return;
        }
        $plan['omitir']++;
    }

    /**
     * @param  array{leer: int, crear: int, actualizar: int, omitir: int, filas: list<array<string, mixed>>, avisos: list<string>}  $plan
     * @param  array<string, mixed>  $datos
     */
    private function sumarDeposito(array &$plan, array $datos): void
    {
        $existente = TesoreriaCrownDeposito::query()
            ->where('sala_id', $datos['sala_id'])
            ->where('codigo', $datos['codigo'])
            ->first();
        if (! $existente) {
            $plan['crear']++;
            $plan['filas'][] = ['accion' => 'crear', 'datos' => $datos];

            return;
        }
        if ($existente->nombre !== $datos['nombre']) {
            $plan['actualizar']++;
            $plan['filas'][] = [
                'accion' => 'actualizar',
                'id' => $existente->id,
                'datos' => ['nombre' => $datos['nombre']],
            ];

            return;
        }
        $plan['omitir']++;
    }

    /**
     * @return array{leer: int, crear: int, actualizar: int, omitir: int, filas: list<array<string, mixed>>, avisos: list<string>}
     */
    private function planVacio(int $leer): array
    {
        return [
            'leer' => $leer,
            'crear' => 0,
            'actualizar' => 0,
            'omitir' => 0,
            'filas' => [],
            'avisos' => [],
        ];
    }

    private function serieImporte(float $valor): string
    {
        $texto = rtrim(rtrim(number_format($valor, 2, '.', ''), '0'), '.');

        return $texto === '' ? '0' : $texto;
    }

    /**
     * @return list<object>
     */
    private function consultar(string $campos, string $sql, ?string $sistema = null): array
    {
        $api = new ApiAnita;
        $payload = [
            'acc' => 'customSql',
            'campos' => $campos,
            'customSql' => $sql,
        ];
        $sistema = $sistema ?? config('caja_anita.sistema');
        if ($sistema !== null && $sistema !== '') {
            $payload['sistema'] = $sistema;
        }
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
