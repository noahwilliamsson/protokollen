Reading material 
================
I'm new to Python so here's stuff I've found useful so far. :)

http://docs.python.org/2/tutorial/modules.html
http://docs.python.org/2/tutorial/datastructures.html
http://legacy.python.org/dev/peps/pep-0008/ (coding style)

http://celery.readthedocs.org/en/latest/getting-started/first-steps-with-celery.html
http://celery.readthedocs.org/en/latest/getting-started/next-steps.html
http://celery.readthedocs.org/en/latest/configuration.html

Notes
-----
To disable generation of compiled .pyc, which clutters directories, do:

    $ export PYTHONDONTWRITEBYTECODE=1

Unicode
-------
Proper unicode handling is a somewhat challenging topic.  Be careful with
bytes, strings and their encoding.  Internally Python uses 2- or 4-bytes
Unicode to store strings.

In general, data sourced from the web (pycurl) or an external program's output
(Python's subprocess) should be assumed to be binary data (bytes / bytearray).

Use ```.decode('UTF-8', 'replace')``` to interpret a chunk of bytes as an UTF-8
string and replace any non-conforming characters with the Unicode REPLACEMENT
CHARACTER.

Use ```.encode('UTF-8')``` to convert a string from an arbitary encoding to
UTF-8.

http://docs.python.org/2/howto/unicode.html
http://docs.python.org/3/howto/unicode.html
http://pycurl.sourceforge.net/doc/unicode.html


INSTALLING
==========

Dependency overview (backend)
-----------------------------
Celery 3.x, a distributed task queue.

Redis 2.6 or newer, a key-value store, is required by Celery.

python-pycurl 7.19.3.
dnspython 1.10.

PhantomJS 1.9.8, a headless browser.
https://github.com/ariya/phantomjs

sslprobe, a SSL/TLS cipher suite prober
https://github.com/noahwilliamsson/sslprobe

Ubuntu 14.04 (Trusty Tahir) comes with:
- Celery 3.1.6
- python-redis 2.7.2
- python-pycurl 7.19.3
- redis-server 2.8.4
- phantomjs 1.9.0


Dependency overview (frontend)
------------------------------
Bootstrap 3.1.1
Django ?

Celery
------
Ubuntu 14.04:

    $ sudo apt-get install python-celery python-celery-redis

Mac OS X:

    $ sudo easy_install celery[redis]

Celery backend dependencies
---------------------------
Ubuntu 14.04:

    $ sudo apt-get install redis-server python-curl python-dnspython

Mac OS X:

    $ (cd src&&curl http://download.redis.io/releases/redis-2.8.7.tar.gz|tar zx)
    $ (cd src/redis-2.8.7 && make -j8)
    $ sudo easy_install pycurl dnspython

External programs
-----------------
* PhantomJS
* sslprobe

Ubuntu 14.04:

    $ sudo apt-get install phantomjs libssl-dev git
    $ (cd src && git clone https://github.com/noahwilliamsson/sslprobe.git)
    $ (cd src/sslprobe && make && cp sslprobe ../../bin && make clean)

Mac OS X:
    $ curl -LO https://bitbucket.org/ariya/phantomjs/downloads/phantomjs-1.9.8-macosx.zip
    $ (cd src && unzip ../phantomjs-1.9.8-macosx.zip)
    $ cp src/phantomjs-1.9.8-macosx/bin/phantomjs bin
    $ (cd src && git clone https://github.com/noahwilliamsson/sslprobe.git)
    $ (cd src/sslprobe && make && cp sslprobe ../../bin && make clean)


RUNNING
=======

Redis
------
Redis is used by Celery as a message broker and result store results.
It needs to be running for Celery to work.

At this moment we don't bother with configuring Redis at all.

Ubuntu 14.04:

    Redis should be running already, if not:
    $ sudo service redis-server start

Mac OS X:

    $ (cd redis-2.8.7 && src/redis-server)

Celery
------
The following commands starts a celery worker node with the package 'backend'
and the logging level set to info.

Celery will exit if the connection to Redis is lost so ideally Celery should
be run as a daemon in a way that it's restarted automatically if it exits.
 
    $ cd celery
    $ celery worker -A backend -l info --autoreload

Starting celery should yield something similar to:

     -------------- celery@nuc v3.1.6 (Cipater)
    ---- **** -----
    --- * ***  * -- Linux-3.13.0-16-generic-x86_64-with-Ubuntu-14.04-trusty
    -- * - **** ---
    - ** ---------- [config]
    - ** ---------- .> broker:      redis://localhost:6379//
    - ** ---------- .> app:         mybackend:0x7fefc990afd0
    - ** ---------- .> concurrency: 4 (prefork)
    - *** --- * --- .> events:      OFF (enable -E to monitor this worker)
    -- ******* ----
    --- ***** ----- [queues]
     -------------- .> celery           exchange=celery(direct) key=celery
    
    [tasks]
      . backend.har.burn_request
      . backend.har.fetch_url
      . backend.har.get_har
      . backend.tls.sslprobe
    
    [2014-03-08 03:09:52,798: INFO/MainProcess] Connected to redis://localhost:6379//
    [2014-03-08 03:09:52,805: INFO/MainProcess] mingle: searching for neighbors
    [2014-03-08 03:09:53,811: INFO/MainProcess] mingle: all alone
    [2014-03-08 03:09:53,824: WARNING/MainProcess] celery@nuc ready.

Sample client
--------------
A sample client is available:

    $ ./client.py


TEST FLOW
=========
Given a domain, or a domain extracted from a URL, to test, do things in the
following order.

1. DNS lookups
2. Initial test for available services
    + HTTP: Check if website exists
    + HTTPS: Check if website exists
    + SMTP: Check if STARTTLS supported
    + FTP: Check if STARTTLS supported
    + DNSSEC: Check if domain has DNSSEC records
3. More rigorous test of each service
    + HTTP (website)
        - HTTP: Generate HAR
        - HTTP: Test for HTTPS support
        - HTTP: Check privacy issues
    + HTTPS
        - HTTPS: Check SSL/TLS protocols and cipher suites
        - HTTPS: Validate certificates
    + SMTP
        - SMTP: Check SSL/TLS protocols and cipher suites
        - SMTP: Validate certificates
    + FTP
        - FTP: Check SSL/TLS protocols and cipher suites
        - FTP: Validate certificates
    + DNS
        - DNS: Inspect DNSSEC


1. DNS lookups
--------------
Given an apex domain, attempt to lookup DNS information for common services:
- NS records
- MX records
- A and AAAA for the apex domain
- A and AAAA for www.<apex>
- A and AAAA for ftp.<apex>

2. Check available services
---------------------------
Determine if services are running by twice attempting to:
   - For HTTP: A complete GET request, following any Location:
   - For HTTPS: A complete GET request, following any Location:
   - For SMTP: EHLO, MAIL FROM:, RCPT TO: <>, RSET

Some DDoS protection services, notably Arbor Networks, attempts to filter out
unwanted traffic by injecting arbitrary redirects using Location: headers or
Javascript on HTTP traffic to see if clients follow them.  If they do, they're
whitelisted for some time.  For HTTPS traffic, it's been observed that the 
initial connection is shutdown on the first attempt.  The client is expected
to reconnect again, because that's what most browsers do in order to handle
broken SSL/TLS stacks on servers that disconnect when they receive unexpected
ClientHello packets from clients.

3a: HTTP: Generate HAR (HTTP Archive)
-------------------------------------
Use PhantomJS to generate a HAR.  The HAR will contain URLs for all resources
required to display the site's main page.

4a: HTTP: Extract domains and test URLs for HTTPS support
---------------------------------------------------------
Create a tree of domains found in the HAR, which each domain containing a
list of resources associated with the domain in question.

Test each domain and see if it supports HTTPS by requesting associated
resource over HTTPS instead of over HTTP, ignoring any resources that were
server over HTTPS in the first place.

If N (say three) resources for a given domain fail to be fetched over HTTPS,
skip testing any further HTTPS resources under the same domain.

Check each returned resource and attempt to fuzzy match it against the
HTTP resource in the HAR.  For a site that has its content available over
both HTTP and HTTPS, it's expected that:
- Content-Type: headers match
- the HTTP status code is 200 (redirects may happen though)
- the size of the returned HTML are within some bounds (content may change)
- Any <title> in HTML document matches(?)
- For images or other binary data, contents are the same

Tests for domains and resources may be tested in parallel.

From test results, compile a list of domains and resources that can be
successfully served over both HTTP and HTTPS.
