<?php

namespace App\Services\Caja\Ferli;

use App\Models\Caja\Tipotransaccion_Caja;
use App\Models\Caja\Usocuentacaja;
use App\Repositories\Caja\CuentacajaRepositoryInterface;
use App\Support\Configuracion\EntornoEmpresaSupport;
use RuntimeException;

/**
 * Maestros de caja para Calzados Ferli (bridge ANITA_IP /usr2/ferli → che_ban.tesmae).
 *
 * - cuentacaja ← tesmae (repositorio genérico; Ferli no usa tesmcbu AGG).
     * - tipotransaccion_caja: Anita tesoreria.t_comp no UNLOAD en Ferli → stubs operativos ERP
     *   (COB/OPP/DEV/ING/EGR/TRA). TRA no existe en tctes Ferli; tesmov nativo es TED/TEH.
 * - usocuentacaja «Local» para POS facturación local.
 */
final class CajaMaestrosAnitaFerliSyncService
{
    /** @var list<array{abreviatura:string,nombre:string,operacion:string,signo:string}> */
    private const TIPOS_CAJA_STUB = [
        ['abreviatura' => 'COB', 'nombre' => 'Cobranza', 'operacion' => 'C', 'signo' => 'I'],
        ['abreviatura' => 'OPP', 'nombre' => 'Orden de pago', 'operacion' => 'P', 'signo' => 'E'],
        ['abreviatura' => 'DEV', 'nombre' => 'Devolución cobranza', 'operacion' => 'E', 'signo' => 'E'],
        ['abreviatura' => 'ING', 'nombre' => 'Ingreso de caja', 'operacion' => 'I', 'signo' => 'I'],
        ['abreviatura' => 'EGR', 'nombre' => 'Egreso de caja', 'operacion' => 'E', 'signo' => 'E'],
        ['abreviatura' => 'TRA', 'nombre' => 'Transferencia', 'operacion' => 'T', 'signo' => 'I'],
    ];

    public function __construct(
        private readonly CuentacajaRepositoryInterface $cuentacajaRepository,
    ) {
    }

    /**
     * @return array{
     *   dry_run: bool,
     *   maestros: array<string, array<string, mixed>>,
     *   diagnostico: list<string>
     * }
     */
    public function sincronizar(bool $dryRun = true): array
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            throw new RuntimeException('CajaMaestrosAnitaFerliSyncService solo aplica a EMPRESA=Calzados Ferli.');
        }

        $diagnostico = [
            'cuentacaja desde che_ban.tesmae (81 cuentas en bridge 254; dry-run previo).',
            'tesmcbu / CBU sync AGG no aplica en Ferli.',
            'tipotransaccion_caja: stubs ERP (Anita tesoreria sin UNLOAD usable).',
            'usocuentacaja Local para vincular medios del POS.',
            'No importa movimientos históricos (tesmov / cobranza Anita).',
        ];

        return [
            'dry_run' => $dryRun,
            'maestros' => [
                'usocuentacaja_local' => $this->syncUsoLocal($dryRun),
                'tipotransaccion_caja' => $this->syncTiposCajaStub($dryRun),
                'cuentacaja' => $this->syncCuentacaja($dryRun),
            ],
            'diagnostico' => $diagnostico,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function syncUsoLocal(bool $dryRun): array
    {
        $nombre = (string) config('facturacion_local.usocuentacaja_nombre', 'Local');
        $existe = Usocuentacaja::query()->where('nombre', $nombre)->exists();
        if ($existe) {
            return [
                'fuente' => 'ERP',
                'en_anita' => 0,
                'insertados' => 0,
                'omitidos' => 1,
                'muestra' => ["ya existe «{$nombre}»"],
            ];
        }

        if (! $dryRun) {
            Usocuentacaja::query()->create(['nombre' => $nombre]);
        }

        return [
            'fuente' => 'ERP stub',
            'en_anita' => 0,
            'insertados' => 1,
            'omitidos' => 0,
            'muestra' => ["crear «{$nombre}»"],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function syncTiposCajaStub(bool $dryRun): array
    {
        $insertados = 0;
        $omitidos = 0;
        $muestra = [];

        foreach (self::TIPOS_CAJA_STUB as $def) {
            $existe = Tipotransaccion_Caja::withTrashed()
                ->where('abreviatura', $def['abreviatura'])
                ->exists();
            if ($existe) {
                $omitidos++;
                continue;
            }
            $insertados++;
            $muestra[] = $def['abreviatura'].' — '.$def['nombre'];
            if ($dryRun) {
                continue;
            }
            Tipotransaccion_Caja::query()->create([
                'nombre' => $def['nombre'],
                'abreviatura' => $def['abreviatura'],
                'operacion' => $def['operacion'],
                'signo' => $def['signo'],
                'estado' => 'A',
            ]);
        }

        return [
            'fuente' => 'stub Ferli (sin Anita)',
            'en_anita' => 0,
            'insertados' => $insertados,
            'omitidos' => $omitidos,
            'muestra' => $muestra,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function syncCuentacaja(bool $dryRun): array
    {
        if ($dryRun) {
            // El comando cuentacaja:sincronizar-anita --dry-run ya midió 81 a crear.
            // Aquí devolvemos conteo vía repositorio listando Anita sin persistir.
            $api = new \App\ApiAnita();
            $filas = json_decode((string) $api->apiCall([
                'acc' => 'list',
                'sistema' => 'che_ban',
                'tabla' => 'tesmae',
                'campos' => 'tesm_cuenta,tesm_desc',
            ]));
            $enAnita = is_array($filas) ? count($filas) : 0;
            $locales = \App\Models\Caja\Cuentacaja::query()->pluck('codigo')->map(
                static fn ($c) => ltrim((string) $c, '0')
            )->all();
            $aCrear = 0;
            $muestra = [];
            if (is_array($filas)) {
                foreach ($filas as $fila) {
                    $codigo = ltrim((string) ($fila->tesm_cuenta ?? ''), '0');
                    if ($codigo === '' || in_array($codigo, $locales, true)) {
                        continue;
                    }
                    $aCrear++;
                    if (count($muestra) < 10) {
                        $muestra[] = ($fila->tesm_cuenta ?? '').' — '.trim((string) ($fila->tesm_desc ?? ''));
                    }
                }
            }

            return [
                'fuente' => 'che_ban.tesmae',
                'en_anita' => $enAnita,
                'insertados' => $aCrear,
                'omitidos' => max(0, $enAnita - $aCrear),
                'muestra' => $muestra,
            ];
        }

        $ret = $this->cuentacajaRepository->sincronizarConAnita(null, false);

        return [
            'fuente' => 'che_ban.tesmae',
            'en_anita' => (int) ($ret['en_anita'] ?? 0),
            'insertados' => (int) ($ret['importados'] ?? 0),
            'omitidos' => (int) ($ret['omitidos'] ?? 0),
            'muestra' => [],
            'errores' => $ret['errores'] ?? [],
        ];
    }
}
