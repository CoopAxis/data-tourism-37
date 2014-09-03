#!/bin/sh
# nom,lat,lon,adr1,adr2,adr3,adr4,tel,mail,web,intro,description,langues_nbr,langues,visite,activites_nbr,activites,services_nbr,services,url
awk '{print NR,",",$0}' ../../../cyrille/data/TouraineloirevalleyActivites-sans-sautsdeLignes.csv > temp.csv
curl http://localhost:8983/solr/tourainetourisme/update/csv \
  -F stream.file=/home/thomas/workspace/sources/data-tourism-37/thomas/solr/load/temp.csv \
  -F header=true -F fieldnames=id,name,,,,,,,,mail,,intro,description,,langues,,,,,,url \
  -F encapsulator=\" \
  -F escape=\\ \
  -F commit=true \
  -F f.langues.split=true \
  -F f.langues.separator=" " \
  -F f.visites.map=oui:true \
  -F f.visites.map=non:false