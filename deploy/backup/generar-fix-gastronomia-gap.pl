#!/usr/bin/env perl
# Genera SQL de corrección gastronomía gap: cobranza + caja_movimiento + caja_movimiento_cuentacaja.
use strict;
use warnings;

my (%cob, %cm, %cmc, %cob_est, %cm_est);

sub limpiar {
    my ($v) = @_;
    return undef unless defined $v;
    return undef if $v eq 'NULL';
    $v =~ s/^'(.*)'$/$1/;
    return $v;
}

sub sql_val {
    my ($v) = @_;
    return 'NULL' unless defined $v;
    return $v if $v =~ /^\d+(\.\d+)?$/;
    $v =~ s/'/\\'/g;
    return "'$v'";
}

sub sql_fecha {
    my ($v) = @_;
    my $x = limpiar($v);
    return 'NULL' unless defined $x;
    $x =~ s/:/-/g;
    return "'$x'";
}

sub sql_ts {
    my ($v) = @_;
    my $x = limpiar($v);
    return 'NULL' unless defined $x;
    return "FROM_UNIXTIME($x)";
}

sub capturar {
    my ($tabla, $en, $vals_ref) = @_;
    my @vals = @$vals_ref;
    return unless @vals;

    if ($tabla eq 'cobranza') {
        my $id = limpiar($vals[1]);
        return unless defined $id && $id >= 10163 && $id <= 10186;
        $cob{$id} = \@vals;
    } elsif ($tabla eq 'caja_movimiento') {
        my $cob_id = limpiar($vals[10]);
        return unless defined $cob_id && $cob_id >= 10163 && $cob_id <= 10186;
        $cm{$cob_id} = \@vals;
    } elsif ($tabla eq 'caja_movimiento_cuentacaja') {
        my $cm_id = limpiar($vals[2]);
        return unless defined $cm_id;
        $cmc{$cm_id} = \@vals;
    } elsif ($tabla eq 'cobranza_estado') {
        my $cob_id = limpiar($vals[2]);
        return unless defined $cob_id && $cob_id >= 10163 && $cob_id <= 10186;
        $cob_est{$cob_id} = \@vals;
    } elsif ($tabla eq 'caja_movimiento_estado') {
        my $cm_id = limpiar($vals[2]);
        return unless defined $cm_id;
        $cm_est{$cm_id} = \@vals;
    }
}

my $tabla = '';
my $en    = 0;
my @vals;

while (my $line = <STDIN>) {
    if ($line =~ /^### INSERT INTO `anitaERP`\.`(cobranza|caja_movimiento|caja_movimiento_cuentacaja|cobranza_estado|caja_movimiento_estado)`/) {
        capturar($tabla, $en, \@vals) if $en;
        $tabla = $1;
        $en    = 1;
        @vals  = ();
        next;
    }
    if ($en && $line =~ /^###   @(\d+)=(.*)$/) {
        $vals[$1] = $2;
        next;
    }
    if ($en && ($line =~ /^# at / || ($line =~ /^### INSERT INTO `/ && $line !~ /\Q$tabla\E/))) {
        capturar($tabla, $en, \@vals);
        $en = 0;
        $tabla = '';
        @vals = ();
    }
}
capturar($tabla, $en, \@vals) if $en;

print "START TRANSACTION;\n\n";

for my $id (sort { $a <=> $b } keys %cob) {
    my @v = @{$cob{$id}};
    print "-- cobranza $id\n";
    print "UPDATE cobranza SET\n";
    print '  empresa_id = ' . sql_val(limpiar($v[2])) . ",\n";
    print '  tipotransaccion_caja_id = ' . sql_val(limpiar($v[3])) . ",\n";
    print '  numerotransaccion = ' . sql_val(limpiar($v[4])) . ",\n";
    print '  fecha = ' . sql_fecha($v[5]) . ",\n";
    print '  cliente_id = ' . sql_val(limpiar($v[7])) . ",\n";
    print '  venta_id = ' . sql_val(limpiar($v[8])) . ",\n";
    print '  detalle = ' . sql_val(limpiar($v[9])) . ",\n";
    print "  estado = 'CONFIRMADA',\n";
    print '  monto = ' . sql_val(limpiar($v[11])) . ",\n";
    print '  cotizacion = ' . sql_val(limpiar($v[12])) . ",\n";
    print '  moneda_id = ' . sql_val(limpiar($v[13])) . ",\n";
    print '  usuario_id = ' . sql_val(limpiar($v[14])) . ",\n";
    print '  created_at = ' . sql_ts($v[16]) . ",\n";
    print '  updated_at = ' . sql_ts($v[17]) . "\n";
    print "WHERE id = $id;\n\n";
}

for my $cob_id (sort { $a <=> $b } keys %cm) {
    my @v = @{$cm{$cob_id}};
    my $cm_id = limpiar($v[1]);
    print "-- caja_movimiento $cm_id (cobranza $cob_id)\n";
    print "UPDATE caja_movimiento SET\n";
    print '  empresa_id = ' . sql_val(limpiar($v[2])) . ",\n";
    print '  tipotransaccion_caja_id = ' . sql_val(limpiar($v[3])) . ",\n";
    print '  numerotransaccion = ' . sql_val(limpiar($v[4])) . ",\n";
    print '  fecha = ' . sql_fecha($v[5]) . ",\n";
    print '  cliente_id = ' . sql_val(limpiar($v[8])) . ",\n";
    print '  cobranza_id = ' . sql_val(limpiar($v[10])) . ",\n";
    print '  venta_id = ' . sql_val(limpiar($v[11])) . ",\n";
    print '  detalle = ' . sql_val(limpiar($v[12])) . ",\n";
    print '  usuario_id = ' . sql_val(limpiar($v[13])) . ",\n";
    print '  created_at = ' . sql_ts($v[15]) . ",\n";
    print '  updated_at = ' . sql_ts($v[16]) . "\n";
    print "WHERE id = $cm_id;\n\n";

    if (my $cc = $cmc{$cm_id}) {
        my @c = @$cc;
        print "UPDATE caja_movimiento_cuentacaja SET\n";
        print '  fecha = ' . sql_fecha($c[3]) . ",\n";
        print '  monto = ' . sql_val(limpiar($c[5])) . ",\n";
        print '  cotizacion = ' . sql_val(limpiar($c[7])) . ",\n";
        print '  created_at = ' . sql_ts($c[10]) . ",\n";
        print '  updated_at = ' . sql_ts($c[11]) . "\n";
        print 'WHERE caja_movimiento_id = ' . $cm_id . ";\n\n";
    }

    if (my $ce = $cm_est{$cm_id}) {
        my @e = @$ce;
        print "UPDATE caja_movimiento_estado SET\n";
        print '  fecha = ' . sql_fecha($e[3]) . ",\n";
        print '  created_at = ' . sql_ts($e[7]) . ",\n";
        print '  updated_at = ' . sql_ts($e[8]) . "\n";
        print 'WHERE caja_movimiento_id = ' . $cm_id . ";\n\n";
    }
}

for my $cob_id (sort { $a <=> $b } keys %cob_est) {
    my @e = @{$cob_est{$cob_id}};
    print "UPDATE cobranza_estado SET\n";
    print '  fecha = ' . sql_fecha($e[3]) . ",\n";
    print '  usuario_id = ' . sql_val(limpiar($e[5])) . ",\n";
    print '  created_at = ' . sql_ts($e[8]) . ",\n";
    print '  updated_at = ' . sql_ts($e[9]) . "\n";
    print "WHERE cobranza_id = $cob_id;\n\n";
}

print "COMMIT;\n";
print STDERR 'cobranzas=', scalar(keys %cob), ' caja_movimiento=', scalar(keys %cm), "\n";
