<?php
require_once __DIR__ . '/db_connect.php';
header('Content-Type: application/json');
try {
    // Simple test query
    $stmt = $pdo->query("SELECT COUNT(*) as cnt FROM calendar");
    $cnt = $stmt->fetch(PDO::FETCH_ASSOC)['cnt'];

    $stmt2 = $pdo->prepare("SELECT id, date, name, inschrijving FROM calendar WHERE LOWER(name) LIKE LOWER('%smakelijk%') AND YEAR(date) = :yr");
    $yr = date('Y');
    $stmt2->execute([':yr' => $yr]);
    $rows = $stmt2->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['ok' => true, 'calendar_count' => (int)$cnt, 'current_year' => $yr, 'smakelijk_rows' => $rows], JSON_PRETTY_PRINT);
} catch (Exception $e) {
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
?>
