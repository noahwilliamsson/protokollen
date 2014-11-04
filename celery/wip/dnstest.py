#!/usr/bin/env python
#

import sys

import dns.resolver
from dns.exception import DNSException
import pprint


domain = sys.argv[1]

r = dns.resolver.Resolver()
r.timeout = 5
try:
	qr = r.query(domain, 'SOA')
	pprint.pprint(qr)
	print("---")
	for ardata in qr.response.answer:
		for rdata in ardata:
			print("-- one rdata --")
			print("type %d, domain %s" % (rdata.rdtype, rdata))
#except (Timeout, NXDOMAIN, YXDOMAIN, NoAnswer, NoNameservers) as e:
except DNSException as e:
	print("Exception: ")
	print(e.__class__.__name__)
