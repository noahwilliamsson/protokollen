#!/bin/sh
#
# Protokollen - update single service
#

if [ $# -lt 7 ]; then
	echo "Usage: $0 <entity ID> <service ID> <service type> <service set ID> <protocol> <hostname> <port> [<protocol> <hostname> <port>, ...]";
	exit 1
fi

entId="$1"
svcId="$2"
svcType="$3"
svcSetId="$4"
shift 4

protocol="$1"
hostname="$2"
port="$3"

F="$hostname"."$port"."$$".json


cd "$(dirname $0)" || exit 1
printf "%5d\t%s\t%s\t%s\n" "$svcId" "$svcType" "$*"
case "$svcType" in
	HTTP )
		./check_preferred_http_host.py $@ > "$F" && ./pk-import-http-prefs.php "$svcId" "$F" && rm -f "$F"
		;;
	HTTPS )
		./check_preferred_http_host.py $@ > "$F" && ./pk-import-http-prefs.php "$svcId" "$F" && rm -f "$F"

		while [ $# -ge 3 ]; do
			protocol="$1"
			hostname="$2"
			port="$3"
			shift 3
			../bin/sslprobe "$hostname" "$port" > "$F" 2>/dev/null && ./pk-import-sslprobe.php "$svcSetId" "$F" && rm -f "$F"
		done

		;;
	SMTP )
		while [ $# -ge 3 ]; do
			protocol="$1"
			hostname="$2"
			port="$3"
			shift 3
			../bin/sslprobe "$hostname" "$port" > "$F" 2>/dev/null && ./pk-import-sslprobe.php "$svcSetId" "$F" && rm -f "$F"
		done
		;;
	DNS )
		;;
esac
