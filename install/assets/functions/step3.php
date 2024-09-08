<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/../../../src/controllers/email_sender.php'; // Inkludiere die Datei mit den E-Mail-Funktionen

function handleStep3($conn) {
    $successMessages = [];

    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        // Generiere automatisch einen zufälligen Verschlüsselungsschlüssel
        $encryption_key = generateRandomString();
        
        // Verschlüsselung des SMTP-Passworts mit dem generierten Encryption Key
        $smtp_password = encryptPassword($_POST['smtp_password'], $encryption_key);

        // Erstellen des Daten-Arrays für die SMTP-Einstellungen
        $data = [
            'smtp_host' => $_POST['smtp_host'],
            'smtp_port' => $_POST['smtp_port'],
            'smtp_user' => $_POST['smtp_user'],
            'smtp_password' => $smtp_password,
            'encryption_key' => $encryption_key, // Automatisch generierter Schlüssel
            'smtp_encryption' => $_POST['smtp_encryption']
        ];

        try {
            // Speichern der SMTP-Einstellungen in der Datenbank
            saveSmtpSetting($conn, $data);

            // SMTP-Einstellungen validieren
            $is_valid_smtp = validate_smtp_settings(
                $data['smtp_host'],
                $data['smtp_port'],
                $data['smtp_user'],
                $_POST['smtp_password'], // Das unverschlüsselte Passwort wird für die SMTP-Validierung verwendet
                $data['smtp_encryption']
            );

            // Rückmeldung an den Benutzer basierend auf dem Validierungsergebnis
            if ($is_valid_smtp) {
                $successMessages[] = "SMTP settings validated and saved successfully.";
                return ['status' => 'success', 'messages' => $successMessages];
            } else {
                throw new Exception("Invalid SMTP settings. Please check your inputs.");
            }
        } catch (Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    return '';
}

function encryptPassword($password, $encryption_key) {
    // Verschlüsselung des Passworts
    $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('aes-256-cbc'));
    $encrypted = openssl_encrypt($password, 'aes-256-cbc', $encryption_key, 0, $iv);
    return base64_encode($encrypted . '::' . $iv);
}

function saveSmtpSetting($conn, $data) {
    // SQL-Abfrage zum Speichern der SMTP-Einstellungen
    $sql = "INSERT INTO smtp_settings (smtp_host, smtp_port, smtp_user, smtp_password, encryption_key, smtp_encryption) 
            VALUES (?, ?, ?, ?, ?, ?)";

    // SQL-Abfrage vorbereiten und ausführen
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("ssssss", $data['smtp_host'], $data['smtp_port'], $data['smtp_user'], $data['smtp_password'], $data['encryption_key'], $data['smtp_encryption']);
        $stmt->execute();
        $stmt->close();
    } else {
        throw new Exception("Error preparing the database query: " . $conn->error);
    }
}

function generateRandomString($length = 20) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $charactersLength = strlen($characters);
    $randomString = '';
    
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, $charactersLength - 1)];
    }
    
    return $randomString;
}
