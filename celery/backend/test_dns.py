# -*- coding: utf-8 -*-
# Coding style:
# http://legacy.python.org/dev/peps/pep-0008/
from __future__ import absolute_import
import sys

import dns.resolver
from dns.exception import DNSException

from backend.celery import app

@app.task
def lookup(rrtype, domain):
	r = dns.resolver.Resolver()
	r.timeout = 5

	error = None
	rd = []
	try:
		qr = r.query(domain, rrtype)
		for ardata in qr.response.answer:
			for rdata in ardata:
				rd.append(rdata)
	#except (Timeout, NXDOMAIN, YXDOMAIN, NoAnswer, NoNameservers) as e:
	except dns.resolver.NXDOMAIN as e:
		error = 'NXDOMAIN'
	except DNSException as e:
		error = e.__class__.__name__

	return error, domain, rd
