# -*- coding: utf-8 -*-
# Coding style:
# http://legacy.python.org/dev/peps/pep-0008/
from __future__ import absolute_import
import sys

# http://www.angryobjects.com/2011/10/15/http-with-python-pycurl-by-example/
# http://pycurl.sourceforge.net/doc/unicode.html
import pycurl
from io import BytesIO

# http://docs.python.org/2/library/subprocess.html
import subprocess

from backend.celery import app


userAgentFmt = 'Mozilla/5.0 (Macintosh; Intel Mac OS X) AppleWebKit/534.34 (KHTML, like Gecko) Safari/534.34 (siteintegrity; {0})'


@app.task
def sslprobe(host):
	error = None
	json = ''
	try:
		args = ['bin/sslprobe']
		args.append(host)
		data = subprocess.check_output(args)
	except subprocess.CalledProcessError as e:
		error = "External command failed with exitcode {}, args: {}".format(e.returncode, e.cmd)

	return error, data.decode('utf-8')
