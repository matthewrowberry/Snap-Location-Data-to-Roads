<?php
require 'config.php';

$conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);


$stmt = $conn->prepare("SELECT * FROM locations WHERE datetime >= :start_date AND datetime < :end_date"); 
$stmt->execute([':start_date' => '2021-06-27 00:00:00.000000', ':end_date' => '2021-06-28 00:00:00.000000']);
$defaultData = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html>
    <head>
        <title>Mission Map</title>
        <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
        <style> #map { height: 600px; } </style>
    </head>
    <body>
        <div id="map"></div>
        <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
        <script>
            const initialPoints = <?= json_encode($defaultData) ?>;
            
            const map = L.map('map');

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; OpenStreetMap contributors'
            }).addTo(map);

            const latlngs = initialPoints.map(point => [
                parseFloat(point.latitude),
                parseFloat(point.longitude)
            ]);

            L.polyline(latlngs, {
                color: 'blue',
                weight: 4,
                opacity: 0.8
            }).addTo(map);

            if (latlngs.length >0) {
                const bounds = L.latLngBounds(latlngs);
                map.fitBounds(bounds, { padding: [50, 50]});
            }

            
            

        </script>

    </body>
</html>


