#!/usr/bin/env python
#
# -*- coding: utf-8 -*-
# vim: ts=4 sts=4 sw=8 noexpandtab
#
# Protokollen - determine http/https support and preferred URL
#
# Usage: ./check_http_primary.py <domain>
#        ./check_http_primary.py skane.se
#
# Coding style:
# http://legacy.python.org/dev/peps/pep-0008/
from __future__ import absolute_import
import sys
import re
import json

# http://www.angryobjects.com/2011/10/15/http-with-python-pycurl-by-example/
# http://pycurl.sourceforge.net/doc/unicode.html
import pycurl
from io import BytesIO

userAgentFmt = 'Mozilla/5.0 (Macintosh; Intel Mac OS X) AppleWebKit/534.34 (KHTML, like Gecko) Safari/534.34 (siteintegrity; {0})'


def check_url(url):
	buf = BytesIO()
	c = pycurl.Curl()
	c.setopt(c.URL, url)
	c.setopt(c.USERAGENT, userAgentFmt.format('www pref test'))
	c.setopt(c.ENCODING, 'gzip')
	c.setopt(c.FOLLOWLOCATION, 1)
	c.setopt(c.CONNECTTIMEOUT, 10)
	c.setopt(c.TIMEOUT, 10)
	#c.setopt(c.SSL_VERIFYPEER, 0)
	if 'openssl' in pycurl.version.lower():
		c.setopt(c.OPT_CERTINFO, 1)
	c.setopt(c.WRITEFUNCTION, buf.write)

	error = None
	status = -1
	last_url = None
	title = None

	content_type = 'text/plain';
	charset = 'iso-8859-1'
	http_charset = False
	try:
		c.perform()
		content_type, _, charset = c.getinfo(c.CONTENT_TYPE).partition(';')
		_, _, charset = charset.partition('=')
		charset = charset.strip().lower()
		if len(charset):
			http_charset = True
		else:
		    charset = 'iso-8859-1'

		last_url = c.getinfo(c.EFFECTIVE_URL)
		status = c.getinfo(c.HTTP_CODE)
		if 'openssl' in pycurl.version.lower():
			certinfo = c.getinfo(c.INFO_CERTINFO)
			certinfo_dict = {}
			for entry in certinfo:
				certinfo_dict[entry[0]] = entry[1]
			#print certinfo_dict

	except pycurl.error as e:
		errno, errstr = e.args
		error = '{0} ({1})'.format(errstr, errno)
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
			if not matches:
				# Look for <meta http-equiv="content-type">
				pattern = re.compile('<meta\s+http-equiv=.content-type.[^>]*content=.([^"\']*)', flags=re.IGNORECASE)
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

	res = {
		'charset': charset,
		'content_type': content_type,
		'error': error,
		'location': last_url,
		'status': status,
		'title': title,
		'url': url
	}

	return res


if len(sys.argv) < 2:
	print('Usage: %s <domain>' % sys.argv[0])
	raise SystemExit

# Zap leading www. from hostname to get apex domain
domain = sys.argv[1].strip('.')
if domain.startswith('www.'):
	domain = domain[4:]

# Build list of schemes and hostnames
schemes = ['http', 'https']
hostnames = [domain, 'www.' + domain]

final_res = {}
for scheme in schemes:
	final_res[scheme] = []
	for hostname in hostnames:
		url = scheme + '://' + hostname + '/'
		res = check_url(url)
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

final_res['domain'] = domain
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
