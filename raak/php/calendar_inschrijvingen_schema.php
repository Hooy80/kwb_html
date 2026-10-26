<?php
function getCalendarInschrijvingenSchema(PDO $pdo): array
{
    $columnDefinitions = $pdo->query('SHOW COLUMNS FROM calendar_inschrijvingen')->fetchAll(PDO::FETCH_ASSOC);
    $columns = array_column($columnDefinitions, 'Field');
    $activityColumn = in_array('id_act', $columns, true)
        ? 'id_act'
        : (in_array('id_cal', $columns, true) ? 'id_cal' : null);
    $peopleColumn = in_array('aantal_personen', $columns, true)
        ? 'aantal_personen'
        : (in_array('aantal', $columns, true) ? 'aantal' : null);

    if (!$activityColumn || !$peopleColumn || !in_array('id', $columns, true) ||
        !in_array('naam', $columns, true) || !in_array('email', $columns, true) ||
        !in_array('info', $columns, true)) {
        throw new PDOException('De kolommen van calendar_inschrijvingen zijn onvolledig.');
    }

    return [
        'activity' => $activityColumn,
        'people' => $peopleColumn,
        'id_auto' => stripos($columnDefinitions[array_search('id', $columns, true)]['Extra'], 'auto_increment') !== false
    ];
}
