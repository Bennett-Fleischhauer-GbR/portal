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

// Überprüfen, ob der Benutzer ein Admin ist, andernfalls zur 403-Seite umleiten
if ($_SESSION['role'] !== 'admin') {
    header("Location: ../errors/403.php");
    exit();
}

include __DIR__ . '/../src/config/dbconnect.php';
require __DIR__ . '/../src/controllers/email_sender.php'; // Inkludiere die Datei mit den E-Mail-Funktionen

$save_settings_message = '';
$save_link_shortener_message = '';

// Benutzerinformationen abrufen
$user_id = $_SESSION['user_id'];
$sql = "SELECT first_name, email, role FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

$save_logo_message = $save_color_message = $save_footer_message = $save_openweather_message = $save_site_message = $save_signature_message = '';
$current_url = "http://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]"; // Aktuelle URL für den Link

// Portal-Einstellungen abrufen
$sql = "SELECT * FROM settings WHERE id = 1";
$stmt = $conn->prepare($sql);
$stmt->execute();
$portal_settings = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Messagebox-Funktion
function display_message($message)
{
    if (!empty($message)) {
        echo $message;
    }
}

// Logos und Favicon speichern
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_logos'])) {
    // Alte Werte aus der Datenbank holen
    $sql = "SELECT logo_white, logo_dark, favicon, favicon_version FROM settings WHERE id = 1";
    $result = $conn->query($sql);
    $portal_settings = $result->fetch_assoc();

    $logo_white_path = $portal_settings['logo_white'];
    $logo_dark_path = $portal_settings['logo_dark'];
    $favicon_path = $portal_settings['favicon'];
    $favicon_version = $portal_settings['favicon_version']; // Favicon-Version

    $changes = [];
    $has_changes = false;

    // Ermittlung des Basis-URLs relativ zum Webserver-Root
    $base_url = rtrim(str_replace($_SERVER['DOCUMENT_ROOT'], '', realpath(__DIR__ . '/../')), '/') . '/';

    // Erhalte den absoluten Pfad des Root-Verzeichnisses
    $document_root = realpath($_SERVER['DOCUMENT_ROOT']);

    // White Mode Logo verarbeiten
    if (isset($_POST['delete_logo_white'])) {
        if (file_exists($logo_white_path)) {
            unlink($logo_white_path);
        }
        $logo_white_path = null;
        $changes[] = "<li><strong>{$GLOBALS['lang']['logo_white']}:</strong> {$GLOBALS['lang']['deleted']}</li>";
        $has_changes = true;
    } elseif (!empty($_FILES['logo_white']['name'])) {
        $target_dir = $document_root . $base_url . "assets/images/";
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0755, true); // Stelle sicher, dass das Verzeichnis existiert
        }
        $target_file = $target_dir . basename($_FILES["logo_white"]["name"]);
        if (move_uploaded_file($_FILES["logo_white"]["tmp_name"], $target_file)) {
            $logo_white_path = $base_url . "assets/images/" . basename($_FILES["logo_white"]["name"]);
            $changes[] = "<li><strong>{$GLOBALS['lang']['logo_white']}:</strong> {$GLOBALS['lang']['changed_to']} " . htmlspecialchars($logo_white_path) . "</li>";
            $has_changes = true;
        }
    }

    // Dark Mode Logo verarbeiten
    if (isset($_POST['delete_logo_dark'])) {
        if (file_exists($logo_dark_path)) {
            unlink($logo_dark_path);
        }
        $logo_dark_path = null;
        $changes[] = "<li><strong>{$GLOBALS['lang']['logo_dark']}:</strong> {$GLOBALS['lang']['deleted']}</li>";
        $has_changes = true;
    } elseif (!empty($_FILES['logo_dark']['name'])) {
        $target_dir = $document_root . $base_url . "assets/images/";
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0755, true); // Stelle sicher, dass das Verzeichnis existiert
        }
        $target_file = $target_dir . basename($_FILES["logo_dark"]["name"]);
        if (move_uploaded_file($_FILES["logo_dark"]["tmp_name"], $target_file)) {
            $logo_dark_path = $base_url . "assets/images/" . basename($_FILES["logo_dark"]["name"]);
            $changes[] = "<li><strong>{$GLOBALS['lang']['logo_dark']}:</strong> {$GLOBALS['lang']['changed_to']} " . htmlspecialchars($logo_dark_path) . "</li>";
            $has_changes = true;
        }
    }

    // Favicon verarbeiten
    if (isset($_POST['delete_favicon'])) {
        if (file_exists($favicon_path)) {
            unlink($favicon_path);
        }
        $favicon_path = null;
        $changes[] = "<li><strong>{$GLOBALS['lang']['favicon']}:</strong> {$GLOBALS['lang']['deleted']}</li>";
        $has_changes = true;
    } elseif (!empty($_FILES['favicon']['name'])) {
        $target_dir = $document_root . $base_url . "assets/icons/";
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0755, true); // Stelle sicher, dass das Verzeichnis existiert
        }
        $target_file = $target_dir . basename($_FILES["favicon"]["name"]);
        if (move_uploaded_file($_FILES["favicon"]["tmp_name"], $target_file)) {
            $favicon_path = $base_url . "assets/icons/" . basename($_FILES["favicon"]["name"]);
            $changes[] = "<li><strong>{$GLOBALS['lang']['favicon']}:</strong> {$GLOBALS['lang']['changed_to']} " . htmlspecialchars($favicon_path) . "</li>";
            $has_changes = true;
        }
    }

    // Wenn Änderungen vorhanden sind, aktualisieren wir die Version des Favicons
    if ($has_changes) {
        $favicon_version++;
        $sql = "UPDATE settings SET logo_white = ?, logo_dark = ?, favicon = ?, favicon_version = ? WHERE id = 1";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssi", $logo_white_path, $logo_dark_path, $favicon_path, $favicon_version);

        if ($stmt->execute()) {
            $save_logo_message = "<div class='alert alert-success'>{$lang['logos_saved_success']}</div>";
            // E-Mail-Benachrichtigung
            $intro_text = $lang['logos_favicon_updated'];
            $change_details = "<ul>" . implode("", $changes) . "</ul>";
            send_change_notification($intro_text, $change_details, $lang['logo_favicon_change_subject'], $conn);
        } else {
            $save_logo_message = "<div class='alert alert-danger'>{$lang['logos_saved_error']}</div>";
        }
        $stmt->close();
    } else {
        $save_logo_message = "<div class='alert alert-info'>{$lang['no_changes']}</div>";
    }
}

// Farben speichern
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_colors'])) {
    // Zuerst die aktuellen Werte aus der Datenbank abrufen
    $sql = "SELECT primary_color, secondary_color FROM settings WHERE id = 1";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $result = $stmt->get_result();
    $current_colors = $result->fetch_assoc();
    $stmt->close();

    // Neue Werte aus dem Formular abrufen
    $primary_color = $_POST['primary_color'];
    $secondary_color = $_POST['secondary_color'];

    $changes = [];
    $has_changes = false;

    // Vergleich der alten und neuen Primärfarbe
    if ($current_colors['primary_color'] !== $primary_color) {
        $changes[] = "<li><strong>{$lang['primary_color']}:</strong> {$lang['changed_from']} " . htmlspecialchars($current_colors['primary_color']) . " {$lang['to']} " . htmlspecialchars($primary_color) . "</li>";
        $has_changes = true;
    }

    // Vergleich der alten und neuen Sekundärfarbe
    if ($current_colors['secondary_color'] !== $secondary_color) {
        $changes[] = "<li><strong>{$lang['secondary_color']}:</strong> {$lang['changed_from']} " . htmlspecialchars($current_colors['secondary_color']) . " {$lang['to']} " . htmlspecialchars($secondary_color) . "</li>";
        $has_changes = true;
    }

    // Wenn Änderungen vorhanden sind, aktualisieren und Benachrichtigung senden
    if ($has_changes) {
        $sql = "UPDATE settings SET primary_color = ?, secondary_color = ? WHERE id = 1";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ss", $primary_color, $secondary_color);

        if ($stmt->execute()) {
            $save_color_message = "<div class='alert alert-success'>{$lang['colors_saved_success']}</div>";
            // E-Mail-Benachrichtigung über erfolgreiche Änderungen
            $intro_text = $lang['colors_updated'];
            $change_details = "<ul>" . implode("", $changes) . "</ul>";
            send_change_notification($intro_text, $change_details, $lang['colors_change_subject'], $conn);
        } else {
            $save_color_message = "<div class='alert alert-danger'>{$lang['colors_saved_error']}</div>";
        }
        $stmt->close();
    } else {
        $save_color_message = "<div class='alert alert-info'>{$lang['no_changes']}</div>";
    }
}

// Footer speichern
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_footer'])) {
    // Zuerst die aktuellen Werte aus der Datenbank abrufen
    $sql = "SELECT footer_text, footer_option, foundation_year, show_copyright FROM settings WHERE id = 1";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $result = $stmt->get_result();
    $current_footer = $result->fetch_assoc();
    $stmt->close();

    // Neue Werte aus dem Formular abrufen
    $footer_text = $_POST['footer_text'];
    $footer_option = $_POST['footer_option'];
    $foundation_year = $_POST['foundation_year'] ?? null;
    $show_copyright = isset($_POST['show_copyright']) ? 1 : 0; // Checkbox-Wert behandeln

    $changes = [];
    $has_changes = false;

    // Vergleich der alten und neuen Footer-Text
    if ($current_footer['footer_text'] !== $footer_text) {
        $changes[] = "<li><strong>{$lang['footer_text']}:</strong> {$lang['changed']}</li>";
        $has_changes = true;
    }

    // Vergleich der alten und neuen Footer-Option
    if ($current_footer['footer_option'] !== $footer_option) {
        $changes[] = "<li><strong>{$lang['footer_option']}:</strong> {$lang['changed_from']} " . htmlspecialchars($current_footer['footer_option']) . " {$lang['to']} " . htmlspecialchars($footer_option) . "</li>";
        $has_changes = true;
    }

    // Vergleich der alten und neuen Gründungsjahr
    if ($current_footer['foundation_year'] != $foundation_year) {
        $changes[] = "<li><strong>{$lang['foundation_year']}:</strong> {$lang['changed_from']} " . htmlspecialchars($current_footer['foundation_year']) . " {$lang['to']} " . htmlspecialchars($foundation_year) . "</li>";
        $has_changes = true;
    }

    // Vergleich der alten und neuen Copyright-Zeichen-Option
    if ($current_footer['show_copyright'] != $show_copyright) {
        $changes[] = "<li><strong>{$lang['show_copyright']}:</strong> " . ($show_copyright ? $lang['enabled'] : $lang['disabled']) . "</li>";
        $has_changes = true;
    }

    // Wenn Änderungen vorhanden sind, aktualisieren und Benachrichtigung senden
    if ($has_changes) {
        $sql = "UPDATE settings SET footer_text = ?, footer_option = ?, foundation_year = ?, show_copyright = ? WHERE id = 1";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssii", $footer_text, $footer_option, $foundation_year, $show_copyright);

        if ($stmt->execute()) {
            $save_footer_message = "<div class='alert alert-success'>{$lang['footer_saved_success']}</div>";
            // E-Mail-Benachrichtigung über erfolgreiche Änderungen
            $intro_text = $lang['footer_updated'];
            $change_details = "<ul>" . implode("", $changes) . "</ul>";
            send_change_notification($intro_text, $change_details, $lang['footer_change_subject'], $conn);
        } else {
            $save_footer_message = "<div class='alert alert-danger'>{$lang['footer_saved_error']}</div>";
        }
        $stmt->close();
    } else {
        $save_footer_message = "<div class='alert alert-info'>{$lang['no_changes']}</div>";
    }
}

// OpenWeather API-Schlüssel speichern
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_openweather'])) {
    // Zuerst die aktuellen Werte aus der Datenbank abrufen
    $sql = "SELECT openweather_api_key FROM settings WHERE id = 1";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $result = $stmt->get_result();
    $current_openweather = $result->fetch_assoc();
    $stmt->close();

    // Neue Werte aus dem Formular abrufen
    $openweather_api_key = $_POST['openweather_api_key'];

    $has_changes = $current_openweather['openweather_api_key'] !== $openweather_api_key;

    if ($has_changes) {
        $sql = "UPDATE settings SET openweather_api_key = ? WHERE id = 1";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $openweather_api_key);

        if ($stmt->execute()) {
            $save_openweather_message = "<div class='alert alert-success'>{$lang['openweather_saved_success']}</div>";
            // E-Mail-Benachrichtigung über erfolgreiche Änderungen
            $intro_text = $lang['openweather_updated'];
            $change_details = "<ul><li>API-Schlüssel: {$lang['changed']}</li></ul>";
            send_change_notification($intro_text, $change_details, $lang['openweather_change_subject'], $conn);
        } else {
            $save_openweather_message = "<div class='alert alert-danger'>{$lang['openweather_saved_error']}</div>";
        }
        $stmt->close();
    } else {
        $save_openweather_message = "<div class='alert alert-info'>{$lang['no_changes']}</div>";
    }
}

// Erfolgsmeldung aus der Session holen, falls vorhanden
$save_settings_message = isset($_SESSION['save_settings_message']) ? $_SESSION['save_settings_message'] : '';

// Erfolgsmeldung aus der Session löschen
unset($_SESSION['save_settings_message']);

// Website-Einstellungen speichern
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_settings'])) {
    // Zuerst die aktuellen Werte aus der Datenbank abrufen
    $sql = "SELECT site_title, company_name, email_signature_name, greeting_text, base_url, language, show_boxes FROM settings WHERE id = 1";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $result = $stmt->get_result();
    $current_settings = $result->fetch_assoc();
    $stmt->close();

    // Neue Werte aus dem Formular abrufen
    $site_title = $_POST['site_title'];
    $company_name = $_POST['company_name'];
    $email_signature_name = $_POST['email_signature_name'];
    $greeting_text = $_POST['greeting_text'];
    $base_url = $_POST['base_url'];
    $language = $_POST['language']; // Neue Sprachvariable
    $show_boxes = isset($_POST['show_boxes']) ? 1 : 0; // Zustand der Boxen

    $changes = [];
    $has_changes = false;

    // Vergleich der alten und neuen Site Title
    if ($current_settings['site_title'] !== $site_title) {
        $changes[] = "<li><strong>{$lang['site_title']}:</strong> {$lang['changed_from']} " . htmlspecialchars($current_settings['site_title']) . " {$lang['to']} " . htmlspecialchars($site_title) . "</li>";
        $has_changes = true;
    }

    // Vergleich der alten und neuen Company Name
    if ($current_settings['company_name'] !== $company_name) {
        $changes[] = "<li><strong>{$lang['company_name']}:</strong> {$lang['changed_from']} " . htmlspecialchars($current_settings['company_name']) . " {$lang['to']} " . htmlspecialchars($company_name) . "</li>";
        $has_changes = true;
    }

    // Vergleich der alten und neuen Email Signature Name
    if ($current_settings['email_signature_name'] !== $email_signature_name) {
        $changes[] = "<li><strong>{$lang['email_signature_name']}:</strong> {$lang['changed_from']} " . htmlspecialchars($current_settings['email_signature_name']) . " {$lang['to']} " . htmlspecialchars($email_signature_name) . "</li>";
        $has_changes = true;
    }

    // Vergleich der alten und neuen Greeting Text
    if ($current_settings['greeting_text'] !== $greeting_text) {
        $changes[] = "<li><strong>{$lang['greeting_text']}:</strong> {$lang['changed_from']} " . htmlspecialchars($current_settings['greeting_text']) . " {$lang['to']} " . htmlspecialchars($greeting_text) . "</li>";
        $has_changes = true;
    }

    // Vergleich der alten und neuen Basis URL
    if ($current_settings['base_url'] !== $base_url) {
        $changes[] = "<li><strong>{$lang['base_url']}:</strong> {$lang['changed_from']} " . htmlspecialchars($current_settings['base_url']) . " {$lang['to']} " . htmlspecialchars($base_url) . "</li>";
        $has_changes = true;
    }

    // Vergleich der alten und neuen Sprache
    if ($current_settings['language'] !== $language) {
        $changes[] = "<li><strong>{$lang['language']}:</strong> {$lang['changed_from']} " . htmlspecialchars($current_settings['language']) . " {$lang['to']} " . htmlspecialchars($language) . "</li>";
        $has_changes = true;
    }

    if ($current_settings['show_boxes'] !== $show_boxes) {
        $changes[] = "<li><strong>{$lang['show_boxes']}:</strong> " . ($show_boxes ? $lang['enabled'] : $lang['disabled']) . "</li>";
        $has_changes = true;
    }

    // Wenn Änderungen vorhanden sind, aktualisieren und Benachrichtigung senden
    if ($has_changes) {
        $sql = "UPDATE settings SET site_title = ?, company_name = ?, email_signature_name = ?, greeting_text = ?, base_url = ?, language = ?, show_boxes = ? WHERE id = 1";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssssssi", $site_title, $company_name, $email_signature_name, $greeting_text, $base_url, $language, $show_boxes);

        if ($stmt->execute()) {
            // Sprachdatei neu laden basierend auf der neuen Sprache
            $language_file = __DIR__ . "/languages/$language.php";
            if (file_exists($language_file)) {
                include $language_file;
            } else {
                include __DIR__ . '/languages/en.php'; // Fallback auf Englisch
            }

            // Erfolgsmeldung festlegen
            $_SESSION['save_settings_message'] = "<div class='alert alert-success'>{$lang['settings_saved_success']}</div>";

            // E-Mail-Benachrichtigung über erfolgreiche Änderungen
            $intro_text = $lang['settings_updated'];
            $change_details = "<ul>" . implode("", $changes) . "</ul>";
            send_change_notification($intro_text, $change_details, $lang['settings_change_subject'], $conn);

            // Seite neu laden, um die neue Sprache anzuwenden
            header("Location: " . $_SERVER['REQUEST_URI']);
            exit();  // Skript nach dem Redirect beenden
        } else {
            $save_settings_message = "<div class='alert alert-danger'>{$lang['settings_saved_error']}</div>";
        }
        $stmt->close();
    } else {
        $save_settings_message = "<div class='alert alert-info'>{$lang['no_changes']}</div>";
    }
}

// Link Shortener API-Einstellungen speichern
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_link_shortener'])) {
    // Zuerst die aktuellen Werte aus der Datenbank abrufen
    $sql = "SELECT link_shortener_api_url, link_shortener_api_token, link_shortener_enabled, base_url FROM settings WHERE id = 1";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $result = $stmt->get_result();
    $current_link_shortener = $result->fetch_assoc();
    $stmt->close();

    // Neue Werte aus dem Formular abrufen
    $link_shortener_api_url = $_POST['link_shortener_api_url'];
    $link_shortener_api_token = $_POST['link_shortener_api_token'];
    $link_shortener_enabled = isset($_POST['link_shortener_enabled']) ? 1 : 0;

    $has_changes =
        $current_link_shortener['link_shortener_api_url'] !== $link_shortener_api_url ||
        $current_link_shortener['link_shortener_api_token'] !== $link_shortener_api_token ||
        $current_link_shortener['link_shortener_enabled'] != $link_shortener_enabled;

    if ($has_changes) {
        $sql = "UPDATE settings SET link_shortener_api_url = ?, link_shortener_api_token = ?, link_shortener_enabled = ? WHERE id = 1";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssi", $link_shortener_api_url, $link_shortener_api_token, $link_shortener_enabled);

        if ($stmt->execute()) {
            $save_link_shortener_message = "<div class='alert alert-success'>{$lang['link_shortener_saved_success']}</div>";
            // E-Mail-Benachrichtigung über erfolgreiche Änderungen
            $intro_text = $lang['link_shortener_updated'];
            $change_details = "<ul><li>API URL: {$lang['changed']}</li><li>API Token: {$lang['changed']}</li><li>{$lang['enabled']}: " . ($link_shortener_enabled ? $lang['enabled'] : $lang['disabled']) . "</li></ul>";
            send_change_notification($intro_text, $change_details, $lang['link_shortener_change_subject'], $conn);
        } else {
            $save_link_shortener_message = "<div class='alert alert-danger'>{$lang['link_shortener_saved_error']}</div>";
        }
        $stmt->close();
    } else {
        $save_link_shortener_message = "<div class='alert alert-info'>{$lang['no_changes']}</div>";
    }
}

// Funktion zum Senden einer Benachrichtigung
function send_change_notification($intro_text, $details, $subject, $conn)
{
    $smtp_settings = get_smtp_settings($conn);
    global $current_url;

    $message_content = "<p>$intro_text</p>$details<p>{$GLOBALS['lang']['view_settings_link']} <a href='$current_url' style='text-decoration: underline;'>{$GLOBALS['lang']['view_here']}</a>.</p>";

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

// Funktion zum Abrufen der SMTP-Einstellungen
function get_smtp_settings($conn)
{
    $sql = "SELECT smtp_host, smtp_port, smtp_user, smtp_password, smtp_encryption FROM smtp_settings WHERE id = 1";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $smtp_settings = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $smtp_settings;
}

// Portal-Einstellungen nach dem Speichern erneut abrufen
$sql = "SELECT * FROM settings WHERE id = 1";
$stmt = $conn->prepare($sql);
$stmt->execute();
$portal_settings = $stmt->get_result()->fetch_assoc();

// Nur schließen, wenn wirklich fertig
if ($stmt) {
    $stmt->close();
}
if ($conn) {
    $conn->close();
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
    $page_specific_title = $lang['main_settings']; ?>
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
                <h2><?php echo $lang['main_settings']; ?></h2>
                <p><?php echo $lang['experience_tagline']; ?></p>
            </div>
        </div>

        <!-- Website Settings -->
        <div class="card mb-5">
            <div class="card-body">
                <h3><?php echo $lang['website_settings']; ?></h3>
                <?php display_message($save_settings_message); ?>
                <form method="post" action="portal_settings.php">
                    <input type="hidden" name="save_settings" value="1">

                    <div class="mb-3">
                        <label for="site_title" class="form-label"><?php echo $lang['site_title']; ?></label>
                        <input type="text" class="form-control" id="site_title" name="site_title"
                            value="<?php echo htmlspecialchars($portal_settings['site_title']); ?>">
                    </div>
                    <div class="mb-3">
                        <label for="company_name" class="form-label"><?php echo $lang['company_name']; ?></label>
                        <input type="text" class="form-control" id="company_name" name="company_name"
                            value="<?php echo htmlspecialchars($portal_settings['company_name']); ?>">
                    </div>
                    <div class="mb-3">
                        <label for="email_signature_name"
                            class="form-label"><?php echo $lang['email_signature_name']; ?></label>
                        <input type="text" class="form-control" id="email_signature_name" name="email_signature_name"
                            value="<?php echo htmlspecialchars($portal_settings['email_signature_name']); ?>">
                    </div>
                    <div class="mb-3">
                        <label for="greeting_text" class="form-label"><?php echo $lang['greeting_text']; ?></label>
                        <textarea class="form-control" id="greeting_text"
                        name="greeting_text"><?php echo htmlspecialchars($portal_settings['greeting_text']); ?></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="base_url"
                            class="form-label"><?php echo $lang['base_url']; ?></label>
                        <input type="text" class="form-control" id="base_url" name="base_url"
                            value="<?php echo htmlspecialchars($portal_settings['base_url']); ?>">
                    </div>

                    <div class="mb-3">
                        <label for="language"
                            class="form-label tight-spacing"><?php echo $lang['language']; ?></label><br>
                        <small class="form-text text-muted">
                            <?php echo $lang['language_note']; ?>
                        </small>
                        <select class="form-control" id="language" name="language">
                            <option value="de" <?php if ($portal_settings['language'] == 'de')
                                echo 'selected'; ?>>Deutsch
                                (German)</option>
                            <option value="en" <?php if ($portal_settings['language'] == 'en')
                                echo 'selected'; ?>>English
                                (English)</option>
                            <option value="es" <?php if ($portal_settings['language'] == 'es')
                                echo 'selected'; ?>>Español
                                (Spanish)</option>
                            <option value="zh" <?php if ($portal_settings['language'] == 'zh')
                                echo 'selected'; ?>>中文
                                (Chinese)</option>
                            <option value="fr" <?php if ($portal_settings['language'] == 'fr')
                                echo 'selected'; ?>>
                                Français (French)</option>
                            <option value="pt" <?php if ($portal_settings['language'] == 'pt')
                                echo 'selected'; ?>>
                                Português (Portuguese)</option>
                            <option value="ja" <?php if ($portal_settings['language'] == 'ja')
                                echo 'selected'; ?>>日本語
                                (Japanese)</option>
                            <option value="it" <?php if ($portal_settings['language'] == 'it')
                                echo 'selected'; ?>>
                                Italiano (Italian)</option>
                            <option value="ar" <?php if ($portal_settings['language'] == 'ar')
                                echo 'selected'; ?>>العربية
                                (Arabic)</option>
                            <option value="tr" <?php if ($portal_settings['language'] == 'tr')
                                echo 'selected'; ?>>Türkçe
                                (Turkish)</option>
                            <!-- Weitere Sprachen hier hinzufügen -->
                        </select>
                    </div>

                    <!-- Show/Hide Boxes Setting -->
                    <div class="mb-3">
                        <div class="mb-3 form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="show_boxes" name="show_boxes" <?php echo $portal_settings['show_boxes'] ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="show_boxes">
                                <?php echo $lang['show_hide_date_time_weather']; ?>
                            </label>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary"><?php echo $lang['save']; ?></button>
                </form>
            </div>
        </div>

        <div class="card mb-5">
            <div class="card-body">
                <h3><?php echo $lang['logos_favicon']; ?></h3>
                <?php display_message($save_logo_message); ?>
                <form method="post" action="portal_settings.php" enctype="multipart/form-data">
                    <input type="hidden" name="save_logos" value="1">

                    <div class="row mb-3 align-items-center">
                        <div class="col-lg-2 col-md-3">
                            <label for="logo_white"><?php echo $lang['logo_white']; ?></label>
                        </div>
                        <div class="col-lg-1 col-md-1 text-center"
                            style="background-color: #a6a6a6; padding: 10px; border-radius: 4px;">
                            <?php if (!empty($portal_settings['logo_white'])): ?>
                                <img src="<?php echo $base_url . htmlspecialchars($portal_settings['logo_white']); ?>"
                                    alt="<?php echo $lang['logo_white_preview']; ?>" class="img-fluid"
                                    style="max-width: 60px; max-height: 60px;">
                            <?php endif; ?>
                        </div>
                        <div class="col-lg-3 col-md-3">
                            <input type="text" class="form-control" id="logo_white_url" name="logo_white_url"
                                placeholder="<?php echo $lang['logo_url_placeholder']; ?>"
                                value="<?php echo htmlspecialchars($portal_settings['logo_white']); ?>">
                        </div>
                        <div class="col-lg-4 col-md-4">
                            <input type="file" class="form-control" id="logo_white" name="logo_white" accept="image/*">
                        </div>
                        <div class="col-lg-1 col-md-1 text-end">
                            <?php if (!empty($portal_settings['logo_white'])): ?>
                                <button type="submit" name="delete_logo_white"
                                    class="btn btn-danger btn-sm"><?php echo $lang['delete']; ?></button>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="row mb-3 align-items-center">
                        <div class="col-lg-2 col-md-3">
                            <label for="logo_dark"><?php echo $lang['logo_dark']; ?></label>
                        </div>
                        <div class="col-lg-1 col-md-1 text-center"
                            style="background-color: #a6a6a6; padding: 10px; border-radius: 4px;">
                            <?php if (!empty($portal_settings['logo_dark'])): ?>
                                <img src="<?php echo $base_url . htmlspecialchars($portal_settings['logo_dark']); ?>"
                                    alt="<?php echo $lang['logo_dark_preview']; ?>" class="img-fluid"
                                    style="max-width: 60px; max-height: 60px;">
                            <?php endif; ?>
                        </div>
                        <div class="col-lg-3 col-md-3">
                            <input type="text" class="form-control" id="logo_dark_url" name="logo_dark_url"
                                placeholder="<?php echo $lang['logo_url_placeholder']; ?>"
                                value="<?php echo htmlspecialchars($portal_settings['logo_dark']); ?>">
                        </div>
                        <div class="col-lg-4 col-md-4">
                            <input type="file" class="form-control" id="logo_dark" name="logo_dark" accept="image/*">
                        </div>
                        <div class="col-lg-1 col-md-1 text-end">
                            <?php if (!empty($portal_settings['logo_dark'])): ?>
                                <button type="submit" name="delete_logo_dark"
                                    class="btn btn-danger btn-sm"><?php echo $lang['delete']; ?></button>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="row mb-3 align-items-center">
                        <div class="col-lg-2 col-md-3">
                            <label for="favicon"><?php echo $lang['favicon']; ?></label>
                        </div>
                        <div class="col-lg-1 col-md-1 text-center"
                            style="background-color: #a6a6a6; padding: 10px; border-radius: 4px;">
                            <?php if (!empty($portal_settings['favicon'])): ?>
                                <img src="<?php echo $base_url . htmlspecialchars($portal_settings['favicon']); ?>"
                                    alt="<?php echo $lang['favicon_preview']; ?>" class="img-fluid"
                                    style="max-width: 60px; max-height: 60px;">
                            <?php endif; ?>
                        </div>
                        <div class="col-lg-3 col-md-3">
                            <input type="text" class="form-control" id="favicon_url" name="favicon_url"
                                placeholder="<?php echo $lang['favicon_url_placeholder']; ?>"
                                value="<?php echo htmlspecialchars($portal_settings['favicon'] ?? ''); ?>">
                        </div>
                        <div class="col-lg-4 col-md-4">
                            <input type="file" class="form-control" id="favicon" name="favicon" accept="image/*">
                        </div>
                        <div class="col-lg-1 col-md-1 text-end">
                            <?php if (!empty($portal_settings['favicon'])): ?>
                                <button type="submit" name="delete_favicon"
                                    class="btn btn-danger btn-sm"><?php echo $lang['delete']; ?></button>
                            <?php endif; ?>
                        </div>
                        <small class="form-text text-muted mt-1">
                            <?php echo $lang['favicon_recommendation']; ?><br>
                            <strong><?php echo $lang['warning']; ?>:</strong>
                            <?php echo $lang['favicon_safari_cache_warning']; ?>
                            <span class="tooltip-text"><?php echo $lang['how_to_reset_cache']; ?>
                                <span class="tooltip-content"><?php echo $lang['reset_cache_instructions']; ?></span>
                            </span>
                        </small>
                    </div>

                    <button type="submit" class="btn btn-primary mt-3"><?php echo $lang['upload_and_save']; ?></button>
                </form>
            </div>
        </div>

        <div class="card mb-5">
            <div class="card-body">
                <h3><?php echo $lang['colors']; ?></h3>
                <?php display_message($save_color_message); ?>
                <form method="post" action="portal_settings.php">
                    <input type="hidden" name="save_colors" value="1">

                    <div class="row mb-3 align-items-center">
                        <div class="col-lg-2 col-md-3">
                            <label for="primary_color"><?php echo $lang['primary_color']; ?></label>
                        </div>
                        <div class="col-lg-2 col-md-2">
                            <input type="color" class="form-control form-control-color" id="primary_color"
                                name="primary_color"
                                value="<?php echo htmlspecialchars($portal_settings['primary_color']); ?>"
                                style="width: 100%;" oninput="syncPrimaryColor()">
                        </div>
                        <div class="col-lg-8 col-md-7">
                            <input type="text" class="form-control" id="primary_color_hex" name="primary_color_hex"
                                placeholder="#000000" pattern="^#([A-Fa-f0-9]{6})$"
                                value="<?php echo htmlspecialchars($portal_settings['primary_color']); ?>"
                                style="width: 100%;" oninput="syncPrimaryHex()">
                        </div>
                    </div>

                    <div class="row mb-3 align-items-center">
                        <div class="col-lg-2 col-md-3">
                            <label for="secondary_color"><?php echo $lang['secondary_color']; ?></label>
                        </div>
                        <div class="col-lg-2 col-md-2">
                            <input type="color" class="form-control form-control-color" id="secondary_color"
                                name="secondary_color"
                                value="<?php echo htmlspecialchars($portal_settings['secondary_color']); ?>"
                                style="width: 100%;" oninput="syncSecondaryColor()">
                        </div>
                        <div class="col-lg-8 col-md-7">
                            <input type="text" class="form-control" id="secondary_color_hex" name="secondary_color_hex"
                                placeholder="#000000" pattern="^#([A-Fa-f0-9]{6})$"
                                value="<?php echo htmlspecialchars($portal_settings['secondary_color']); ?>"
                                style="width: 100%;" oninput="syncSecondaryHex()">
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary mt-3"><?php echo $lang['save']; ?></button>
                </form>
            </div>
        </div>


        <!-- Footer -->
        <div class="card mb-5">
            <div class="card-body">
                <h3><?php echo $lang['footer']; ?></h3>
                <?php display_message($save_footer_message); ?>
                <form method="post" action="portal_settings.php">
                    <input type="hidden" name="save_footer" value="1">

                    <div class="mb-3">
                        <label for="footer_text" class="form-label"><?php echo $lang['footer_text']; ?></label>
                        <input type="text" class="form-control" id="footer_text" name="footer_text"
                            value="<?php echo htmlspecialchars($portal_settings['footer_text']); ?>">
                    </div>
                    <div class="mb-3">
                        <label for="footer_option" class="form-label"><?php echo $lang['footer_option']; ?></label>
                        <select class="form-control" id="footer_option" name="footer_option">
                            <option value="text_only" <?php if ($portal_settings['footer_option'] == 'text_only')
                                echo 'selected'; ?>><?php echo $lang['text_only']; ?></option>
                            <option value="current_year_text" <?php if ($portal_settings['footer_option'] == 'current_year_text')
                                echo 'selected'; ?>><?php echo $lang['current_year_text']; ?></option>
                            <option value="foundation_year_text" <?php if ($portal_settings['footer_option'] == 'foundation_year_text')
                                echo 'selected'; ?>>
                                <?php echo $lang['foundation_year_text']; ?>
                            </option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="foundation_year" class="form-label"><?php echo $lang['foundation_year']; ?>
                            (<?php echo $lang['if_selected']; ?>)</label>
                        <input type="number" class="form-control" id="foundation_year" name="foundation_year" min="1900"
                            max="2099" step="1"
                            value="<?php echo htmlspecialchars($portal_settings['foundation_year']); ?>">
                    </div>
                    <div class="mb-3">
                        <label for="show_copyright" class="form-label"><?php echo $lang['show_copyright']; ?></label>
                        <input type="checkbox" class="form-check-input" id="show_copyright" name="show_copyright"
                            value="1" <?php if ($portal_settings['show_copyright'])
                                echo 'checked'; ?>>
                    </div>

                    <button type="submit" class="btn btn-primary"><?php echo $lang['save']; ?></button>
                </form>
            </div>
        </div>


        <!-- OpenWeather API-Schlüssel -->
        <div class="card mb-5">
            <div class="card-body">
                <h3 class="mb-1"><?php echo $lang['openweather_api_key']; ?></h3>
                <p style="margin-bottom: 0.5rem;">
                    <a href="https://openweathermap.org" target="_blank" class="text-primary">
                        <?php echo $lang['visit_openweather_site']; ?>
                    </a>
                </p>
                <?php display_message($save_openweather_message); ?>
                <form method="post" action="portal_settings.php">
                    <input type="hidden" name="save_openweather" value="1">

                    <div class="form-row">
                        <label for="openweather_api_key" class="form-label"><?php echo $lang['api_key']; ?></label>
                        <input type="text" class="form-control" id="openweather_api_key" name="openweather_api_key"
                            value="<?php echo htmlspecialchars($portal_settings['openweather_api_key']); ?>">
                    </div>

                    <button type="submit" class="btn btn-primary mt-3"><?php echo $lang['save']; ?></button>
                </form>
            </div>
        </div>



        <!-- Link Shortener API-Einstellungen -->
        <div class="card mb-5">
            <div class="card-body">
                <h3 class="mb-1"><?php echo $lang['link_shortener_api_settings']; ?></h3>
                <p style="margin-bottom: 0.5rem;">
                    <a href="https://yourls.org/" target="_blank" class="text-primary">
                        <?php echo $lang['visit_link_shortener_site']; ?>
                    </a>
                </p>
                <?php display_message($save_link_shortener_message); ?>
                <form method="post" action="portal_settings.php">
                    <input type="hidden" name="save_link_shortener" value="1">

                    <div class="mb-3">
                        <label for="link_shortener_api_url" class="form-label"><?php echo $lang['api_url']; ?></label>
                        <input type="text" class="form-control" id="link_shortener_api_url"
                            name="link_shortener_api_url"
                            value="<?php echo htmlspecialchars($portal_settings['link_shortener_api_url'] ?? ''); ?>"
                            required>
                    </div>

                    <div class="mb-3">
                        <label for="link_shortener_api_token"
                            class="form-label"><?php echo $lang['api_token']; ?></label>
                        <input type="text" class="form-control" id="link_shortener_api_token"
                            name="link_shortener_api_token"
                            value="<?php echo htmlspecialchars($portal_settings['link_shortener_api_token'] ?? ''); ?>"
                            required>
                    </div>

                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="link_shortener_enabled"
                            name="link_shortener_enabled" <?php echo !empty($portal_settings['link_shortener_enabled']) ? 'checked' : ''; ?>>
                        <label class="form-check-label"
                            for="link_shortener_enabled"><?php echo $lang['enable_link_shortener']; ?></label>
                    </div>

                    <button type="submit" class="btn btn-primary mt-3"><?php echo $lang['save']; ?></button>
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