# Setup

## Create database (pk) and user (pk / protokoll)
    $ mysql -uroot -p < init-1-database.sql

If you already have a database and a database user you can skip this step.

## Create tables
    $ mysql -upk -pprotokoll pk < init-2-tables.sql

## Load initial entities
    $ mysql -upk -pprotokoll pk < init-3-entities.sql

## Create a bunch of services
    $ php pk-initialize-from-entities.php

(it's OK to run this script more than once; it's won't overwrite anything but service descriptions)

# Database design

This describes the overall database design.

## Table: entity

An entity is a company or an invidual with an internet prescense.  The internet presence is defined as a set of one or more public internet services.

## Table: services

A public facing internet service may be one of the following types:

+ **DNS**
    - A zone in DNS such as *example.com*
+ **EMAIL**
    - An email domain such as *example.com*
+ **HTTP**
    - A website such as *https://example.com* or *http://example.com*
+ **WEBMAIL**
    - A webmail service such as *https://webmail.example.com*
+ **FTP**
    - An FTP service such as *ftp://ftp.example.com*

An internet service is generally associated with one or more domain names.  A single website may consist of two hostnames such as *example.com* and *www.example.com* (nevermind in addition to supporting both HTTP and HTTPS protocols).  Or an FTP-site may be available under both *ftp.example.com* and *example.com*.

A zone in DNS only has a single domain associated with it, just like a domain used for email.  On the DNS plane they are however usually backed by more than a single hostname.

## Table: service_set

A zone in DNS is backed by one or more nameservers as pointed out by NS records.  The same goes for email domains, which in DNS is backed by zero or more MX records (with the A or AAAA for the zone being the fallback if no MXs are present).

A service set defines a group of hostnames[:port] that makes up the service.

## Table: service_vhosts

Each hostname associated with a service may point to one or more IP-addresses.  Each unique IP-address is considered a node, despite the fact that a single node may have both an IPv4 and an IPv6 address (or even multiple addresses from the same family).  Since there is no reliable way of detecting if two IP-addresses point to the same physical node we'll have to treat each IP-address as an independent node.

This table essentially keeps track of hostname <-> IP mappings.

## Table: nodes

This table contains IP-addresses and nothing more.  The table's primary key is used in the *service_vhosts* table, mostly to detect when hostnames changes their IP-addresses.  It exists to quickly be able to figure out what services are present on a given IP-address.

## Table: json

A store with JSON data.

## Table: logs

This table contains logs of changes in the database.

## Table: certs

A store with PEM-encoded X.509 certificates picked up from sslprobe runs.  Could be useful for later analysis.


## Table: service_vhost_certs

Mapping between certs and where they have been seen.
