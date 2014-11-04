#!/usr/bin/env python
# -*- coding: utf-8 -*-
#
# Sample client to drive tests
#

import sys
# http://docs.python.org/2/library/urlparse.html
from urlparse import urlparse

from backend import test_dns, test_har, test_tls


if len(sys.argv) < 2:
	print('Usage: %s <url>' % sys.argv[0])
	raise SystemExit

url = sys.argv[1]
if url[:7] != 'http://' and url[:8] != 'https://':
	url = 'http://' + url

o = urlparse(url)
domain = o.netloc
print("* Testing domain '%s' and URL '%s'" % (domain, url))

ipv4_error, host, ipv4_rd = test_dns.lookup('A', domain)
if not ipv4_error:
	print("* Domain exists and has IPv4 addresses: %s" % (', '.join(ipv4_rd)))

ipv6_error, host, ipv6_rd = test_dns.lookup('AAAA', host)
if ipv4_error and ipv6_error:
	print("ERROR: DNS lookup error for domain '%s': %s" % (domain, error))
	raise SystemExit

# Find APEX domain
apex = None
domain_parts = domain.split('.')
while True:
	host = '.'.join(domain_parts)
	error, host, rd = test_dns.lookup('SOA', host)
	if not error:
		apex = host
		break
	
	domain_parts = domain_parts[1:]
	# TODO: Exploit data in Public Suffix List
	# http://publicsuffix.org/list/effective_tld_names.dat
	if len(domain_parts) < 2:
		break

if not apex:
	print("ERROR: Failed to find apex domain for host: %s" % domain)
	raise SystemExit

print("* Apex domain: %s" % apex)



# Some sites employ DDoS protection which expects clients to either follow
# redirects (HTTP) or reconnect if the first SSL/TLS connection is shutdown.
# Make two connection attempts here in an attempt to get ourselves whitelisted.
error, n = test_har.burn_request_v6.delay(url, 2).get(interval=0.1)
if error != None:
	print("burn_request: failed with error '%s' after %d attempts" % (error, n))
	raise SystemExit
else:
	print("burn_request: succeeded on attempt %d" % n)

#r = test_har.fetch_url.delay(url)
#error, html = r.get(interval=0.1)
#if error:
#	print("fetch_url: ", error)
#	raise SystemExit
#print html.encode('utf-8')

error, har = test_har.get_har.delay(url).get(interval=0.1)
if error:
	print("get_har: ", error)
print(har.encode('utf-8'))

o = urlparse(url)
error, data = test_tls.sslprobe.delay(o.netloc).get(interval=0.1)
if error:
	print("sslprobe: ", error)
print(data)
