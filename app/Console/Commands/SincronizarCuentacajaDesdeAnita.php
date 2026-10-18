<?php

namespace App\Console\Commands;

use App\ApiAnita;
use App\Models\Caja\Cuentacaja;
use App\Repositories\Caja\CuentacajaRepositoryInterface;
use Illuminate\Console\Command;

class SincronizarCuentacajaDesdeAnita extends Command
{
    protected $signature = 'cuentacaja:sincronizar-anita
                            {--codigo= : Importar solo una cuenta por tesm_cuenta Anita}
                            {--con-cbu : En AGG, sincronizar también CBU desde tesmcbu al finalizar}
                            {--dry-run : Solo listar qué se importaría, sin grabar}';

    protected $description = 'Importa cuentas de caja desde Anita (tesmae) que no existen en cuentacaja.';

    public function handle(CuentacajaRepositoryInterface $repository): int
    {
        $codigo = $this->option('codigo');
        $sincronizarCbu = (bool) $this->option('con-cbu');

        if ($this->option('dry-run')) {
            return $this->dryRun($codigo ?: null);
        }

        try {
            if ($codigo !== null && $codigo !== '') {
                $this->info("Importando cuenta Anita tesm_cuenta={$codigo}…");
            } else {
                $this->info('Importando cuentas de caja faltantes desde Anita (tesmae)…');
            }

            $ret = $repository->sincronizarConAnita($codigo ?: null, $sincronizarCbu);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info(
            "En Anita: {$ret['en_anita']}; importados: {$ret['importados']}; omitidos (ya en ERP): {$ret['omitidos']}."
        );

        if ($ret['en_anita'] === 0) {
            $this->warn('Anita no devolvió cuentas en tesmae. Revise la conexión ANITA_* y variables del bridge.');
        }

        foreach ($ret['errores'] as $err) {
            $this->warn($err);
        }

        return self::SUCCESS;
    }

    private function dryRun(?string $codigo): int
    {
        $this->warn('DRY-RUN: no se graba nada. Maestro Anita: tesmae (tesmov es movimiento, no el catálogo).');

        // Crown tesmae no tiene tesm_nro_cbu; AGG/Ferli sí (o vía tesmcbu).
        $esCrown = \App\Support\Configuracion\EntornoEmpresaSupport::esCrown();
        $campos = $esCrown
            ? 'tesm_cuenta, tesm_desc, tesm_codigo_banco, tesm_cta_contable, tesm_cod_mon, tesm_tipo_cuenta'
            : 'tesm_cuenta, tesm_desc, tesm_nro_cbu, tesm_codigo_banco, tesm_cta_contable, tesm_cod_mon';
        $data = [
            'acc' => 'list',
            'sistema' => 'che_ban',
            'tabla' => 'tesmae',
            'campos' => $campos,
        ];
        if ($codigo !== null && $codigo !== '') {
            $codigo = trim($codigo);
            $key = preg_match('/^\d+$/', $codigo) === 1
                ? str_pad(ltrim($codigo, '0') === '' ? '0' : ltrim($codigo, '0'), 8, '0', STR_PAD_LEFT)
                : substr(str_pad($codigo, 8, ' ', STR_PAD_RIGHT), 0, 8);
            $data['whereArmado'] = " WHERE tesm_cuenta = '".$key."' ";
        }

        try {
            $filas = json_decode((new ApiAnita())->apiCall($data));
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if (! is_array($filas)) {
            $this->error('Anita no devolvió un listado válido de tesmae.');

            return self::FAILURE;
        }

        $locales = Cuentacaja::query()->pluck('codigo')->all();
        $localesNorm = array_map(static function ($c) {
            $c = trim((string) $c);
            if (preg_match('/^\d+$/', $c) === 1) {
                $s = ltrim($c, '0');

                return $s === '' ? '0' : $s;
            }

            return $c;
        }, $locales);

        $aCrear = [];
        $omitidas = 0;
        $conCbu = 0;
        foreach ($filas as $fila) {
            $codigoAnita = trim((string) ($fila->tesm_cuenta ?? ''));
            $codigoLocal = preg_match('/^\d+$/', $codigoAnita) === 1
                ? (ltrim($codigoAnita, '0') === '' ? '0' : ltrim($codigoAnita, '0'))
                : $codigoAnita;
            $cbu = trim((string) ($fila->tesm_nro_cbu ?? ''));
            $row = [
                $codigoAnita,
                (string) ($fila->tesm_desc ?? ''),
                $esCrown ? (string) ($fila->tesm_tipo_cuenta ?? '') : $cbu,
                (string) ($fila->tesm_codigo_banco ?? ''),
                (string) ($fila->tesm_cta_contable ?? ''),
                (string) ($fila->tesm_cod_mon ?? ''),
            ];
            if (in_array($codigoLocal, $localesNorm, true)) {
                $omitidas++;
                continue;
            }
            if ($cbu !== '') {
                $conCbu++;
            }
            $aCrear[] = $row;
        }

        $this->info('En tesmae: '.count($filas).'; a crear en ERP: '.count($aCrear).'; ya en ERP: '.$omitidas
            .($esCrown ? '' : '; de las nuevas con CBU: '.$conCbu).'.');

        if ($aCrear === []) {
            $this->info('Nada para importar.');

            return self::SUCCESS;
        }

        $this->table(
            ['tesm_cuenta', 'tesm_desc', $esCrown ? 'tipo' : 'CBU', 'banco', 'cta_contable', 'mon'],
            $aCrear
        );

        return self::SUCCESS;
    }
}
