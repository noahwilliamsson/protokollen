#!/usr/bin/env python
#
# Protokollen - DNSSEC validation test
#
# Test beds:
#	 - sigok.verteiltesysteme.net
#	 - sigfail.verteiltesysteme.net
#
# -*- coding: utf-8 -*-
# vim: ts=4 sts=4 sw=4 noexpandtab
#
from __future__ import absolute_import
import sys
import re
import json
import struct
import time
import dns.name, dns.query, dns.resolver, dns.dnssec, dns.message, dns.rdatatype
from dns.exception import DNSException


def get_nameservers(zone):
	ns_set = set([])
	err = None
	try:
		r = resolver.query(zone, dns.rdatatype.NS)
		for rr in r:
			ns_set.add(rr.to_text())
	except dns.exception.Timeout:
		err = 'Timeout while fetching nameservers for zone'
	except dns.resolver.NoNameservers:
		err = 'Zone has no nameservers'
	except dns.resolver.NXDOMAIN:
		err = 'Zone not found'
	return err, ns_set

def resolve_nameservers(ns_set):
	err = None
	ip_set = set([])
	for ns in ns_set:
		try:
			rdtype = dns.rdatatype.A
			r = resolver.query(ns, rdtype, raise_on_no_answer=False)
			if r.rrset != None:
				for rr in r.rrset:
					ip_set.add(rr.to_text())
			rdtype = dns.rdatatype.AAAA
			r = resolver.query(ns, rdtype, raise_on_no_answer=False)
			if r.rrset != None:
				for rr in r.rrset:
					ip_set.add(rr.to_text())
		except dns.exception.Timeout:
			err = 'Timeout while resolving nameserver: {}'.format(ns)
		except dns.resolver.NXDOMAIN:
			err = 'Nameserver not found: {}'.format(ns)
	return err, ip_set

def get_zone_dnskeys(zone, ns_ip):
	err = None
	rr_dnskey = None
	rr_rrsig = None
	hostname = zone.to_text(omit_final_dot=True)
	try:
		rdtype = dns.rdatatype.DNSKEY
		rdclass = dns.rdataclass.IN
		q = dns.message.make_query(zone, rdtype, want_dnssec=True)
		r = dns.query.tcp(q, ns_ip, timeout=5)
		if r.rcode() != 0:
			err = 'DNSKEY query for zone {} failed with rcode: {}'.format(hostname, dns.rcode.to_text(r.rcode()))
		elif len(r.answer) != 2:
			err = 'No DNSKEY in zone {}'.format(hostname)
		else:
			rr_dnskey = r.find_rrset(r.answer, zone, rdclass, rdtype)
			rr_rrsig = r.find_rrset(r.answer, zone, rdclass, dns.rdatatype.RRSIG, rdtype)
	except dns.exception.Timeout:
		err = 'Timeout while querying zone {} for DNSKEY'.format(hostname)
	except EOFError:
		err = 'Network I/O error'
	return err, rr_dnskey, rr_rrsig

def get_ds_key_tag_from_ns(zone, ns_ip):
	err = None
	ds = set([])
	hostname = zone.to_text(omit_final_dot=True)
	try:
		rdtype = dns.rdatatype.DS
		rdclass = dns.rdataclass.IN
		q = dns.message.make_query(zone, rdtype, want_dnssec=True)
		r = dns.query.tcp(q, ns_ip, timeout=5)
		if r.rcode() != 0:
			err = 'DS query for zone {} failed with rcode: {}'.format(hostname, dns.rcode.to_text(r.rcode()))
		elif not r.answer:
			err = 'No DS in zone {}'.format(hostname)
		else:
			#rr_rrsig = r.find_rrset(r.answer, zone, rdclass, dns.rdatatype.RRSIG, rdtype)
			rrset_ds = r.find_rrset(r.answer, zone, rdclass, rdtype)
			for rr in rrset_ds:
				ds.add(rr.key_tag)
	except dns.exception.Timeout:
		err = 'Timeout while querying zone {} for DS'.format(hostname)
	except EOFError:
		err = 'Network I/O error'
	except:
		pass
	return err, ds

# Implementation credits:
# Adam Portier <adam_portier@cable.comcast.com>
# https://github.com/ajportier
def getKeyTag(rdata):
	'''Return the key_tag for the given DNSKEY rdata, as specified in RFC 4034.'''

	if rdata.algorithm == 1:
		return struct.unpack('!H', rdata.key[-3:-1])[0]
	key_str = struct.pack('!HBB', rdata.flags, rdata.protocol, rdata.algorithm) + rdata.key
	ac = 0
	for i in range(len(key_str)):
		b, = struct.unpack('B',key_str[i])
		if i & 1:
			ac += b
		else:
			ac += (b << 8)
	ac += (ac >> 16) & 0xffff
	return ac & 0xffff


if len(sys.argv) < 4:
	print('Usage: %s <protocol> <hostname> <port> [[<protocol> <hostname> <port>], ..]' % sys.argv[0])
	raise SystemExit

# Build set of hostnames to check
hostnames = set()
for i in xrange(1, len(sys.argv), 3):
	hostnames.add(sys.argv[i+1].lower())

resolver = dns.resolver.Resolver()
resolver.timeout = 5

res = {}
for hostname in hostnames:
	res[hostname] = {
		'dnskey': False,
		'ds': False,
		'error': None,
		'ns': [],
		'parent': None,
		'secure': False,
		'zone': None,
	}

	domain = dns.name.from_unicode(unicode(hostname, 'utf-8'))
	try:
		# Find zone for domain
		zone = dns.resolver.zone_for_name(hostname)
		res[hostname]['zone'] = zone.to_unicode(omit_final_dot=True).lower()
		#print "Found child zone",zone

		# Find parent zone
		labels = zone.labels[1:]
		parent_name = dns.name.from_text('.'.join(labels))
		parent = dns.resolver.zone_for_name(parent_name)
		res[hostname]['parent'] = parent.to_unicode(omit_final_dot=True).lower()
		#print "Found parent zone",parent
	except dns.exception.Timeout:
		err = 'Timeout while attempting to find zone for hostname'
		res[hostname]['error'] = err
		continue

	# Fetch list of nameservers for zone
	err, child_ns = get_nameservers(zone)
	if err:
		res[hostname]['error'] = err
		continue
	for ns in child_ns:
		res[hostname]['ns'].append(ns.strip('.'))
	res[hostname]['ns'].sort()
	#print "Found child NS",child_ns

	# Fetch list of nameservers for parent zone
	err, parent_ns = get_nameservers(parent)
	if err:
		res[hostname]['error'] = err
		continue
	#print "Found parent NS",parent_ns

	# Resolve nameservers for child and parent zones
	err, child_ns_ips = resolve_nameservers(child_ns)
	if not child_ns_ips:
		res[hostname]['error'] = err
		continue
	#print "Found child NS IP",child_ns_ips

	err, parent_ns_ips = resolve_nameservers(parent_ns)
	if not parent_ns_ips:
		res[hostname]['error'] = err
		continue
	#print "Found parent NS IP",parent_ns_ips

	# Fetch delegation signer (DS) for child zone
	zone_ds_key_tags = set([])
	i = 0
	for ns_ip in parent_ns_ips:
		err, key_tags = get_ds_key_tag_from_ns(zone, ns_ip)
		if not err:
			zone_ds_key_tags.update(key_tags)
			i += 1
			if i == 4:  # Enough
				break;

	# Fetch and validate DNSKEY for zone
	zone_dnskeys = None
	zone_key_tags = set([])
	zone_key_tags_expired = set([])
	i = 0
	for ns_ip in child_ns:
		err, dnskeys, rrsigs = get_zone_dnskeys(zone, ns_ip)
		if err:
			res[hostname]['error'] = err
			continue
		#print "Found DNSKEY at child"
		try:
			dns.dnssec.validate(dnskeys, rrsigs, {zone: dnskeys})
			zone_dnskeys = dnskeys
			for rr in dnskeys:
				zone_key_tags.add(getKeyTag(rr))
			for rr in rrsigs:
				t = int(time.strftime('%Y%m%d%H%M%S', time.gmtime()))
				if len(str(rr.expiration)) != 14:
					t = int(time.time())
				expires_in_secs = rr.expiration - t
				if expires_in_secs < 0:
					zone_key_tags_expired.add(rr.key_tag)
		except dns.dnssec.ValidationFailure as e:
			res[hostname]['error'] = str(e)
		i += 1
		if i == 4:  # Enough
			break;

	# Note precense of DS and DNSKEY
	key_tags = zone_ds_key_tags.copy()
	if not key_tags:
		res[hostname]['error'] = 'No DS at parent zone {}'.format(parent.to_text())
	else:
		res[hostname]['ds'] = True

	if not zone_key_tags:
		res[hostname]['error'] = 'No DNSKEY in zone {}'.format(zone.to_text())
	else:
		res[hostname]['dnskey'] = True

	# Validate DNSKEY against DS
	if zone_ds_key_tags and zone_key_tags:
		# Retain key tags present in both parent and child zones
		key_tags.intersection_update(zone_key_tags)
		# Subtract expired RRSIG key tags from child zone
		key_tags.difference_update(zone_key_tags_expired)
		if not key_tags:
			res[hostname]['error'] = 'No valid DNSKEYs ({} expired RRSIGs)'.format(len(zone_key_tags_expired))
			continue
	else:
		continue

	# Attempt to validate the domain too
	try:
		q = dns.message.make_query(domain, dns.rdatatype.ANY, want_dnssec=True)
		r = dns.query.tcp(q, ns, timeout=5)
		if r.rcode() != 0:
			name = domain.to_text(omit_final_dot=True)
			err = 'ANY query for domain {} failed with rcode {}'.format(name, dns.rcode.to_text(r.rcode()))
			res[hostname]['error'] = err
			continue
		elif len(r.answer) < 2:
			continue

		for rr in r.answer:
			if rr.rdtype == dns.rdatatype.RRSIG:
				continue
			rrsig = r.find_rrset(r.answer, domain, rr.rdclass, dns.rdatatype.RRSIG, rr.rdtype)
			dns.dnssec.validate(rr, rrsig, {zone: zone_dnskeys})

		if res[hostname]['ds'] and res[hostname]['dnskey']:
			res[hostname]['secure'] = True

	except dns.exception.Timeout:
		err = 'Timeout while resolving hostname'
		res[hostname]['error'] = err
	except dns.dnssec.ValidationFailure as e:
		res[hostname]['error'] = str(e)
	except EOFError:
		res[hostname]['error'] = 'Network I/O error'

print json.dumps(res, indent=2, sort_keys=True)
