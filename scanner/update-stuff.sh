#!/bin/sh
#
# Protokollen - update script
#

JOBS=20
cd "$(dirname $0)" || exit 1
./pk-update-services.php DNS | xargs -L1 -P "$JOBS" ./pk-update-service.sh
./pk-update-services.php HTTP | xargs -L1 -P "$JOBS" ./pk-update-service.sh
./pk-update-services.php HTTPS | xargs -L1 -P "$JOBS" ./pk-update-service.sh
./pk-update-services.php Webmail | xargs -L1 -P "$JOBS" ./pk-update-service.sh
./pk-update-services.php SMTP | xargs -L1 -P "$JOBS" ./pk-update-service.sh
