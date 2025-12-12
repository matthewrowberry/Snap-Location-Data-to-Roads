#dependencies
import pandas as pd
from geopy.distance import great_circle
from datetime import datetime
#import data

loc = pd.read_csv('data.csv')
print(len(loc))


#remove rows where distance between them is less than x

#define function to do so
def simplify(df, lat_col='latitude', lon_col='longitude',threshold_ft=85,time_col='datetime'):
    df = df.sort_values(time_col)
    keep_rows = []

    i = 0
    while i<len(df):
        current_row = df.iloc[i]
        start_idx = i

        
        while i+1 <len(df):
            next_row = df.iloc[i+1]
            p1 = (current_row[lat_col],current_row[lon_col])
            p2 = (next_row[lat_col],next_row[lon_col])

            dist = great_circle(p1, p2).ft

            if dist >threshold_ft:
                break

            i+= 1
            
        keep_rows.append(df.iloc[start_idx])
        if start_idx != i:
            keep_rows.append(df.iloc[i])

        i+=1
        if(i%50000==0):
            print(i)

    return pd.DataFrame(keep_rows).reset_index(drop=True)

#run the function

loc = simplify(loc)

print(len(loc))


def removeOverspeeds(df, lat_col='latitude', lon_col='longitude',threshold_ft=35,time_col='datetime'):
    df = df.sort_values(time_col)
    keep_rows = []

    format_str = "%Y-%m-%d %H:%M:%S"
    keep_rows.append(df.iloc[0])
    current_row = df.iloc[0]
    i = 1
    while i<len(df):
        
        start_idx = i

        p1 = (current_row[lat_col],current_row[lon_col])
        datetime1 = current_row[time_col]
        dt1 = datetime.strptime(datetime1, format_str)

        
        next_row = df.iloc[i]
    
        p2 = (next_row[lat_col],next_row[lon_col])
        datetime2 = next_row[time_col]
        dt2 = datetime.strptime(datetime2,format_str)

        time_diff = dt2-dt1
        dist = great_circle(p1, p2).ft

        #if speed is less than 700 mph then keep it
        if(time_diff.total_seconds() > 0 and dist/(time_diff.total_seconds())<147):
            keep_rows.append(df.iloc[i])
            current_row = df.iloc[i]
        elif(time_diff.total_seconds()==0):
            keep_rows.append(df.iloc[i])
            current_row = df.iloc[i]

        i+=1
        if(i%50000==0):
            print(i)

    return pd.DataFrame(keep_rows).reset_index(drop=True)


loc = removeOverspeeds(loc)

print(len(loc))

loc.to_csv('refined_data.csv')