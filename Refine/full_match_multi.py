# --------------------------------------------------------------
#  FAST, ORDER-PRESERVING, RETRY-ROBUST, CHUNKED OSRM SNAPPING
# --------------------------------------------------------------
import requests
import pandas as pd
from datetime import datetime, timedelta
from concurrent.futures import ThreadPoolExecutor, as_completed
import time
import numpy as np
import threading
from typing import List, Tuple
from tqdm import tqdm  # pip install tqdm

# ------------------------------------------------------------------
# CONFIGURATION
# ------------------------------------------------------------------
MAX_CONCURRENT = 6
MAX_RETRIES    = 5
BASE_TIMEOUT   = 15
CSV_IN         = 'refined_data.csv'
CSV_OUT        = 'FULLSNAPV2.csv'
WRITE_BUFFER_SIZE = 1000  # Write every N segments (tune for performance)

# ------------------------------------------------------------------
# LOAD INPUT
# ------------------------------------------------------------------
points = pd.read_csv(CSV_IN)
lon_lat_pairs = points[['longitude', 'latitude']].values.tolist()
datetimes = points['datetime'].values

# Buffer: stores rows until ready to write in order
results_buffer: List[List[dict]] = [None] * (len(lon_lat_pairs) - 1)
buffer_lock = threading.Lock()  # Protect write index

# ------------------------------------------------------------------
# REUSABLE SESSION PER THREAD
# ------------------------------------------------------------------
_session_local = threading.local()

def get_session() -> requests.Session:
    if not hasattr(_session_local, "session"):
        s = requests.Session()
        adapter = requests.adapters.HTTPAdapter(
            pool_connections=MAX_CONCURRENT,
            pool_maxsize=MAX_CONCURRENT,
            max_retries=0,
        )
        s.mount("http://", adapter)
        _session_local.session = s
    return _session_local.session

# ------------------------------------------------------------------
# CORE ROUTING + INTERPOLATION
# ------------------------------------------------------------------
def fetch_with_retry(
    start_coord: Tuple[float, float],
    end_coord:   Tuple[float, float],
    start_dt_str: str,
    end_dt_str:   str,
) -> List[dict]:

    start_dt = datetime.strptime(start_dt_str, "%Y-%m-%d %H:%M:%S")
    end_dt   = datetime.strptime(end_dt_str,   "%Y-%m-%d %H:%M:%S")
    total_seconds = (end_dt - start_dt).total_seconds()

    url = (
        f"http://127.0.0.1:5000/route/v1/driving/"
        f"{start_coord[0]},{start_coord[1]};{end_coord[0]},{end_coord[1]}"
        f"?geometries=geojson&overview=full"
    )

    for attempt in range(1, MAX_RETRIES + 1):
        try:
            resp = get_session().get(url, timeout=BASE_TIMEOUT)
            resp.raise_for_status()
            data = resp.json()

            if data.get("code") != "Ok":
                raise RuntimeError(f"OSRM error: {data.get('code')}")

            coords = data["routes"][0]["geometry"]["coordinates"]
            if len(coords) < 2:
                return []

            interval = total_seconds / (len(coords) - 1)
            rows: List[dict] = []

            rows.append({
                "datetime":     start_dt,
                "latitude":     coords[0][1],
                "longitude":    coords[0][0],
                "original-ish": 1,
            })

            for j in range(1, len(coords) - 1):
                interp_dt = start_dt + timedelta(seconds=j * interval)
                rows.append({
                    "datetime":     interp_dt,
                    "latitude":     coords[j][1],
                    "longitude":    coords[j][0],
                    "original-ish": 0,
                })

            return rows

        except Exception as exc:
            if attempt == MAX_RETRIES:
                print(f"Failed after {MAX_RETRIES} attempts: {start_coord} to {end_coord} | {exc}")
                return []
            backoff = (2 ** attempt) + np.random.random()
            print(f"Retry {attempt}/{MAX_RETRIES} | {exc} | wait {backoff:.2f}s")
            time.sleep(backoff)

    return []

# ------------------------------------------------------------------
# WORKER
# ------------------------------------------------------------------
def process_segment(args: Tuple[int, str, str, Tuple[float,float], Tuple[float,float]]
                   ) -> Tuple[int, List[dict]]:
    idx, start_dt_str, end_dt_str, start_c, end_c = args
    rows = fetch_with_retry(start_c, end_c, start_dt_str, end_dt_str)
    return idx, rows

# ------------------------------------------------------------------
# CHUNKED CSV WRITER (writes in order)
# ------------------------------------------------------------------
def write_buffered_rows(csv_file, buffer, start_idx):
    if not buffer:
        return
    df = pd.DataFrame(buffer)
    df['datetime'] = pd.to_datetime(df['datetime'])
    df = df.sort_values('datetime')
    df.to_csv(csv_file, mode='a', header=False, index=False)

# Open CSV and write header ONCE
with open(CSV_OUT, 'w', newline='') as f:
    pd.DataFrame(columns=['datetime', 'latitude', 'longitude', 'original-ish']).to_csv(f, index=False)

# ------------------------------------------------------------------
# BUILD TASKS
# ------------------------------------------------------------------
tasks = [
    (i - 1, datetimes[i-1], datetimes[i], lon_lat_pairs[i-1], lon_lat_pairs[i])
    for i in range(1, len(lon_lat_pairs))
]

# ------------------------------------------------------------------
# MULTITHREADED EXECUTION + CHUNKED WRITE
# ------------------------------------------------------------------
print(f"Starting {len(tasks):,} segments → {CSV_OUT} (chunked write every {WRITE_BUFFER_SIZE})")

write_buffer = []
next_to_write = 0  # Index of next segment we can write

with ThreadPoolExecutor(max_workers=MAX_CONCURRENT) as executor:
    future_to_idx = {executor.submit(process_segment, t): t[0] for t in tasks}

    # Use tqdm for nice progress
    for future in tqdm(as_completed(future_to_idx), total=len(tasks), desc="Routing", unit="seg"):
        idx = future_to_idx[future]
        try:
            buf_idx, rows = future.result()
            results_buffer[buf_idx] = rows or []  # Store even if empty
        except Exception as exc:
            print(f"Future error (seg {idx+1}): {exc}")
            results_buffer[idx] = []

        # --- CHUNKED WRITE: flush when we have a contiguous block ---
        with buffer_lock:
            # Add completed segment to write buffer
            if results_buffer[idx]:
                write_buffer.extend(results_buffer[idx])

            # Check if we can write up to current idx
            while next_to_write < len(results_buffer) and results_buffer[next_to_write] is not None:
                segment_rows = results_buffer[next_to_write]
                if segment_rows:
                    write_buffer.extend(segment_rows)
                results_buffer[next_to_write] = None  # Free memory
                next_to_write += 1

                # Flush when buffer is large enough
                if len(write_buffer) >= WRITE_BUFFER_SIZE * 10:  # ~10k rows
                    with open(CSV_OUT, 'a', newline='') as f:
                        write_buffered_rows(f, write_buffer, next_to_write)
                    write_buffer.clear()

    # --- FINAL FLUSH ---
    if write_buffer:
        with open(CSV_OUT, 'a', newline='') as f:
            write_buffered_rows(f, write_buffer, next_to_write)

# ------------------------------------------------------------------
# DONE
# ------------------------------------------------------------------
failed = sum(1 for x in results_buffer if x is None or len(x) == 0)
print(f"\nDONE! Output: {CSV_OUT}")
print(f"   Segments processed: {len(tasks):,}")
print(f"   Failed/skipped: {failed:,}")