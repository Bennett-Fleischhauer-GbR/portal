<?php
// Namespace Imports für PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Einbinden der benötigten PHPMailer-Klassen und der Datenbankverbindung
require __DIR__ . '/../phpmailer/src/PHPMailer.php';
require __DIR__ . '/../phpmailer/src/SMTP.php';
require __DIR__ . '/../phpmailer/src/Exception.php';
require __DIR__ . '/../config/dbconnect.php'; // Datenbankverbindung

// Verhindern des direkten Zugriffs auf die Datei
if (basename($_SERVER['PHP_SELF']) == basename(__FILE__)) {
    header("Location: ../../public/errors/403.php");
    exit;
}

// Überprüfen, ob der Benutzer eingeloggt ist
if (isset($_SESSION['user_id'])) {
    // Benutzer ist eingeloggt, wir holen die Benutzersprache
    $user_id = $_SESSION['user_id'];
    
    $sql = "SELECT user_language FROM users WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $user_language = $stmt->get_result()->fetch_assoc()['user_language'] ?? 'en'; // Default to 'en' if no user language is set
    $stmt->close();

    $language = $user_language ?? 'en'; // Fallback auf Englisch, falls keine Sprache eingestellt ist
} else {
    // Benutzer ist nicht eingeloggt, wir holen die Systemsprache aus den Einstellungen
    $sql = "SELECT language FROM settings WHERE id = 1"; // Systemsprache abrufen
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $system_language = $stmt->get_result()->fetch_assoc()['language'];
    $stmt->close();

    $language = $system_language;
}

// Sprachdatei laden
$language_file = __DIR__ . "/../languages/{$language}.php";
if (file_exists($language_file)) {
    include $language_file;
} else {
    include __DIR__ . '/../languages/en.php'; // Fallback auf Englisch, falls Datei nicht existiert
}


// Funktion zum Abrufen des Verschlüsselungsschlüssels aus der Datenbank
function get_encryption_key($conn) {
    $encryption_key = ''; // Initialisierung der Variablen
    $id = 1; // Initialisierung der ID, die in der Abfrage verwendet wird
    try {
        $stmt = $conn->prepare("SELECT encryption_key FROM smtp_settings WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->bind_result($encryption_key);
        $stmt->fetch();
        $stmt->close();
        return $encryption_key;
    } catch (Exception $e) {
        error_log("Fehler beim Abrufen des Verschlüsselungsschlüssels: " . $e->getMessage());
        return false;
    }
}

// Funktion zum Abrufen von Signatur, Firmenname, Farben, und Footer-Text aus der Datenbank
function get_email_footer_data($conn)
{
    try {
        $stmt = $conn->query("SELECT company_name, email_signature_name, primary_color, site_title, footer_text, footer_option, foundation_year, show_copyright FROM settings LIMIT 1");
        $row = $stmt->fetch_assoc();

        if ($row) {
            return $row;
        } else {
            throw new Exception("Footer-Daten nicht gefunden.");
        }
    } catch (Exception $e) {
        error_log("Fehler beim Abrufen der Footer-Daten: " . $e->getMessage());
        return false;
    }
}


// Funktion zum Abrufen der Absender-E-Mail aus den SMTP-Einstellungen
function get_sender_email($conn) {
    try {
        $stmt = $conn->query("SELECT smtp_user FROM smtp_settings WHERE id = 1 LIMIT 1");
        $row = $stmt->fetch_assoc();

        if ($row) {
            return $row['smtp_user'];
        } else {
            throw new Exception("Absender-E-Mail nicht gefunden.");
        }
    } catch (Exception $e) {
        error_log("Fehler beim Abrufen der Absender-E-Mail: " . $e->getMessage());
        return false;
    }
}

/**
 * Verschlüsselt ein Passwort mit AES-256-CBC.
 *
 * @param string $password Das Klartext-Passwort.
 * @param object $conn Die Datenbankverbindung.
 * @return string Das verschlüsselte Passwort.
 */
function encrypt_password($password, $conn)
{
    $encryption_key = get_encryption_key($conn);
    if (!$encryption_key) {
        return false; // Fehler beim Abrufen des Schlüssels
    }
    $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('aes-256-cbc'));
    $encrypted = openssl_encrypt($password, 'aes-256-cbc', $encryption_key, 0, $iv);
    return base64_encode($encrypted . '::' . $iv);
}

/**
 * Entschlüsselt ein verschlüsseltes Passwort.
 *
 * @param string $encrypted_password Das verschlüsselte Passwort.
 * @param object $conn Die Datenbankverbindung.
 * @return string Das entschlüsselte Passwort.
 */
function decrypt_password($encrypted_password, $conn)
{
    $encryption_key = get_encryption_key($conn);
    if (!$encryption_key) {
        return false; // Fehler beim Abrufen des Schlüssels
    }
    list($encrypted_data, $iv) = explode('::', base64_decode($encrypted_password), 2);
    return openssl_decrypt($encrypted_data, 'aes-256-cbc', $encryption_key, 0, $iv);
}

/**
 * Validiert die SMTP-Einstellungen durch Senden einer Test-E-Mail.
 *
 * @param string $host Der SMTP-Host.
 * @param int $port Der SMTP-Port.
 * @param string $user Der SMTP-Benutzername.
 * @param string $password Das SMTP-Passwort.
 * @param string $encryption Die SMTP-Verschlüsselungsmethode.
 * @return bool Gibt true zurück, wenn die SMTP-Einstellungen gültig sind, andernfalls false.
 */
function validate_smtp_settings($host, $port, $user, $password, $encryption)
{
    global $lang; // Zugriff auf die Sprachvariable

    $mail = new PHPMailer(true);

    try {
        // Konfiguration des SMTP-Servers
        $mail->isSMTP();
        $mail->Host = $host;
        $mail->SMTPAuth = true;
        $mail->Username = $user;
        $mail->Password = $password;
        $mail->SMTPSecure = $encryption;
        $mail->Port = $port;

        // Timeout und Debug-Einstellungen
        $mail->Timeout = 5; // Timeout auf 5 Sekunden setzen
        $mail->SMTPDebug = 0; // Keine Debug-Ausgabe
        $mail->Debugoutput = 'html'; // Ausgabeformat

        // Verbindung testen durch Hinzufügen eines Testempfängers
        $mail->addAddress('test@example.com');
        $mail->Subject = $lang['smtp_test_email_subject']; // Sprachdatei verwendet
        $mail->Body = $lang['smtp_test_email_body']; // Sprachdatei verwendet
        $mail->send();

        return true;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Sendet eine E-Mail mit einem vorgegebenen Template.
 *
 * @param string $recipient Der Empfänger der E-Mail.
 * @param string $subject Der Betreff der E-Mail.
 * @param string $header Der Header der E-Mail.
 * @param string $content Der Inhalt der E-Mail.
 * @param array $smtp_settings Die SMTP-Einstellungen.
 * @param object $conn Die Datenbankverbindung.
 * @return bool Gibt true zurück, wenn die E-Mail erfolgreich gesendet wurde, andernfalls false.
 */
function send_email($recipient, $subject, $header, $content, $smtp_settings, $conn)
{
    global $lang; // Zugriff auf Sprachvariablen
    $mail = new PHPMailer(true);
    $footer_data = get_email_footer_data($conn);
    $from_email = get_sender_email($conn);

    if (!$footer_data || !$from_email) {
        return false; // Fehler beim Abrufen der Footer-Daten oder der Absender-E-Mail
    }

    $company_name = $footer_data['company_name'];
    $email_signature = $footer_data['email_signature_name'];
    $primary_color = $footer_data['primary_color'] ?: '#152039'; // Fallback-Farbe
    $from_name = $footer_data['site_title'] . ' | ' . $company_name; // Zusammensetzung von Seitentitel und Firmenname

    // Footer-Text von der Website abrufen
    $footer_text = htmlspecialchars_decode($footer_data['footer_text'], ENT_QUOTES);
    $footer_option = $footer_data['footer_option'];
    $foundation_year = $footer_data['foundation_year'];
    $show_copyright = $footer_data['show_copyright'];

    // Copyright-Symbol basierend auf der Datenbank
    $copyright_symbol = $show_copyright ? "&copy; " : "";

    // Footer-Inhalt entsprechend der `footer_option` generieren
    if ($footer_option == 'text_only') {
        $footer_content = $copyright_symbol . html_entity_decode($footer_text, ENT_QUOTES, 'UTF-8');
    } elseif ($footer_option == 'current_year_text') {
        $footer_content = $copyright_symbol . date('Y') . " " . html_entity_decode($footer_text, ENT_QUOTES, 'UTF-8');
    } elseif ($footer_option == 'foundation_year_text') {
        $footer_content = $copyright_symbol . $foundation_year . ' - ' . date('Y') . " " . html_entity_decode($footer_text, ENT_QUOTES, 'UTF-8');
    }

    try {
        // SMTP-Konfiguration
        $mail->isSMTP();
        $mail->Host = $smtp_settings['smtp_host'];
        $mail->SMTPAuth = true;
        $mail->Username = $smtp_settings['smtp_user'];
        $mail->Password = decrypt_password($smtp_settings['smtp_password'], $conn);
        $mail->SMTPSecure = $smtp_settings['smtp_encryption'];
        $mail->Port = $smtp_settings['smtp_port'];

        // Absenderinformationen
        $mail->setFrom($from_email, $from_name);
        $mail->addAddress($recipient);

        // E-Mail-Inhalt
        $mail->isHTML(true);
        $mail->CharSet = 'UTF-8';
        $mail->Subject = $subject;

        // E-Mail-Template mit dynamischer Primärfarbe und Footer
        $mail->Body = "
                <html>
                <head>
                    <style>
                        body {
                            font-family: Arial, sans-serif;
                            background-color: #f4f4f4;
                            color: #333;
                            line-height: 1.6;
                        }
                        .container {
                            width: 80%;
                            margin: auto;
                            overflow: hidden;
                            background: #fff;
                            padding: 20px;
                            border-radius: 10px;
                            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
                        }
                        .header {
                            background: $primary_color;
                            color: #fff;
                            padding: 10px 0;
                            text-align: center;
                            border-radius: 10px;
                        }
                        .header h1 {
                            margin: 0;
                            font-size: 24px;
                        }
                        .content {
                            padding: 20px;
                        }
                        .content p {
                            font-size: 18px;
                        }
                        .footer {
                            margin-top: 20px;
                            text-align: center;
                            font-size: 14px;
                            color: #777;
                        }
                    </style>
                </head>
                <body>
                    <div class='container'>
                        <div class='header'>
                            <h1>$header</h1>
                        </div>
                        <div class='content'>
                            <p>$content</p>
                            <p>{$lang['email_signature']},<br>$email_signature</p>
                        </div>
                        <div class='footer'>
                            $footer_content
                        </div>
                    </div>
                </body>
                </html>
                ";

        // E-Mail senden
        $mail->send();

        return true;
    } catch (Exception $e) {
        error_log("E-Mail konnte nicht gesendet werden. Fehler: {$mail->ErrorInfo}");
        return false;
    }
}
