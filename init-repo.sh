#!/bin/sh
# Install sslprobe and phantomjs into bin/
(git clone https://github.com/noahwilliamsson/sslprobe src/sslprobe && cd src/sslprobe && make -j8 && ln sslprobe ../../bin)
(cd src && curl -L https://bitbucket.org/ariya/phantomjs/downloads/phantomjs-1.9.8-linux-x86_64.tar.bz2 | tar jxf - && ln phantomjs-1.9.8-linux-x86_64/bin/phantomjs ../bin)
