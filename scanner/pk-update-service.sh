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

cd "$(dirname $0)" || exit 1
while [ $# -ge 3 ]; do
	protocol="$1"
	hostname="$2"
	port="$3"
	printf "%5d\t%s\t%s\t%s\n" "$svcId" "$svcType" "$hostname:$port ($protocol)"

	JSON="$protocol.$hostname.$port.$$".json

	case "$protocol" in
	http )
		./check_preferred_http_host.py $@ > "$JSON" \
			&& ./pk-import-http-prefs.php "$svcId" "$JSON" \
			&& rm -f "$JSON"
		;;
	https )
		./check_preferred_http_host.py $@ > "$JSON" \
			&& ./pk-import-http-prefs.php "$svcId" "$JSON" \
			&& rm -f "$JSON"
		../bin/sslprobe "$hostname" "$port" > "$JSON" 2>/dev/null \
			&& ./pk-import-sslprobe.php "$svcSetId" "$JSON" \
			&& rm -f "$JSON"
		;;
	smtp )
		../bin/sslprobe "$hostname" "$port" > "$JSON" 2>/dev/null \
			&& ./pk-import-sslprobe.php "$svcSetId" "$JSON" \
			&& rm -f "$JSON"
		;;
	dns )
		;;
	esac

	# Skip to next service host
	shift 3
done
