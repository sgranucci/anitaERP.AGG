<?php

namespace App\Services\Solicitudpago;

use App\Support\Database\SqlDialectSupport;
use App\ApiAnita;
use App\Models\Configuracion\Empresa;
use App\Models\Contable\Centrocosto;
use App\Models\Contable\Cuentacontable;
use App\Models\Seguridad\Usuario;
use App\Models\Solicitudpago\Concepto_Solicitudpago;
use App\Models\Solicitudpago\Concepto_Solicitudpago_Cuenta;
use App\Models\Solicitudpago\Concepto_Solicitudpago_Formapago;
use App\Models\Solicitudpago\Concepto_Solicitudpago_Usuario;
use App\Models\Solicitudpago\Formapagosol;
use App\Models\Solicitudpago\Sector_Solicitudpago;
use App\Repositories\Solicitudpago\FormapagosolRepositoryInterface;
use App\Repositories\Solicitudpago\Sector_SolicitudpagoRepositoryInterface;
use App\Support\Solicitudpago\ConceptoSolicitudpagoCuentaEmpresaSupport;
use App\Support\Solicitudpago\ConceptoSolicitudpagoEstados;
use App\Support\Solicitudpago\ConceptoSolicitudpagoFormaPago;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Sync pull completo concsol* (che_ban) → ERP. Sin escritura a Anita.
 */
class ConceptoSolicitudpagoAnitaSyncService
{
    public function __construct(
        private Sector_SolicitudpagoRepositoryInterface $sectorRepository,
        private FormapagosolRepositoryInterface $formapagosolRepository,
    ) {
    }

    public function sincronizar(): array
    {
        ini_set('max_execution_time', '600');

        $this->sectorRepository->sincronizarConAnita();
        $this->formapagosolRepository->sincronizarConAnita();

        $api = new ApiAnita();
        $cabeceras = $this->listarTabla($api, 'concsolmae', 'csolm_id, csolm_detalle, csolm_sector, csolm_forma_pago, csolm_estado');
        $usuarios = $this->agruparPorConcepto(
            $this->listarTabla($api, 'concsolusu', 'csolu_id, csolu_nivel, csolu_usuario, csolu_usuario_orig, csolu_desde_monto'),
            'csolu_id'
        );
        $cuentas = $this->agruparPorConcepto(
            $this->listarTabla($api, 'concsolcta', 'csolc_id, csolc_empresa, csolc_cuenta, csolc_ccosto, csolc_d_h'),
            'csolc_id'
        );
        $formas = $this->agruparPorConcepto(
            $this->listarTabla($api, 'concsolfpago', 'csolf_id, csolf_id_formapago'),
            'csolf_id'
        );

        $mapaUsuarios = $this->mapaAnitaUsuarioAErp($api);
        $mapaSectores = Sector_Solicitudpago::query()->pluck('id', 'codigo')->all();
        $mapaFormapago = Formapagosol::query()->pluck('id', 'codigo')->all();
        $mapaEmpresas = Empresa::query()->pluck('id', 'codigo')->all();
        $mapaCcosto = Centrocosto::query()->pluck('id', 'codigo')->all();

        $creados = 0;
        $actualizados = 0;
        $omitidos = 0;

        DB::transaction(function () use (
            $cabeceras, $usuarios, $cuentas, $formas,
            $mapaUsuarios, $mapaSectores, $mapaFormapago, $mapaEmpresas, $mapaCcosto,
            &$creados, &$actualizados, &$omitidos
        ) {
            foreach ($cabeceras as $row) {
                $codigo = (int) ($row->csolm_id ?? 0);
                if ($codigo <= 0) {
                    $omitidos++;
                    continue;
                }

                $sectorCodigo = (int) ($row->csolm_sector ?? 0);
                $attrs = [
                    'codigo' => $codigo,
                    'nombre' => $this->recortar(trim((string) ($row->csolm_detalle ?? '')), 50) ?: (string) $codigo,
                    'sector_solicitudpago_id' => $mapaSectores[$sectorCodigo] ?? null,
                    'forma_pago' => ConceptoSolicitudpagoFormaPago::desdeAnita($row->csolm_forma_pago ?? 0),
                    'estado' => ConceptoSolicitudpagoEstados::desdeAnita($row->csolm_estado ?? 'A'),
                ];

                $concepto = Concepto_Solicitudpago::query()->where('codigo', $codigo)->first();
                if ($concepto) {
                    $concepto->update($attrs);
                    $actualizados++;
                } else {
                    $concepto = Concepto_Solicitudpago::query()->create($attrs);
                    $creados++;
                }

                $this->reemplazarUsuarios($concepto, $usuarios[$codigo] ?? [], $mapaUsuarios);
                $this->reemplazarCuentas($concepto, $cuentas[$codigo] ?? [], $mapaEmpresas, $mapaCcosto);
                $this->reemplazarFormapagos($concepto, $formas[$codigo] ?? [], $mapaFormapago);
            }
        });

        return [
            'cabeceras' => count($cabeceras),
            'creados' => $creados,
            'actualizados' => $actualizados,
            'omitidos' => $omitidos,
        ];
    }

    /**
     * @return list<object>
     */
    private function listarTabla(ApiAnita $api, string $tabla, string $campos): array
    {
        $parsed = ApiAnita::parsearRespuestaLista($api->apiCall([
            'acc' => 'list',
            'sistema' => 'che_ban',
            'tabla' => $tabla,
            'campos' => $campos,
        ]));

        if ($parsed['error_lectura'] !== null) {
            Log::warning('concepto_solicitudpago.anita_sync.lectura', [
                'tabla' => $tabla,
                'error' => $parsed['error_lectura'],
            ]);
        }

        return $parsed['filas'];
    }

    /**
     * @param  list<object>  $filas
     * @return array<int, list<object>>
     */
    private function agruparPorConcepto(array $filas, string $campoId): array
    {
        $out = [];
        foreach ($filas as $fila) {
            $id = (int) ($fila->{$campoId} ?? 0);
            if ($id <= 0) {
                continue;
            }
            $out[$id][] = $fila;
        }

        return $out;
    }

    /**
     * @return array<int, int> Anita usu_usuario => ERP usuario.id
     */
    private function mapaAnitaUsuarioAErp(ApiAnita $api): array
    {
        $parsed = ApiAnita::parsearRespuestaLista($api->apiCall([
            'acc' => 'list',
            'sistema' => 'compras',
            'tabla' => 'usuario',
            'campos' => 'usu_usuario, usu_logname',
        ]));

        $porLogname = Usuario::query()
            ->get(['id', 'usuario'])
            ->keyBy(fn (Usuario $u) => mb_strtolower(trim((string) $u->usuario)));

        $mapa = [];
        foreach ($parsed['filas'] as $fila) {
            $anitaId = (int) ($fila->usu_usuario ?? 0);
            $logname = mb_strtolower(trim((string) ($fila->usu_logname ?? '')));
            if ($anitaId <= 0 || $logname === '') {
                continue;
            }
            $erp = $porLogname->get($logname);
            if ($erp) {
                $mapa[$anitaId] = (int) $erp->id;
            }
        }

        return $mapa;
    }

    /**
     * @param  list<object>  $filas
     * @param  array<int, int>  $mapaUsuarios
     */
    private function reemplazarUsuarios(Concepto_Solicitudpago $concepto, array $filas, array $mapaUsuarios): void
    {
        Concepto_Solicitudpago_Usuario::query()
            ->where('concepto_solicitudpago_id', $concepto->id)
            ->delete();

        foreach ($filas as $fila) {
            $anitaUsuario = (int) ($fila->csolu_usuario ?? 0);
            $anitaOrig = (int) ($fila->csolu_usuario_orig ?? 0);
            $usuarioId = $anitaUsuario > 0 ? ($mapaUsuarios[$anitaUsuario] ?? null) : null;
            $usuarioOrigId = $anitaOrig > 0 ? ($mapaUsuarios[$anitaOrig] ?? null) : null;

            Concepto_Solicitudpago_Usuario::query()->create([
                'concepto_solicitudpago_id' => $concepto->id,
                'nivel' => max(1, (int) ($fila->csolu_nivel ?? 1)),
                'usuario_id' => $usuarioId,
                'usuario_orig_id' => $usuarioOrigId,
                'desde_monto' => (float) ($fila->csolu_desde_monto ?? 0),
            ]);
        }
    }

    /**
     * @param  list<object>  $filas
     * @param  array<int|string, int>  $mapaEmpresas
     * @param  array<int|string, int>  $mapaCcosto
     */
    private function reemplazarCuentas(
        Concepto_Solicitudpago $concepto,
        array $filas,
        array $mapaEmpresas,
        array $mapaCcosto
    ): void {
        Concepto_Solicitudpago_Cuenta::query()
            ->where('concepto_solicitudpago_id', $concepto->id)
            ->delete();

        $vistos = [];
        foreach ($filas as $fila) {
            $empresaCodigo = (int) ($fila->csolc_empresa ?? 0);

            $codigoCuenta = ltrim(trim((string) ($fila->csolc_cuenta ?? '')), '0');
            if ($codigoCuenta === '') {
                $codigoCuenta = '0';
            }
            $codigoAnita = (string) (int) ($fila->csolc_cuenta ?? 0);

            $ccostoCodigo = (int) ($fila->csolc_ccosto ?? 0);
            $centrocostoId = null;
            if ($ccostoCodigo > 0) {
                $centrocostoId = $mapaCcosto[(string) $ccostoCodigo]
                    ?? $mapaCcosto[$ccostoCodigo]
                    ?? null;
            }

            $dh = strtoupper(trim((string) ($fila->csolc_d_h ?? 'D')));
            if ($dh !== 'H') {
                $dh = 'D';
            }

            // Anita empresa 0 = cuenta genérica: solo empresas con usuarios asignados
            // (Biyemas/Kandiko/Rebisco). Budget/Temporal no operan SP.
            $cuentasMatch = Cuentacontable::query()
                ->where(function ($q) use ($codigoAnita, $codigoCuenta) {
                    $q->where('codigo', $codigoAnita)
                        ->orWhere('codigo', $codigoCuenta)
                        ->orWhereRaw(SqlDialectSupport::castEntero('codigo').' = ?', [(int) $codigoAnita]);
                });

            if ($empresaCodigo > 0) {
                $empresaId = $mapaEmpresas[$empresaCodigo] ?? null;
                if ($empresaId === null || ! ConceptoSolicitudpagoCuentaEmpresaSupport::esOperativa((int) $empresaId)) {
                    continue;
                }
                $cuentasMatch->where('empresa_id', $empresaId);
            } else {
                $idsOperativas = ConceptoSolicitudpagoCuentaEmpresaSupport::idsConUsuariosAsignados();
                if ($idsOperativas === []) {
                    continue;
                }
                $cuentasMatch->whereIn('empresa_id', $idsOperativas);
            }

            foreach ($cuentasMatch->get(['id', 'empresa_id']) as $cuenta) {
                $empresaId = (int) $cuenta->empresa_id;
                if ($empresaId <= 0) {
                    continue;
                }
                $clave = $empresaId.'-'.$cuenta->id;
                if (isset($vistos[$clave])) {
                    continue;
                }
                $vistos[$clave] = true;

                Concepto_Solicitudpago_Cuenta::query()->create([
                    'concepto_solicitudpago_id' => $concepto->id,
                    'empresa_id' => $empresaId,
                    'cuentacontable_id' => $cuenta->id,
                    'centrocosto_id' => $centrocostoId,
                    'debe_haber' => $dh,
                ]);
            }
        }
    }

    /**
     * @param  list<object>  $filas
     * @param  array<int, int>  $mapaFormapago
     */
    private function reemplazarFormapagos(Concepto_Solicitudpago $concepto, array $filas, array $mapaFormapago): void
    {
        Concepto_Solicitudpago_Formapago::query()
            ->where('concepto_solicitudpago_id', $concepto->id)
            ->delete();

        $vistos = [];
        foreach ($filas as $fila) {
            $fpCodigo = (int) ($fila->csolf_id_formapago ?? 0);
            $fpId = $mapaFormapago[$fpCodigo] ?? null;
            if ($fpId === null || isset($vistos[$fpId])) {
                continue;
            }
            $vistos[$fpId] = true;

            Concepto_Solicitudpago_Formapago::query()->create([
                'concepto_solicitudpago_id' => $concepto->id,
                'formapagosol_id' => $fpId,
            ]);
        }
    }

    private function recortar(string $valor, int $len): string
    {
        if (function_exists('mb_substr')) {
            return mb_substr($valor, 0, $len);
        }

        return substr($valor, 0, $len);
    }
}
