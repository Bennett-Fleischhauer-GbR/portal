<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

session_start();

// Verbindung zur Datenbank herstellen
include __DIR__ . '/../../src/config/dbconnect.php';

// Sprachdatei und Einstellungen aus der Datenbank abrufen
$sql = "SELECT logo_dark, site_title, language FROM settings WHERE id = 1";
$stmt = $conn->prepare($sql);
$stmt->execute();
$settings = $stmt->get_result()->fetch_assoc();
$logo_dark = $settings['logo_dark'];
$site_title_vertical = $settings['site_title'];
$language = $settings['language'];
$stmt->close();

// Sprachdatei laden
$language_file = __DIR__ . "/../../src/languages/$language.php";

// Prüfen, ob die Sprachdatei existiert
if (file_exists($language_file)) {
    include $language_file;
} else {
    // Fallback auf Englisch, wenn die Sprachdatei nicht gefunden wird
    include __DIR__ . '/../../src/languages/en.php';
}
?>

<!DOCTYPE html>
<html lang="<?php echo $language; ?>">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="x-ua-compatible" content="ie=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="author" content="Bennett & Fleischhauer GbR">
    <meta name="robots" content="noindex, nofollow">

    <!-- Titel der Seite -->
    <title><?php echo $lang['403_title']; ?></title>

    <!-- Benutzerdefinierte Styles -->
    <link rel="stylesheet" href="../css/error.css">

    <!-- Dynamische Styles (falls notwendig) -->
    <?php include __DIR__ . '/../../src/includes/dynamic_styles.php'; ?>

    <style>
        .page:before {
            display: block;
            content: '';
            -webkit-box-flex: 0;
            -ms-flex: 0 1 474px;
            flex: 0 1 474px;
            background: var(--primary-color) url('<?php echo htmlspecialchars($logo_dark); ?>') 50% 50% no-repeat;
            background-size: 55% auto;
        }
    </style>
</head>

<body>
    <div class="page">
        <div class="main">
            <h1><?php echo $lang['server_error']; ?></h1>
            <div class="error-code">403</div>
            <h2><?php echo $lang['403_forbidden']; ?></h2>
            <p class="lead"><?php echo $lang['403_message']; ?></p>
            <hr />
            <p><?php echo $lang['what_you_can_do']; ?></p>
            <div class="help-actions">
                <a href="javascript:location.reload();"><?php echo $lang['reload_page']; ?></a>
                <a href="javascript:history.back();"><?php echo $lang['go_back']; ?></a>
                <a href="../index.php"><?php echo $lang['homepage']; ?></a>
            </div>
        </div>
    </div>
</body>

</html>