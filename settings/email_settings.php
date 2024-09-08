<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

session_start();

// Überprüfen, ob der Benutzer eingeloggt ist, andernfalls zur Login-Seite umleiten
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

// Überprüfen, ob der Benutzer ein Admin ist, andernfalls zur 404-Seite umleiten
if ($_SESSION['role'] !== 'admin') {
    header("Location: ../errors/403.php");
    exit();
}

include __DIR__ . '/../src/config/dbconnect.php';
require __DIR__ . '/../src/controllers/email_sender.php'; // Inkludiere die Datei mit den E-Mail-Funktionen

// Benutzersprache abrufen
$user_id = $_SESSION['user_id'];
$sql = "SELECT user_language FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_language = $stmt->get_result()->fetch_assoc()['user_language'];
$stmt->close();

$language = $user_language ?? 'en'; // Fallback auf Englisch, falls keine Sprache eingestellt ist

$language_file = __DIR__ . "/../src/languages/{$language}.php";

// Prüfen, ob die Sprachdatei existiert
if (file_exists($language_file)) {
    include $language_file;
} else {
    // Fallback auf Englisch, wenn die Sprachdatei nicht gefunden wird
    include __DIR__ . '/../src/languages/en.php';
}

// Benutzerinformationen abrufen
$user_id = $_SESSION['user_id'];
$sql = "SELECT first_name, email, role FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$first_name = $user['first_name'];
$email = $user['email'];
$role = $user['role'];
$stmt->close();

// SMTP-Einstellungen abrufen
$sql = "SELECT * FROM smtp_settings WHERE id = 1";
$stmt = $conn->prepare($sql);
$stmt->execute();
$smtp_settings = $stmt->get_result()->fetch_assoc();
$stmt->close();

$save_message = '';
$test_message = '';
$current_url = "http://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";

// Skript-Timeout reduzieren
set_time_limit(6);

// SMTP-Einstellungen speichern
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save'])) {
    $smtp_host = $_POST['smtp_host'];
    $smtp_port = $_POST['smtp_port'];
    $smtp_user = $_POST['smtp_user'];
    $smtp_password_raw = $_POST['smtp_password']; // Unverschlüsseltes Passwort zur Überprüfung
    $smtp_password = encrypt_password($smtp_password_raw, $conn); // Passwort verschlüsseln
    $smtp_encryption = $_POST['smtp_encryption'];

    // Änderungen tracken
    $changes = [];
    $has_changes = false;

    if ($smtp_settings['smtp_host'] !== $smtp_host) {
        $changes[] = "<li><strong>{$lang['smtp_host']}:</strong> {$lang['changed_from']} " . $smtp_settings['smtp_host'] . " {$lang['to']} " . $smtp_host . "</li>";
        $has_changes = true;
    }

    if ($smtp_settings['smtp_port'] != $smtp_port) {
        $changes[] = "<li><strong>{$lang['smtp_port']}:</strong> {$lang['changed_from']} " . $smtp_settings['smtp_port'] . " {$lang['to']} " . $smtp_port . "</li>";
        $has_changes = true;
    }

    if ($smtp_settings['smtp_user'] !== $smtp_user) {
        $changes[] = "<li><strong>{$lang['smtp_user']}:</strong> {$lang['changed_from']} " . $smtp_settings['smtp_user'] . " {$lang['to']} " . $smtp_user . "</li>";
        $has_changes = true;
    }

    $password_changed = decrypt_password($smtp_settings['smtp_password'], $conn) !== $smtp_password_raw;
    if ($password_changed) {
        $changes[] = "<li><strong>{$lang['smtp_password']}:</strong> {$lang['password_changed']}</li>";
        $has_changes = true;
    } else {
        $changes[] = "<li><strong>{$lang['smtp_password']}:</strong> {$lang['password_not_changed']}</li>";
    }

    if ($smtp_settings['smtp_encryption'] !== $smtp_encryption) {
        $changes[] = "<li><strong>{$lang['smtp_encryption']}:</strong> {$lang['changed_from']} " . $smtp_settings['smtp_encryption'] . " {$lang['to']} " . $smtp_encryption . "</li>";
        $has_changes = true;
    }

    if ($has_changes) {
        $is_valid = validate_smtp_settings($smtp_host, $smtp_port, $smtp_user, $smtp_password_raw, $smtp_encryption);

        if ($is_valid) {
            $sql = "UPDATE smtp_settings SET smtp_host = ?, smtp_port = ?, smtp_user = ?, smtp_password = ?, smtp_encryption = ? WHERE id = 1";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sisss", $smtp_host, $smtp_port, $smtp_user, $smtp_password, $smtp_encryption);

            if ($stmt->execute()) {
                $save_message = "<div class='alert alert-success'>{$lang['smtp_settings_saved_success']}</div>";

                $intro_text = $lang['smtp_settings_updated'];
                $change_details = "<ul>" . implode("", $changes) . "</ul>";
                send_change_notification($intro_text, $change_details, $lang['smtp_settings_change_subject'], $conn);
            } else {
                $save_message = "<div class='alert alert-danger'>{$lang['smtp_settings_saved_error']}</div>";
            }
            $stmt->close();
        } else {
            $save_message = "<div class='alert alert-danger'>{$lang['smtp_settings_invalid']}</div>";
            $failed_details = "<ul>" . implode("", $changes) . "</ul>";
            send_change_notification($lang['smtp_settings_failed_intro'], $failed_details, $lang['smtp_settings_failed_subject'], $conn, true);
        }
    } else {
        $save_message = "<div class='alert alert-info'>{$lang['no_changes']}</div>";
    }
}

// Test-E-Mail senden mit PHPMailer
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['send_test'])) {
    $test_email = $_POST['test_email'];
    $smtp_password = decrypt_password($smtp_settings['smtp_password'], $conn);

    if (send_email(
        $test_email,
        $lang['smtp_test_email_subject'],
        $lang['smtp_test_email_subject'],
        $lang['smtp_test_email_body'],
        $smtp_settings,
        $conn
    )) {
        $test_message = "<div class='alert alert-success'>{$lang['smtp_test_email_success']}</div>";
    } else {
        $test_message = "<div class='alert alert-danger'>{$lang['smtp_test_email_error']}</div>";
    }
}

/**
 * Funktion zum Senden einer Benachrichtigung über SMTP-Änderungen.
 *
 * @param string $intro_text Der Einführungstext, der die Änderungen einleitet.
 * @param string $details Die Details der Änderungen.
 * @param string $subject Der Betreff der E-Mail.
 * @param object $conn Die Datenbankverbindung.
 * @param bool $is_error Gibt an, ob es sich um eine Fehlermeldung handelt.
 */
function send_change_notification($intro_text, $details, $subject, $conn, $is_error = false) {
    global $smtp_settings, $current_url, $lang;

    $message_content = "
        <p>$intro_text</p>
        $details
        <p>" . $lang['view_settings_link_here'] . " <a href='$current_url' style='text-decoration: underline;'>" . $lang['here'] . "</a>.</p>
    ";

    $sql = "SELECT email FROM users";
    $result = $conn->query($sql);
    while ($user = $result->fetch_assoc()) {
        send_email(
            $user['email'],
            $subject,
            $subject,
            $message_content,
            $smtp_settings,
            $conn
        );
    }
}


$conn->close();
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
    $page_specific_title = $lang['smtp_settings']; ?>
    <title><?php echo $page_specific_title . " | " . $site_title . " | " . $company_name; ?></title>

     <!-- Favicon -->
    <?php include __DIR__ . '/../src/includes/favicon.php'; ?>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Font Awesome für Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">

    <!-- Benutzerdefinierte Styles -->
    <link rel="stylesheet" href="../css/index.css">
    
    <!-- Dynamische Styles (falls notwendig) -->
    <?php include __DIR__ . '/../src/includes/dynamic_styles.php'; ?>
</head>
<body>

<?php include __DIR__ . '/../src/includes/header.php'; ?>

<div class="container main-content">
    <div class="row mb-4">
        <div class="col-12">
            <h2><?php echo $lang['smtp_settings_title']; ?></h2>
            <p><?php echo $lang['smtp_settings_description']; ?></p>
        </div>
    </div>
    
    <div class="card mb-5">
        <div class="card-body">
            <h3><?php echo $lang['smtp_settings']; ?></h3>
            <?php if (!empty($save_message)) echo $save_message; ?>
            <form method="post" action="email_settings.php">
                <input type="hidden" name="save" value="1">
                <div class="mb-3">
                    <label for="smtp_host" class="form-label"><?php echo $lang['smtp_host']; ?></label>
                    <input type="text" class="form-control" id="smtp_host" name="smtp_host" value="<?php echo $smtp_settings['smtp_host'] ?? ''; ?>" required>
                </div>
                <div class="mb-3">
                    <label for="smtp_port" class="form-label"><?php echo $lang['smtp_port']; ?></label>
                    <input type="number" class="form-control" id="smtp_port" name="smtp_port" value="<?php echo $smtp_settings['smtp_port'] ?? ''; ?>" required>
                </div>
                <div class="mb-3">
                    <label for="smtp_user" class="form-label"><?php echo $lang['smtp_user']; ?></label>
                    <input type="text" class="form-control" id="smtp_user" name="smtp_user" value="<?php echo $smtp_settings['smtp_user'] ?? ''; ?>" required>
                </div>
                <div class="mb-3">
                    <label for="smtp_password" class="form-label"><?php echo $lang['smtp_password']; ?></label>
                    <input type="password" class="form-control" id="smtp_password" name="smtp_password" required>
                </div>
                <div class="mb-3">
                    <label for="smtp_encryption" class="form-label"><?php echo $lang['smtp_encryption']; ?></label>
                    <select class="form-control" id="smtp_encryption" name="smtp_encryption" required>
                        <option value="ssl" <?php if (isset($smtp_settings['smtp_encryption']) && $smtp_settings['smtp_encryption'] == 'ssl') echo 'selected'; ?>>SSL</option>
                        <option value="tls" <?php if (isset($smtp_settings['smtp_encryption']) && $smtp_settings['smtp_encryption'] == 'tls') echo 'selected'; ?>>TLS</option>
                    </select>
                    <small class="form-text text-muted">
                        <?php echo $lang['smtp_encryption_hint']; ?>
                    </small>
                </div>
                <button type="submit" class="btn btn-primary"><?php echo $lang['save']; ?></button>
            </form>
        </div>
    </div>

    <div class="card mb-5">
        <div class="card-body">
            <h3><?php echo $lang['smtp_test_email_title']; ?></h3>
            <?php if (isset($test_message)) echo $test_message; ?>
            <form method="post" action="email_settings.php">
                <input type="hidden" name="send_test" value="1">
                <div class="mb-3">
                    <label for="test_email" class="form-label"><?php echo $lang['smtp_test_email_address']; ?></label>
                    <input type="email" class="form-control" id="test_email" name="test_email" required>
                </div>
                <button type="submit" class="btn btn-primary"><?php echo $lang['send_test_email']; ?></button>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../src/includes/footer.php'; ?>

<div class="theme-toggle">
    <i class="fas fa-adjust"></i><span class="theme-name"></span>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="../js/scripts.js"></script>
</body>
</html>
