# Snap Location Data to Roads (Mission Map)

I built a website to display the gps data that I had collected as I served a mission for the Church of Jesus Christ of Latter-Day Saints. This required removing extraneous points, removing unecessary points, and interpolating the gps points using OSRM (open source routing machine). Then I uploaded it to a SQL database and created a site to view the data.

## Demo
[Demo](mrowberry.com/mission-map/newdemo)

## Instructions for Build and Use

Steps to build and/or run the software:

1. Export GPS data with an ID, datetime, Latitude and Longtitude, name the file data.csv
2. run refine.py on the file
3. Set up OSRM for your region using a guide such as this one: https://medium.com/ivymobility-developers/open-source-routing-machine-43db9ae06fb7
4. Once set up, run full_matchmulti.py
5. Set up config.php file with your database's details
6. Use the upload.php endpoint on your server to upload all data
7. Navigate to the newdemo page to filter route by date

Instructions for using the software:

1. Navigate to /missionmap/newdemo on your web server

## Development Environment 

To recreate the development environment, you need the following software and/or libraries with the specified versions:

* OSRM
* Pandas
* GeoPy

## Useful Websites to Learn More

I found these websites useful in developing this software:

* [OSRM Setup Guide](https://medium.com/ivymobility-developers/open-source-routing-machine-43db9ae06fb7)
* [OSRM Documentation](https://project-osrm.org/)

## Future Work

The following items I plan to fix, improve, and/or add to this project in the future:

* [ ] Use different map engine to offer more customization
* [ ] Change database to a 6 hour system so that less rows need to be returned from database
* [ ] Fix bug with start and end points staying on map after changing the date
