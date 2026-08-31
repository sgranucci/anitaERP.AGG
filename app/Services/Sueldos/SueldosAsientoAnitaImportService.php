<?php

namespace App\Services\Sueldos;

use App\Models\Contable\Cuentacontable;
use App\Models\Sueldos\Concepto_Imputacion_Sueldos;
use App\Models\Sueldos\Concepto_Sueldos;
use App\Support\Contable\CuentaAutomaticaClaves;
use App\Support\Sueldos\SueldosAsientoAnitaReader;
use App\Support\Sueldos\SueldosAsientoMapeoSupport;
use Illuminate\Support\Facades\DB;

/**
 * Migra asimae/asicon/asicta de Anita a concepto_imputacion_sueldos
 * y completa las patas fijas de sueldos en cuentas automáticas.
 */
class SueldosAsientoAnitaImportService
{
    /** @var array<string, string> */
    public const AUTOMATICAS_CODIGO = [
        CuentaAutomaticaClaves::SUELDOS_A_PAGAR => '213010001',
        CuentaAutomaticaClaves::SUELDOS_GASTO_REMUNERATIVO => '521060001',
        CuentaAutomaticaClaves::SUELDOS_GASTO_NO_REMUNERATIVO => '521070006',
        CuentaAutomaticaClaves::SUELDOS_GASTO_CONTRIBUCION => '521060006',
        CuentaAutomaticaClaves::SUELDOS_PASIVO_RETENCION => '213010002',
        CuentaAutomaticaClaves::SUELDOS_PASIVO_CONTRIBUCION => '213010002',
    ];

    /**
     * @param  list<int>  $empresaIds
     * @return array<string, mixed>
     */
    public function analizar(array $empresaIds, bool $reemplazar = false): array
    {
        return $this->armarPlan($empresaIds, $reemplazar);
    }

    /**
     * @param  list<int>  $empresaIds
     * @return array<string, mixed>
     */
    public function ejecutar(array $empresaIds, bool $reemplazar = false): array
    {
        $plan = $this->armarPlan($empresaIds, $reemplazar);

        DB::transaction(function () use ($plan, $reemplazar) {
            foreach ($plan['imputaciones'] as $fila) {
                if (! in_array($fila['accion'], ['crear', 'actualizar'], true)) {
                    continue;
                }
                $payload = [
                    'empresa_id' => $fila['empresa_id'],
                    'alcance' => SueldosAsientoMapeoSupport::ALCANCE_CONCEPTO,
                    'clave' => (string) $fila['concepto_id'],
                    'concepto_id' => $fila['concepto_id'],
                    'rubro' => null,
                    'tipo' => null,
                    'cuenta_debe_id' => $fila['cuenta_debe_id'],
                    'cuenta_haber_id' => $fila['cuenta_haber_id'],
                    'observacion' => $fila['observacion'],
                ];
                if ($fila['accion'] === 'crear') {
                    Concepto_Imputacion_Sueldos::query()->create($payload);
                    continue;
                }
                if ((int) ($fila['existente_id'] ?? 0) > 0) {
                    Concepto_Imputacion_Sueldos::query()
                        ->whereKey((int) $fila['existente_id'])
                        ->update($payload + ['updated_at' => now()]);
                }
            }

            foreach ($plan['automaticas'] as $fila) {
                if (! in_array($fila['accion'], ['crear', 'actualizar'], true)) {
                    continue;
                }
                $query = DB::table('contabilidad_cuenta_automatica')
                    ->where('empresa_id', $fila['empresa_id'])
                    ->where('clave', $fila['clave']);
                if ($query->exists()) {
                    if ($reemplazar || (int) ($fila['cuenta_id_actual'] ?? 0) <= 0) {
                        $query->update([
                            'cuentacontable_id' => $fila['cuenta_id'],
                            'updated_at' => now(),
                        ]);
                    }
                    continue;
                }
                DB::table('contabilidad_cuenta_automatica')->insert([
                    'empresa_id' => $fila['empresa_id'],
                    'clave' => $fila['clave'],
                    'cuentacontable_id' => $fila['cuenta_id'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });

        return $plan;
    }

    /**
     * @param  list<int>  $empresaIds
     * @return array<string, mixed>
     */
    private function armarPlan(array $empresaIds, bool $reemplazar): array
    {
        $empresaIds = array_values(array_unique(array_filter(
            array_map('intval', $empresaIds),
            fn (int $id) => $id > 0
        )));
        if ($empresaIds === []) {
            throw new \InvalidArgumentException('Indique al menos una empresa.');
        }

        $anita = app(SueldosAsientoAnitaReader::class)->leer();
        $mapeos = $this->consolidarConceptos($anita);

        $conceptos = Concepto_Sueldos::query()
            ->whereIn('codigo', array_keys($mapeos))
            ->get(['id', 'codigo', 'descripcion', 'tipo'])
            ->keyBy(fn (Concepto_Sueldos $c) => (int) $c->codigo);

        $codigosCuenta = [];
        foreach ($mapeos as $m) {
            if ($m['cuenta_debe'] !== '') {
                $codigosCuenta[$m['cuenta_debe']] = true;
            }
            if ($m['cuenta_haber'] !== '') {
                $codigosCuenta[$m['cuenta_haber']] = true;
            }
        }
        foreach (self::AUTOMATICAS_CODIGO as $codigo) {
            $codigosCuenta[$codigo] = true;
        }

        $cuentas = Cuentacontable::query()
            ->whereIn('empresa_id', $empresaIds)
            ->whereIn('codigo', array_keys($codigosCuenta))
            ->get(['id', 'empresa_id', 'codigo', 'nombre']);

        $cuentaPorEmpCodigo = [];
        foreach ($cuentas as $cuenta) {
            $cuentaPorEmpCodigo[(int) $cuenta->empresa_id.'|'.trim((string) $cuenta->codigo)] = $cuenta;
        }

        $existentes = Concepto_Imputacion_Sueldos::query()
            ->whereIn('empresa_id', $empresaIds)
            ->where('alcance', SueldosAsientoMapeoSupport::ALCANCE_CONCEPTO)
            ->get();
        $existentePorClave = [];
        foreach ($existentes as $fila) {
            $existentePorClave[(int) $fila->empresa_id.'|'.$fila->clave] = $fila;
        }

        $autoActual = DB::table('contabilidad_cuenta_automatica')
            ->whereIn('empresa_id', $empresaIds)
            ->whereIn('clave', array_keys(self::AUTOMATICAS_CODIGO))
            ->get();
        $autoPorClave = [];
        foreach ($autoActual as $fila) {
            $autoPorClave[(int) $fila->empresa_id.'|'.$fila->clave] = $fila;
        }

        $imputaciones = [];
        $errores = [];
        $conteo = ['crear' => 0, 'actualizar' => 0, 'igual' => 0, 'omitir' => 0];

        foreach ($mapeos as $codigo => $mapeo) {
            $concepto = $conceptos[$codigo] ?? null;
            if ($concepto === null) {
                $errores[] = 'Concepto Anita '.$codigo.' no está en el ERP.';
                continue;
            }

            foreach ($empresaIds as $empresaId) {
                $debe = $mapeo['cuenta_debe'] !== ''
                    ? ($cuentaPorEmpCodigo[$empresaId.'|'.$mapeo['cuenta_debe']] ?? null)
                    : null;
                $haber = $mapeo['cuenta_haber'] !== ''
                    ? ($cuentaPorEmpCodigo[$empresaId.'|'.$mapeo['cuenta_haber']] ?? null)
                    : null;

                $debeId = $debe ? (int) $debe->id : null;
                $haberId = $haber ? (int) $haber->id : null;
                if ($debeId === null && $haberId === null) {
                    $errores[] = sprintf(
                        'Empresa %d concepto %d: faltan cuentas %s / %s',
                        $empresaId,
                        $codigo,
                        $mapeo['cuenta_debe'] ?: '-',
                        $mapeo['cuenta_haber'] ?: '-'
                    );
                    $conteo['omitir']++;
                    continue;
                }

                $existente = $existentePorClave[$empresaId.'|'.$concepto->id] ?? null;
                $accion = 'crear';
                if ($existente !== null) {
                    $igual = (int) ($existente->cuenta_debe_id ?? 0) === (int) ($debeId ?? 0)
                        && (int) ($existente->cuenta_haber_id ?? 0) === (int) ($haberId ?? 0);
                    if ($igual) {
                        $accion = 'igual';
                    } elseif ($reemplazar) {
                        $accion = 'actualizar';
                    } else {
                        $accion = 'omitir';
                    }
                }

                $conteo[$accion] = ($conteo[$accion] ?? 0) + 1;
                $imputaciones[] = [
                    'empresa_id' => $empresaId,
                    'concepto_id' => (int) $concepto->id,
                    'codigo' => $codigo,
                    'descripcion' => (string) $concepto->descripcion,
                    'tipo' => (string) $concepto->tipo,
                    'cuenta_debe_codigo' => $mapeo['cuenta_debe'],
                    'cuenta_haber_codigo' => $mapeo['cuenta_haber'],
                    'cuenta_debe_id' => $debeId,
                    'cuenta_haber_id' => $haberId,
                    'observacion' => $mapeo['observacion'],
                    'origen_asiento' => $mapeo['origen_asiento'],
                    'resta_de' => $mapeo['resta_de'],
                    'existente_id' => $existente?->id,
                    'accion' => $accion,
                ];
            }
        }

        $automaticas = [];
        $conteoAuto = ['crear' => 0, 'actualizar' => 0, 'igual' => 0, 'omitir' => 0];
        foreach ($empresaIds as $empresaId) {
            foreach (self::AUTOMATICAS_CODIGO as $clave => $codigo) {
                $cuenta = $cuentaPorEmpCodigo[$empresaId.'|'.$codigo] ?? null;
                if ($cuenta === null) {
                    $errores[] = sprintf('Empresa %d: falta cuenta automática %s (%s)', $empresaId, $clave, $codigo);
                    $conteoAuto['omitir']++;
                    continue;
                }
                $actual = $autoPorClave[$empresaId.'|'.$clave] ?? null;
                $cuentaId = (int) $cuenta->id;
                $accion = 'crear';
                if ($actual !== null) {
                    $actualId = (int) ($actual->cuentacontable_id ?? 0);
                    if ($actualId === $cuentaId) {
                        $accion = 'igual';
                    } elseif ($actualId <= 0 || $reemplazar) {
                        $accion = 'actualizar';
                    } else {
                        $accion = 'omitir';
                    }
                }
                $conteoAuto[$accion] = ($conteoAuto[$accion] ?? 0) + 1;
                $automaticas[] = [
                    'empresa_id' => $empresaId,
                    'clave' => $clave,
                    'codigo' => $codigo,
                    'cuenta_id' => $cuentaId,
                    'cuenta_id_actual' => $actual ? (int) ($actual->cuentacontable_id ?? 0) : null,
                    'accion' => $accion,
                ];
            }
        }

        return [
            'cabeceras' => $anita['cabeceras'],
            'empresa_ids' => $empresaIds,
            'reemplazar' => $reemplazar,
            'mapeos' => array_values($mapeos),
            'imputaciones' => $imputaciones,
            'automaticas' => $automaticas,
            'conteo' => $conteo,
            'conteo_automaticas' => $conteoAuto,
            'errores' => $errores,
        ];
    }

    /**
     * @param  array{
     *   cabeceras: array<int, array{nro: int, titulo: string, centro_costos: string, ccosto_contab: int}>,
     *   lineas: array<int, array<int, array{nro: int, linea: int, cuenta: string, dh: string}>>,
     *   conceptos: list<array{nro: int, linea: int, concepto: int, linea_con: int, signo: string}>
     * }  $anita
     * @return array<int, array{
     *   codigo: int,
     *   cuenta_debe: string,
     *   cuenta_haber: string,
     *   resta_de: string,
     *   origen_asiento: int,
     *   observacion: string
     * }>
     */
    private function consolidarConceptos(array $anita): array
    {
        $porConcepto = [];
        foreach ($anita['conceptos'] as $fila) {
            $porConcepto[(int) $fila['concepto']][] = $fila;
        }

        $mapeos = [];
        foreach ($porConcepto as $codigo => $filas) {
            $desdeSueldos = array_values(array_filter(
                $filas,
                fn (array $f) => (int) $f['nro'] === SueldosAsientoAnitaReader::ASIENTO_SUELDOS
            ));
            $origen = $desdeSueldos !== []
                ? SueldosAsientoAnitaReader::ASIENTO_SUELDOS
                : SueldosAsientoAnitaReader::ASIENTO_PREVISION;
            $usar = $origen === SueldosAsientoAnitaReader::ASIENTO_SUELDOS
                ? $desdeSueldos
                : array_values(array_filter(
                    $filas,
                    fn (array $f) => (int) $f['nro'] === $origen
                ));

            $debe = '';
            $haber = '';
            $restaDe = '';
            foreach ($usar as $fila) {
                $linea = $anita['lineas'][$fila['nro']][$fila['linea']] ?? null;
                if ($linea === null) {
                    continue;
                }
                if ($fila['signo'] === '-') {
                    $restaDe = $linea['cuenta'];
                    continue;
                }
                if ($linea['dh'] === 'D') {
                    $debe = $linea['cuenta'];
                }
                if ($linea['dh'] === 'H') {
                    $haber = $linea['cuenta'];
                }
            }

            if ($debe === '' && $haber === '') {
                continue;
            }

            $titulo = $anita['cabeceras'][$origen]['titulo'] ?? ('asiento '.$origen);
            $obs = 'Anita '.$titulo;
            if ($restaDe !== '') {
                $obs .= '; resta '.$restaDe;
            }
            $obs = mb_substr($obs, 0, 160);

            $mapeos[$codigo] = [
                'codigo' => $codigo,
                'cuenta_debe' => $debe,
                'cuenta_haber' => $haber,
                'resta_de' => $restaDe,
                'origen_asiento' => $origen,
                'observacion' => $obs,
            ];
        }

        ksort($mapeos);

        return $mapeos;
    }
}
