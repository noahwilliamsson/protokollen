#!/bin/sh
#
# Protokollen - update script
#

JOBS=20
cd "$(dirname $0)" || exit 1
./pk-list-services-as-tsv.php | xargs -L 1 -P "$JOBS" ./update-svc-host.sh
