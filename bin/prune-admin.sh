#!/bin/bash

#===========================================================
# Cache pruning stuff
#===========================================================
# get the date string
# date_string=$(date +%m%d%Y)
cache_date_string=$(date +%s -d '1 day ago')
spc_date_string=$(date +%s -d '30 day ago')

# change directory
cd /var/www/html/elombre/wordpress/wp-content/plugins/tasc/cache/
# remove cache files that are not from today (basically older than today)
for i in $(/bin/ls *.cache); do
  ds=$(echo $i | cut -d'-' -f1);
  if [ $ds -lt $cache_date_string ]; then
    # remove the cache file
    rm $i;
    #echo $i;
  fi
done

# remove spc files that are 30 days old or more
for i in $(/bin/ls *.spc); do
  ds=$(echo $i | cut -d'-' -f1);
  if [ $ds -lt $spc_date_string ]; then
    # remove the cache file
    rm $i;
    #echo $i;
  fi
done

#===========================================================
# Tascbot log pruning stuff
#===========================================================
# change directory
cd /var/www/html/elombre/wordpress/wp-content/plugins/tasc/logs/
for i in $(/bin/ls *.log); do
  # truncate log file
  echo -n > $i;
  #echo $i;
done

#===========================================================
# Post Image pruning stuff
# -- runs the prunebot PHP script that removes images
# -- and files from designated file system directories
#===========================================================
