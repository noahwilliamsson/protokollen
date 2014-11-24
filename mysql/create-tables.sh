#!/bin/sh
mysqldump -upk -pprotokoll -d pk browser_profiles certs entities entity_sources entity_tags json logs reports services svc_group_map svc_groups tags test_dns_addresses test_dnssec_statuses test_sslprobe_certs test_sslprobes test_www_prefs > init-2-tables.sql
mysqldump -upk -pprotokoll --skip-extended-insert --skip-quick pk entities tags entity_sources entity_tags > init-3-entities.sql
