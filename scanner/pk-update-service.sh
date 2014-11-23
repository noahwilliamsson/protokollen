#!/bin/sh
#
# Protokollen - update single service
#

if [ $# -lt 7 ]; then
	echo "Usage: $0 <entity ID> <service ID> <service type> <service set ID> <protocol> <hostname> <port> [<protocol> <hostname> <port>, ...]";
	exit 1
fi

# Skip rows beginning with a comment
echo "$1"|grep -q '^[^0-9]' && exit 0

entId="$1"
svcId="$2"
svcType="$3"
svcGrpId="$4"
shift 4

cd "$(dirname $0)" || exit 1
loop=0
while [ $# -ge 3 ]; do
	loop=$(expr $loop + 1)
	protocol="$1"
	hostname="$2"
	port="$3"
	printf "%5d\t%s\t%s\t%s\n" "$svcId" "$svcType" "$hostname:$port ($protocol)"

	JSON="$protocol.$hostname.$port.$$".json

	# Update DNS records regardless of service
	test "$loop" = "1" && ./check_dns_address_records.py $@ > "$JSON" \
		&& ./pk-import-dns-addresses.php "$svcId" "$svcGrpId" "$JSON" \
		&& rm -f "$JSON"

	# Update DNSSEC status regardless of service
	test "$loop" = "1" && ./check_dnssec_status.py $@ > "$JSON" \
		&& ./pk-import-dnssec-status.php "$svcId" "$svcGrpId" "$JSON" \
		&& rm -f "$JSON"

	case "$protocol" in
	http )
		test "$loop" = "1" && ./check_www_primary.py $@ > "$JSON" \
			&& ./pk-import-www-primary.php "$svcId" "$svcGrpId" "$JSON" \
			&& rm -f "$JSON" && exit 0
		;;
	https )
		test "$loop" = "1" && ./check_www_primary.py $@ > "$JSON" \
			&& ./pk-import-www-primary.php "$svcId" "$svcGrpId" "$JSON" \
			&& rm -f "$JSON"
		../bin/sslprobe "$hostname" "$port" > "$JSON" 2>/dev/null \
			&& ./pk-import-sslprobe.php "$svcId" "$svcGrpId" "$hostname" "$JSON" \
			&& rm -f "$JSON"
		;;
	smtp )
		../bin/sslprobe "$hostname" "$port" > "$JSON" 2>/dev/null \
			&& ./pk-import-sslprobe.php "$svcId" "$svcGrpId" "$hostname" "$JSON" \
			&& rm -f "$JSON"
		;;
	dns )
		# Not yet implemented
		;;
	esac

	# Skip to next service host
	shift 3
done
