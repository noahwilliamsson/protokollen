#!/usr/bin/env python
#
# Protokollen - probe a bunch of hosts for their preferred URL
#
# -*- coding: utf-8 -*-
# vim: ts=4 sts=4 sw=4 noexpandtab
#
from __future__ import absolute_import
import sys
import re
import json

# http://www.angryobjects.com/2011/10/15/http-with-python-pycurl-by-example/
# http://pycurl.sourceforge.net/doc/unicode.html
import pycurl
from io import BytesIO

# https://stackoverflow.com/questions/2087370/decode-html-entities-in-python-string
# NOTE: Requires Python 2
import HTMLParser

import dns.resolver
from dns.exception import DNSException

# https://docs.python.org/2/library/urlparse.html
# NOTE: For Python 3, use import urllib.parse
from urlparse import urlparse


userAgentFmt = 'Mozilla/5.0 (Macintosh; Intel Mac OS X) AppleWebKit/534.34 (KHTML, like Gecko) Safari/534.34 (Protokollen; {0})'


def check_url(url, resolve=None):
	buf = BytesIO()
	c = pycurl.Curl()
	c.setopt(c.URL, url)
	c.setopt(c.USERAGENT, userAgentFmt.format('www pref test'))
	c.setopt(c.ENCODING, 'gzip')
	c.setopt(c.FOLLOWLOCATION, 1)
	c.setopt(c.CONNECTTIMEOUT, 10)
	c.setopt(c.TIMEOUT, 10)
	if resolve:
		c.setopt(c.RESOLVE, resolve)
		hostname, port, ip = resolve[0].split(':', 2)
		port = int(port)
	else:
		ip = None
		parts = urlparse(url)
		hostname = parts.netloc
		if parts.port:
			port = int(parts.port)
		elif scheme == "http":
			port = 80
		elif scheme == "https":
			port = 443

	if 'openssl' in pycurl.version.lower():
		c.setopt(c.OPT_CERTINFO, 1)
	c.setopt(c.WRITEFUNCTION, buf.write)

	error = None
	status = -1
	last_hostname = None
	last_ip = None
	last_port = None
	last_url = None
	title = None

	content_type = 'text/plain';
	charset = 'iso-8859-1'
	http_charset = False
	try:
		c.perform()
		ct_header = c.getinfo(c.CONTENT_TYPE);
		if ct_header:
			content_type, _, charset = ct_header.partition(';')
			_, _, charset = charset.partition('=')
			charset = charset.strip().lower()
			if len(charset):
				http_charset = True
			else:
			    charset = 'iso-8859-1'

		last_ip = c.getinfo(c.PRIMARY_IP)
		last_url = c.getinfo(c.EFFECTIVE_URL)
		parts = urlparse(last_url)
		last_hostname = parts.netloc
		last_port = parts.port
		if parts.port:
			last_port = int(parts.port)
		elif parts.scheme == "http":
			last_port = 80
		elif parts.scheme == "https":
			last_port = 443

		status = c.getinfo(c.HTTP_CODE)
		if 'openssl' in pycurl.version.lower():
			certinfo = c.getinfo(c.INFO_CERTINFO)
			certinfo_dict = {}
			for entry in certinfo:
				certinfo_dict[entry[0]] = entry[1]
			#print certinfo_dict

	except pycurl.error as e:
		errno, errstr = e.args
		error = '{0} ({1})'.format(errstr, errno).strip()
		# Remove variable data from error messages
		# 'Connection timed out after 5001 milliseconds (28)'
		# 'Resolving timed out after 5001 milliseconds (28)'
		error = re.sub(r'(\w+ timed out).*(\s+\(\d+\))', r'\1\2', error)
	finally:
		c.close()

	binary_data = buf.getvalue()
	if error is None and content_type == 'text/html':
		# Attempt to decode using HTTP header first
		try:
			html = binary_data.decode(charset, 'ignore')
		except TypeError:
			try:
				html = binary_data.decode('utf-8', 'ignore')
				charset = 'utf-8'
			except TypeError:
				html = ''

		if http_charset is False:
			# Look for <meta charset=""> tag (HTML5)
			pattern = re.compile('<meta\s+charset=.([^"\']*)', flags=re.IGNORECASE)
			matches = pattern.search(html)
			if matches:
				charset = matches.group(1).strip().lower()
			else:
				# Look for <meta http-equiv="content-type">
				# <meta http-equiv="Content-Type" content="text/html; charset=utf-8"
				pattern = re.compile('<meta\s+http-equiv=.content-type.[^>]*content=.([^"\']*)', flags=re.IGNORECASE)
				matches = pattern.search(html)
				if not matches:
					pattern = re.compile('<meta\s+content=.([^"\']*).\s+http-equiv=.content-type.[^>]', flags=re.IGNORECASE)
					matches = pattern.search(html)
				if matches:
					content_type, _, charset = matches.group(1).partition(';')
					_, _, charset = charset.partition('=')
					charset = charset.strip().lower()
			if len(charset):
				html = binary_data.decode(charset, 'ignore')

		pattern = re.compile("<title>(.+?)</title>", flags=re.IGNORECASE|re.DOTALL)
		matches = pattern.search(html)
		if matches:
			title = matches.group(1).strip()
	buf.close()

	try:
		h = HTMLParser.HTMLParser()
		decoded_title = h.unescape(title)
		if decoded_title:
			title = decoded_title
	except:
		pass

	# Convert IDN hostname to Unicode
	hostname = dns.name.from_text(hostname).to_unicode(omit_final_dot=True)

	res = {
		'charset': charset,
		'contentType': content_type,
		'error': error,
		'hostname': hostname,
		'ip': ip,
		'lastHostname': last_hostname,
		'lastIp': last_ip,
		'lastPort': last_port,
		'location': last_url,
		'port': port,
		'status': status,
		'title': title,
		'url': url
	}

	return res


if len(sys.argv) < 4:
	print('Usage: %s <protocol> <hostname> <port> [[<protocol> <hostname> <port>], ..]' % sys.argv[0])
	raise SystemExit

# Build list of schemes and hostnames
schemes = set([])
sites = set()
for i in xrange(1, len(sys.argv), 3):
	scheme = sys.argv[i].lower()
	hostname = sys.argv[i+1]
	port = sys.argv[i+2]

	if scheme == 'http' and port != '80':
		hostname += ':' + port
	elif scheme == 'https' and port != '443':
		hostname += ':' + port

	schemes.add(scheme)
	sites.add((scheme, hostname))

r = dns.resolver.Resolver()
r.timeout = 10
final_res = {}
for scheme, hostport in sites:
	if not scheme in final_res:
		final_res[scheme] = []

	hostname, _, explicit_port = hostport.partition(':')
	error = None
	rd = []
	try:
		domain = dns.name.from_unicode(unicode(hostname, 'utf-8'))
		qr = r.query(domain, 'A')
		for rr in qr.rrset:
			rd.append(rr.to_text())
	#except (Timeout, NXDOMAIN, YXDOMAIN, NoAnswer, NoNameservers) as e:
	except dns.resolver.NXDOMAIN as e:
		error = 'NXDOMAIN'
	except DNSException as e:
		error = e.__class__.__name__

	try:
		domain = dns.name.from_unicode(unicode(hostname, 'utf-8'))
		qr = r.query(domain, 'AAAA')
		for rr in qr.rrset:
			rd.append(rr.to_text())
	except dns.resolver.NXDOMAIN as e:
		error = 'NXDOMAIN'
	except DNSException as e:
		error = e.__class__.__name__

	# Curl needs puny-coded hostnames
	hostname = domain.to_text(omit_final_dot=True)
	for ip in sorted(rd):
		url = scheme + '://' + hostname
		if explicit_port:
			port = explicit_port
			url += ':' + port
		elif scheme == 'http':
			port = "80"
		elif scheme == 'https':
			port = "443"
		url += '/'

		resolve = [ hostname + ':' + port + ':' + ip ]
		# Also use for redirects to other schemes on the same host
		if port != "443":
			resolve.append(hostname + ':443:' + ip)
		if port != "80":
			resolve.append(hostname + ':80:' + ip)

		#print "Trying URL '%s' with resolve opts '%s'" % (url, resolve)
		res = check_url(url, resolve)
		final_res[scheme].append(res)

obj = {}
for scheme in final_res:
	for res in final_res[scheme]:
		if res['error']:
			continue

		last_url = res['location']
		url = res['url']
		if not obj.has_key(last_url):
			obj[last_url] = {}
		obj[last_url][url] = res

final_res['preferred'] = None
if obj:
	# http:// will sort before https://
	primary_url = sorted(list(obj))[0]
	primary_title = None
	for url in obj[primary_url]:
	    res = obj[primary_url][url]
	    if res['location'] == primary_url:
			primary_title = res['title']
			break
	#print("Primary URL:%s, title:%s" % (primary_url, primary_title))
	final_res['preferred'] = {'url': primary_url, 'title': primary_title}

# Dump JSON with sorted keys so JSONs can be compared later
print json.dumps(final_res, indent=2, sort_keys=True)
