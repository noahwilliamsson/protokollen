#!/usr/bin/env python

import json
import sys

# http://docs.python.org/2/library/urlparse.html
from urlparse import urlparse


if len(sys.argv) < 2:
	print "Usage:", sys.argv[0], "<file.json>"
	raise SystemExit

data = open(sys.argv[1]).read()
har = json.loads(data)
domain_map = {}

for e in har['log']['entries']:
	url = e['request']['url']
	o = urlparse(url)

	# Create list at key if not already present
	domain_map[o.netloc] = domain_map.get(o.netloc, [])
	domain_map[o.netloc].append(url)

for d, list in domain_map.iteritems():
	print d
	for u in list:
		print "\t", u[:30]
