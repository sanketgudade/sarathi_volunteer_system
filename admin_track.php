<?php
require_once 'config/config.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: adminlogin.php");
    exit();
}

$conn = getDBConnection();
$admin_id = $_SESSION['admin_id'];

// Get all active volunteers with their latest location
$track_sql = "SELECT v.id, v.full_name, v.email, v.total_points,
                     vl.latitude, vl.longitude, vl.timestamp,
                     va.status as attendance_status,
                     t.title as current_task,
                     v.last_location_update
              FROM volunteers v
              LEFT JOIN volunteer_locations vl ON v.id = vl.volunteer_id 
                  AND vl.timestamp = (SELECT MAX(timestamp) FROM volunteer_locations WHERE volunteer_id = v.id)
              LEFT JOIN volunteer_attendance va ON v.id = va.volunteer_id 
                  AND DATE(va.check_in_time) = CURDATE()
              LEFT JOIN tasks t ON v.id = t.assigned_to 
                  AND t.status IN ('assigned', 'in_progress')
              WHERE v.status = 'active'
              ORDER BY v.full_name";
$track_result = mysqli_query($conn, $track_sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Live Volunteer Tracking - Sarathi Admin</title>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <style>
        .tracking-container {
            display: grid;
            grid-template-columns: 300px 1fr;
            gap: 20px;
            height: calc(100vh - 100px);
        }
        #map {
            height: 100%;
            border-radius: var(--radius);
        }
        .volunteer-list {
            overflow-y: auto;
            padding: 10px;
        }
        .volunteer-item {
            background: white;
            padding: 15px;
            margin-bottom: 10px;
            border-radius: var(--radius-sm);
            border: 1px solid var(--border);
            cursor: pointer;
        }
        .volunteer-item:hover {
            border-color: var(--primary);
        }
        .volunteer-item.active {
            border-left: 4px solid var(--primary);
        }
        .location-status {
            font-size: 0.8rem;
            color: var(--text-light);
        }
        .location-status.online {
            color: var(--secondary);
        }
        .location-status.offline {
            color: var(--danger);
        }
    </style>
</head>
<body>
    <div class="admin-header">
        <div class="container header-content">
            <a href="admin_panel.php" class="admin-logo">
                <div class="admin-logo-icon">
                    <i class="fas fa-map-marker-alt"></i>
                </div>
                <div class="admin-logo-text">
                    Live <span>Tracking</span>
                </div>
            </a>
        </div>
    </div>
    
    <div class="container">
        <div class="tracking-container">
            <!-- Volunteer List -->
            <div class="volunteer-list">
                <h3>Volunteers</h3>
                <?php while($volunteer = mysqli_fetch_assoc($track_result)): 
                    $isOnline = strtotime($volunteer['timestamp']) > (time() - 300); // 5 minutes
                ?>
                <div class="volunteer-item" data-id="<?php echo $volunteer['id']; ?>"
                     data-lat="<?php echo $volunteer['latitude']; ?>"
                     data-lng="<?php echo $volunteer['longitude']; ?>"
                     onclick="focusVolunteer(<?php echo $volunteer['id']; ?>)">
                    <div style="display: flex; justify-content: space-between;">
                        <strong><?php echo htmlspecialchars($volunteer['full_name']); ?></strong>
                        <span class="location-status <?php echo $isOnline ? 'online' : 'offline'; ?>">
                            <i class="fas fa-circle"></i>
                            <?php echo $isOnline ? 'Live' : 'Offline'; ?>
                        </span>
                    </div>
                    <div style="font-size: 0.9rem; color: var(--text-light);">
                        <?php echo htmlspecialchars($volunteer['email']); ?>
                    </div>
                    <?php if($volunteer['current_task']): ?>
                    <div style="margin-top: 5px;">
                        <i class="fas fa-tasks"></i> 
                        <?php echo htmlspecialchars(substr($volunteer['current_task'], 0, 30)); ?>...
                    </div>
                    <?php endif; ?>
                    <?php if($volunteer['timestamp']): ?>
                    <div style="font-size: 0.8rem; margin-top: 5px;">
                        Last seen: <?php echo date('h:i A', strtotime($volunteer['timestamp'])); ?>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endwhile; ?>
            </div>
            
            <!-- Map -->
            <div id="map"></div>
        </div>
    </div>
    
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        let map;
        let markers = {};
        
        // Initialize map
        function initMap() {
            map = L.map('map').setView([20.5937, 78.9629], 5); // Default India center
            
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap contributors'
            }).addTo(map);
            
            // Add volunteer markers
            <?php 
            mysqli_data_seek($track_result, 0);
            while($volunteer = mysqli_fetch_assoc($track_result)): 
                if ($volunteer['latitude'] && $volunteer['longitude']):
            ?>
            addVolunteerMarker(
                <?php echo $volunteer['id']; ?>,
                <?php echo $volunteer['latitude']; ?>,
                <?php echo $volunteer['longitude']; ?>,
                "<?php echo addslashes($volunteer['full_name']); ?>",
                "<?php echo addslashes($volunteer['current_task'] ?? 'No task'); ?>"
            );
            <?php 
                endif;
            endwhile; 
            ?>
        }
        
        function addVolunteerMarker(id, lat, lng, name, task) {
            const marker = L.marker([lat, lng], {
                icon: L.divIcon({
                    html: `<div style="background: var(--primary); color: white; 
                           border-radius: 50%; width: 40px; height: 40px; 
                           display: flex; align-items: center; justify-content: center;">
                           <i class="fas fa-user"></i></div>`,
                    className: 'volunteer-marker',
                    iconSize: [40, 40]
                })
            }).addTo(map);
            
            marker.bindPopup(`
                <strong>${name}</strong><br>
                Task: ${task}<br>
                <button onclick="showRoute(${lat}, ${lng})" 
                        style="margin-top: 10px; padding: 5px 10px; background: var(--primary); 
                               color: white; border: none; border-radius: 4px; cursor: pointer;">
                    <i class="fas fa-route"></i> Show Route
                </button>
            `);
            
            markers[id] = marker;
        }
        
        function focusVolunteer(id) {
            const volunteerItem = document.querySelector(`.volunteer-item[data-id="${id}"]`);
            const lat = parseFloat(volunteerItem.dataset.lat);
            const lng = parseFloat(volunteerItem.dataset.lng);
            
            if (!isNaN(lat) && !isNaN(lng)) {
                map.setView([lat, lng], 15);
                markers[id].openPopup();
            }
            
            // Highlight selected volunteer
            document.querySelectorAll('.volunteer-item').forEach(item => {
                item.classList.remove('active');
            });
            volunteerItem.classList.add('active');
        }
        
        function showRoute(lat, lng) {
            // Open Google Maps with directions
            window.open(`https://www.google.com/maps/dir/?api=1&destination=${lat},${lng}`);
        }
        
        // Refresh locations every 30 seconds
        setInterval(() => {
            fetch('get_live_locations.php')
                .then(response => response.json())
                .then(data => {
                    data.forEach(volunteer => {
                        if (markers[volunteer.id] && volunteer.latitude && volunteer.longitude) {
                            markers[volunteer.id].setLatLng([volunteer.latitude, volunteer.longitude]);
                        }
                    });
                });
        }, 30000);
        
        // Initialize on load
        document.addEventListener('DOMContentLoaded', initMap);
    </script>
</body>
</html>
<?php mysqli_close($conn); ?>