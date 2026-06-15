#!/usr/bin/env perl
# Omite bloques de una tabla en un stream mysqldump (CREATE + INSERT).
# Uso: gunzip -c dump.sql.gz | perl filter-skip-table.pl padron_iibb_arba | mysql ...

use strict;
use warnings;

my $skip_table = shift @ARGV // '';
die "Uso: $0 <nombre_tabla>\n" unless $skip_table ne '';

my $skipping = 0;

while (my $line = <STDIN>) {
    if ($line =~ /^(?:DROP TABLE IF EXISTS|CREATE TABLE|LOCK TABLES) `$skip_table`/i
        || $line =~ /^INSERT INTO `$skip_table`/i) {
        $skipping = 1;
        next;
    }

    if ($skipping) {
        if ($line =~ /^CREATE TABLE `/ && $line !~ /`$skip_table`/) {
            $skipping = 0;
            print $line;
        } elsif ($line =~ /^UNLOCK TABLES;/ && $skipping) {
            $skipping = 0;
        }
        next;
    }

    print $line;
}
