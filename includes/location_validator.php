<?php
class LocationValidator {
    
    // Calculate distance between two coordinates (Haversine formula)
    public static function calculateDistance($lat1, $lon1, $lat2, $lon2) {
        $earthRadius = 6371000; // meters
        
        $lat1 = deg2rad($lat1);
        $lon1 = deg2rad($lon1);
        $lat2 = deg2rad($lat2);
        $lon2 = deg2rad($lon2);
        
        $latDelta = $lat2 - $lat1;
        $lonDelta = $lon2 - $lon1;
        
        $angle = 2 * asin(sqrt(pow(sin($latDelta/2), 2) + 
            cos($lat1) * cos($lat2) * pow(sin($lonDelta/2), 2)));
        
        return $angle * $earthRadius; // distance in meters
    }
    
    // Validate if location is within task radius
    public static function validateTaskLocation($task_id, $current_lat, $current_lng, $conn) {
        // Get task location
        $sql = "SELECT location_lat, location_lng, radius_meters 
                FROM tasks WHERE id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "i", $task_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $task = mysqli_fetch_assoc($result);
        
        if (!$task || !$task['location_lat'] || !$task['location_lng']) {
            return [
                'valid' => true,
                'distance' => 0,
                'message' => 'Task location not set'
            ];
        }
        
        $distance = self::calculateDistance(
            $current_lat, $current_lng,
            $task['location_lat'], $task['location_lng']
        );
        
        $is_valid = $distance <= $task['radius_meters'];
        
        return [
            'valid' => $is_valid,
            'distance' => round($distance),
            'max_allowed' => $task['radius_meters'],
            'message' => $is_valid ? 
                'Location valid' : 
                "You are {$distance}m away. Must be within {$task['radius_meters']}m",
            'task_location' => [
                'lat' => $task['location_lat'],
                'lng' => $task['location_lng']
            ]
        ];
    }
    
    // Get address from coordinates (using Nominatim/OpenStreetMap)
    public static function getAddressFromCoordinates($lat, $lng) {
        $url = "https://nominatim.openstreetmap.org/reverse?format=json&lat={$lat}&lon={$lng}&zoom=18";
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_USERAGENT, 'SarathiVolunteerSystem/1.0');
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        $data = json_decode($response, true);
        
        if (isset($data['display_name'])) {
            return $data['display_name'];
        }
        
        return "Location: {$lat}, {$lng}";
    }
}
?>