<?php

declare(strict_types=1);

namespace App\Services\Ventas\Crown;

use App\ApiAnita;
use App\Models\Configuracion\Condicioniva;
use App\Models\Configuracion\Localidad;
use App\Models\Configuracion\Provincia;
use App\Models\Stock\Listaprecio;
use App\Models\Ventas\Condicionventa;
use App\Models\Ventas\Condicionventacuota;
use App\Models\Ventas\TipoempresaCliente;
use App\Models\Ventas\Tipotransaccion;
use App\Models\Ventas\Transporte;
use App\Models\Ventas\Zonavta;
use App\Services\Ventas\ClienteAnitaSyncService;
use App\Services\Ventas\PuntoventaAnitaSyncService;
use App\Support\Configuracion\EntornoEmpresaSupport;
use RuntimeException;

/**
 * Maestros de ventas para Crown (bridge ac-server1 /usr2/casino).
 *
 * Tanda 1: condmae, tipoemp, zonavta, expreso, t_comp.
 * Tanda 2: sucursal→PV (CAI vacío en Anita).
 * Tanda 3: premae cabeceras (sin stkpre / artículos).
 * Tanda 4: climae clientes.
 */
final class VentasMaestrosAnitaCrownSyncService
{
    private const PATH_DEFAULT = '/usr2/casino';

    /** @var list<array{id: string, valor: string}> */
    private const TIPOS_PLAZO = [
        ['id' => '1', 'valor' => 'D'],
        ['id' => '2', 'valor' => 'F'],
        ['id' => '3', 'valor' => 'O'],
        ['id' => '4', 'valor' => 'R'],
    ];

    public function __construct(
        private readonly PuntoventaAnitaSyncService $puntoventaSync,
        private readonly ClienteAnitaSyncService $clienteSync,
    ) {}

    /**
     * @param  list<int>  $tandas
     * @return array{
     *   dry_run: bool,
     *   path: string,
     *   maestros: array<string, array<string, mixed>>,
     *   diagnostico: list<string>
     * }
     */
    public function sincronizar(bool $dryRun = true, ?string $pathSistema = null, array $tandas = [1, 2, 3, 4]): array
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            throw new RuntimeException('VentasMaestrosAnitaCrownSyncService solo aplica a EMPRESA=CROWN.');
        }

        $path = rtrim($pathSistema ?: (string) config('anita.bdd_path', self::PATH_DEFAULT), '/')
            ?: self::PATH_DEFAULT;
        $tandas = array_values(array_unique(array_map('intval', $tandas)));
        $maestros = [];
        $diagnostico = [];

        if (in_array(1, $tandas, true)) {
            $maestros += [
                'condicionventa' => $this->syncCondicionventa($dryRun),
                'tipoempresa_cliente' => $this->syncTipoempresa($dryRun),
                'zonavta' => $this->syncZonavta($dryRun),
                'transporte' => $this->syncTransporte($dryRun),
                'tipotransaccion' => $this->syncTipotransaccion($dryRun),
                'vendedor' => $this->syncVendedorDiagnostico(),
                'cobrador' => [
                    'fuente' => 'cobrador',
                    'en_anita' => 0,
                    'insertados' => 0,
                    'omitidos' => 0,
                    'muestra' => ['Anita cobrador vacía — nada que importar'],
                    'errores' => [],
                ],
            ];
            $diagnostico[] = 'Tanda 1: condmae, tipoemp, zonavta, expreso, t_comp.';
        }

        if (in_array(2, $tandas, true)) {
            $maestros['puntoventa'] = $this->syncPuntoventa($dryRun);
            $maestros['cai'] = $this->syncCaiDiagnostico();
            $diagnostico[] = 'Tanda 2: sucursal→puntoventa. CAI Anita vacía.';
        }

        if (in_array(3, $tandas, true)) {
            $maestros['listaprecio'] = $this->syncListaprecio($dryRun);
            $diagnostico[] = 'Tanda 3: premae cabeceras (sin precios stkpre ni artículos).';
        }

        if (in_array(4, $tandas, true)) {
            $maestros['cliente'] = $this->syncCliente($dryRun);
            $diagnostico[] = 'Tanda 4: climae clientes (sin comprobantes).';
        }

        return [
            'dry_run' => $dryRun,
            'path' => $path,
            'maestros' => $maestros,
            'diagnostico' => $diagnostico,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function syncCondicionventa(bool $dryRun): array
    {
        $filas = $this->listar('ventas', 'condmae', 'conm_codigo,conm_desc');
        $cuotas = $this->listar(
            'ventas',
            'condmov',
            'conv_codigo,conv_nro_cuota,conv_tipo_plazo,conv_dia,conv_fecha_vto,conv_porc_monto,conv_porc_interes'
        );
        $cuotasPorCodigo = [];
        foreach ($cuotas as $cuota) {
            $cod = trim((string) ($cuota['conv_codigo'] ?? ''));
            if ($cod === '') {
                continue;
            }
            $cuotasPorCodigo[$cod][] = $cuota;
        }

        $insertados = 0;
        $actualizados = 0;
        $omitidos = 0;
        $muestra = [];
        $errores = [];

        foreach ($filas as $fila) {
            $codigo = trim((string) ($fila['conm_codigo'] ?? ''));
            $nombre = trim((string) ($fila['conm_desc'] ?? ''));
            if ($codigo === '') {
                $omitidos++;
                continue;
            }
            if ($nombre === '') {
                $nombre = $codigo;
            }

            $existente = Condicionventa::query()->where('codigo', $codigo)->first();
            if ($existente) {
                $actualizados++;
                if (count($muestra) < 8) {
                    $muestra[] = "upd {$codigo} {$nombre}";
                }
                if (! $dryRun && $existente->nombre !== $nombre) {
                    $existente->update(['nombre' => $nombre]);
                }
                continue;
            }

            $insertados++;
            if (count($muestra) < 8) {
                $muestra[] = "new {$codigo} {$nombre}";
            }
            if ($dryRun) {
                continue;
            }

            try {
                $condicion = Condicionventa::create([
                    'codigo' => $codigo,
                    'nombre' => $nombre,
                ]);
                foreach ($cuotasPorCodigo[$codigo] ?? [] as $cuota) {
                    $tipo = collect(self::TIPOS_PLAZO)
                        ->firstWhere('id', (string) ($cuota['conv_tipo_plazo'] ?? '1'));
                    $fechaVto = (int) ($cuota['conv_fecha_vto'] ?? 0);
                    Condicionventacuota::create([
                        'condicionventa_id' => $condicion->id,
                        'cuota' => ((int) ($cuota['conv_nro_cuota'] ?? 0)) + 1,
                        'tipoplazo' => $tipo['valor'] ?? 'D',
                        'plazo' => (int) ($cuota['conv_dia'] ?? 0),
                        'fechavencimiento' => $fechaVto > 0 ? $fechaVto : null,
                        'porcentaje' => ((float) ($cuota['conv_porc_monto'] ?? 0)) > 0
                            ? (float) $cuota['conv_porc_monto']
                            : null,
                        'interes' => ((float) ($cuota['conv_porc_interes'] ?? 0)) > 0
                            ? (float) $cuota['conv_porc_interes']
                            : null,
                    ]);
                }
            } catch (\Throwable $e) {
                $insertados--;
                $errores[] = "{$codigo}: ".$e->getMessage();
            }
        }

        return [
            'fuente' => 'condmae+condmov',
            'en_anita' => count($filas),
            'insertados' => $insertados,
            'actualizados' => $actualizados,
            'omitidos' => $omitidos,
            'muestra' => $muestra,
            'errores' => $errores,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function syncTipoempresa(bool $dryRun): array
    {
        $filas = $this->listar('ventas', 'tipoemp', 'tipoe_codigo,tipoe_desc');
        $insertados = 0;
        $actualizados = 0;
        $omitidos = 0;
        $muestra = [];

        foreach ($filas as $fila) {
            $codigo = trim((string) ($fila['tipoe_codigo'] ?? ''));
            $nombre = trim((string) ($fila['tipoe_desc'] ?? ''));
            if ($codigo === '') {
                $omitidos++;
                continue;
            }
            if ($nombre === '') {
                $nombre = $codigo;
            }

            $existente = TipoempresaCliente::query()
                ->where('codigo', $codigo)
                ->orWhere('codigo', ltrim($codigo, '0'))
                ->first();

            if ($existente) {
                $actualizados++;
                if (count($muestra) < 8) {
                    $muestra[] = "upd {$codigo} {$nombre}";
                }
                if (! $dryRun && $existente->nombre !== $nombre) {
                    $existente->update(['nombre' => $nombre]);
                }
                continue;
            }

            $insertados++;
            if (count($muestra) < 8) {
                $muestra[] = "new {$codigo} {$nombre}";
            }
            if (! $dryRun) {
                TipoempresaCliente::create([
                    'codigo' => $codigo,
                    'nombre' => $nombre,
                ]);
            }
        }

        return [
            'fuente' => 'tipoemp',
            'en_anita' => count($filas),
            'insertados' => $insertados,
            'actualizados' => $actualizados,
            'omitidos' => $omitidos,
            'muestra' => $muestra,
            'errores' => [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function syncZonavta(bool $dryRun): array
    {
        $filas = $this->listar('ventas', 'zonavta', 'zonv_codigo,zonv_desc');
        $insertados = 0;
        $actualizados = 0;
        $omitidos = 0;
        $muestra = [];

        foreach ($filas as $fila) {
            $codigo = trim((string) ($fila['zonv_codigo'] ?? ''));
            $nombre = trim((string) ($fila['zonv_desc'] ?? ''));
            if ($codigo === '') {
                $omitidos++;
                continue;
            }
            if ($nombre === '') {
                $nombre = $codigo;
            }

            $existente = Zonavta::query()->where('codigo', $codigo)->first();
            if ($existente) {
                $actualizados++;
                if (count($muestra) < 8) {
                    $muestra[] = "upd {$codigo} {$nombre}";
                }
                if (! $dryRun) {
                    $existente->update(['nombre' => $nombre, 'codigo' => $codigo]);
                }
                continue;
            }

            $insertados++;
            if (count($muestra) < 8) {
                $muestra[] = "new {$codigo} {$nombre}";
            }
            if (! $dryRun) {
                $payload = ['nombre' => $nombre, 'codigo' => $codigo];
                if (ctype_digit($codigo) && ! Zonavta::query()->whereKey((int) $codigo)->exists()) {
                    $zona = new Zonavta($payload);
                    $zona->id = (int) $codigo;
                    $zona->save();
                } else {
                    Zonavta::create($payload);
                }
            }
        }

        return [
            'fuente' => 'zonavta',
            'en_anita' => count($filas),
            'insertados' => $insertados,
            'actualizados' => $actualizados,
            'omitidos' => $omitidos,
            'muestra' => $muestra,
            'errores' => [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function syncTransporte(bool $dryRun): array
    {
        $filas = $this->listar(
            'ventas',
            'expreso',
            'expr_codigo,expr_nombre,expr_direccion,expr_localidad,expr_provincia,expr_cod_postal,expr_telefono,expr_cuit,expr_cond_iva,expr_nro_interno'
        );
        $insertados = 0;
        $actualizados = 0;
        $omitidos = 0;
        $muestra = [];
        $errores = [];

        foreach ($filas as $fila) {
            $codigo = trim((string) ($fila['expr_codigo'] ?? ''));
            $nombre = trim((string) ($fila['expr_nombre'] ?? ''));
            if ($codigo === '' || $nombre === '') {
                $omitidos++;
                continue;
            }

            $payload = $this->payloadTransporte($fila, $codigo, $nombre);
            $existente = Transporte::query()->where('codigo', $codigo)->first();

            if ($existente) {
                $actualizados++;
                if (count($muestra) < 8) {
                    $muestra[] = "upd {$codigo} {$nombre}";
                }
                if (! $dryRun) {
                    $existente->update($payload);
                }
                continue;
            }

            $insertados++;
            if (count($muestra) < 8) {
                $muestra[] = "new {$codigo} {$nombre}";
            }
            if ($dryRun) {
                continue;
            }

            try {
                Transporte::create($payload);
            } catch (\Throwable $e) {
                $insertados--;
                $errores[] = "{$codigo}: ".$e->getMessage();
            }
        }

        return [
            'fuente' => 'expreso',
            'en_anita' => count($filas),
            'insertados' => $insertados,
            'actualizados' => $actualizados,
            'omitidos' => $omitidos,
            'muestra' => $muestra,
            'errores' => $errores,
        ];
    }

    /**
     * @param  array<string, mixed>  $fila
     * @return array<string, mixed>
     */
    private function payloadTransporte(array $fila, string $codigo, string $nombre): array
    {
        $provinciaId = (int) ($fila['expr_provincia'] ?? 0);
        $localidadId = (int) ($fila['expr_localidad'] ?? 0);
        if ($provinciaId > 0 && ! Provincia::query()->whereKey($provinciaId)->exists()) {
            $provinciaId = 0;
        }
        if ($localidadId > 0 && ! Localidad::query()->whereKey($localidadId)->exists()) {
            $localidadId = 0;
        }

        $condAnita = trim((string) ($fila['expr_cond_iva'] ?? '0'));
        $condicionivaId = match ($condAnita) {
            '3' => 3,
            '4' => 2,
            '5' => 4,
            default => 1,
        };
        if (! Condicioniva::query()->whereKey($condicionivaId)->exists()) {
            $condicionivaId = (int) (Condicioniva::query()->orderBy('id')->value('id') ?? 1);
        }

        $limpio = static function (mixed $v, string $vacio = ''): string {
            $s = trim((string) ($v ?? ''));
            if ($s === '' || $s === '0') {
                return $vacio;
            }

            return $s;
        };

        return [
            'codigo' => $codigo,
            'nombre' => $nombre,
            'domicilio' => $limpio($fila['expr_direccion'] ?? null, ' '),
            'provincia_id' => $provinciaId > 0 ? $provinciaId : null,
            'localidad_id' => $localidadId > 0 ? $localidadId : null,
            'codigopostal' => $limpio($fila['expr_cod_postal'] ?? null),
            'telefono' => $limpio($fila['expr_telefono'] ?? null),
            'email' => '',
            'nroinscripcion' => $limpio($fila['expr_cuit'] ?? null),
            'condicioniva_id' => $condicionivaId,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function syncTipotransaccion(bool $dryRun): array
    {
        $filas = $this->listar(
            'ventas',
            't_comp',
            'tcomp_clave,tcomp_desc,tcomp_oper,tcomp_oper_stk,tcomp_genera_asi,tcomp_estado,tcomp_tipo_comp'
        );
        $insertados = 0;
        $actualizados = 0;
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
                $actualizados++;
                if (count($muestra) < 12) {
                    $muestra[] = 'upd '.$clave.' — '.trim((string) ($fila['tcomp_desc'] ?? ''));
                }
                if (! $dryRun) {
                    try {
                        $this->aplicarCamposTipo($existente, $clave, $fila);
                        $existente->save();
                    } catch (\Throwable $e) {
                        $actualizados--;
                        $errores[] = "{$clave}: ".$e->getMessage();
                    }
                }
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
                    $this->aplicarCamposTipo($existente, $clave, $fila);
                    $existente->save();
                } else {
                    $tipo = new Tipotransaccion();
                    $this->aplicarCamposTipo($tipo, $clave, $fila);
                    $tipo->save();
                }
            } catch (\Throwable $e) {
                $insertados--;
                $errores[] = "{$clave}: ".$e->getMessage();
            }
        }

        return [
            'fuente' => 't_comp',
            'en_anita' => count($filas),
            'insertados' => $insertados,
            'actualizados' => $actualizados,
            'omitidos' => $omitidos,
            'muestra' => $muestra,
            'errores' => $errores,
        ];
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
        $esCobranza = in_array($clave, ['COB', 'COA', 'COV', 'CIE', 'ING'], true);
        $esCredito = ! $esCobranza && (
            str_starts_with($clave, 'NC')
            || in_array($clave, ['CME', 'CSL', 'CRE', 'CIB', 'NT'], true)
            || ($operAnita === 'R' && ! in_array($clave, ['REI', 'APA'], true))
        );
        $esDebito = str_starts_with($clave, 'ND');

        $operacion = 'V';
        if ($esCredito) {
            $operacion = 'C';
        }

        $operacionstock = 'O';
        if ($esCredito) {
            $operacionstock = 'E';
        } elseif (in_array($clave, ['FAC', 'FCE', 'FAH', 'FA', 'FAB', 'FOL', 'FME', 'FSL', 'FRE', 'TF', 'TIC', 'T', 'REM'], true)
            || str_starts_with($clave, 'FA')
            || $esDebito) {
            $operacionstock = 'S';
        }
        if (in_array($clave, ['TRE', 'AJE', 'AAR'], true)) {
            $operacionstock = 'E';
        }
        if (in_array($clave, ['TRS', 'AJS', 'ROT', 'DON', 'VTO', 'EVE'], true)
            || preg_match('/^S[A-Z]/', $clave) === 1) {
            $operacionstock = 'S';
        }

        $signo = $esCredito ? 'R' : 'S';
        if ($esDebito) {
            $signo = 'S';
            $operacion = 'V';
        }
        if ($esCobranza) {
            $signo = 'R';
            $operacion = 'V';
            $operacionstock = 'N';
        }

        $generaAsiento = strtoupper(trim((string) ($fila['tcomp_genera_asi'] ?? 'N'))) === 'S';

        $tipo->nombre = $nombre;
        $tipo->abreviatura = $clave;
        $tipo->codigo = $codigoAfip;
        $tipo->operacion = $operacion;
        $tipo->operacionstock = $operacionstock;
        $tipo->signo = $signo;
        $tipo->estado = $estado;
        $tipo->genera_asiento = $generaAsiento;
        $tipo->iva_ventas = in_array($codigoAfip, ['001', '002', '003', '006', '011', '051', '201'], true)
            || str_starts_with($clave, 'FA')
            || str_starts_with($clave, 'FM')
            || str_starts_with($clave, 'FS')
            || str_starts_with($clave, 'FR')
            || str_starts_with($clave, 'TF')
            || str_starts_with($clave, 'TI')
            || str_starts_with($clave, 'NC')
            || str_starts_with($clave, 'ND')
            || in_array($clave, ['CME', 'CSL', 'CRE', 'NCH', 'NCD'], true);
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
    private function syncCaiDiagnostico(): array
    {
        $filas = $this->listar(
            'ventas',
            'cai',
            'cai_orden,cai_tipo,cai_desc,cai_letra,cai_sucursal,cai_nro_cai,cai_fecha_vto'
        );

        return [
            'fuente' => 'cai',
            'en_anita' => count($filas),
            'insertados' => 0,
            'omitidos' => count($filas),
            'muestra' => count($filas) === 0
                ? ['Anita cai vacía — sin CAI/CAE manual que importar']
                : ['hay filas: revisar importación'],
            'errores' => [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function syncListaprecio(bool $dryRun): array
    {
        $filas = $this->listar(
            'ventas',
            'premae',
            'prem_lista,prem_desc,prem_incl_impuesto,prem_formula'
        );
        $insertados = 0;
        $actualizados = 0;
        $omitidos = 0;
        $muestra = [];
        $errores = [];

        foreach ($filas as $fila) {
            $codigo = trim((string) ($fila['prem_lista'] ?? ''));
            $nombre = trim((string) ($fila['prem_desc'] ?? ''));
            if ($codigo === '' || ! ctype_digit($codigo)) {
                $omitidos++;
                continue;
            }
            if ($nombre === '') {
                $nombre = 'Lista '.$codigo;
            }

            $existente = Listaprecio::query()
                ->where('codigo', $codigo)
                ->orWhere('codigo', (int) $codigo)
                ->first();

            if ($existente) {
                $actualizados++;
                if (count($muestra) < 8) {
                    $muestra[] = "upd {$codigo} {$nombre}";
                }
                if (! $dryRun && $existente->nombre !== $nombre) {
                    $existente->update(['nombre' => $nombre]);
                }
                continue;
            }

            $insertados++;
            if (count($muestra) < 8) {
                $muestra[] = "new {$codigo} {$nombre}";
            }
            if ($dryRun) {
                continue;
            }

            try {
                $incluye = strtoupper(trim((string) ($fila['prem_incl_impuesto'] ?? 'N'))) === 'S' ? '1' : '2';
                Listaprecio::create([
                    'nombre' => $nombre,
                    'formula' => trim((string) ($fila['prem_formula'] ?? '0')) ?: '0',
                    'incluyeimpuesto' => $incluye,
                    'codigo' => (int) $codigo,
                    'tiponumeracion_id' => null,
                    'usuarioultcambio_id' => (int) (\Illuminate\Support\Facades\Auth::id() ?: 0) ?: null,
                ]);
            } catch (\Throwable $e) {
                $insertados--;
                $errores[] = "{$codigo}: ".$e->getMessage();
            }
        }

        return [
            'fuente' => 'premae',
            'en_anita' => count($filas),
            'insertados' => $insertados,
            'actualizados' => $actualizados,
            'omitidos' => $omitidos,
            'muestra' => $muestra,
            'errores' => $errores,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function syncCliente(bool $dryRun): array
    {
        $lista = $this->clienteSync->listarDesdeAnita();
        $enAnita = count($lista);
        $muestra = [];
        foreach (array_slice($lista, 0, 8) as $row) {
            $cod = trim((string) ($row->clim_cliente ?? $row->codigo ?? ''));
            $muestra[] = $cod !== '' ? "cliente {$cod}" : 'sin código';
        }

        if ($dryRun) {
            return [
                'fuente' => 'climae',
                'en_anita' => $enAnita,
                'insertados' => $enAnita,
                'actualizados' => 0,
                'omitidos' => 0,
                'muestra' => $muestra,
                'errores' => [],
            ];
        }

        $ret = $this->clienteSync->sincronizarConAnita();

        return [
            'fuente' => 'climae',
            'en_anita' => (int) ($ret['en_anita'] ?? $enAnita),
            'insertados' => (int) ($ret['importados'] ?? 0),
            'actualizados' => (int) ($ret['actualizados'] ?? 0),
            'omitidos' => (int) ($ret['omitidos'] ?? 0),
            'muestra' => $muestra,
            'errores' => array_slice($ret['errores'] ?? [], 0, 15),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function syncVendedorDiagnostico(): array
    {
        $filas = $this->listar(
            'ventas',
            'vendedor',
            'vend_codigo,vend_nombre,vend_empresa'
        );
        $muestra = [];
        foreach ($filas as $fila) {
            $codigo = trim((string) ($fila['vend_codigo'] ?? ''));
            $nombre = trim((string) ($fila['vend_nombre'] ?? ''));
            $muestra[] = $nombre === ''
                ? "omitido {$codigo} (nombre vacío)"
                : "candidato {$codigo} {$nombre}";
        }

        return [
            'fuente' => 'vendedor',
            'en_anita' => count($filas),
            'insertados' => 0,
            'omitidos' => count($filas),
            'muestra' => $muestra !== [] ? $muestra : ['sin filas'],
            'errores' => [],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function listar(string $sistema, string $tabla, string $campos): array
    {
        $api = new ApiAnita();
        $raw = $api->apiCall([
            'acc' => 'list',
            'sistema' => $sistema,
            'tabla' => $tabla,
            'campos' => $campos,
        ]);
        $error = ApiAnita::extraerMensajeError($raw);
        if ($error !== null) {
            throw new RuntimeException("{$tabla}: {$error}");
        }
        $filas = json_decode((string) $raw, true);
        if (! is_array($filas)) {
            return [];
        }

        return array_map(static function ($fila) {
            if (! is_array($fila)) {
                return [];
            }
            $out = [];
            foreach ($fila as $k => $v) {
                $out[$k] = is_string($v) ? trim($v) : $v;
            }

            return $out;
        }, $filas);
    }
}
