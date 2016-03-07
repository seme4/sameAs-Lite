use testdb;
create table if not exists mysqlstore (canon VARCHAR(256), symbol VARCHAR(256), PRIMARY KEY (symbol), INDEX(canon)) ENGINE = MYISAM;
insert into mysqlstore values("http://www.wikidata.org/entity/Q23436", "http://www.wikidata.org/entity/Q23436");
insert into mysqlstore values("http://www.wikidata.org/entity/Q23436", "http://dbpedia.org/resource/Embra");
insert into mysqlstore values("http://www.wikidata.org/entity/Q23436", "http://data.nytimes.com/edinburgh_scotland_geo");
insert into mysqlstore values("http://www.wikidata.org/entity/Q23436", "http://sws.geonames.org/2650225/");
insert into mysqlstore values("http://www.wikidata.org/entity/Q23436", "http://data.ordnancesurvey.co.uk/id/50kGazetteer/81482");

insert into mysqlstore values("http://www.wikidata.org/entity/Q220966", "http://www.wikidata.org/entity/Q220966");
insert into mysqlstore values("http://www.wikidata.org/entity/Q220966", "http://dbpedia.org/resource/Soton");
insert into mysqlstore values("http://www.wikidata.org/entity/Q220966", "http://data.nytimes.com/southampton_england_geo");
insert into mysqlstore values("http://www.wikidata.org/entity/Q220966", "http://data.ordnancesurvey.co.uk/id/50kGazetteer/218013");
insert into mysqlstore values("http://www.wikidata.org/entity/Q220966", "http://sws.geonames.org/1831142/");

insert into mysqlstore values("http://www.wikidata.org/entity/Q6940372", "http://www.wikidata.org/entity/Q6940372");
insert into mysqlstore values("http://www.wikidata.org/entity/Q6940372", "http://dbpedia.org/resource/Manc");
insert into mysqlstore values("http://www.wikidata.org/entity/Q6940372", "http://data.nytimes.com/manchester_england_geo");
insert into mysqlstore values("http://www.wikidata.org/entity/Q6940372", "http://data.ordnancesurvey.co.uk/id/50kGazetteer/155254");
insert into mysqlstore values("http://www.wikidata.org/entity/Q6940372", "http://sws.geonames.org/524894/");
