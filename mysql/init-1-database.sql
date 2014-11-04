create database pk default charset utf8mb4;
grant all privileges on pk.* to 'pk'@'localhost' identified by 'protokoll';
grant all privileges on pk.* to 'pk'@'%' identified by 'protokoll';
