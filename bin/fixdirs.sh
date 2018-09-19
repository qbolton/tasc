#!/bin/bash

#===========================================================
# 
#===========================================================

# the year argument
YEAR=$1

# uploads directory
UPLOADSDIR=/var/www/html/elombre/wordpress/wp-content/uploads
SITESDIR=${UPLOADSDIR}/sites
MONTHS="01 02 03 04 05 06 07 08 09 10 11 12"
APACHEGROUP=48
WEBUSERGROUP=503

cd ${SITESDIR}
# loop over sites in SITESDIR
for site in $(/bin/ls); do
  if [ -d ${site}/${YEAR} ]; then
    echo "processing site ${site}"
    cd ${site}/${YEAR}
    for month in $(echo ${MONTHS}); do
      if [ -d ${month} ]; then
        # check its group membership
        group=$(/usr/bin/stat -c %g ${month})
        if [ ${group} -eq ${APACHEGROUP} ]; then
          # delete the directory and replace it
          echo "deleting ${month} and replacing it"
          rm -fr ${month}; mkdir ${month}
        fi
      else
        echo "creating ${month}"
        # create the directory
        mkdir ${month}
      fi
    done
  fi
  # reset directory
  cd ${SITESDIR}
done
