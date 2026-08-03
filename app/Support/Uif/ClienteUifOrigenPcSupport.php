<?php

namespace App\Support\Uif;

use App\Models\Configuracion\Empresa;
use App\Models\Uif\Cliente_Uif;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Services\Caja\Estacionamiento\EstacionamientoPvService;
use App\Support\Caja\Estacionamiento\EstacionamientoIdentificadorPc;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use RuntimeException;

/**
 * Contexto UIF multi-sala:
 * - PC con PV estacionamiento → origen fijo (caja).
 * - Sin PC → lectura por empresas asignadas al usuario; escritura pide empresa.
 */
final class ClienteUifOrigenPcSupport
{
    public const SESSION_EMPRESA_ID = 'uif_empresa_id';

    /**
     * @return array{
     *   modo: string,
     *   identificador_pc: string,
     *   pc_configurada: bool,
     *   origen_fijo: bool,
     *   empresas_uif: Collection,
     *   origenes_permitidos: list<string>,
     *   empresa_id: int|null,
     *   empresa_nombre: string,
     *   origen: string|null,
     *   sala_id: int|null,
     *   label: string
     * }
     */
    public static function contexto(?Request $request = null): array
    {
        $request ??= app()->bound('request') ? request() : null;
        $pc = EstacionamientoIdentificadorPc::resolver($request instanceof Request ? $request : null);
        $verTodas = self::debeVerTodasLasEmpresasUif();
        $empresasUif = $verTodas ? self::todasEmpresasUif() : self::empresasUifAsignadas();
        $origenesPermitidos = self::origenesDesdeEmpresas($empresasUif);
        $pcCtx = self::intentarResolverPc($request);

        // Solo cajeros de caja quedan fijados a la PC; encargadas/operadores ven las 3 salas.
        if ($pcCtx !== null && ! $verTodas) {
            return [
                'modo' => 'pc',
                'identificador_pc' => $pcCtx['identificador_pc'],
                'pc_configurada' => true,
                'origen_fijo' => true,
                'empresas_uif' => $empresasUif->isEmpty()
                    ? collect([(object) ['id' => $pcCtx['empresa_id'], 'nombre' => $pcCtx['empresa_nombre']]])
                    : $empresasUif,
                'origenes_permitidos' => $origenesPermitidos !== []
                    ? array_values(array_unique(array_merge($origenesPermitidos, [$pcCtx['origen']])))
                    : [$pcCtx['origen']],
                'empresa_id' => $pcCtx['empresa_id'],
                'empresa_nombre' => $pcCtx['empresa_nombre'],
                'origen' => $pcCtx['origen'],
                'sala_id' => $pcCtx['sala_id'],
                'label' => $pcCtx['label'],
            ];
        }

        $empresaId = self::empresaIdDesdeRequestOSesion($request, $empresasUif)
            ?? ($pcCtx['empresa_id'] ?? null);
        if ($empresaId && ! self::empresaPermitida((int) $empresaId, $empresasUif)) {
            $empresaId = null;
        }
        $origen = $empresaId ? self::origenDesdeEmpresaId((int) $empresaId) : null;

        return [
            'modo' => 'empresa',
            'identificador_pc' => $pc,
            'pc_configurada' => $pcCtx !== null,
            'origen_fijo' => false,
            'empresas_uif' => $empresasUif,
            'origenes_permitidos' => $origenesPermitidos,
            'empresa_id' => $empresaId ? (int) $empresaId : null,
            'empresa_nombre' => $empresaId
                ? (string) ($empresasUif->firstWhere('id', (int) $empresaId)->nombre
                    ?? ($pcCtx['empresa_nombre'] ?? ''))
                : '',
            'origen' => $origen,
            'sala_id' => $origen ? ClienteUifArchivoStorage::salaId($origen) : null,
            'label' => $origen ? self::labelOrigen($origen) : (
                $verTodas ? 'Todas las salas (BSA/KSA/RSA)' : 'Sin empresa seleccionada'
            ),
        ];
    }

    /**
     * Encargadas UIF, operadores (reportes/consulta) y admin: las 3 empresas.
     * Cajeros de caja: solo empresa de la PC / asignadas.
     */
    public static function debeVerTodasLasEmpresasUif(): bool
    {
        if ((string) session('rol_nombre') === 'administrador') {
            return true;
        }
        if (! function_exists('perfilClienteUif')) {
            return false;
        }
        $perfil = perfilClienteUif();

        return in_array($perfil, ['supervisor', 'operador'], true);
    }

    /**
     * Acceso al módulo: PC configurada o al menos una empresa UIF asignada.
     */
    public static function assertPuedeAcceder(?Request $request = null): void
    {
        $ctx = self::contexto($request);
        if ($ctx['pc_configurada']) {
            return;
        }
        if ($ctx['origenes_permitidos'] !== []) {
            return;
        }

        throw new RuntimeException(
            'No puede operar UIF: esta PC ('.$ctx['identificador_pc'].') no tiene configuración de '
            .'punto de venta de estacionamiento y su usuario no tiene empresas UIF asignadas '
            .'(BSA/KSA/RSA). Configure la PC o asigne empresas al usuario.'
        );
    }

    /**
     * Escritura (alta/premio/archivos): origen fijo de PC o empresa elegida.
     *
     * @return array{identificador_pc:string,empresa_id:int,empresa_nombre:string,origen:string,sala_id:int,label:string,modo:string}
     */
    public static function resolverParaEscritura(?Request $request = null, ?int $empresaId = null): array
    {
        $request ??= app()->bound('request') ? request() : null;
        $ctx = self::contexto($request);

        if ($ctx['pc_configurada'] && $ctx['origen'] !== null && $ctx['empresa_id']) {
            return [
                'identificador_pc' => $ctx['identificador_pc'],
                'empresa_id' => (int) $ctx['empresa_id'],
                'empresa_nombre' => $ctx['empresa_nombre'],
                'origen' => (string) $ctx['origen'],
                'sala_id' => (int) $ctx['sala_id'],
                'label' => $ctx['label'],
                'modo' => 'pc',
            ];
        }

        $empresaId = $empresaId
            ?? ($request instanceof Request ? (int) $request->input('empresa_id', 0) : 0)
            ?: (int) ($ctx['empresa_id'] ?? 0);

        if ($empresaId <= 0) {
            throw new RuntimeException(
                'Debe indicar la empresa (BSA/KSA/RSA) para identificar el origen del cliente o premio. '
                .'Esta PC no tiene configuración de caja; el origen se toma de la empresa elegida.'
            );
        }

        if (! self::empresaPermitida($empresaId, $ctx['empresas_uif'])) {
            throw new RuntimeException('La empresa seleccionada no está asignada a su usuario.');
        }

        $origen = self::origenDesdeEmpresaId($empresaId);
        if ($origen === null) {
            throw new RuntimeException(
                'La empresa_id '.$empresaId.' no está mapeada a un origen UIF. Revise config uif.anita_origenes.'
            );
        }

        self::persistirEmpresaSesion($empresaId);

        $nombre = (string) ($ctx['empresas_uif']->firstWhere('id', $empresaId)->nombre ?? '');

        return [
            'identificador_pc' => $ctx['identificador_pc'],
            'empresa_id' => $empresaId,
            'empresa_nombre' => $nombre,
            'origen' => $origen,
            'sala_id' => ClienteUifArchivoStorage::salaId($origen),
            'label' => self::labelOrigen($origen),
            'modo' => 'empresa',
        ];
    }

    /** @deprecated Usar resolverParaEscritura / contexto */
    public static function resolverObligatorio(?Request $request = null): array
    {
        return self::resolverParaEscritura($request);
    }

    public static function intentarResolver(?Request $request = null): ?array
    {
        try {
            $ctx = self::contexto($request);
            if ($ctx['origen'] === null || $ctx['empresa_id'] === null) {
                return null;
            }

            return [
                'identificador_pc' => $ctx['identificador_pc'],
                'empresa_id' => (int) $ctx['empresa_id'],
                'empresa_nombre' => $ctx['empresa_nombre'],
                'origen' => (string) $ctx['origen'],
                'sala_id' => (int) $ctx['sala_id'],
                'label' => $ctx['label'],
            ];
        } catch (\Throwable) {
            return null;
        }
    }

    public static function intentarResolverPc(?Request $request = null): ?array
    {
        $request ??= app()->bound('request') ? request() : null;
        $pc = EstacionamientoIdentificadorPc::resolver($request instanceof Request ? $request : null);

        try {
            $cfg = app(EstacionamientoPvService::class)->resolverConfiguracionPv(
                $request instanceof Request ? $request : null
            );
        } catch (InvalidArgumentException) {
            return null;
        }

        if ($cfg === null) {
            return null;
        }

        $empresaId = (int) $cfg->empresa_id;
        $origen = self::origenDesdeEmpresaId($empresaId);
        if ($origen === null) {
            return null;
        }

        return [
            'identificador_pc' => $pc,
            'empresa_id' => $empresaId,
            'empresa_nombre' => (string) (optional($cfg->empresa)->nombre ?? ''),
            'origen' => $origen,
            'sala_id' => ClienteUifArchivoStorage::salaId($origen),
            'label' => self::labelOrigen($origen),
        ];
    }

    public static function origenDesdeEmpresaId(int $empresaId): ?string
    {
        if ($empresaId <= 0) {
            return null;
        }
        foreach (config('uif.anita_origenes', []) as $origen => $cfg) {
            if ((int) ($cfg['empresa_id'] ?? 0) === $empresaId) {
                return strtolower((string) $origen);
            }
        }

        return null;
    }

    public static function empresaIdDesdeOrigen(string $origen): ?int
    {
        $origen = strtolower(trim($origen));
        $id = (int) (config("uif.anita_origenes.{$origen}.empresa_id") ?? 0);

        return $id > 0 ? $id : null;
    }

    public static function labelOrigen(string $origen): string
    {
        $origen = strtolower(trim($origen));
        if ($origen === '') {
            return '—';
        }

        return match ($origen) {
            'biyemas' => 'BSA (Biyemas)',
            'kandiko' => 'KSA (Kandiko)',
            'rebisco' => 'RSA (Rebisco)',
            default => strtoupper($origen),
        };
    }

    /** @return array<string, string> */
    public static function opcionesOrigen(?array $soloOrigenes = null): array
    {
        $out = [];
        foreach (array_keys(config('uif.anita_origenes', [])) as $origen) {
            $origen = (string) $origen;
            if ($soloOrigenes !== null && ! in_array($origen, $soloOrigenes, true)) {
                continue;
            }
            $out[$origen] = self::labelOrigen($origen);
        }

        return $out;
    }

    public static function origenDeCliente(?Cliente_Uif $cliente): ?string
    {
        if ($cliente === null) {
            return null;
        }
        $origen = strtolower(trim((string) ($cliente->anita_origen ?? '')));

        return $origen !== '' ? $origen : null;
    }

    public static function origenDeClienteId(int $clienteUifId): ?string
    {
        if ($clienteUifId <= 0) {
            return null;
        }
        $origen = Cliente_Uif::query()->whereKey($clienteUifId)->value('anita_origen');

        return self::origenDeCliente(
            $origen ? new Cliente_Uif(['anita_origen' => $origen]) : null
        );
    }

    public static function assertClienteOperable(Cliente_Uif $cliente, ?Request $request = null): void
    {
        $ctx = self::contexto($request);
        $origenCliente = self::origenDeCliente($cliente);
        if ($origenCliente === null) {
            return;
        }

        if ($ctx['origen_fijo']) {
            if ($origenCliente !== $ctx['origen']) {
                throw new RuntimeException(
                    'Este cliente pertenece a '.$origenCliente.' ('.self::labelOrigen($origenCliente).'). '
                    .'Esta PC opera '.$ctx['origen'].' ('.$ctx['label'].').'
                );
            }

            return;
        }

        if ($ctx['origenes_permitidos'] !== [] && ! in_array($origenCliente, $ctx['origenes_permitidos'], true)) {
            throw new RuntimeException(
                'Este cliente es de '.self::labelOrigen($origenCliente)
                .' y su usuario no tiene esa empresa asignada.'
            );
        }
    }

    /** @deprecated */
    public static function assertClienteOperableEnPc(Cliente_Uif $cliente, ?Request $request = null): void
    {
        self::assertClienteOperable($cliente, $request);
    }

    public static function persistirEmpresaSesion(int $empresaId): void
    {
        if ($empresaId > 0 && app()->bound('session')) {
            session([self::SESSION_EMPRESA_ID => $empresaId]);
        }
    }

    /** @return Collection<int, Empresa> */
    public static function empresasUifAsignadas(): Collection
    {
        if (self::debeVerTodasLasEmpresasUif()) {
            return self::todasEmpresasUif();
        }

        /** @var EmpresaRepositoryInterface $repo */
        $repo = app(EmpresaRepositoryInterface::class);
        $asignadas = $repo->allFiltrado();
        $idsUif = self::empresaIdsUifConfig();

        return $asignadas
            ->filter(fn ($e) => isset($idsUif[(int) $e->id]))
            ->values();
    }

    /** @return Collection<int, Empresa> */
    public static function todasEmpresasUif(): Collection
    {
        $ids = array_keys(self::empresaIdsUifConfig());
        if ($ids === []) {
            return collect();
        }

        return Empresa::query()
            ->whereIn('id', $ids)
            ->orderBy('id')
            ->get();
    }

    /** @return array<int, true> */
    private static function empresaIdsUifConfig(): array
    {
        $idsUif = [];
        foreach (config('uif.anita_origenes', []) as $cfg) {
            $id = (int) ($cfg['empresa_id'] ?? 0);
            if ($id > 0) {
                $idsUif[$id] = true;
            }
        }

        return $idsUif;
    }

    /**
     * @param  Collection<int, mixed>  $empresas
     * @return list<string>
     */
    public static function origenesDesdeEmpresas(Collection $empresas): array
    {
        $out = [];
        foreach ($empresas as $e) {
            $origen = self::origenDesdeEmpresaId((int) $e->id);
            if ($origen !== null) {
                $out[] = $origen;
            }
        }

        return array_values(array_unique($out));
    }

    /**
     * @param  Collection<int, mixed>  $empresasUif
     */
    private static function empresaPermitida(int $empresaId, Collection $empresasUif): bool
    {
        if ($empresasUif->isEmpty()) {
            // Sin restricción de sesión (todas las empresas): permitir si mapea a origen UIF
            return self::origenDesdeEmpresaId($empresaId) !== null;
        }

        return $empresasUif->contains(fn ($e) => (int) $e->id === $empresaId);
    }

    /**
     * @param  Collection<int, mixed>  $empresasUif
     */
    private static function empresaIdDesdeRequestOSesion(?Request $request, Collection $empresasUif): ?int
    {
        $candidatos = [];
        if ($request instanceof Request) {
            $candidatos[] = (int) $request->input('empresa_id', 0);
            $candidatos[] = (int) $request->input('filtro_empresa_id', 0);
        }
        if (app()->bound('session')) {
            $candidatos[] = (int) session(self::SESSION_EMPRESA_ID, 0);
        }
        if ($empresasUif->count() === 1) {
            $candidatos[] = (int) $empresasUif->first()->id;
        }

        foreach ($candidatos as $id) {
            if ($id > 0 && self::empresaPermitida($id, $empresasUif)) {
                return $id;
            }
        }

        return null;
    }
}
