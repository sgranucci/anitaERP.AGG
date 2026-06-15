#!/usr/bin/env perl
# Lista ventas del gap desde mysqlbinlog --verbose.
use strict;
use warnings;

my $en_venta = 0;
my @vals;
my %ventas;

while (my $line = <STDIN>) {
    if ($line =~ /^### INSERT INTO `anitaERP`\.`venta`/) {
        $en_venta = 1;
        @vals = ();
        next;
    }

    if ($en_venta && $line =~ /^###   @(\d+)=(.*)$/) {
        $vals[$1] = $2;
        next;
    }

    if ($en_venta && ($line =~ /^# at / || $line =~ /^### INSERT INTO `/)) {
        if (@vals >= 12) {
            my $id = limpiar($vals[1]);
            $ventas{$id} = {
                id                  => $id,
                fecha               => limpiar($vals[2]),
                fechajornada        => limpiar($vals[3]),
                puntoventa_id       => limpiar($vals[5]),
                numerocomprobante   => limpiar($vals[6]),
                total               => limpiar($vals[12]),
                usuario_id          => limpiar($vals[16]),
                leyenda             => limpiar($vals[17]),
            };
        }
        $en_venta = 0;
        @vals = ();
        redo if $line =~ /^### INSERT INTO `anitaERP`\.`venta`/;
    }
}

sub limpiar {
    my ($v) = @_;
    return '' unless defined $v;
    $v =~ s/^NULL$//;
    $v =~ s/^'(.*)'$/$1/;
    return $v;
}

print "venta_id\tfecha\tfechajornada\tpuntoventa_id\tnumerocomprobante\ttotal\tusuario_id\tleyenda\n";
for my $id (sort { $a <=> $b } keys %ventas) {
    my $v = $ventas{$id};
    print join("\t", map { $v->{$_} // '' } qw(
        id fecha fechajornada puntoventa_id numerocomprobante total usuario_id leyenda
    )), "\n";
}

print STDERR 'ventas=', scalar(keys %ventas), "\n";
