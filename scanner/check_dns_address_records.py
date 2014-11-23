#!/usr/bin/env python
#
# Protokollen - count DNS record types for service group
#
# -*- coding: utf-8 -*-
# vim: ts=4 sts=4 sw=4 noexpandtab
#
from __future__ import absolute_import
import sys
import re
import json

import dns.resolver
from dns.exception import DNSException

if len(sys.argv) < 4:
	print('Usage: %s <protocol> <hostname> <port> [[<protocol> <hostname> <port>], ..]' % sys.argv[0])
	raise SystemExit

# Build list of schemes and hostnames
hostnames = set()
for i in xrange(1, len(sys.argv), 3):
	hostnames.add(sys.argv[i+1])

r = dns.resolver.Resolver()
r.timeout = 10
res = {}
res['hosts'] = len(hostnames)
res['cname'] = 0
res['a'] = 0
res['aaaa'] = 0
res['records'] = {}
for hostname in hostnames:
	a = []
	aaaa = []
	cname = []
	domain = dns.name.from_unicode(unicode(hostname, 'utf-8'))
	try:
		qr = r.query(domain, 'A')
		for ans in qr.response.answer:
			for i in ans.items:
				if i.rdtype == dns.rdatatype.A:
					res['a'] += 1
					a.append(i.address)
				elif i.rdtype == dns.rdatatype.CNAME:
					res['cname'] += 1
					cname.append(i.target.to_text(omit_final_dot=True))
	#except (Timeout, NXDOMAIN, YXDOMAIN, NoAnswer, NoNameservers) as e:
	except dns.resolver.NXDOMAIN as e:
		error = 'NXDOMAIN'
	except DNSException as e:
		error = e.__class__.__name__
	try:
		qr = r.query(domain, 'AAAA')
		for ans in qr.response.answer:
			for i in ans.items:
				if i.rdtype == dns.rdatatype.AAAA:
					res['aaaa'] += 1
					aaaa.append(i.address)
				elif i.rdtype == dns.rdatatype.CNAME:
					res['cname'] += 1
					cname.append(i.target.to_text(omit_final_dot=True))
	#except (Timeout, NXDOMAIN, YXDOMAIN, NoAnswer, NoNameservers) as e:
	except dns.resolver.NXDOMAIN as e:
		error = 'NXDOMAIN'
	except DNSException as e:
		error = e.__class__.__name__

	obj = { 'a': sorted(a), 'aaaa': sorted(aaaa), 'cname': sorted(cname) }
	res['records'][hostname] = obj


# Dump JSON with sorted keys so JSONs can be compared later
print json.dumps(res, indent=2, sort_keys=True)
