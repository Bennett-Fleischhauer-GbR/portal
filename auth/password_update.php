<?php

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

session_start();

include __DIR__ . '/../src/config/dbconnect.php';
require __DIR__ . '/../src/controllers/email_sender.php'; // Inkludiere die Datei mit den E-Mail-Funktionen

$message = "";

if (isset($_GET['token'])) {
    $token = $_GET['token'];

    $sql = "SELECT * FROM password_resets WHERE token = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();

    // Sprachdatei und Einstellungen aus der Datenbank abrufen
    $sql = "SELECT language, logo_white, site_title FROM settings WHERE id = 1";
    $stmt_logo = $conn->prepare($sql);
    $stmt_logo->execute();
    $settings = $stmt_logo->get_result()->fetch_assoc();
    $language = $settings['language'];
    $logo_white = $settings['logo_white'];
    $site_title_vertical = $settings['site_title'];
    $stmt_logo->close();

    // Sprachdatei laden
    $language_file = __DIR__ . "/../src/languages/$language.php";

    // Prüfen, ob die Sprachdatei existiert
    if (file_exists($language_file)) {
        include $language_file;
    } else {
        // Fallback auf Englisch, wenn die Sprachdatei nicht gefunden wird
        include __DIR__ . '/../src/languages/en.php';
    }

    if ($result->num_rows > 0) {
        $reset = $result->fetch_assoc();
        $user_id = $reset['user_id'];
        $stmt->close(); // Schließt das erste Statement

        if ($_SERVER["REQUEST_METHOD"] == "POST") {
            $password = $_POST['password'];
            $confirm_password = $_POST['confirm_password'];

            if ($password === $confirm_password) {
                $hashed_password = password_hash($password, PASSWORD_BCRYPT);

                // Passwort aktualisieren
                $sql = "UPDATE users SET password = ? WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("si", $hashed_password, $user_id);
                $stmt->execute();
                $stmt->close(); // Schließt das zweite Statement

                // Passwort-Reset-Token löschen
                $sql = "DELETE FROM password_resets WHERE token = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("s", $token);
                $stmt->execute();
                $stmt->close(); // Schließt das dritte Statement

                // Benutzerinformationen abrufen
                $sql = "SELECT first_name, email FROM users WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $user_result = $stmt->get_result();
                $user = $user_result->fetch_assoc();
                $stmt->close(); // Schließt das vierte Statement

                // E-Mail-Benachrichtigung an den Benutzer
                $subject = $lang['email_subject_password_changed'];
                $header = $lang['email_header_password_changed'];
                $content = "
                    <p>{$lang['dear_user']},</p>
                    <p>{$lang['email_body_password_changed']}</p>
                    <p>{$lang['contact_us_immediately']}</p>
                ";
                send_email($user['email'], $subject, $header, $content, get_smtp_settings($conn), $conn);

                $message = "<div class='message success'>{$lang['password_success']} <a href='login.php'>{$lang['login']}</a></div>";
            } else {
                $message = "<div class='message error'>{$lang['password_mismatch_error']}</div>";
            }
        }
    } else {
        $message = "<div class='message error'>{$lang['invalid_token']} <a href='login.php'>{$lang['login']}</a></div>";
        $stmt->close(); // Schließt das Statement im Fehlerfall
    }
} else {
    header("Location: ../index.php");
    exit();
}

$conn->close();

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
    $page_specific_title = $lang['password_update']; ?>
    <title><?php echo $page_specific_title . " | " . $settings['site_title']; ?></title>

    <!-- Favicon -->
    <?php include __DIR__ . '/../src/includes/favicon.php'; ?>

    <!-- Font Awesome für Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">

    <!-- Icons für Passwort Logik-->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">

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
        <h2><?php echo $lang['password_update']; ?></h2>
        <form method="post" action="">
            <label for="password"><?php echo $lang['new_password']; ?>:</label>
            <input type="password" id="new_password" name="password" required oninput="checkPasswordStrength()">
            <div class="password-strength-label"><?php echo $lang['password_strength']; ?></div>
            <div class="password-strength-bar">
                <div class="password-strength-level" id="password-strength-level"></div>
                <span></span><span></span>
            </div>
            <div class="requirements" id="requirements">
                <div class="requirement" id="length-requirement">
                    <span class="icon bi bi-x-circle-fill text-danger"></span> <?php echo $lang['password_requirement_length']; ?>
                </div>
                <div class="requirement" id="lowercase-requirement">
                    <span class="icon bi bi-x-circle-fill text-danger"></span> <?php echo $lang['password_requirement_lowercase']; ?>
                </div>
                <div class="requirement" id="uppercase-requirement">
                    <span class="icon bi bi-x-circle-fill text-danger"></span> <?php echo $lang['password_requirement_uppercase']; ?>
                </div>
                <div class="requirement" id="number-requirement">
                    <span class="icon bi bi-x-circle-fill text-danger"></span> <?php echo $lang['password_requirement_number']; ?>
                </div>
                <div class="requirement" id="special-requirement">
                    <span class="icon bi bi-x-circle-fill text-danger"></span> <?php echo $lang['password_requirement_special']; ?>
                </div>
            </div>
            <label for="confirm_password"><?php echo $lang['confirm_password']; ?>:</label>
            <input type="password" id="confirm_password" name="confirm_password" required><br>
            <div class="options">
                <button type="submit" name="update_password" class="btn btn-primary" id="password-submit-btn" disabled><?php echo $lang['update_password']; ?></button>
            </div>
            <?php echo $message; ?>
        </form>
    </div>
    <div class="vertical-container">
        <div class="vertical-text"><?php echo htmlspecialchars($site_title_vertical); ?></div>
    </div>

    <!-- Einbindung des externen JavaScripts -->
    <script src="../js/passwordStrengthChecker.js"></script>
</body>
</html>
