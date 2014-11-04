#!/bin/bash
# Inspect SSL/TLS versions, cipher support and supported standards

ulimit -c unlimited
cd /root/sslprobe/ssl
TMPFILE=/tmp/sslprobe.$$


# Update list of open ports so we can filter out
# large hosting providers whose customers aren't
# running SSL/TLS anyway
echo "SSLPROBE: Starting prescan at $(date)"
/root/scripts/do-scan-ports.sh

echo "SSLPROBE: Dumping hostname list to $TMPFILE at $(date)"
# mysql -Bse 'SELECT DISTINCT hostname FROM dns_data d LEFT JOIN dns_zones z ON d.zone_id=z.id AND d.version=z.version WHERE rr_type IN("A","AAAA") AND SUBSTRING_INDEX(rr_data,".",3) NOT IN ("46.30.211","194.9.94","195.74.38","194.9.95","212.97.132","141.8.224","79.99.7","82.98.86","188.95.227","83.168.226","89.221.250","62.116.143","62.116.181","216.8.179") ORDER BY REVERSE(hostname)' pc > $TMPFILE
mysql -Bse 'SELECT DISTINCT hostname FROM dns_data d LEFT JOIN dns_zones z ON d.zone_id=z.id AND d.version=z.version LEFT JOIN tcp_scans s ON d.rr_data=s.ip WHERE rr_type IN("A","AAAA") AND SUBSTRING_INDEX(rr_data,".",3) NOT IN ("46.30.211","194.9.94","195.74.38","194.9.95","212.97.132","141.8.224","79.99.7","82.98.86","188.95.227","83.168.226","89.221.250","62.116.143","62.116.181","216.8.179") AND (rr_type = "AAAA" OR s.port_443=1) ORDER BY REVERSE(hostname)' pc > $TMPFILE
echo "SSLPROBE: Probing hosts at $(date)"
< $TMPFILE xargs -P 64 -n 1 /root/sslprobe/sslprobe 2>/dev/null
rm "$TMPFILE"
echo "SSLPROBE: Commit repository at $(date)"
(git add . && git commit -m "$(date)" .) 
