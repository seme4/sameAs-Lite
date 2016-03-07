create database testdb;
create user 'testuser'@'127.0.0.1' identified by 'testpass';
grant all privileges on *.* to 'testuser'@'127.0.0.1';
flush privileges;
