<?php

namespace App\Services\Compras;

use App\Models\Compras\Comprobante_Proveedor;
use App\Models\Compras\Ordencompra;
use App\Models\Compras\Ordencompra_Historia;
use App\Models\Compras\Precarga_Comprobante_Proveedor;
use App\Models\Compras\Precarga_Comprobante_Proveedor_Recepcion;
use App\Models\Compras\Proveedor_Cuentacorriente;
use App\Models\Configuracion\Arbolaprobacion_Movimiento;
use App\Models\Stock\Recepcion_Proveedor;
use App\Repositories\Configuracion\EmpresaRepository;
use App\Support\Compras\OrdencompraEnvioCuentasAPagarGateSupport;
use App\Support\Compras\OrdencompraLegajoAnitaScanFacturaSupport;
use App\Support\Compras\OrdencompraLegajoBandejaFiltros;
use App\Support\Compras\OrdencompraLegajoGastronomiaSupport;
use App\Support\Compras\OrdencompraListadoFiltros;
use App\Support\Compras\OrdencompraSectorVisibilidadSupport;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class OrdencompraLegajoBandejaService
{
    /**
     * @param  array<string, mixed>  $filtros
     */
    public function paginar(array $filtros, int $perPage = 30): LengthAwarePaginator
    {
        $query = $this->queryBase($filtros);
        $pagina = $query->paginate($perPage)->withQueryString();
        $filas = $this->hidratar($pagina->getCollection());
        $pagina->setCollection($filas);

        return $pagina;
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return Collection<int, array<string, mixed>>
     */
    public function listar(array $filtros, int $limite = 2000): Collection
    {
        $ocs = $this->queryBase($filtros)->limit($limite)->get();

        return $this->hidratar($ocs);
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    private function queryBase(array $filtros): Builder
    {
        $query = Ordencompra::query()
            ->select([
                'ordencompra.id',
                'ordencompra.numeroordencompra',
                'ordencompra.fecha',
                'ordencompra.empresa_id',
                'ordencompra.proveedor_id',
                'ordencompra.centrocosto_id',
                'ordencompra.sector_legajocompra_id',
                'ordencompra.estadoordencompra',
                'ordencompra.es_contrato',
                'ordencompra.contrato_requiere_recepcion',
                'ordencompra.contrato_vigencia_desde',
                'ordencompra.contrato_vigencia_hasta',
                'ordencompra.created_at',
            ])
            ->with([
                'empresas:id,codigo,nombre',
                'proveedores:id,codigo,nombre',
                'centrocostos:id,codigo,nombre',
                'sector_legajocompras:id,nombre',
            ])
            ->leftJoin('empresa', 'empresa.id', '=', 'ordencompra.empresa_id')
            ->leftJoin('centrocosto', 'centrocosto.id', '=', 'ordencompra.centrocosto_id')
            ->leftJoin('proveedor', 'proveedor.id', '=', 'ordencompra.proveedor_id')
            ->leftJoin('usuario', 'usuario.id', '=', 'ordencompra.creousuario_id')
            ->leftJoin('sector_legajocompra', 'sector_legajocompra.id', '=', 'ordencompra.sector_legajocompra_id')
            ->leftJoin('condicioncompra', 'condicioncompra.id', '=', 'ordencompra.condicioncompra_id')
            ->leftJoin('requisicion', 'requisicion.id', '=', 'ordencompra.requisicion_id')
            ->orderByDesc('ordencompra.fecha')
            ->orderByDesc('ordencompra.id');

        app(EmpresaRepository::class)->aplicarFiltroEmpresasAsignadas($query, 'ordencompra.empresa_id');
        OrdencompraSectorVisibilidadSupport::aplicarFiltro($query);
        $this->aplicarFiltrosBusqueda($query, $filtros);
        $this->aplicarFiltrosDocumento($query, $filtros);

        $ccGastro = OrdencompraLegajoGastronomiaSupport::centrocostoIdsCircuito();
        $tab = (string) ($filtros['tab'] ?? OrdencompraLegajoBandejaFiltros::TAB_TODOS);
        if ($tab === OrdencompraLegajoBandejaFiltros::TAB_GASTRONOMIA && $ccGastro !== []) {
            $query->whereIn('ordencompra.centrocosto_id', $ccGastro);
        } elseif ($tab === OrdencompraLegajoBandejaFiltros::TAB_RESTO && $ccGastro !== []) {
            $query->where(function ($q) use ($ccGastro) {
                $q->whereNull('ordencompra.centrocosto_id')
                    ->orWhereNotIn('ordencompra.centrocosto_id', $ccGastro);
            });
        }

        $sectorCompras = OrdencompraEnvioCuentasAPagarGateSupport::sectorIdPorNombre(
            OrdencompraEnvioCuentasAPagarGateSupport::SECTOR_COMPRAS
        );
        $sectorGastro = OrdencompraLegajoGastronomiaSupport::sectorGastronomiaId();
        $sectorCxp = OrdencompraEnvioCuentasAPagarGateSupport::sectorIdPorNombre(
            OrdencompraEnvioCuentasAPagarGateSupport::SECTOR_CUENTAS_A_PAGAR
        );
        $sectorFin = OrdencompraLegajoGastronomiaSupport::sectorFinalizadoId();

        $vista = (string) ($filtros['vista'] ?? OrdencompraLegajoBandejaFiltros::VISTA_PENDIENTES);
        if ($vista === OrdencompraLegajoBandejaFiltros::VISTA_PENDIENTES) {
            $query->where(function ($q) use ($sectorCompras) {
                $q->where('ordencompra.sector_legajocompra_id', $sectorCompras);
                if ($sectorCompras <= 0) {
                    $q->orWhereNull('ordencompra.sector_legajocompra_id');
                }
            });
        } elseif ($vista === OrdencompraLegajoBandejaFiltros::VISTA_ESTADOS) {
            $activos = array_values(array_filter([$sectorCompras, $sectorGastro, $sectorCxp]));
            if ($activos !== []) {
                $query->whereIn('ordencompra.sector_legajocompra_id', $activos);
            }
        } elseif ($vista === OrdencompraLegajoBandejaFiltros::VISTA_CXP) {
            if ($sectorCxp > 0) {
                $query->where('ordencompra.sector_legajocompra_id', $sectorCxp);
            }
        } elseif ($vista === OrdencompraLegajoBandejaFiltros::VISTA_PAGOS) {
            $sectoresPago = array_values(array_filter([$sectorCxp, $sectorFin]));
            if ($sectoresPago !== []) {
                $query->whereIn('ordencompra.sector_legajocompra_id', $sectoresPago);
            }
            $query->where(function ($q) {
                $this->whereExisteComprobante($q);
                $q->orWhere(function ($w) {
                    $this->whereExistePago($w);
                });
            });
        } elseif ($vista === OrdencompraLegajoBandejaFiltros::VISTA_ARCHIVADOS) {
            if ($sectorFin > 0) {
                $query->where('ordencompra.sector_legajocompra_id', $sectorFin);
            } else {
                $query->whereRaw('1 = 0');
            }
        } else {
            $todos = array_values(array_filter([$sectorCompras, $sectorGastro, $sectorCxp, $sectorFin]));
            $query->where(function ($q) use ($todos) {
                if ($todos !== []) {
                    $q->whereIn('ordencompra.sector_legajocompra_id', $todos);
                }
                $q->orWhereHas('ordencompra_historias');
            });
        }

        $this->aplicarAtajo($query, (string) ($filtros['atajo'] ?? ''));

        return $query;
    }

    /**
     * @param  Collection<int, Ordencompra>  $ocs
     * @return Collection<int, array<string, mixed>>
     */
    private function hidratar(Collection $ocs): Collection
    {
        $ids = $ocs->pluck('id')->map(fn ($id) => (int) $id)->all();
        $historias = $this->ultimaHistoriaPorOc($ids);
        $facturas = $this->facturasPorOc($ocs);
        foreach (OrdencompraLegajoAnitaScanFacturaSupport::facturasPorOcs($ocs) as $ocId => $anita) {
            $facturas[$ocId] = array_merge($facturas[$ocId] ?? [], $anita);
        }
        $coms = $this->comsPorOc($ids);
        $precargaIds = [];
        foreach ($facturas as $lista) {
            foreach ($lista as $fac) {
                $precargaIds[] = (int) $fac['id'];
            }
        }
        $asignadas = $this->asignacionesPorPrecarga($precargaIds);
        $comprobantes = $this->comprobantesPorOc($ids, $precargaIds, $facturas);
        $cpIds = [];
        foreach ($comprobantes as $lista) {
            foreach ($lista as $cp) {
                $cpIds[] = (int) $cp['id'];
            }
        }
        $pagos = $this->pagosPorComprobante($cpIds);
        $decisiones = $this->ultimaDecisionArbol($ids);

        return $ocs->values()->map(function (Ordencompra $oc) use ($historias, $facturas, $coms, $asignadas, $comprobantes, $pagos, $decisiones) {
            $id = (int) $oc->id;
            $hist = $historias[$id] ?? null;
            $desde = $hist['fecha'] ?? ($oc->created_at ? Carbon::parse($oc->created_at) : null);
            $facs = $facturas[$id] ?? [];
            $comList = $coms[$id] ?? [];
            $tieneFactura = $facs !== [];
            $tieneCom = $comList !== [];
            $exigeCom = OrdencompraEnvioCuentasAPagarGateSupport::exigeRecepcionCom($oc, $tieneCom);
            $decision = $decisiones[$id] ?? null;
            $esGastro = OrdencompraLegajoGastronomiaSupport::requiereCircuito($oc);
            $primeraFac = $facs[0] ?? null;
            $primeraCom = $comList[0] ?? null;
            $comAsignada = false;
            foreach ($facs as $fac) {
                if (! empty($asignadas[(int) $fac['id']])) {
                    $comAsignada = true;
                    break;
                }
            }
            $cps = $comprobantes[$id] ?? [];
            $primeraCp = $cps[0] ?? null;
            $pago = null;
            foreach ($cps as $cp) {
                if (isset($pagos[(int) $cp['id']])) {
                    $pago = $pagos[(int) $cp['id']];
                    break;
                }
            }

            return [
                'id' => $id,
                'numero' => (string) $oc->numeroordencompra,
                'fecha' => $oc->fecha ? Carbon::parse($oc->fecha)->format('d/m/Y') : '',
                'empresa' => (string) ($oc->empresas->nombre ?? ''),
                'proveedor' => (string) ($oc->proveedores->nombre ?? ''),
                'centrocosto' => trim(($oc->centrocostos->codigo ?? '').' '.($oc->centrocostos->nombre ?? '')),
                'sector' => (string) ($oc->sector_legajocompras->nombre ?? '—'),
                'sector_id' => (int) ($oc->sector_legajocompra_id ?? 0),
                'estado_oc' => (string) $oc->estadoordencompra,
                'dias' => OrdencompraLegajoGastronomiaSupport::diasEnUbicacion($desde),
                'fecha_ubicacion' => $desde ? $desde->format('d/m/Y H:i') : '',
                'tiene_factura' => $tieneFactura,
                'tiene_com' => $tieneCom,
                'tiene_com_asignada' => $comAsignada,
                'tiene_comprobante' => $primeraCp !== null,
                'tiene_pago' => $pago !== null,
                'exige_com' => $exigeCom,
                'paquete_ok' => $tieneFactura && (! $exigeCom || $tieneCom),
                'es_gastronomia' => $esGastro,
                'puede_enviar' => $esGastro && OrdencompraLegajoGastronomiaSupport::puedeMostrarEnviar($oc),
                'puede_enviar_cxp' => ! $esGastro && OrdencompraLegajoGastronomiaSupport::puedeMostrarEnviarCuentasAPagar($oc),
                'puede_finalizar' => OrdencompraLegajoGastronomiaSupport::puedeFinalizar($oc),
                'decision' => $decision['estado'] ?? '',
                'firmante' => $decision['usuario'] ?? '',
                'fecha_decision' => $decision['fecha'] ?? '',
                'comentario_decision' => $decision['comentario'] ?? '',
                'url_oc' => can('editar-ordencompra', false)
                    ? route('editar_ordencompra', ['id' => $id])
                    : route('solo_consulta_ordencompra', ['id' => $id]),
                'url_factura' => $primeraFac['url_pdf'] ?? null,
                'url_com' => $primeraCom['url_pdf'] ?? null,
                'url_historia' => route('ordencompra_legajo_bandeja_historia', ['id' => $id]),
                'url_paquete' => route('ordencompra_legajo_bandeja_paquete', ['id' => $id]),
                'url_asignar_com' => route('ordencompra_legajo_bandeja_asignar_com', ['id' => $id]),
                'url_cargar_cxp' => empty($primeraCp) ? ($primeraFac['url_cargar_cxp'] ?? null) : null,
                'url_comprobante' => $primeraCp['url'] ?? null,
                'url_pago' => $pago['url'] ?? null,
                'etiqueta_pago' => $pago['etiqueta'] ?? '',
                'url_enviar' => route('ordencompra_enviar_gastronomia', ['id' => $id]),
                'url_enviar_cxp' => route('ordencompra_enviar_cuentas_a_pagar', ['id' => $id]),
                'url_finalizar' => route('ordencompra_finalizar_legajo', ['id' => $id]),
            ];
        });
    }

    /**
     * @param  Builder<\App\Models\Compras\Ordencompra>  $query
     * @param  array<string, mixed>  $filtros
     */
    private function aplicarFiltrosBusqueda(Builder $query, array $filtros): void
    {
        if (! empty($filtros['empresa_id'])) {
            $query->where('ordencompra.empresa_id', (int) $filtros['empresa_id']);
        }
        if (! OrdencompraListadoFiltros::tieneCriteriosTexto($filtros)) {
            return;
        }

        $query->where(function ($q) use ($filtros) {
            $inner = $filtros;
            $inner['empresa_id'] = null;
            OrdencompraListadoFiltros::aplicar($q, $inner);
            $valor = trim((string) ($filtros['valor'] ?? ''));
            if ($valor !== '' && ($filtros['modo'] ?? OrdencompraListadoFiltros::MODO_TODOS) === OrdencompraListadoFiltros::MODO_TODOS) {
                $this->aplicarBusquedaDocumento($q, $valor);
            }
        });
    }

    /**
     * @param  Builder<\App\Models\Compras\Ordencompra>  $query
     * @param  array<string, mixed>  $filtros
     */
    private function aplicarFiltrosDocumento(Builder $query, array $filtros): void
    {
        $nroFac = trim((string) ($filtros['nro_factura'] ?? ''));
        if ($nroFac !== '') {
            $query->where(function ($q) use ($nroFac) {
                $this->whereExisteFacturaNumero($q, $nroFac);
            });
        }
        $nroCom = trim((string) ($filtros['nro_com'] ?? ''));
        if ($nroCom !== '') {
            $query->where(function ($q) use ($nroCom) {
                $this->whereExisteComNumero($q, $nroCom);
            });
        }
        $nroOp = trim((string) ($filtros['nro_op'] ?? ''));
        if ($nroOp !== '') {
            $query->where(function ($q) use ($nroOp) {
                $this->whereExistePagoNumero($q, $nroOp);
            });
        }
    }

    /**
     * @param  Builder<\App\Models\Compras\Ordencompra>  $query
     */
    private function aplicarAtajo(Builder $query, string $atajo): void
    {
        if ($atajo === '') {
            return;
        }
        if ($atajo === OrdencompraLegajoBandejaFiltros::ATAJO_SIN_FACTURA) {
            $query->where(function ($q) {
                $this->whereExistePrecargaPdf($q, true);
            });

            return;
        }
        if ($atajo === OrdencompraLegajoBandejaFiltros::ATAJO_SIN_COM) {
            $query->where(function ($q) {
                $this->whereExisteCom($q, true);
            });

            return;
        }
        if ($atajo === OrdencompraLegajoBandejaFiltros::ATAJO_COM_SIN_ASIGNAR) {
            $query->where(function ($q) {
                $this->whereExistePrecargaPdf($q, false);
            })->where(function ($q) {
                $this->whereExisteCom($q, false);
            })->where(function ($q) {
                $this->whereExisteAsignacionCom($q, true);
            });

            return;
        }
        if ($atajo === OrdencompraLegajoBandejaFiltros::ATAJO_LISTO_CARGAR) {
            $query->where(function ($q) {
                $this->whereExistePrecargaPdf($q, false);
            })->where(function ($q) {
                $this->whereExisteComprobante($q, true);
            });

            return;
        }
        if ($atajo === OrdencompraLegajoBandejaFiltros::ATAJO_FC_CARGADA) {
            $query->where(function ($q) {
                $this->whereExisteComprobante($q, false);
            });

            return;
        }
        if ($atajo === OrdencompraLegajoBandejaFiltros::ATAJO_CON_PAGO) {
            $query->where(function ($q) {
                $this->whereExistePago($q, false);
            });
        }
    }

    /**
     * @param  Builder<\App\Models\Compras\Ordencompra>  $q
     */
    private function aplicarBusquedaDocumento(Builder $q, string $valor): void
    {
        $q->orWhere(function ($w) use ($valor) {
            $this->whereExisteFacturaNumero($w, $valor);
        });
        $q->orWhere(function ($w) use ($valor) {
            $this->whereExisteComNumero($w, $valor);
        });
        $q->orWhere(function ($w) use ($valor) {
            $this->whereExistePagoNumero($w, $valor);
        });
    }

    /**
     * @param  Builder<\App\Models\Compras\Ordencompra>  $q
     */
    private function whereExistePrecargaPdf(Builder $q, bool $not = false): void
    {
        $method = $not ? 'whereNotExists' : 'whereExists';
        $q->{$method}(function ($e) {
            $e->selectRaw('1')
                ->from('precarga_comprobante_proveedor as pcp')
                ->whereColumn('pcp.empresa_id', 'ordencompra.empresa_id')
                ->whereColumn('pcp.numeroordencompra', 'ordencompra.numeroordencompra')
                ->whereNotNull('pcp.rutaalmacenamiento')
                ->where('pcp.rutaalmacenamiento', '!=', '')
                ->where(function ($w) {
                    $w->whereNull('pcp.estado')
                        ->orWhereRaw('UPPER(TRIM(pcp.estado)) != ?', ['ANULADA']);
                });
        });
    }

    /**
     * @param  Builder<\App\Models\Compras\Ordencompra>  $q
     */
    private function whereExisteCom(Builder $q, bool $not = false): void
    {
        $method = $not ? 'whereNotExists' : 'whereExists';
        $q->{$method}(function ($e) {
            $e->selectRaw('1')
                ->from('recepcion_proveedor as rp')
                ->whereColumn('rp.ordencompra_id', 'ordencompra.id')
                ->where('rp.tipo', Recepcion_Proveedor::TIPO_RECEPCION)
                ->where('rp.estado', Recepcion_Proveedor::ESTADO_CONFIRMADA);
        });
    }

    /**
     * @param  Builder<\App\Models\Compras\Ordencompra>  $q
     */
    private function whereExisteAsignacionCom(Builder $q, bool $not = false): void
    {
        if (! Schema::hasTable('precarga_comprobante_proveedor_recepcion')) {
            if (! $not) {
                $q->whereRaw('1 = 0');
            }

            return;
        }
        $method = $not ? 'whereNotExists' : 'whereExists';
        $q->{$method}(function ($e) {
            $e->selectRaw('1')
                ->from('precarga_comprobante_proveedor_recepcion as pcr')
                ->join('precarga_comprobante_proveedor as pcp', 'pcp.id', '=', 'pcr.precarga_comprobante_proveedor_id')
                ->whereColumn('pcp.empresa_id', 'ordencompra.empresa_id')
                ->whereColumn('pcp.numeroordencompra', 'ordencompra.numeroordencompra');
        });
    }

    /**
     * @param  Builder<\App\Models\Compras\Ordencompra>  $q
     */
    private function whereExisteComprobante(Builder $q, bool $not = false): void
    {
        $method = $not ? 'whereNotExists' : 'whereExists';
        $q->{$method}(function ($e) {
            $e->selectRaw('1')
                ->from('comprobante_proveedor as cp')
                ->where(function ($w) {
                    $w->whereColumn('cp.ordencompra_id', 'ordencompra.id')
                        ->orWhere(function ($p) {
                            $p->whereNotNull('cp.precarga_comprobante_proveedor_id')
                                ->whereExists(function ($pre) {
                                    $pre->selectRaw('1')
                                        ->from('precarga_comprobante_proveedor as pcp')
                                        ->whereColumn('pcp.id', 'cp.precarga_comprobante_proveedor_id')
                                        ->whereColumn('pcp.empresa_id', 'ordencompra.empresa_id')
                                        ->whereColumn('pcp.numeroordencompra', 'ordencompra.numeroordencompra');
                                });
                        });
                })
                ->where(function ($w) {
                    $w->whereNull('cp.estado')
                        ->orWhereRaw('UPPER(TRIM(cp.estado)) != ?', ['ANULADA']);
                });
        });
    }

    /**
     * @param  Builder<\App\Models\Compras\Ordencompra>  $q
     */
    private function whereExistePago(Builder $q, bool $not = false): void
    {
        $method = $not ? 'whereNotExists' : 'whereExists';
        $q->{$method}(function ($e) {
            $e->selectRaw('1')
                ->from('proveedor_cuentacorriente as pcc')
                ->join('comprobante_proveedor as cp', 'cp.id', '=', 'pcc.comprobante_proveedor_id')
                ->where('pcc.pagoproveedor_id', '>', 0)
                ->where(function ($w) {
                    $w->whereColumn('cp.ordencompra_id', 'ordencompra.id')
                        ->orWhereExists(function ($pre) {
                            $pre->selectRaw('1')
                                ->from('precarga_comprobante_proveedor as pcp')
                                ->whereColumn('pcp.id', 'cp.precarga_comprobante_proveedor_id')
                                ->whereColumn('pcp.empresa_id', 'ordencompra.empresa_id')
                                ->whereColumn('pcp.numeroordencompra', 'ordencompra.numeroordencompra');
                        });
                });
        });
    }

    /**
     * @param  Builder<\App\Models\Compras\Ordencompra>  $q
     */
    private function whereExisteFacturaNumero(Builder $q, string $valor): void
    {
        $digitos = preg_replace('/\D+/', '', $valor) ?: $valor;
        $like = '%'.addcslashes($valor, '%_\\').'%';
        $q->whereExists(function ($e) use ($digitos, $like) {
            $e->selectRaw('1')
                ->from('precarga_comprobante_proveedor as pcp')
                ->whereColumn('pcp.empresa_id', 'ordencompra.empresa_id')
                ->whereColumn('pcp.numeroordencompra', 'ordencompra.numeroordencompra')
                ->where(function ($w) use ($digitos, $like) {
                    $w->where('pcp.numerocomprobante', 'like', '%'.$digitos.'%')
                        ->orWhere('pcp.numerocomprobante', 'like', $like);
                });
        })->orWhereExists(function ($e) use ($digitos, $like) {
            $e->selectRaw('1')
                ->from('comprobante_proveedor as cp')
                ->whereColumn('cp.ordencompra_id', 'ordencompra.id')
                ->where(function ($w) use ($digitos, $like) {
                    $w->where('cp.numerocomprobante', 'like', '%'.$digitos.'%')
                        ->orWhere('cp.numerocomprobante', 'like', $like);
                });
        });
    }

    /**
     * @param  Builder<\App\Models\Compras\Ordencompra>  $q
     */
    private function whereExisteComNumero(Builder $q, string $valor): void
    {
        $like = '%'.addcslashes($valor, '%_\\').'%';
        $q->whereExists(function ($e) use ($like, $valor) {
            $e->selectRaw('1')
                ->from('recepcion_proveedor as rp')
                ->whereColumn('rp.ordencompra_id', 'ordencompra.id')
                ->where('rp.tipo', Recepcion_Proveedor::TIPO_RECEPCION)
                ->where(function ($w) use ($like, $valor) {
                    $w->where('rp.numerorecepcion', 'like', $like);
                    if (ctype_digit($valor)) {
                        $w->orWhere('rp.id', (int) $valor)
                            ->orWhere('rp.anita_nro', (int) $valor);
                    }
                });
        });
    }

    /**
     * @param  Builder<\App\Models\Compras\Ordencompra>  $q
     */
    private function whereExistePagoNumero(Builder $q, string $valor): void
    {
        $like = '%'.addcslashes($valor, '%_\\').'%';
        $q->whereExists(function ($e) use ($like, $valor) {
            $e->selectRaw('1')
                ->from('proveedor_cuentacorriente as pcc')
                ->join('comprobante_proveedor as cp', 'cp.id', '=', 'pcc.comprobante_proveedor_id')
                ->join('pagoproveedor as pp', 'pp.id', '=', 'pcc.pagoproveedor_id')
                ->where('pcc.pagoproveedor_id', '>', 0)
                ->whereColumn('cp.ordencompra_id', 'ordencompra.id')
                ->where(function ($w) use ($like, $valor) {
                    $w->where('pp.numerotransaccion', 'like', $like);
                    if (ctype_digit($valor)) {
                        $w->orWhere('pp.id', (int) $valor)
                            ->orWhere('pp.numerotransaccion', (int) $valor);
                    }
                });
        });
    }

    /**
     * @param  list<int>  $ids
     * @return array<int, array{fecha: Carbon|null}>
     */
    private function ultimaHistoriaPorOc(array $ids): array
    {
        if ($ids === []) {
            return [];
        }
        $rows = Ordencompra_Historia::query()
            ->whereIn('ordencompra_id', $ids)
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->get(['ordencompra_id', 'fecha']);
        $out = [];
        foreach ($rows as $row) {
            $ocId = (int) $row->ordencompra_id;
            if (isset($out[$ocId])) {
                continue;
            }
            $out[$ocId] = [
                'fecha' => $row->fecha ? Carbon::parse($row->fecha) : null,
            ];
        }

        return $out;
    }

    /**
     * @param  Collection<int, Ordencompra>  $ocs
     * @return array<int, list<array{id: int, url_pdf: string, url_cargar_cxp: string}>>
     */
    private function facturasPorOc(Collection $ocs): array
    {
        $claves = [];
        foreach ($ocs as $oc) {
            $num = trim((string) $oc->numeroordencompra);
            $emp = (int) $oc->empresa_id;
            if ($num !== '' && $emp > 0) {
                $claves[$emp.'|'.$num] = (int) $oc->id;
            }
        }
        if ($claves === []) {
            return [];
        }
        $query = Precarga_Comprobante_Proveedor::query()
            ->whereNotNull('rutaalmacenamiento')
            ->where('rutaalmacenamiento', '!=', '')
            ->where(function ($q) {
                $q->whereNull('estado')
                    ->orWhereRaw('UPPER(TRIM(estado)) != ?', ['ANULADA']);
            })
            ->where(function ($q) use ($claves) {
                foreach (array_keys($claves) as $clave) {
                    [$emp, $num] = explode('|', $clave, 2);
                    $q->orWhere(function ($w) use ($emp, $num) {
                        $w->where('empresa_id', (int) $emp)->where('numeroordencompra', $num);
                    });
                }
            })
            ->orderByDesc('id');
        $out = [];
        foreach ($query->get(['id', 'empresa_id', 'numeroordencompra']) as $pre) {
            $clave = ((int) $pre->empresa_id).'|'.trim((string) $pre->numeroordencompra);
            if (! isset($claves[$clave])) {
                continue;
            }
            $ocId = $claves[$clave];
            $preId = (int) $pre->id;
            $out[$ocId][] = [
                'id' => $preId,
                'origen' => 'precarga',
                'url_pdf' => route('ordencompra_legajo_bandeja_factura_pdf', [
                    'id' => $ocId,
                    'precarga' => $preId,
                    'inline' => 1,
                ]),
                'url_cargar_cxp' => route('crear_comprobante_proveedor', ['precarga_id' => $preId]),
            ];
        }

        return $out;
    }

    /**
     * @param  list<int>  $ids
     * @return array<int, list<array{id: int, url_pdf: string}>>
     */
    private function comsPorOc(array $ids): array
    {
        if ($ids === []) {
            return [];
        }
        $rows = Recepcion_Proveedor::query()
            ->whereIn('ordencompra_id', $ids)
            ->where('tipo', Recepcion_Proveedor::TIPO_RECEPCION)
            ->where('estado', Recepcion_Proveedor::ESTADO_CONFIRMADA)
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->get(['id', 'ordencompra_id']);
        $out = [];
        foreach ($rows as $row) {
            $ocId = (int) $row->ordencompra_id;
            $recId = (int) $row->id;
            $out[$ocId][] = [
                'id' => $recId,
                'url_pdf' => route('ordencompra_legajo_bandeja_com_pdf', [
                    'id' => $ocId,
                    'recepcion' => $recId,
                    'inline' => 1,
                ]),
            ];
        }

        return $out;
    }

    /**
     * @param  list<int>  $precargaIds
     * @return array<int, list<int>>
     */
    /**
     * @param  list<int>  $ids
     * @param  list<int>  $precargaIds
     * @param  array<int, list<array{id: int}>>  $facturas
     * @return array<int, list<array{id: int, url: string}>>
     */
    private function comprobantesPorOc(array $ids, array $precargaIds, array $facturas): array
    {
        if ($ids === [] && $precargaIds === []) {
            return [];
        }
        $preAOc = [];
        foreach ($facturas as $ocId => $lista) {
            foreach ($lista as $fac) {
                $preAOc[(int) $fac['id']] = (int) $ocId;
            }
        }
        $query = Comprobante_Proveedor::query()
            ->where(function ($q) {
                $q->whereNull('estado')
                    ->orWhereRaw('UPPER(TRIM(estado)) != ?', ['ANULADA']);
            });
        $query->where(function ($q) use ($ids, $precargaIds) {
            if ($ids !== []) {
                $q->whereIn('ordencompra_id', $ids);
            }
            if ($precargaIds !== []) {
                $q->orWhereIn('precarga_comprobante_proveedor_id', $precargaIds);
            }
        });
        $out = [];
        foreach ($query->orderByDesc('id')->get(['id', 'ordencompra_id', 'precarga_comprobante_proveedor_id']) as $cp) {
            $ocId = (int) ($cp->ordencompra_id ?? 0);
            if ($ocId <= 0) {
                $ocId = $preAOc[(int) $cp->precarga_comprobante_proveedor_id] ?? 0;
            }
            if ($ocId <= 0) {
                continue;
            }
            $out[$ocId][] = [
                'id' => (int) $cp->id,
                'url' => route('editar_comprobante_proveedor', ['id' => (int) $cp->id]),
            ];
        }

        return $out;
    }

    /**
     * @param  list<int>  $comprobanteIds
     * @return array<int, array{id: int, url: string, etiqueta: string}>
     */
    private function pagosPorComprobante(array $comprobanteIds): array
    {
        if ($comprobanteIds === []) {
            return [];
        }
        $rows = Proveedor_Cuentacorriente::query()
            ->with(['pagoproveedores:id,tipocomprobante,letra,sucursal,numerotransaccion'])
            ->whereIn('comprobante_proveedor_id', $comprobanteIds)
            ->where('pagoproveedor_id', '>', 0)
            ->orderByDesc('id')
            ->get(['comprobante_proveedor_id', 'pagoproveedor_id']);
        $out = [];
        foreach ($rows as $row) {
            $cpId = (int) $row->comprobante_proveedor_id;
            if (isset($out[$cpId])) {
                continue;
            }
            $pago = $row->pagoproveedores;
            $pagoId = (int) $row->pagoproveedor_id;
            $out[$cpId] = [
                'id' => $pagoId,
                'url' => route('editar_pagoproveedor', ['id' => $pagoId]),
                'etiqueta' => $pago ? $pago->etiquetaComprobante() : ('OP #'.$pagoId),
            ];
        }

        return $out;
    }

    private function asignacionesPorPrecarga(array $precargaIds): array
    {
        if ($precargaIds === [] || ! Schema::hasTable('precarga_comprobante_proveedor_recepcion')) {
            return [];
        }
        $out = [];
        $rows = Precarga_Comprobante_Proveedor_Recepcion::query()
            ->whereIn('precarga_comprobante_proveedor_id', $precargaIds)
            ->get(['precarga_comprobante_proveedor_id', 'recepcion_proveedor_id']);
        foreach ($rows as $row) {
            $preId = (int) $row->precarga_comprobante_proveedor_id;
            $out[$preId][] = (int) $row->recepcion_proveedor_id;
        }

        return $out;
    }

    /**
     * @param  list<int>  $ids
     * @return array<int, array{estado: string, usuario: string, fecha: string, comentario: string}>
     */
    private function ultimaDecisionArbol(array $ids): array
    {
        if ($ids === []) {
            return [];
        }
        $nombreA = 'Aprobado';
        $nombreR = 'Rechazado';
        $rows = Arbolaprobacion_Movimiento::query()
            ->with('destinatariousuarios:id,nombre')
            ->whereIn('ordencompra_id', $ids)
            ->where('circuito_oc', OrdencompraLegajoGastronomiaSupport::CIRCUITO_SECTOR)
            ->whereIn('estado', [$nombreA, $nombreR])
            ->orderByDesc('fechaproceso')
            ->orderByDesc('id')
            ->get();
        $out = [];
        foreach ($rows as $row) {
            $ocId = (int) $row->ordencompra_id;
            if (isset($out[$ocId])) {
                continue;
            }
            $out[$ocId] = [
                'estado' => (string) $row->estado,
                'usuario' => (string) ($row->destinatariousuarios->nombre ?? ''),
                'fecha' => $row->fechaproceso ? Carbon::parse($row->fechaproceso)->format('d/m/Y H:i') : '',
                'comentario' => (string) ($row->observacion ?? ''),
            ];
        }

        return $out;
    }
}
