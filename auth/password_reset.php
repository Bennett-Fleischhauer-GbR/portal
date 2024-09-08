<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

session_start();

include __DIR__ . '/../src/config/dbconnect.php';
require __DIR__ . '/../src/controllers/email_sender.php'; // Inkludiere die Datei mit den E-Mail-Funktionen

$message = "";

// Sprachdatei und Einstellungen aus der Datenbank abrufen
$sql = "SELECT logo_white, site_title, language FROM settings WHERE id = 1";
$stmt = $conn->prepare($sql);
$stmt->execute();
$settings = $stmt->get_result()->fetch_assoc();
$logo_white = $settings['logo_white'];
$site_title_vertical = $settings['site_title'];
$language = $settings['language'];
$stmt->close(); // Schließen des Statements

// Sprachdatei laden
$language_file = __DIR__ . "/../src/languages/$language.php";

// Prüfen, ob die Sprachdatei existiert
if (file_exists($language_file)) {
    include $language_file;
} else {
    // Fallback auf Englisch, wenn die Sprachdatei nicht gefunden wird
    include __DIR__ . '/../src/languages/en.php';
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST['email'];

    // Benutzerinformationen abrufen
    $sql = "SELECT * FROM users WHERE email = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        $user_id = $user['id'];
        $stmt->close(); // Schließen des ersten Statements

        $token = bin2hex(random_bytes(50));
        $created_at = date('Y-m-d H:i:s');

        // Token in der Datenbank speichern
        $sql = "INSERT INTO password_resets (user_id, token, created_at) VALUES (?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iss", $user_id, $token, $created_at);
        $stmt->execute();
        $stmt->close(); // Schließen des zweiten Statements

        // Dynamische Domain und Protokoll ermitteln
        $protocol = $_SERVER['REQUEST_SCHEME'];
        $domain = $_SERVER['HTTP_HOST'];

        // Passwort-Reset-Link generieren (dynamische Domain)
        $reset_link = "$protocol://$domain/auth/password_update.php?token=$token";

        // E-Mail-Inhalt
        $subject = $lang['email_subject_password_reset'];
        $header = $lang['email_header_password_reset'];
        $content = "
            <p>{$lang['dear_user']},</p>
            <p>{$lang['email_body_password_reset']}</p>
            <p><a href='$reset_link' style='color: #1a82e2; text-decoration: none;'>{$lang['reset_password']}</a></p>
            <p>{$lang['email_body_password_reset_ignore']}</p>
        ";

        // E-Mail senden
        if (send_email($email, $subject, $header, $content, get_smtp_settings($conn), $conn)) {
            $message = "<div class='message success'>{$lang['password_reset_email_sent']}</div>";
        } else {
            $message = "<div class='message error'>{$lang['password_reset_email_error']}</div>";
        }
    } else {
        $message = "<div class='message error'>{$lang['user_not_found']}</div>";
        $stmt->close(); // Schließen des Statements im Fehlerfall
    }
}

$conn->close();

/**
 * Funktion zum Abrufen der SMTP-Einstellungen aus der Datenbank.
 *
 * @param mysqli $conn Die MySQLi-Verbindung.
 * @return array|null Gibt ein Array mit den SMTP-Einstellungen zurück oder null, wenn sie nicht gefunden wurden.
 */
function get_smtp_settings($conn) {
    $sql = "SELECT smtp_host, smtp_port, smtp_user, smtp_password, smtp_encryption FROM smtp_settings WHERE id = 1";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $result = $stmt->get_result();
    $smtp_settings = $result->fetch_assoc();
    $stmt->close();

    return $smtp_settings ?: null;
}
?>

<!DOCTYPE html>
<html lang="<?php echo $language; ?>">
<head>
    <!-- Meta-Tags -->
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="author" content="Bennett & Fleischhauer GbR">
    <meta name="robots" content="noindex, nofollow">

    <!-- Titel der Seite -->
    <?php include __DIR__ . '/../src/includes/page_title.php'; 
    $page_specific_title = $lang['password_reset']; ?>
    <title><?php echo $page_specific_title . " | " . $site_title_vertical; ?></title>

    <!-- Favicon -->
    <?php include __DIR__ . '/../src/includes/favicon.php'; ?>

    <!-- Font Awesome für Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">

    <!-- Benutzerdefinierte Styles -->
    <link rel="stylesheet" href="../css/login.css">

    <!-- Dynamische Styles (falls notwendig) -->
    <?php include __DIR__ . '/../src/includes/dynamic_styles.php'; ?>

    <!-- Dynamisches CSS für den vertikalen Text -->
    <style>
        :root {
            --char-count: <?php echo strlen($site_title_vertical); ?>;
        }
        .vertical-text {
            font-size: calc(120vh / var(--char-count)); /* Dynamische Anpassung der Schriftgröße */
        }
    </style>
</head>
<body>
    <div class="reset-box">
        <div class="logo-container">
            <img src="<?php echo htmlspecialchars($logo_white); ?>" alt="Logo">
        </div>
        <h2><?php echo $lang['password_reset']; ?></h2>
        <div class="back-to-login">
            <a href="login.php"><i class="fas fa-arrow-left"></i><?php echo $lang['back_to_login']; ?></a>
        </div>
        <form method="post" action="password_reset.php">
            <label for="email"><?php echo $lang['email']; ?>:</label>
            <input type="email" id="email" name="email" required><br>
            <div class="options">
                <button type="submit"><?php echo $lang['reset_password']; ?></button>
            </div>
            <?php echo $message; ?>
        </form>
    </div>
    <div class="vertical-container">
        <div class="vertical-text"><?php echo htmlspecialchars($site_title_vertical); ?></div>
    </div>
</body>
</html>
