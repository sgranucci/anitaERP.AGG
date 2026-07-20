<?php

namespace App\Models\Sueldos;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Ingresos y aportes por mes (IngresoAporteType) de un otro empleador (pluriempleo).
 */
class Siradig_Otro_Empleador_Mes_Sueldos extends Model
{
    protected $table = 'siradig_otro_empleador_mes_sueldos';

    protected $fillable = [
        'otro_empleador_id',
        'mes',
        'regimen',
        'obra_soc',
        'seg_soc',
        'seg_soc_anses',
        'seg_soc_cajas',
        'sind',
        'gan_brut',
        'ret_gan',
        'retrib_no_hab',
        'ajuste',
        'ajuste_rem_gravadas',
        'ajuste_rem_exe_no_alcanzadas',
        'exe_no_alc',
        'asign_fam',
        'int_prest_emp',
        'remun_judiciales',
        'indem_ley4003',
        'remun_ley19640',
        'remun_cct_petro',
        'cursos_semin',
        'indum_equip_emp',
        'sac',
        'horas_ext_gr',
        'horas_ext_ex',
        'mat_did',
        'movilidad',
        'viaticos',
        'otros_con_an',
        'bonos_prod',
        'fallos_caja',
        'con_sim_nat',
        'remun_exenta_ley27549',
        'suplem_partic_ley19101',
        'teletrabajo_exento',
        'no_ret_med_caut',
    ];

    protected $casts = [
        'otro_empleador_id' => 'integer',
        'mes' => 'integer',
    ];

    public function otroEmpleador(): BelongsTo
    {
        return $this->belongsTo(Siradig_Otro_Empleador_Sueldos::class, 'otro_empleador_id');
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(Siradig_Otro_Empleador_Mes_Detalle_Sueldos::class, 'otro_empleador_mes_id');
    }
}
