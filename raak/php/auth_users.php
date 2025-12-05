<?php
// Sessie configuratie
ini_set('session.cookie_path', '/');
ini_set('session.cookie_httponly', 1);
session_start();

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once 'db_connect.php';
require_once 'smtp_mail.php';

// Check of gebruiker ingelogd is
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Niet ingelogd']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$userFunctie = $_SESSION['user_functie'];

try {
    // GET - Lijst gebruikers (admin & bestuur)
    if ($method === 'GET') {
        if ($userFunctie !== 'admin' && $userFunctie !== 'bestuur') {
            echo json_encode(['success' => false, 'error' => 'Geen toegang']);
            exit;
        }

        $stmt = $pdo->query("
            SELECT id, naam, voornaam, login, email, functie, actief 
            FROM bestuur 
            ORDER BY naam, voornaam
        ");
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['success' => true, 'users' => $users]);
    }

    // POST - Nieuwe gebruiker aanmaken (alleen admin)
    elseif ($method === 'POST') {
        if ($userFunctie !== 'admin') {
            echo json_encode(['success' => false, 'error' => 'Alleen admins kunnen gebruikers aanmaken']);
            exit;
        }

        $input = json_decode(file_get_contents('php://input'), true);
        
        $naam = $input['naam'] ?? '';
        $voornaam = $input['voornaam'] ?? '';
        $email = $input['email'] ?? '';
        $functie = $input['functie'] ?? 'wijkmeester';
        
        // Genereer automatisch login en wachtwoord
        $login = strtolower(substr($voornaam, 0, 1) . $naam); // bv: whooyberghs
        $login = preg_replace('/[^a-z0-9]/', '', $login); // Verwijder speciale tekens
        
        // Check of login uniek is, zo niet voeg getal toe
        $baseLogin = $login;
        $counter = 1;
        while (true) {
            $stmt = $pdo->prepare("SELECT id FROM bestuur WHERE login = :login");
            $stmt->execute(['login' => $login]);
            if (!$stmt->fetch()) {
                break; // Login is uniek
            }
            $login = $baseLogin . $counter;
            $counter++;
        }
        
        // Genereer random wachtwoord
        $password = bin2hex(random_bytes(8)); // 16 karakters random wachtwoord

        // Validatie
        if (empty($naam) || empty($voornaam) || empty($email)) {
            echo json_encode(['success' => false, 'error' => 'Naam, voornaam en email zijn verplicht']);
            exit;
        }

        // Hash wachtwoord
        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);

        // Insert nieuwe gebruiker
        $stmt = $pdo->prepare("
            INSERT INTO bestuur (naam, voornaam, login, email, paswoord, functie, actief)
            VALUES (:naam, :voornaam, :login, :email, :paswoord, :functie, 1)
        ");
        $stmt->execute([
            'naam' => $naam,
            'voornaam' => $voornaam,
            'login' => $login,
            'email' => $email,
            'paswoord' => $hashedPassword,
            'functie' => $functie
        ]);

        $newUserId = $pdo->lastInsertId();
        
        // Stuur email naar nieuwe gebruiker via SMTP
        $subject = "Uw account voor RAAK Achterbos Bestuur";
        
        // Email body
        $emailMessage = "Beste $voornaam $naam,\n\n";
        $emailMessage .= "Er is een account voor u aangemaakt op het RAAK Achterbos bestuurspaneel.\n\n";
        $emailMessage .= "Login gegevens:\n";
        $emailMessage .= "Website: https://raakachterbos.be/bestuur\n";
        $emailMessage .= "Login: $login\n";
        $emailMessage .= "Wachtwoord: $password\n\n";
        $emailMessage .= "U kunt dit wachtwoord wijzigen na het inloggen via 'Gebruikers' > 'Mijn Profiel'.\n\n";
        $emailMessage .= "Met vriendelijke groeten,\n";
        $emailMessage .= "RAAK Achterbos";
        
        // Verstuur email via SMTP
        $mailSent = sendEmail($email, "$voornaam $naam", $subject, $emailMessage);

        if ($mailSent) {
            echo json_encode([
                'success' => true, 
                'id' => $newUserId,
                'message' => "Gebruiker aangemaakt. Login gegevens zijn verstuurd naar $email"
            ]);
        } else {
            echo json_encode([
                'success' => true, 
                'id' => $newUserId,
                'message' => "Gebruiker aangemaakt maar email kon niet verstuurd worden."
            ]);
        }
    }

    // PUT - Update gebruiker (admin) of eigen wachtwoord (iedereen)
    elseif ($method === 'PUT') {
        $input = json_decode(file_get_contents('php://input'), true);
        $userId = $input['id'] ?? null;

        if (!$userId) {
            echo json_encode(['success' => false, 'error' => 'Gebruiker ID ontbreekt']);
            exit;
        }

        // Wijkmeester mag alleen eigen wachtwoord wijzigen
        if ($userFunctie === 'wijkmeester' && $userId != $_SESSION['user_id']) {
            echo json_encode(['success' => false, 'error' => 'Je mag alleen je eigen wachtwoord wijzigen']);
            exit;
        }

        // Eigen profiel wijzigen (iedereen voor zichzelf)
        $isOwnProfile = ($userId == $_SESSION['user_id']);
        
        // Check toegang: eigen profiel of admin
        if (!$isOwnProfile && $userFunctie !== 'admin') {
            echo json_encode(['success' => false, 'error' => 'Geen toegang']);
            exit;
        }

        $updates = [];
        $params = ['id' => $userId];

        // Basisgegevens (iedereen voor zichzelf, admin voor iedereen)
        if (isset($input['naam'])) {
            $updates[] = "naam = :naam";
            $params['naam'] = $input['naam'];
        }
        if (isset($input['voornaam'])) {
            $updates[] = "voornaam = :voornaam";
            $params['voornaam'] = $input['voornaam'];
        }
        if (isset($input['email'])) {
            $updates[] = "email = :email";
            $params['email'] = $input['email'];
        }
        if (isset($input['login'])) {
            // Check of login uniek is
            $stmt = $pdo->prepare("SELECT id FROM bestuur WHERE login = :login AND id != :id");
            $stmt->execute(['login' => $input['login'], 'id' => $userId]);
            if ($stmt->fetch()) {
                echo json_encode(['success' => false, 'error' => 'Deze login is al in gebruik']);
                exit;
            }
            $updates[] = "login = :login";
            $params['login'] = $input['login'];
        }
        if (isset($input['password']) && !empty($input['password'])) {
            $hashedPassword = password_hash($input['password'], PASSWORD_BCRYPT);
            $updates[] = "paswoord = :paswoord";
            $params['paswoord'] = $hashedPassword;
        }
        
        // Alleen admin mag functie en actief status wijzigen
        if ($userFunctie === 'admin') {
            if (isset($input['functie'])) {
                $updates[] = "functie = :functie";
                $params['functie'] = $input['functie'];
            }
            if (isset($input['actief'])) {
                $updates[] = "actief = :actief";
                $params['actief'] = $input['actief'];
            }
        }

        if (empty($updates)) {
            echo json_encode(['success' => false, 'error' => 'Geen velden om te updaten']);
            exit;
        }

        $sql = "UPDATE bestuur SET " . implode(', ', $updates) . " WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        echo json_encode(['success' => true]);
    }

    // DELETE - Deactiveer gebruiker (alleen admin)
    elseif ($method === 'DELETE') {
        if ($userFunctie !== 'admin') {
            echo json_encode(['success' => false, 'error' => 'Alleen admins kunnen gebruikers deactiveren']);
            exit;
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $userId = $input['id'] ?? null;

        if (!$userId) {
            echo json_encode(['success' => false, 'error' => 'Gebruiker ID ontbreekt']);
            exit;
        }

        // Deactiveer in plaats van verwijderen
        $stmt = $pdo->prepare("UPDATE bestuur SET actief = 0 WHERE id = :id");
        $stmt->execute(['id' => $userId]);

        echo json_encode(['success' => true]);
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Server fout: ' . $e->getMessage()]);
}
