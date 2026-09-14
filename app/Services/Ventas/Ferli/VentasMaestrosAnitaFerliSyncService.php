<?php

namespace App\Services\Ventas\Ferli;

use App\ApiAnita;
use App\Models\Ventas\Tipotransaccion;
use App\Services\Ventas\PuntoventaAnitaSyncService;
use App\Services\Ventas\VendedorAnitaSyncService;
use App\Support\Configuracion\EntornoEmpresaSupport;
use RuntimeException;

/**
 * Maestros de ventas para Calzados Ferli (bridge ANITA_IP /usr2/ferli).
 *
 * - puntoventa ← sucursal (servicio genérico; empresa por suc_nroemp / CUIT / default).
 * - tipotransaccion ← ventas.t_comp (esquema Ferli: tcomp_clave/desc/oper/estado/tipo_comp).
 * - vendedor ← sync genérico.
 *
 * No importa histórico de venta/pedidos: solo catálogos necesarios para operar.
 */
final class VentasMaestrosAnitaFerliSyncService
{
    private const PATH_DEFAULT = '/usr2/ferli';

    public function __construct(
        private readonly PuntoventaAnitaSyncService $puntoventaSync,
        private readonly VendedorAnitaSyncService $vendedorSync,
    ) {
    }

    /**
     * @return array{
     *   dry_run: bool,
     *   path: string,
     *   maestros: array<string, array<string, mixed>>,
     *   diagnostico: list<string>
     * }
     */
    public function sincronizar(bool $dryRun = true, ?string $pathSistema = null): array
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            throw new RuntimeException('VentasMaestrosAnitaFerliSyncService solo aplica a EMPRESA=Calzados Ferli.');
        }

        $path = rtrim($pathSistema ?: (string) config('anita.bdd_path', self::PATH_DEFAULT), '/') ?: self::PATH_DEFAULT;

        $diagnostico = [
            'Anita Ferli ventas: sucursal (PV), t_comp (tipos), vendedor.',
            'tipotransaccion: altas faltantes por abreviatura; no pisa existentes.',
            'condmae/condicionventa: UNLOAD inestable en Ferli — no se importa aquí (ya hay filas ERP).',
            'No importa comprobantes históricos (venta/cobranza Anita).',
        ];

        return [
            'dry_run' => $dryRun,
            'path' => $path,
            'maestros' => [
                'puntoventa' => $this->syncPuntoventa($dryRun),
                'tipotransaccion' => $this->syncTipotransaccion($dryRun),
                'vendedor' => $this->syncVendedor($dryRun),
            ],
            'diagnostico' => $diagnostico,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function syncPuntoventa(bool $dryRun): array
    {
        $lista = $this->puntoventaSync->listarDesdeAnita();
        $enAnita = count($lista);
        $aCrear = 0;
        $aActualizar = 0;
        $omitidos = 0;
        $muestra = [];
        $errores = [];

        foreach ($lista as $row) {
            $codigo = \App\Support\Stock\AnitaSync\Puntoventa\PuntoventaFieldMapper::mapCodigo($row);
            if ($codigo === null) {
                $omitidos++;
                continue;
            }
            $nombre = \App\Support\Stock\AnitaSync\Puntoventa\PuntoventaFieldMapper::mapNombre($row);
            $existente = \App\Models\Ventas\Puntoventa::withTrashed()
                ->where('codigo', $codigo)
                ->orWhere('codigo', ltrim($codigo, '0'))
                ->first();
            if ($existente) {
                $aActualizar++;
                if (count($muestra) < 8) {
                    $muestra[] = "upd {$codigo} {$nombre}";
                }
            } else {
                $aCrear++;
                if (count($muestra) < 8) {
                    $muestra[] = "new {$codigo} {$nombre}";
                }
            }
        }

        if (! $dryRun && $enAnita > 0) {
            $ret = $this->puntoventaSync->sincronizarConAnita();
            $aCrear = (int) ($ret['importados'] ?? $aCrear);
            $aActualizar = (int) ($ret['actualizados'] ?? $aActualizar);
            $omitidos = (int) ($ret['omitidos'] ?? $omitidos);
            $errores = $ret['errores'] ?? [];
        }

        return [
            'fuente' => 'sucursal',
            'en_anita' => $enAnita,
            'insertados' => $aCrear,
            'actualizados' => $aActualizar,
            'omitidos' => $omitidos,
            'muestra' => $muestra,
            'errores' => $errores,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function syncTipotransaccion(bool $dryRun): array
    {
        $filas = $this->listarTComp();
        $enAnita = count($filas);
        $insertados = 0;
        $omitidos = 0;
        $muestra = [];
        $errores = [];

        foreach ($filas as $fila) {
            $clave = strtoupper(trim((string) ($fila['tcomp_clave'] ?? '')));
            if ($clave === '') {
                $omitidos++;
                continue;
            }

            $existente = Tipotransaccion::withTrashed()->where('abreviatura', $clave)->first();
            if ($existente && ! $existente->trashed()) {
                $omitidos++;
                continue;
            }

            $insertados++;
            if (count($muestra) < 12) {
                $accion = $existente?->trashed() ? 'restore' : 'new';
                $muestra[] = $accion.' '.$clave.' — '.trim((string) ($fila['tcomp_desc'] ?? ''));
            }

            if ($dryRun) {
                continue;
            }

            try {
                if ($existente && $existente->trashed()) {
                    $existente->restore();
                    $this->actualizarTipoDesdeFila($existente, $clave, $fila);
                } else {
                    $this->crearTipoDesdeFila($clave, $fila);
                }
            } catch (\Throwable $e) {
                $insertados--;
                $errores[] = "{$clave}: ".$e->getMessage();
            }
        }

        return [
            'fuente' => 'ventas.t_comp',
            'en_anita' => $enAnita,
            'insertados' => $insertados,
            'omitidos' => $omitidos,
            'muestra' => $muestra,
            'errores' => $errores,
        ];
    }

    /**
     * @param  array<string, mixed>  $fila
     */
    private function crearTipoDesdeFila(string $clave, array $fila): void
    {
        $tipo = new Tipotransaccion();
        $this->aplicarCamposTipo($tipo, $clave, $fila);
        $tipo->save();
    }

    /**
     * @param  array<string, mixed>  $fila
     */
    private function actualizarTipoDesdeFila(Tipotransaccion $tipo, string $clave, array $fila): void
    {
        $this->aplicarCamposTipo($tipo, $clave, $fila);
        $tipo->save();
    }

    /**
     * @param  array<string, mixed>  $fila
     */
    private function aplicarCamposTipo(Tipotransaccion $tipo, string $clave, array $fila): void
    {
        $nombre = trim((string) ($fila['tcomp_desc'] ?? ''));
        if ($nombre === '') {
            $nombre = $clave;
        }
        if (mb_strlen($nombre) > 255) {
            $nombre = mb_substr($nombre, 0, 255);
        }

        $estadoAnita = strtoupper(trim((string) ($fila['tcomp_estado'] ?? 'A')));
        $estado = $estadoAnita === 'A' ? 'A' : 'S';

        $tipoComp = preg_replace('/\D+/', '', (string) ($fila['tcomp_tipo_comp'] ?? '')) ?? '';
        $codigoAfip = $tipoComp !== '' ? str_pad($tipoComp, 3, '0', STR_PAD_LEFT) : '000';

        $operAnita = strtoupper(trim((string) ($fila['tcomp_oper'] ?? 'S')));
        $esCredito = str_starts_with($clave, 'NC') || $operAnita === 'R';
        $esDebito = str_starts_with($clave, 'ND');

        $operacion = 'V';
        if ($esCredito) {
            $operacion = 'C';
        } elseif (in_array($clave, ['COB', 'COA', 'CIE', 'REM'], true)) {
            $operacion = $clave === 'REM' ? 'R' : 'V';
        }

        $operacionstock = $esCredito ? 'E' : 'S';
        if (in_array($clave, ['COB', 'COA', 'CIE', 'CLI'], true)) {
            $operacionstock = 'N';
        }

        $signo = ($esCredito || $operAnita === 'R') ? 'R' : 'S';
        if ($esDebito) {
            $signo = 'S';
            $operacion = 'V';
            $operacionstock = 'S';
        }

        $tipo->nombre = $nombre;
        $tipo->abreviatura = $clave;
        $tipo->codigo = $codigoAfip;
        $tipo->operacion = $operacion;
        $tipo->operacionstock = $operacionstock;
        $tipo->signo = $signo;
        $tipo->estado = $estado;
        $tipo->iva_ventas = in_array($codigoAfip, ['001', '006', '011', '051', '201'], true)
            || str_starts_with($clave, 'FA')
            || str_starts_with($clave, 'NC')
            || str_starts_with($clave, 'ND');
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function listarTComp(): array
    {
        $api = new ApiAnita();
        $raw = $api->apiCall([
            'acc' => 'list',
            'sistema' => 'ventas',
            'tabla' => 't_comp',
            'campos' => 'tcomp_clave,tcomp_desc,tcomp_oper,tcomp_concepto,tcomp_estado,tcomp_tipo_comp,tcomp_tipo_oper',
            'orderBy' => 'tcomp_clave',
        ]);
        $error = ApiAnita::extraerMensajeError($raw);
        if ($error !== null) {
            throw new RuntimeException('t_comp: '.$error);
        }
        $filas = json_decode((string) $raw, true);
        if (! is_array($filas)) {
            return [];
        }

        return $filas;
    }

    /**
     * @return array<string, mixed>
     */
    private function syncVendedor(bool $dryRun): array
    {
        if ($dryRun) {
            return [
                'fuente' => 'vendedor',
                'en_anita' => null,
                'insertados' => 0,
                'omitidos' => 0,
                'muestra' => ['dry-run: se ejecutará vendedor:sincronizar-anita al persistir'],
                'errores' => [],
            ];
        }

        $ret = $this->vendedorSync->sincronizarConAnita();

        return [
            'fuente' => 'vendedor',
            'en_anita' => (int) ($ret['en_anita'] ?? 0),
            'insertados' => (int) ($ret['importados'] ?? $ret['insertados'] ?? 0),
            'omitidos' => (int) ($ret['omitidos'] ?? 0),
            'muestra' => [],
            'errores' => $ret['errores'] ?? [],
        ];
    }
}
