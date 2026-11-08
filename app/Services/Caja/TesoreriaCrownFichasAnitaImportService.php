<?php

namespace App\Services\Caja;

use App\ApiAnita;
use App\Models\Caja\TesoreriaCrownConversionFicha;
use App\Models\Caja\TesoreriaCrownFicha;
use App\Models\Caja\TesoreriaCrownFichaBanca;
use App\Models\Caja\TesoreriaCrownFichaCierre;
use App\Models\Caja\TesoreriaCrownFichaMovimiento;
use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Trae fichamae / conversion / fichammov / fichacmov / fcanjemesa / fichamov /
 * cierreficha / bancatipo de Anita a las tablas ERP del poder público de fichas.
 */
class TesoreriaCrownFichasAnitaImportService
{
    /**
     * @return array{
     *     catalogo: array{crear: int, actualizar: int, omitir: int},
     *     conversion: array{crear: int, actualizar: int, omitir: int},
     *     movimientos: array{crear: int, actualizar: int, omitir: int},
     *     cierres: array{crear: int, actualizar: int, omitir: int},
     *     banca: array{crear: int, actualizar: int, omitir: int},
     *     filas: list<array<string, mixed>>
     * }
     */
    public function plan(string $desdeYmd, string $hastaYmd): array
    {
        $this->assertCrown();
        [$desde, $hasta] = $this->rango($desdeYmd, $hastaYmd);
        $filas = [];

        $catalogo = $this->planCatalogo($filas);
        $conversion = $this->planConversion($desde, $hasta, $filas);
        $movimientos = $this->planMovimientos($desde, $hasta, $filas);
        $cierres = $this->planCierres($hasta, $filas);
        $banca = $this->planBanca($filas);

        return compact('catalogo', 'conversion', 'movimientos', 'cierres', 'banca', 'filas');
    }

    /**
     * @param  array{filas: list<array<string, mixed>>}  $plan
     * @return array{altas: int, cambios: int}
     */
    public function aplicar(array $plan): array
    {
        $altas = 0;
        $cambios = 0;
        DB::transaction(function () use ($plan, &$altas, &$cambios) {
            foreach ($plan['filas'] as $fila) {
                if ($fila['accion'] === 'omitir') {
                    continue;
                }
                $modelo = $fila['modelo'];
                $datos = $fila['datos'];
                if ($fila['accion'] === 'crear') {
                    // updateOrCreate evita chocarse si el plan se armó sin ver una fila ya grabada.
                    $unicos = self::claveUnicaModelo($modelo, $datos);
                    if ($unicos !== []) {
                        $row = $modelo::query()->updateOrCreate($unicos, $datos);
                        if ($row->wasRecentlyCreated) {
                            $altas++;
                        } else {
                            $cambios++;
                        }
                    } else {
                        $modelo::query()->create($datos);
                        $altas++;
                    }
                } elseif ($fila['accion'] === 'actualizar' && ! empty($fila['id'])) {
                    $row = $modelo::query()->find($fila['id']);
                    if ($row) {
                        $row->fill($datos)->save();
                        $cambios++;
                    }
                }
            }
        });

        return compact('altas', 'cambios');
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     * @return array{crear: int, actualizar: int, omitir: int}
     */
    private function planCatalogo(array &$filas): array
    {
        $crear = $actualizar = $omitir = 0;
        $existentes = TesoreriaCrownFicha::query()->get()->keyBy('codigo');
        foreach ($this->consultar(
            'c,s,v,o,canje,d',
            'SELECT fichm_codigo c, fichm_serie s, fichm_valor v, fichm_orden_carga o,'
            .' fichm_ficha_canje canje, fichm_desc_color d FROM fichamae ORDER BY 4'
        ) as $fila) {
            $codigo = (int) ($fila->c ?? 0);
            if ($codigo <= 0) {
                continue;
            }
            $datos = [
                'codigo' => $codigo,
                'serie' => trim((string) ($fila->s ?? '')),
                'valor' => round((float) ($fila->v ?? 0), 2),
                'orden_carga' => (int) ($fila->o ?? 0),
                'ficha_canje' => strtoupper(trim((string) ($fila->canje ?? ''))) === 'S',
                'desc_color' => trim((string) ($fila->d ?? '')),
            ];
            $actual = $existentes->get($codigo);
            $accion = $this->accionCatalogo($actual, $datos);
            if ($accion === 'crear') {
                $crear++;
            } elseif ($accion === 'actualizar') {
                $actualizar++;
            } else {
                $omitir++;
            }
            $filas[] = [
                'grupo' => 'catalogo',
                'accion' => $accion,
                'modelo' => TesoreriaCrownFicha::class,
                'id' => $actual?->id,
                'datos' => $datos,
                'etiqueta' => 'ficha '.$codigo,
            ];
        }

        return compact('crear', 'actualizar', 'omitir');
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     * @return array{crear: int, actualizar: int, omitir: int}
     */
    private function planConversion(int $desde, int $hasta, array &$filas): array
    {
        $crear = $actualizar = $omitir = 0;
        // Incluye el día anterior al rango (conversión inicial del primer día).
        $desdeConv = (int) date('Ymd', strtotime($this->ymd($desde).' -1 day'));
        $existentes = TesoreriaCrownConversionFicha::query()
            ->whereDate('fecha', '>=', $this->ymd($desdeConv))
            ->whereDate('fecha', '<=', $this->ymd($hasta))
            ->get()
            ->keyBy(fn (TesoreriaCrownConversionFicha $r) => $r->fecha->format('Y-m-d').'|'.$r->codigo_ficha);

        foreach ($this->consultar(
            'c,f,fin',
            'SELECT conv_codigo_ficha c, conv_fecha f, conv_conv_final fin FROM conversion'
            .' WHERE conv_fecha BETWEEN '.$desdeConv.' AND '.$hasta
        ) as $fila) {
            $codigo = (int) ($fila->c ?? 0);
            $fechaInt = (int) ($fila->f ?? 0);
            if ($codigo <= 0 || $fechaInt <= 0) {
                continue;
            }
            $fecha = $this->ymd($fechaInt);
            $final = (int) ($fila->fin ?? 0);
            $clave = $fecha.'|'.$codigo;
            $datos = [
                'fecha' => $fecha,
                'codigo_ficha' => $codigo,
                'conv_final' => $final,
            ];
            $actual = $existentes->get($clave);
            if ($actual === null) {
                $accion = 'crear';
                $crear++;
            } elseif ((int) $actual->conv_final !== $final) {
                $accion = 'actualizar';
                $actualizar++;
            } else {
                $accion = 'omitir';
                $omitir++;
            }
            $filas[] = [
                'grupo' => 'conversion',
                'accion' => $accion,
                'modelo' => TesoreriaCrownConversionFicha::class,
                'id' => $actual?->id,
                'datos' => $datos,
                'etiqueta' => 'conv '.$fecha.' #'.$codigo,
            ];
        }

        return compact('crear', 'actualizar', 'omitir');
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     * @return array{crear: int, actualizar: int, omitir: int}
     */
    private function planMovimientos(int $desde, int $hasta, array &$filas): array
    {
        $crear = $actualizar = $omitir = 0;
        // Las asignaciones I vienen sin filtro de rango: hay que indexar también el histórico.
        $existentes = TesoreriaCrownFichaMovimiento::query()
            ->where(function ($q) use ($desde, $hasta) {
                $q->where(function ($q2) use ($desde, $hasta) {
                    $q2->whereDate('fecha', '>=', $this->ymd($desde))
                        ->whereDate('fecha', '<=', $this->ymd($hasta));
                })->orWhere(function ($q2) {
                    $q2->where('origen', TesoreriaCrownFichaMovimiento::ORIGEN_MESA)
                        ->where('tipo_oper', 'I');
                });
            })
            ->get()
            ->keyBy(fn (TesoreriaCrownFichaMovimiento $r) => implode('|', [
                $r->fecha->format('Y-m-d'),
                $r->origen,
                $r->codigo_ficha,
                (string) ($r->tipo_oper ?? ''),
            ]));

        $fuentes = [
            [
                // Asignación inicial (I): hace falta el histórico completo para armar la reserva
                // (último cierre + Σ I). El rango --desde/--hasta no alcanza.
                'origen' => TesoreriaCrownFichaMovimiento::ORIGEN_MESA,
                'sql' => 'SELECT fichmv_fecha f, fichmv_codigo c, fichmv_tipo_oper t, SUM(fichmv_cantidad) q'
                    .' FROM fichammov WHERE fichmv_tipo_oper = "I"'
                    .' GROUP BY 1, 2, 3',
                'conTipo' => true,
            ],
            [
                'origen' => TesoreriaCrownFichaMovimiento::ORIGEN_MESA,
                'sql' => 'SELECT fichmv_fecha f, fichmv_codigo c, fichmv_tipo_oper t, SUM(fichmv_cantidad) q'
                    .' FROM fichammov WHERE fichmv_fecha BETWEEN '.$desde.' AND '.$hasta
                    .' AND fichmv_tipo_oper <> "I"'
                    .' GROUP BY 1, 2, 3',
                'conTipo' => true,
            ],
            [
                'origen' => TesoreriaCrownFichaMovimiento::ORIGEN_CAJERO,
                'sql' => 'SELECT fichcv_fecha f, fichcv_codigo c, fichcv_tipo_oper t, SUM(fichcv_cantidad) q'
                    .' FROM fichacmov WHERE fichcv_fecha BETWEEN '.$desde.' AND '.$hasta
                    .' GROUP BY 1, 2, 3',
                'conTipo' => true,
            ],
            [
                'origen' => TesoreriaCrownFichaMovimiento::ORIGEN_CANJE,
                'sql' => 'SELECT fcanjm_fecha f, fcanjm_codigo c, SUM(fcanjm_cantidad) q'
                    .' FROM fcanjemesa WHERE fcanjm_fecha BETWEEN '.$desde.' AND '.$hasta
                    .' GROUP BY 1, 2',
                'conTipo' => false,
            ],
            [
                'origen' => TesoreriaCrownFichaMovimiento::ORIGEN_OTROS,
                'sql' => 'SELECT fichv_fecha f, fichv_codigo_ficha c, SUM(fichv_cantidad) q'
                    .' FROM fichamov WHERE fichv_fecha BETWEEN '.$desde.' AND '.$hasta
                    .' GROUP BY 1, 2',
                'conTipo' => false,
            ],
        ];

        foreach ($fuentes as $fuente) {
            $campos = $fuente['conTipo'] ? 'f,c,t,q' : 'f,c,q';
            foreach ($this->consultar($campos, $fuente['sql']) as $fila) {
                $fechaInt = (int) ($fila->f ?? 0);
                $codigo = (int) ($fila->c ?? 0);
                if ($fechaInt <= 0 || $codigo <= 0) {
                    continue;
                }
                $fecha = $this->ymd($fechaInt);
                $tipo = $fuente['conTipo']
                    ? strtoupper(trim((string) ($fila->t ?? '')))
                    : '';
                $cantidad = (int) ($fila->q ?? 0);
                $clave = implode('|', [$fecha, $fuente['origen'], $codigo, $tipo]);
                $datos = [
                    'fecha' => $fecha,
                    'origen' => $fuente['origen'],
                    'codigo_ficha' => $codigo,
                    'tipo_oper' => $tipo !== '' ? $tipo : '',
                    'cantidad' => $cantidad,
                ];
                $actual = $existentes->get($clave);
                if ($actual === null) {
                    $accion = 'crear';
                    $crear++;
                } elseif ((int) $actual->cantidad !== $cantidad) {
                    $accion = 'actualizar';
                    $actualizar++;
                } else {
                    $accion = 'omitir';
                    $omitir++;
                }
                $filas[] = [
                    'grupo' => 'movimientos',
                    'accion' => $accion,
                    'modelo' => TesoreriaCrownFichaMovimiento::class,
                    'id' => $actual?->id,
                    'datos' => $datos,
                    'etiqueta' => $fuente['origen'].' '.$fecha.' #'.$codigo.($tipo ? ' '.$tipo : ''),
                ];
            }
        }

        return compact('crear', 'actualizar', 'omitir');
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     * @return array{crear: int, actualizar: int, omitir: int}
     */
    private function planCierres(int $hasta, array &$filas): array
    {
        $crear = $actualizar = $omitir = 0;
        $existentes = TesoreriaCrownFichaCierre::query()
            ->whereDate('fecha', '<=', $this->ymd($hasta))
            ->get()
            ->keyBy(fn (TesoreriaCrownFichaCierre $r) => $r->fecha->format('Y-m-d').'|'.$r->codigo_ficha);

        foreach ($this->consultar(
            'c,f,r',
            'SELECT cief_codigo_ficha c, cief_fecha f, cief_reserva r FROM cierreficha'
            .' WHERE cief_fecha <= '.$hasta
        ) as $fila) {
            $codigo = (int) ($fila->c ?? 0);
            $fechaInt = (int) ($fila->f ?? 0);
            if ($codigo <= 0 || $fechaInt <= 0) {
                continue;
            }
            $fecha = $this->ymd($fechaInt);
            $reserva = (int) ($fila->r ?? 0);
            $clave = $fecha.'|'.$codigo;
            $datos = [
                'fecha' => $fecha,
                'codigo_ficha' => $codigo,
                'reserva' => $reserva,
            ];
            $actual = $existentes->get($clave);
            if ($actual === null) {
                $accion = 'crear';
                $crear++;
            } elseif ((int) $actual->reserva !== $reserva) {
                $accion = 'actualizar';
                $actualizar++;
            } else {
                $accion = 'omitir';
                $omitir++;
            }
            $filas[] = [
                'grupo' => 'cierres',
                'accion' => $accion,
                'modelo' => TesoreriaCrownFichaCierre::class,
                'id' => $actual?->id,
                'datos' => $datos,
                'etiqueta' => 'cierre '.$fecha.' #'.$codigo,
            ];
        }

        return compact('crear', 'actualizar', 'omitir');
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     * @return array{crear: int, actualizar: int, omitir: int}
     */
    private function planBanca(array &$filas): array
    {
        $crear = $actualizar = $omitir = 0;
        $existentes = TesoreriaCrownFichaBanca::query()->get()->keyBy('codigo_ficha');
        foreach ($this->consultar(
            'c,q',
            'SELECT b.bant_codigo c, SUM(b.bant_cantidad) q FROM bancatipo b, mesajuego m'
            .' WHERE m.mesaj_estado = "A" AND b.bant_tipojuego = m.mesaj_tipoj_banca'
            .' GROUP BY 1'
        ) as $fila) {
            $codigo = (int) ($fila->c ?? 0);
            if ($codigo <= 0) {
                continue;
            }
            $cantidad = (int) ($fila->q ?? 0);
            $datos = [
                'codigo_ficha' => $codigo,
                'cantidad' => $cantidad,
            ];
            $actual = $existentes->get($codigo);
            if ($actual === null) {
                $accion = 'crear';
                $crear++;
            } elseif ((int) $actual->cantidad !== $cantidad) {
                $accion = 'actualizar';
                $actualizar++;
            } else {
                $accion = 'omitir';
                $omitir++;
            }
            $filas[] = [
                'grupo' => 'banca',
                'accion' => $accion,
                'modelo' => TesoreriaCrownFichaBanca::class,
                'id' => $actual?->id,
                'datos' => $datos,
                'etiqueta' => 'banca #'.$codigo,
            ];
        }

        return compact('crear', 'actualizar', 'omitir');
    }

    /**
     * @param  TesoreriaCrownFicha|null  $actual
     * @param  array<string, mixed>  $datos
     */
    private function accionCatalogo($actual, array $datos): string
    {
        if ($actual === null) {
            return 'crear';
        }
        if (
            (string) $actual->serie !== (string) $datos['serie']
            || abs((float) $actual->valor - (float) $datos['valor']) > 0.001
            || (int) $actual->orden_carga !== (int) $datos['orden_carga']
            || (bool) $actual->ficha_canje !== (bool) $datos['ficha_canje']
            || (string) $actual->desc_color !== (string) $datos['desc_color']
        ) {
            return 'actualizar';
        }

        return 'omitir';
    }

    /**
     * @param  class-string  $modelo
     * @param  array<string, mixed>  $datos
     * @return array<string, mixed>
     */
    private static function claveUnicaModelo(string $modelo, array $datos): array
    {
        return match ($modelo) {
            TesoreriaCrownConversionFicha::class => [
                'fecha' => $datos['fecha'],
                'codigo_ficha' => $datos['codigo_ficha'],
            ],
            TesoreriaCrownFichaMovimiento::class => [
                'fecha' => $datos['fecha'],
                'origen' => $datos['origen'],
                'codigo_ficha' => $datos['codigo_ficha'],
                'tipo_oper' => $datos['tipo_oper'] ?? '',
            ],
            TesoreriaCrownFichaCierre::class => [
                'fecha' => $datos['fecha'],
                'codigo_ficha' => $datos['codigo_ficha'],
            ],
            TesoreriaCrownFichaBanca::class => [
                'codigo_ficha' => $datos['codigo_ficha'],
            ],
            TesoreriaCrownFicha::class => [
                'codigo' => $datos['codigo'],
            ],
            default => [],
        };
    }

    private function assertCrown(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            throw new RuntimeException('La importación de fichas Crown solo corre en Crown.');
        }
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function rango(string $desdeYmd, string $hastaYmd): array
    {
        $desde = (int) str_replace('-', '', $desdeYmd);
        $hasta = (int) str_replace('-', '', $hastaYmd);
        if ($desde <= 0 || $hasta < $desde) {
            throw new RuntimeException('Rango de fechas inválido.');
        }

        return [$desde, $hasta];
    }

    private function ymd(int $fechaInt): string
    {
        return sprintf(
            '%04d-%02d-%02d',
            intdiv($fechaInt, 10000),
            intdiv($fechaInt % 10000, 100),
            $fechaInt % 100
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
