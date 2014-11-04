#!/bin/sh
#
# Protokollen - update script
#

cd "$(dirname $0)" || exit 1
./pk-list-services-as-tsv.php |while read entId svcId svcType hostname; do
	F="$hostname".json
	./check_http_primary.py "$hostname" > "$F" && ./pk-import-http-prefs.php "$F" && rm -f "$F"
	../bin/sslprobe "$hostname" > "$F" 2>/dev/null && ./pk-import-tls-statuses.php "$svcId" "$F" && rm -f "$F"
	../bin/sslprobe "www.$hostname" > "$F" 2>/dev/null && ./pk-import-tls-statuses.php "$svcId" "$F" && rm -f "$F"
done
