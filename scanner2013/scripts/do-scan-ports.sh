#!/bin/sh
# Speed up SSL/TLS probe by scanning for open ports
# Host with no filtered or closed ports can then be
# safely skipped in do-ssl-probes.sh
TMPLIST=/tmp/nmap.ip.$RANDOM

# Do IPv4 scan (ca 15 min with 20k hosts)
echo "PRESCAN: Doing IPv4 scanning at $(date)"
mysql -Bse 'SELECT DISTINCT rr_data FROM dns_data WHERE rr_type IN("A")' pc | egrep -v ':|^0.0.0.0$|^10\.|^172\.1[6789]\.|^172\.2[0-9]\.|^172\.[01]\.|^192\.168\.|^24.\.|^25.\.' > "$TMPLIST"
nmap -sS -p 25,80,443 -P0 -iL "$TMPLIST" -oG "$TMPLIST".G --randomize_hosts -n >/dev/null && php /root/scripts/nmap-import-scan.php "$TMPLIST".G

# Do IPv6 TCP connect() scan (ca 2 min per 1k hosts)
echo "PRESCAN: Doing IPv6 scanning at $(date)"
mysql -Bse 'SELECT DISTINCT rr_data FROM dns_data WHERE rr_type IN("AAAA")' pc | grep ':' |egrep -v '^fe80|^::|^3ffe:' > "$TMPLIST".6
nmap -sT -6 -p 25,80,443 -P0 -iL "$TMPLIST".6 -oG "$TMPLIST".6.G --randomize_hosts -n >/dev/null && php /root/scripts/nmap-import-scan.php "$TMPLIST".6.G

rm "$TMPLIST" "$TMPLIST".G
rm "$TMPLIST".6 "$TMPLIST".6.G

echo "PRESCAN: Done at $(date)"
