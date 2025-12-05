<?php
// Unsubscribe pagina voor mailinglijst
require_once __DIR__ . '/db_connect.php';

$message = '';
$success = false;
$showForm = true;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    
    if (empty($email)) {
        $message = 'Vul alstublieft uw email adres in.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = 'Vul alstublieft een geldig email adres in.';
    } else {
        // Zoek alle tabellen waar dit email adres voorkomt met mail=1
        try {
            // Haal alle inschrijvingen tabellen op
            $stmt = $pdo->query("SELECT DISTINCT tabel FROM inschrijvingen WHERE tabel IS NOT NULL AND tabel != ''");
            $tabellen = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            $updatedCount = 0;
            
            foreach ($tabellen as $tabel) {
                // Check of tabel bestaat
                $tableCheckStmt = $pdo->query("SHOW TABLES LIKE '$tabel'");
                if (!$tableCheckStmt->fetch()) continue;
                
                // Check of mail kolom bestaat
                $columnCheckStmt = $pdo->query("SHOW COLUMNS FROM `$tabel` LIKE 'mail'");
                if (!$columnCheckStmt->fetch()) continue;
                
                // Check of email kolom bestaat
                $emailColumnStmt = $pdo->query("SHOW COLUMNS FROM `$tabel` LIKE 'email'");
                if (!$emailColumnStmt->fetch()) continue;
                
                // Update mail naar 0 voor dit email adres
                $updateStmt = $pdo->prepare("UPDATE `$tabel` SET mail = 0 WHERE email = :email AND mail = 1");
                $updateStmt->execute([':email' => $email]);
                $updatedCount += $updateStmt->rowCount();
            }
            
            if ($updatedCount > 0) {
                $success = true;
                $showForm = false;
                $message = "U bent succesvol uitgeschreven uit onze mailinglijst.";
            } else {
                $message = "U was al uitgeschreven of uw email adres werd niet gevonden in onze mailinglijst.";
            }
            
        } catch (PDOException $e) {
            $message = 'Er is een fout opgetreden. Probeer het later opnieuw of neem contact op via raakmolachterbos@gmail.com';
            error_log("Unsubscribe error: " . $e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Uitschrijven Mailinglijst - RAAK Achterbos</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            padding: 40px;
            max-width: 500px;
            width: 100%;
            text-align: center;
        }
        
        .icon {
            font-size: 64px;
            margin-bottom: 20px;
        }
        
        .success .icon {
            color: #4caf50;
        }
        
        .error .icon {
            color: #f44336;
        }
        
        h1 {
            color: #333;
            margin-bottom: 20px;
            font-size: 24px;
        }
        
        p {
            color: #666;
            line-height: 1.6;
            margin-bottom: 30px;
        }
        
        .btn {
            display: inline-block;
            padding: 12px 30px;
            background: #667eea;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            transition: background 0.3s;
        }
        
        .btn:hover {
            background: #764ba2;
        }
        
        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #eee;
            font-size: 14px;
            color: #999;
        }
    </style>
</head>
<body>
    <div class="container <?php echo $success ? 'success' : ($message ? 'error' : ''); ?>">
        <?php if ($showForm): ?>
            <div class="icon" style="color: #667eea;">📧</div>
            <h1>Uitschrijven Mailinglijst</h1>
            <p>Vul uw email adres in om u uit te schrijven van onze mailinglijst.</p>
            
            <?php if ($message): ?>
                <div style="background: #ffebee; color: #c62828; padding: 15px; border-radius: 5px; margin-bottom: 20px;">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" style="margin: 20px 0;">
                <input type="email" 
                       name="email" 
                       placeholder="uw.email@voorbeeld.be" 
                       required
                       style="width: 100%; padding: 12px; border: 2px solid #ddd; border-radius: 5px; font-size: 16px; margin-bottom: 15px;">
                <button type="submit" class="btn" style="width: 100%; cursor: pointer; border: none; font-size: 16px;">
                    Uitschrijven
                </button>
            </form>
            
            <p style="font-size: 14px; color: #999;">
                U ontvangt geen emails meer van onze mailinglijst voor toekomstige activiteiten.<br>
                U blijft wel ingeschreven voor de activiteiten waarvoor u zich al heeft aangemeld.
            </p>
        <?php else: ?>
            <div class="icon">
                <?php echo $success ? '✓' : '✗'; ?>
            </div>
            
            <h1><?php echo $success ? 'Uitgeschreven' : 'Fout'; ?></h1>
            
            <p><?php echo htmlspecialchars($message); ?></p>
            
            <?php if ($success): ?>
                <p style="font-size: 14px;">
                    U ontvangt geen emails meer van onze mailinglijst voor toekomstige activiteiten.<br>
                    U blijft wel ingeschreven voor de activiteiten waarvoor u zich al heeft aangemeld.
                </p>
            <?php endif; ?>
            
            <a href="https://raakachterbos.be" class="btn">Terug naar website</a>
        <?php endif; ?>
        
        <div class="footer">
            RAAK Achterbos<br>
            Voor vragen: <a href="mailto:raakmolachterbos@gmail.com">raakmolachterbos@gmail.com</a>
        </div>
    </div>
</body>
</html>
