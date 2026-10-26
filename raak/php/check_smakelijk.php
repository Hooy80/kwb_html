<?php
header('Content-Type: application/json');
require_once __DIR__ . '/db_connect.php';

try {
    $currentYear = date('Y');

    // Zoek activiteiten voor huidig jaar met naam matching
    $stmtYear = $pdo->prepare("SELECT * FROM calendar WHERE LOWER(name) LIKE LOWER('%smakelijk%wandelen%') AND YEAR(date) = :year ORDER BY date ASC");
    $stmtYear->execute([':year' => $currentYear]);
    $resultsYear = $stmtYear->fetchAll(PDO::FETCH_ASSOC);

    // Zoek alle activiteiten met naam matching (alle jaren)
    $stmtAll = $pdo->prepare("SELECT * FROM calendar WHERE LOWER(name) LIKE LOWER('%smakelijk%wandelen%') ORDER BY date ASC");
    $stmtAll->execute();
    $resultsAll = $stmtAll->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'year' => $currentYear,
        'resultsYear' => $resultsYear,
        'resultsAll' => $resultsAll
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

// Zelfvernietiging: probeer het script te verwijderen uit de server
@unlink(__FILE__);

?>
