# Create database (pk) and user (pk / protokoll)
    $ mysql -uroot -p < init-1-database.sql

# Create tables
    $ mysql -upk -pprotokoll pk < init-2-tables.sql

# Load initial entities
    $ mysql -upk -pprotokoll pk < init-3-entities.sql

# Create a bunch of services
    $ php pk-initialize-from-entities.php
