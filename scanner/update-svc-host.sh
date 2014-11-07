#!/bin/sh
#
# Protokollen - update single HTTP service host
#

if [ $# -ne 4 ]; then
	echo "Usage: $0 <entity ID> <service ID> <service type> <hostname>";
	exit 1
fi

entId="$1"
svcId="$2"
svcType="$3"
hostname="$4"
F="$hostname"."$$".json

cd "$(dirname $0)" || exit 1
printf "%s\t%s\n" "$svcType" "$hostname"
./check_http_primary.py "$hostname" > "$F" && ./pk-import-http-prefs.php "$F" && rm -f "$F"
../bin/sslprobe "$hostname" > "$F" 2>/dev/null && ./pk-import-tls-statuses.php "$svcId" "$F" && rm -f "$F"
../bin/sslprobe "www.$hostname" > "$F" 2>/dev/null && ./pk-import-tls-statuses.php "$svcId" "$F" && rm -f "$F"
