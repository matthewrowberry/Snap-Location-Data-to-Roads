import requests
import pandas as pd
import json
from datetime import datetime, timedelta


points = pd.read_csv('refined_data.csv')

lon_lat_pairs = points[['longitude','latitude']].values.tolist()

#for simplifying at first:
#lon_lat_pairs = lon_lat_pairs[:10000]

i = 1

final = pd.DataFrame(columns=['datetime', 'latitude', 'longitude','original-ish'])
start = points.iloc[0]['datetime']
while i<len(lon_lat_pairs):
    
    end = points.iloc[i]['datetime']

    strung = str(lon_lat_pairs[i-1][0]) + "," + str(lon_lat_pairs[i-1][1]) + ";" + str(lon_lat_pairs[i][0]) + "," + str(lon_lat_pairs[i][1])
    url = "http://127.0.0.1:5000/route/v1/driving/" + strung + "?geometries=geojson&overview=full"
    response = requests.get(url).json()
    if(response["code"] != "Ok"):
        i += 1
        continue
    coordinateList = response["routes"][0]["geometry"]["coordinates"]

    start_dt = datetime.strptime(start, "%Y-%m-%d %H:%M:%S")
    end_dt = datetime.strptime(end, "%Y-%m-%d %H:%M:%S")

    total_seconds = (end_dt - start_dt).total_seconds()


    #4 second difference from start to end, 2 points, output has start, middle, middle, middle, end 
    interval_seconds = total_seconds/(len(coordinateList)-1)

    final.loc[len(final)] = [start_dt,coordinateList[0][1],coordinateList[0][0],1]

    j = 1
    while j<(len(coordinateList)-1):
        final.loc[len(final)] = [(start_dt + timedelta(seconds=j * interval_seconds)),coordinateList[j][1],coordinateList[j][0],0]
        j += 1
    # Create an empty DataFrame with columns

    start = end
    i += 1
    if(i%1000 == 0):
        print(f"iteration #{i}; final dataset length: {len(final)}")
    

final.to_csv('FULLSNAPV2.csv')