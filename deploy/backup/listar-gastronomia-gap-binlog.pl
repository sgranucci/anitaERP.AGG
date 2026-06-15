#!/usr/bin/env perl
# Lista ventas y cobranzas del gap desde mysqlbinlog --verbose (audits JSON).
use strict;
use warnings;
use JSON::PP qw(decode_json);

my %ventas;
my %cobranzas;
my $buf = '';

while (my $line = <STDIN>) {
    next unless $line =~ /###   @8='(.+)'$/;
    my $raw = $1;
    $raw =~ s/\\"/"/g;
    $raw =~ s/\\u([0-9a-fA-F]{4})/chr(hex($1))/ge;
    $raw =~ s/\\\\/\\/g;
    my $data = eval { decode_json($raw) };
    next unless ref $data eq 'HASH';

    if (exists $data->{numerocomprobante} && exists $data->{puntoventa_id}) {
        my $id = $data->{id} // next;
        $ventas{$id} = $data;
    } elsif (exists $data->{cobranza_id} || (exists $data->{importe} && exists $data->{formapago_id})) {
        my $id = $data->{id} // next;
        $cobranzas{$id} = $data;
    }
}

print "venta_id\tfecha\tcreated_hint\tpuntoventa_id\tnumerocomprobante\ttotal\tcodigo\tcliente_id\n";
for my $id (sort { $a <=> $b } keys %ventas) {
    my $v = $ventas{$id};
    print join("\t", map { $_ // '' } (
        $id,
        $v->{fecha},
        $v->{fechajornada} // '',
        $v->{puntoventa_id},
        $v->{numerocomprobante},
        $v->{total},
        $v->{codigo},
        $v->{cliente_id},
    )), "\n";
}

print "\n# cobranzas\n";
print "cobranza_id\tfecha\timporte\tformapago_id\tcliente_id\n";
for my $id (sort { $a <=> $b } keys %cobranzas) {
    my $c = $cobranzas{$id};
    print join("\t", map { $_ // '' } (
        $id,
        $c->{fecha} // $c->{fechacobranza} // '',
        $c->{importe} // $c->{total} // '',
        $c->{formapago_id} // '',
        $c->{cliente_id} // '',
    )), "\n";
}

print STDERR "ventas=", scalar(keys %ventas), " cobranzas=", scalar(keys %cobranzas), "\n";
