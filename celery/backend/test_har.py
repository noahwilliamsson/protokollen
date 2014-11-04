# -*- coding: utf-8 -*-
# Coding style:
# http://legacy.python.org/dev/peps/pep-0008/
from __future__ import absolute_import
import sys
import tempfile

# http://www.angryobjects.com/2011/10/15/http-with-python-pycurl-by-example/
# http://pycurl.sourceforge.net/doc/unicode.html
import pycurl
from io import BytesIO

# http://docs.python.org/2/library/subprocess.html
import subprocess

from backend.celery import app


userAgentFmt = 'Mozilla/5.0 (Macintosh; Intel Mac OS X) AppleWebKit/534.34 (KHTML, like Gecko) Safari/534.34 (siteintegrity; {0})'

@app.task
def burn_request_v4(url, max_attempts):
	return burn_request(url, max_attempts, 4)

@app.task
def burn_request_v6(url, max_attempts):
	return burn_request(url, max_attempts, 6)

@app.task
def burn_request(url, max_attempts, ip_version):

	buf = BytesIO()
	c = pycurl.Curl()
	c.setopt(c.URL, url)
	c.setopt(c.WRITEFUNCTION, buf.write)
	c.setopt(c.FOLLOWLOCATION, 1)
	c.setopt(c.CONNECTTIMEOUT, 2)
	c.setopt(c.TIMEOUT, 5)

	if ip_version == 4:
		c.setopt(c.IPRESOLVE, c.IPRESOLVE_V4)
	elif ip_version == 6:
		c.setopt(c.IPRESOLVE, c.IPRESOLVE_V6)

	error = None
	attempt = 0
	for i in range(0, max_attempts):
		c.setopt(c.USERAGENT, userAgentFmt.format('burn request %d' % (i + 1)))
		try:
			attempt += 1
			c.perform()
			error = None
			break
		except pycurl.error as e:
			errno, errstr = e.args
			error = '{0} ({1})'.format(errstr, errno)
	c.close()
	buf.close()
	return error, attempt


@app.task
def fetch_url(url):

	buf = BytesIO()
	c = pycurl.Curl()
	c.setopt(c.URL, url)
	c.setopt(c.WRITEFUNCTION, buf.write)
	c.setopt(c.TIMEOUT, 5)
	c.setopt(c.CONNECTTIMEOUT, 2)
	c.setopt(c.FOLLOWLOCATION, 1)
	c.setopt(c.USERAGENT, userAgentFmt.format('fetch'))

	error = None
	charset = 'UTF-8'
	try:
		c.perform()
		_, _, charset = c.getinfo(c.CONTENT_TYPE).partition('charset=')
		if not len(charset):
			charset = 'UTF-8'
	except pycurl.error as e:
		errno, errstr = e.args
		error = '{0} ({1})'.format(errstr, errno)
	finally:
		c.close()

	binary_data = buf.getvalue()
	buf.close()

	return error, binary_data.decode(charset, 'ignore')


@app.task
def get_har(url):
	error = None
	har = ''
	try:
		with tempfile.NamedTemporaryFile(delete=False) as f:
			filename = f.name;
			f.close()

			args = ['bin/phantomjs', 'resources/har.js']
			args.append(url)
			args.append(filename)
			subprocess.check_output(args)
			har = open(filename).read().decode('utf-8')
			f.unlink(filename)

	except subprocess.CalledProcessError as e:
		error = "External command failed with exitcode {}, args: {}".format(e.returncode, e.cmd)

	return error, har
