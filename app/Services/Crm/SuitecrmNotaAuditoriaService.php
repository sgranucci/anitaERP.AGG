<?php

namespace App\Services\Crm;

use App\Repositories\Crm\SuitecrmNotaAuditoriaRepository;
use App\Support\Ventas\SuitecrmNotaAuditoriaListadoFiltros;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SuitecrmNotaAuditoriaService
{
    public function __construct(
        private readonly SuitecrmConfigService $config,
        private readonly SuitecrmNotaAuditoriaRepository $repository,
        private readonly SuitecrmNotaVisibilidadService $visibilidad,
    ) {}

    public function isHabilitado(): bool
    {
        return $this->config->isHabilitado();
    }

    /**
     * @return Collection<int, object{id:string,label:string,notas:int}>
     */
    public function opcionesVendedor(): Collection
    {
        if (! $this->isHabilitado()) {
            return collect();
        }

        return $this->repository->listarVendedoresConNotas()->map(function ($u) {
            $nombre = trim(((string) $u->first_name).' '.((string) $u->last_name));
            if ($nombre === '') {
                $nombre = (string) $u->user_name;
            }

            return (object) [
                'id' => (string) $u->id,
                'label' => $nombre.' ('.$u->user_name.')',
                'notas' => (int) $u->notas,
            ];
        });
    }

    /**
     * @param  array{
     *     vendedor_crm_id:?string,
     *     fecha_desde:?string,
     *     fecha_hasta:?string,
     *     parent_type:string,
     *     texto:string,
     *     solo_vinculo_erp:bool
     * }  $filtros
     * @return array{filas: list<array<string, mixed>>, total: int, agrupadas_por_fecha: array<string, list<array<string, mixed>>>}
     */
    public function generar(array $filtros): array
    {
        if (! $this->isHabilitado()) {
            return [
                'filas' => [],
                'total' => 0,
                'agrupadas_por_fecha' => [],
            ];
        }

        $excluirSupervisor = $this->visibilidad->puedeVerNotasSupervisor()
            ? []
            : $this->visibilidad->userIdsSupervisorSuitecrm();

        $raw = $this->repository->listar($filtros, $excluirSupervisor);
        $clientesPorClave = $this->indiceClientesAnita();

        $filas = [];
        foreach ($raw as $nota) {
            $fila = $this->mapearFila($nota, $clientesPorClave);
            if (! $this->filaTieneContenido($fila)) {
                continue;
            }
            if (! empty($filtros['solo_vinculo_erp']) && empty($fila['cliente_id'])) {
                continue;
            }
            $filas[] = $fila;
        }

        $agrupadas = [];
        foreach ($filas as $fila) {
            $agrupadas[$fila['fecha']][] = $fila;
        }

        return [
            'filas' => $filas,
            'total' => count($filas),
            'agrupadas_por_fecha' => $agrupadas,
        ];
    }

    /**
     * @param  array{
     *     vendedor_crm_id:?string,
     *     fecha_desde:?string,
     *     fecha_hasta:?string,
     *     parent_type:string,
     *     texto:string,
     *     solo_vinculo_erp:bool
     * }  $filtros
     */
    public function paginar(array $filtros, int $page = 1, int $perPage = 50): LengthAwarePaginator
    {
        $resultado = $this->generar($filtros);
        $filas = $resultado['filas'];
        $page = max(1, $page);
        $slice = array_slice($filas, ($page - 1) * $perPage, $perPage);

        return new LengthAwarePaginator(
            $slice,
            count($filas),
            $perPage,
            $page,
            [
                'path' => LengthAwarePaginator::resolveCurrentPath(),
                'query' => array_merge(
                    SuitecrmNotaAuditoriaListadoFiltros::paraQueryString($filtros),
                    ['consultar' => 1]
                ),
            ]
        );
    }

    /**
     * @param  array{
     *     vendedor_crm_id:?string,
     *     fecha_desde:?string,
     *     fecha_hasta:?string,
     *     parent_type:string,
     *     texto:string,
     *     solo_vinculo_erp:bool
     * }  $filtros
     * @param  Collection<int, object>  $vendedores
     */
    public function armarSubtitulo(array $filtros, Collection $vendedores): string
    {
        $partes = [];

        $desde = $filtros['fecha_desde'] ?? null;
        $hasta = $filtros['fecha_hasta'] ?? null;
        if ($desde !== null || $hasta !== null) {
            $partes[] = 'DESDE '.($desde !== null ? $this->fechaDisplayCorta($desde) : '…')
                .' HASTA '.($hasta !== null ? $this->fechaDisplayCorta($hasta) : '…');
        }

        if (($filtros['vendedor_crm_id'] ?? null) !== null) {
            $v = $vendedores->firstWhere('id', $filtros['vendedor_crm_id']);
            $partes[] = 'Vendedor: '.($v->label ?? $filtros['vendedor_crm_id']);
        }
        if (($filtros['parent_type'] ?? '') !== '') {
            $partes[] = 'Tipo: '.(SuitecrmNotaAuditoriaListadoFiltros::TIPOS[$filtros['parent_type']] ?? $filtros['parent_type']);
        }
        if (trim((string) ($filtros['texto'] ?? '')) !== '') {
            $partes[] = 'Texto: '.$filtros['texto'];
        }
        if (! empty($filtros['solo_vinculo_erp'])) {
            $partes[] = 'Solo con vínculo ERP';
        }

        return implode(' · ', $partes);
    }

    /**
     * @param  array{cliente_id:?int, codigo_anita:?string, cuit_anita:?string, relacionado:string, asunto:string, nota:string}  $fila
     */
    private function filaTieneContenido(array $fila): bool
    {
        return trim((string) ($fila['relacionado'] ?? '')) !== ''
            || trim((string) ($fila['asunto'] ?? '')) !== ''
            || trim((string) ($fila['nota'] ?? '')) !== '';
    }

    /**
     * Índice Anita: por codigo+cuit, por codigo y por cuit normalizado.
     *
     * @return array{por_codigo_cuit: array<string, object>, por_codigo: array<string, object>, por_cuit: array<string, object>}
     */
    private function indiceClientesAnita(): array
    {
        $clientes = DB::table('cliente')
            ->select(['id', 'codigo', 'nombre', 'numerodocumento'])
            ->get();

        $porCodigoCuit = [];
        $porCodigo = [];
        $porCuit = [];

        foreach ($clientes as $c) {
            $codigo = trim((string) $c->codigo);
            $cuit = $this->normalizarCuit((string) $c->numerodocumento);
            if ($codigo !== '' && $cuit !== '') {
                $porCodigoCuit[$codigo.'|'.$cuit] = $c;
            }
            if ($codigo !== '' && ! isset($porCodigo[$codigo])) {
                $porCodigo[$codigo] = $c;
            }
            if ($cuit !== '' && ! isset($porCuit[$cuit])) {
                $porCuit[$cuit] = $c;
            }
        }

        return [
            'por_codigo_cuit' => $porCodigoCuit,
            'por_codigo' => $porCodigo,
            'por_cuit' => $porCuit,
        ];
    }

    /**
     * @param  array{por_codigo_cuit: array<string, object>, por_codigo: array<string, object>, por_cuit: array<string, object>}  $indice
     * @return array<string, mixed>
     */
    private function mapearFila(object $nota, array $indice): array
    {
        $parentType = (string) ($nota->parent_type ?? '');
        [$tipoLabel, $relacionado, $empresaPotencial] = $this->resolverRelacionado($nota);

        $codigoCrm = trim((string) ($nota->account_codigo ?? ''));
        $cuitCrm = trim((string) ($nota->account_cuit ?? ''));
        $cliente = $this->resolverClienteAnita($codigoCrm, $cuitCrm, $indice);

        $vendedor = trim(((string) ($nota->vendedor_first_name ?? '')).' '.((string) ($nota->vendedor_last_name ?? '')));
        if ($vendedor === '') {
            $vendedor = (string) ($nota->vendedor_user_name ?? '');
        }

        $fechaRaw = (string) ($nota->date_entered ?? '');
        $fecha = strlen($fechaRaw) >= 10 ? substr($fechaRaw, 0, 10) : '';

        return [
            'id' => (string) ($nota->id ?? ''),
            'fecha' => $fecha,
            'fecha_display' => $fecha !== '' ? $this->fechaDisplay($fecha) : '',
            'parent_type' => $parentType,
            'tipo' => $tipoLabel,
            'relacionado' => $relacionado,
            'empresa_potencial' => $empresaPotencial,
            'asunto' => trim((string) ($nota->name ?? '')),
            'nota' => trim((string) ($nota->description ?? '')),
            'vendedor' => $vendedor,
            'vendedor_user_name' => (string) ($nota->vendedor_user_name ?? ''),
            'codigo_crm' => $codigoCrm,
            'cuit_crm' => $cuitCrm,
            'cliente_id' => $cliente?->id ? (int) $cliente->id : null,
            'codigo_anita' => $cliente ? trim((string) $cliente->codigo) : null,
            'cliente_anita' => $cliente ? trim((string) $cliente->nombre) : null,
            'nombreempresa' => config('app.empresa'),
        ];
    }

    /**
     * @return array{0: string, 1: string, 2: string}
     */
    private function resolverRelacionado(object $nota): array
    {
        $parentType = (string) ($nota->parent_type ?? '');

        if ($parentType === 'Accounts') {
            $nombre = trim((string) ($nota->account_name ?? ''));
            if ($nombre === '') {
                $nombre = trim((string) ($nota->cuenta_relacionada_c ?? ''));
            }

            return ['Cta', $nombre, ''];
        }

        if ($parentType === 'Leads') {
            $persona = trim(((string) ($nota->lead_first_name ?? '')).' '.((string) ($nota->lead_last_name ?? '')));
            if ($persona === '') {
                $persona = trim((string) ($nota->cli_potencial_relacionado_c ?? ''));
            }
            $empresa = trim((string) ($nota->lead_account_name ?? ''));

            return ['CP', $persona, $empresa];
        }

        if ($parentType === 'Contacts') {
            $nombre = trim(((string) ($nota->contact_first_name ?? '')).' '.((string) ($nota->contact_last_name ?? '')));
            if ($nombre === '') {
                $nombre = trim((string) ($nota->cli_potencial_relacionado_c ?? ''));
            }
            if ($nombre === '') {
                $nombre = trim((string) ($nota->cuenta_relacionada_c ?? ''));
            }

            return ['Cont', $nombre, ''];
        }

        $fallback = trim((string) ($nota->cuenta_relacionada_c ?? ''));
        if ($fallback === '') {
            $fallback = trim((string) ($nota->cli_potencial_relacionado_c ?? ''));
        }

        return [$parentType !== '' ? $parentType : 'Otro', $fallback, ''];
    }

    /**
     * @param  array{por_codigo_cuit: array<string, object>, por_codigo: array<string, object>, por_cuit: array<string, object>}  $indice
     */
    private function resolverClienteAnita(string $codigo, string $cuit, array $indice): ?object
    {
        $cuitNorm = $this->normalizarCuit($cuit);
        $codigo = trim($codigo);

        if ($codigo !== '' && $cuitNorm !== '') {
            $key = $codigo.'|'.$cuitNorm;
            if (isset($indice['por_codigo_cuit'][$key])) {
                return $indice['por_codigo_cuit'][$key];
            }
        }

        if ($codigo !== '' && isset($indice['por_codigo'][$codigo])) {
            return $indice['por_codigo'][$codigo];
        }

        if ($cuitNorm !== '' && isset($indice['por_cuit'][$cuitNorm])) {
            return $indice['por_cuit'][$cuitNorm];
        }

        return null;
    }

    private function normalizarCuit(string $cuit): string
    {
        return preg_replace('/\D+/', '', $cuit) ?? '';
    }

    private function fechaDisplay(string $ymd): string
    {
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $ymd, $m) === 1) {
            return $m[3].'-'.$m[2].'-'.$m[1];
        }

        return $ymd;
    }

    /** Formato compacto del ejemplo SuiteCRM: 13/05/26 */
    private function fechaDisplayCorta(string $ymd): string
    {
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $ymd, $m) === 1) {
            return $m[3].'/'.$m[2].'/'.substr($m[1], -2);
        }

        return $ymd;
    }
}
