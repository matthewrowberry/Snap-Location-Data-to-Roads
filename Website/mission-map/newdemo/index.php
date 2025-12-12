<?php
require '../config.php';

$conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

/* ---------- YOUR ORIGINAL DEFAULT RANGE ---------- */
$defaultStart = '2021-06-27 00:00:00';
$defaultEnd   = '2021-06-28 00:00:00';

$stmt = $conn->prepare(
    "SELECT * FROM path 
     WHERE datetime >= :start_date 
       AND datetime <  :end_date 
     ORDER BY datetime ASC"
);
$stmt->execute([
    ':start_date' => $defaultStart,
    ':end_date'   => $defaultEnd
]);
$defaultData = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mission Path Tracker</title>

    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Leaflet -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <!-- Flatpickr -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">

    <style>
        body {background:#f8f9fa;}
        .container-fluid {padding:20px;}
        #map {height:600px; border-radius:12px; box-shadow:0 4px 12px rgba(0,0,0,.1);}
        .controls {background:#fff; padding:20px; border-radius:12px; box-shadow:0 2px 8px rgba(0,0,0,.1); margin-bottom:20px;}
        .btn-primary{background:#0d6efd;border:none;}
        .btn-primary:hover{background:#0b5ed7;}
        .loading{display:none;color:#0d6efd;font-style:italic;}
    </style>
</head>
<body>
<div class="container-fluid">

    <!-- ==== CONTROLS ==== -->
    <div class="controls">
        <h4 class="mb-3">Mission Path Tracker</h4>
        <div class="row g-3 align-items-end">
            <div class="col-md-5">
                <label for="startDate" class="form-label">Start Date & Time</label>
                <input type="text" id="startDate" class="form-control" value="2021-06-27 00:00">
            </div>
            <div class="col-md-5">
                <label for="endDate" class="form-label">End Date & Time</label>
                <input type="text" id="endDate" class="form-control" value="2021-06-27 23:59">
            </div>
            <div class="col-md-2">
                <button id="updateMap" class="btn btn-primary w-100">Update Map</button>
            </div>
        </div>
        <div class="mt-2">
            <small class="text-muted">
                Showing path from <span id="rangeDisplay"></span>
            </small>
            <span id="loading" class="loading">Loading path...</span>
        </div>
    </div>

    <!-- ==== MAP ==== -->
    <div id="map"></div>
</div>

<!-- Scripts -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

<script>
    // ---------- MAP SETUP ----------
    const map = L.map('map');
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors'
    }).addTo(map);

    let polyline = null;

    // ---------- FLATPICKR ----------
    flatpickr("#startDate", {
        enableTime: true,
        dateFormat: "Y-m-d H:i",
        time_24hr: true,
        defaultDate: "2021-06-27 00:00"
    });
    flatpickr("#endDate", {
        enableTime: true,
        dateFormat: "Y-m-d H:i",
        time_24hr: true,
        defaultDate: "2021-06-27 23:59"
    });

    // ---------- INITIAL DATA ----------
    const initialPoints = <?= json_encode($defaultData) ?>;  // Already an array
    renderPath(initialPoints);
    updateRangeDisplay();

    // ---------- UPDATE BUTTON ----------
    document.getElementById('updateMap').addEventListener('click', () => {
        const start = document.getElementById('startDate').value;
        const end   = document.getElementById('endDate').value;

        if (!start || !end) {
            alert('Please select both start and end date/time.');
            return;
        }

        document.getElementById('loading').style.display = 'inline';
        fetchPathData(start, end);
    });

    // ---------- AJAX CALL ----------
    function fetchPathData(start, end) {
        fetch(`getdata.php?start=${encodeURIComponent(start)}&end=${encodeURIComponent(end)}`)
            .then(r => {
                if (!r.ok) throw new Error('Network error');
                return r.json();
            })
            .then(data => {
                document.getElementById('loading').style.display = 'none';

                // FIXED: Use data.points (the array), not data
                if (!data.points || !Array.isArray(data.points)) {
                    throw new Error('Invalid data format: expected "points" array');
                }

                renderPath(data.points);  // Pass the array
                updateRangeDisplay(start, end);
            })
            .catch(err => {
                document.getElementById('loading').style.display = 'none';
                console.error('Fetch error:', err);
                alert('Failed to load path: ' + err.message);
            });
    }

    // ---------- RENDER POLYLINE ----------
    function renderPath(points) {
        if (polyline) map.removeLayer(polyline);

        if (!points || points.length === 0) {
            alert('No location data found for the selected range.');
            map.setView([0, 0], 2);
            return;
        }

        const latlngs = points.map(p => [
            parseFloat(p.latitude),
            parseFloat(p.longitude)
        ]).filter(coord => !isNaN(coord[0]) && !isNaN(coord[1]));

        if (latlngs.length === 0) {
            alert('No valid coordinates in data.');
            return;
        }

        polyline = L.polyline(latlngs, {
            color: '#0d6efd',
            weight: 5,
            opacity: 0.9,
            lineCap: 'round',
            lineJoin: 'round'
        }).addTo(map);

        // Start marker
        L.circleMarker(latlngs[0], {radius: 6, color: 'green', fillOpacity: 1})
            .addTo(map)
            .bindTooltip('Start', {permanent: true, direction: 'right'});

        // End marker
        if (latlngs.length > 1) {
            L.circleMarker(latlngs[latlngs.length - 1], {radius: 6, color: 'red', fillOpacity: 1})
                .addTo(map)
                .bindTooltip('End', {permanent: true, direction: 'right'});
        }

        const bounds = L.latLngBounds(latlngs);
        map.fitBounds(bounds, {padding: [50, 50]});
    }

    // ---------- RANGE DISPLAY ----------
    function updateRangeDisplay(s = null, e = null) {
        const start = s || document.getElementById('startDate').value;
        const end   = e || document.getElementById('endDate').value;
        document.getElementById('rangeDisplay').textContent = `${start} to ${end}`;
    }
</script>
</body>
</html>