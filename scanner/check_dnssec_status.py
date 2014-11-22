#!/usr/bin/env python
#
# Protokollen - DNSSEC validation test
#
# Test beds:
#     - sigok.verteiltesysteme.net
#     - sigfail.verteiltesysteme.net
#
# -*- coding: utf-8 -*-
# vim: ts=4 sts=4 sw=4 noexpandtab
#
from __future__ import absolute_import
import sys
import re
import json
import dns.name, dns.query, dns.resolver, dns.dnssec, dns.message, dns.rdatatype
from dns.exception import DNSException

if len(sys.argv) < 4:
	print('Usage: %s <protocol> <hostname> <port> [[<protocol> <hostname> <port>], ..]' % sys.argv[0])
	raise SystemExit

# Build set of hostnames to check
hostnames = set()
for i in xrange(1, len(sys.argv), 3):
	hostnames.add(sys.argv[i+1])

r = dns.resolver.Resolver()
#r.nameservers = ['194.14.105.31']
r.timeout = 10

hostname_zone_nslist = []
for hostname in hostnames:
	domain = dns.name.from_unicode(unicode(hostname, 'utf-8'))
	try:
		# Find zone
		zone = dns.resolver.zone_for_name(hostname)
	except dns.exception.Timeout:
		print "TIMEOUT attempting to find zone name for hostname", hostname
		continue
	
	# Fetch list of nameservers
	try:
		nslist = set([])
		ns_res = dns.resolver.query(zone, dns.rdatatype.NS)
		for ns_rr in ns_res.rrset:
			# Resolve NS domains to IP addresses
			ns = ns_rr.to_text()
			res = dns.resolver.query(ns, dns.rdatatype.A, raise_on_no_answer=False)
			for rr in res:
				nslist.add(rr.to_text())
			#res = dns.resolver.query(ns, dns.rdatatype.AAAA, raise_on_no_answer=False)
			for rr in res:
				nslist.add(rr.to_text())
		hostname_zone_nslist.append((domain, zone, nslist))
	except dns.exception.Timeout:
		print "TIMEOUT in NS query for zone", zone

for (domain, zone, nslist) in hostname_zone_nslist:
	zone_q = dns.message.make_query(zone, dns.rdatatype.DNSKEY, want_dnssec=True)
	for ns in nslist:
		try:
			print "Attempting to query NS records for zone", zone, "at NS", ns
			res = dns.query.tcp(zone_q, ns, timeout=5)
			if res.rcode() != 0:
				print "DNSKEY query for zone", zone, "failed with rcode", res.rcode()
				continue
			elif len(res.answer) != 2:
				print "Expected 2 answers RRs for DNSKEY query for zone", zone, "but got", len(res.answer)
				continue
			# Attempt to validate answer against zone key
			dnskey_rrset = res.answer[0]
			rrsigset = res.answer[1]
			dns.dnssec.validate(dnskey_rrset, rrsigset, {zone: dnskey_rrset})
			print "DNSSEC OK for zone", zone, "at NS", ns
		except dns.exception.Timeout:
			print "TIMEOUT in DNSKEY query for zone", zone
		except dns.dnssec.ValidationFailure as e:
			print "DNSSEC ERROR for zone",zone,"at NS",ns,"(",e,")"
		except Exception as e:
			print "UNKNOWN ERROR:", e, "::", type(e), "::", e.args

		#if domain == zone:
		#	continue

		try:
			# Attempt to validate the domain too
			q = dns.message.make_query(domain, dns.rdatatype.A, want_dnssec=True)
			res = dns.query.tcp(q, ns, timeout=5)
			print "A query for domain",domain,"returned",len(res.answer),"answers"
			# XXX - Figure out how to handly multiple answers RRs reliably
			if res.rcode() != 0:
				print "DNSKEY query for zone", zone, "failed with rcode", res.rcode()
				continue
			elif len(res.answer) < 2:
				print "Expected 2 answers RRs for A query for domain", domain, "but got", len(res.answer)
				continue
			for rr in res.answer:
				print "   RR:",rr
			rr = res.answer[0]
			sig = res.answer[1]
			dns.dnssec.validate(rr, sig, {zone: dnskey_rrset})
			print "DNSSEC OK for domain", domain, "in zone", zone, "at NS", ns
		except dns.exception.Timeout:
			print "TIMEOUT in A query for domain", domain, "in zone", zone, "at NS", ns
		except dns.dnssec.ValidationFailure as e:
			print "DNSSEC ERROR for A query for domain", domain, "in zone", zone, "at NS", ns, "(", e, ")"
	print "Done probing domain", domain, "in zone", zone
	print ""
