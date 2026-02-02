<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

// Include database connectie
require_once __DIR__ . '/db_connect.php';

try {
    // Check of er een specifiek werkjaar is opgegeven via GET parameter
    $requestedWerkjaar = isset($_GET['werkjaar']) ? (int)$_GET['werkjaar'] : null;
    
    // Bepaal werkjaar
    $today = new DateTime();
    $currentYear = (int)$today->format('Y');
    $currentMonth = (int)$today->format('m');
    
    // Als er een werkjaar is opgegeven, filter op dat werkjaar
    // Anders alle activiteiten ophalen (voor werkjaar dropdown)
    if ($requestedWerkjaar !== null) {
        // Gebruik opgegeven werkjaar (bijv. 2025 = sept 2025 - aug 2026)
        $werkjaarStart = $requestedWerkjaar . '-09-01';
        $werkjaarEind = ($requestedWerkjaar + 1) . '-08-31';
        
        // Haal activiteiten op voor specifiek werkjaar
        $stmt = $pdo->prepare("
            SELECT 
                id,
                date,
                name,
                start_hour,
                stop_hour,
                place,
                comment,
                info,
                inschrijving,
                CASE 
                    WHEN date < CURDATE() THEN 'past'
                    WHEN date = CURDATE() THEN 'today'
                    ELSE 'future'
                END as status,
                DATEDIFF(CURDATE(), date) as days_ago
            FROM calendar
            WHERE date >= :werkjaar_start AND date <= :werkjaar_eind
            ORDER BY date ASC
        ");
        
        $stmt->execute([
            'werkjaar_start' => $werkjaarStart,
            'werkjaar_eind' => $werkjaarEind
        ]);
    } else {
        // Haal alle activiteiten op (voor werkjaar dropdown)
        $stmt = $pdo->prepare("
            SELECT 
                id,
                date,
                name,
                start_hour,
                stop_hour,
                place,
                comment,
                info,
                inschrijving,
                CASE 
                    WHEN date < CURDATE() THEN 'past'
                    WHEN date = CURDATE() THEN 'today'
                    ELSE 'future'
                END as status,
                DATEDIFF(CURDATE(), date) as days_ago
            FROM calendar
            ORDER BY date ASC
        ");
        
        $stmt->execute();
        $werkjaarStart = null;
        $werkjaarEind = null;
    }
    $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Debug info toevoegen
    $debugInfo = [
        'werkjaar_start' => $werkjaarStart ?? 'all',
        'werkjaar_eind' => $werkjaarEind ?? 'all',
        'current_date' => $today->format('Y-m-d'),
        'count' => count($activities)
    ];
    
    // Format de data voor React
    $formattedActivities = array_map(function($activity) {
        // Zoek naar foto in activities folder
        $dateFormatted = str_replace('-', '', $activity['date']); // yyyymmdd
        $nameFormatted = str_replace(' ', '', $activity['name']); // zonder spaties
        $baseName = $dateFormatted . '_' . $nameFormatted;
        
        $photoExtension = null;
        $activitiesDir = __DIR__ . '/../activities/';
        
        // Check verschillende extensies
        $extensions = ['jpg', 'jpeg', 'png', 'JPG', 'JPEG', 'PNG', 'gif', 'webp'];
        foreach ($extensions as $ext) {
            if (file_exists($activitiesDir . $baseName . '.' . $ext)) {
                $photoExtension = $ext;
                break;
            }
        }
        
        return [
            'id' => (int)$activity['id'],
            'date' => $activity['date'],
            'name' => $activity['name'],
            'startHour' => $activity['start_hour'],
            'stopHour' => $activity['stop_hour'],
            'place' => $activity['place'],
            'comment' => $activity['comment'],
            'info' => $activity['info'],
            'inschrijving' => (int)$activity['inschrijving'],
            'status' => $activity['status'],
            'daysAgo' => (int)$activity['days_ago'],
            'photoExtension' => $photoExtension,
            'photoFilename' => $photoExtension ? $baseName . '.' . $photoExtension : null
        ];
    }, $activities);
    
    // Return array direct (voor backwards compatibility)
    echo json_encode($formattedActivities);

} catch(PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
