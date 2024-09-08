<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

session_start();

// Versuche, die Datei einzubinden, aber unterdrücke Fehler
if (!@include(__DIR__ . '/../src/config/dbconnect.php')) {
    // Wenn die Datei nicht eingebunden werden kann, leite auf die Fehlerseite weiter
    header("Location: ../errors/500.php");
    exit();
}

// Überprüfen, ob die Verbindung zur Datenbank fehlschlägt
if (!isset($conn) || !$conn instanceof mysqli || $conn->connect_error) {
    // Umleitung zur Fehlerseite, wenn keine Verbindung hergestellt werden konnte
    header("Location: ../errors/500.php");
    exit(); // Skript sofort beenden, um Fehler zu verhindern
}

$message = "";

// Überprüfen, ob der Installationsordner gelöscht werden soll
if (isset($_GET['deleteInstaller']) && $_GET['deleteInstaller'] === 'true') {
    deleteInstallerFolder(__DIR__ . '/../install'); // Installationsordner löschen
}

function deleteInstallerFolder($dir) {
    if (!is_dir($dir)) {
        return;
    }
    
    $files = array_diff(scandir($dir), ['.', '..']);
    
    foreach ($files as $file) {
        $filePath = "$dir/$file";
        if (is_dir($filePath)) {
            deleteInstallerFolder($filePath); // Rekursiv für Unterordner
        } else {
            unlink($filePath); // Lösche Datei
        }
    }
    
    // Lösche das Verzeichnis selbst
    rmdir($dir);
}

// Sprachdatei und Einstellungen aus der Datenbank abrufen
$sql = "SELECT logo_white, site_title, language FROM settings WHERE id = 1";
$stmt = $conn->prepare($sql);
$stmt->execute();
$settings = $stmt->get_result()->fetch_assoc();
$logo_white = $settings['logo_white'];
$site_title_vertical = $settings['site_title'];
$language = $settings['language'];
$stmt->close();

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
    $username = $_POST['username'];
    $password = $_POST['password'];

    $sql = "SELECT * FROM users WHERE username = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        if (password_verify($password, $user['password'])) {
            // Benutzerinformationen in der Session speichern
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['role'] = $user['role']; // Rolle des Benutzers speichern (z.B. admin, user)

            // Umleitung zur Startseite für alle Benutzer
            header("Location: ../index.php");
            exit();
        } else {
            $message = "<div class='message error'>{$lang['wrong_password']}</div>";
        }
    } else {
        $message = "<div class='message error'>{$lang['user_not_found']}</div>";
    }

    $stmt->close();
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
    $page_specific_title = $lang['login']; ?>
    <title><?php echo $page_specific_title . " | " . $site_title_vertical; ?></title>

    <!-- Favicon -->
    <?php include __DIR__ . '/../src/includes/favicon.php'; ?>

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
    <div class="login-box">
        <div class="logo-container">
            <img src="<?php echo htmlspecialchars($logo_white); ?>" alt="Logo">
        </div>
        <form method="post" action="login.php">
            <label for="username"><?php echo $lang['username']; ?>:</label>
            <input type="text" id="username" name="username" required><br>
            <label for="password"><?php echo $lang['password']; ?>:</label>
            <input type="password" id="password" name="password" required><br>
            <div class="options">
                <a href="password_reset.php"><?php echo $lang['forgot_password']; ?></a>
                <button type="submit"><?php echo $lang['login']; ?></button>
            </div>
            <?php echo $message; ?>
        </form>
    </div>
    <div class="vertical-container">
        <div class="vertical-text"><?php echo htmlspecialchars($site_title_vertical); ?></div>
    </div>
</body>
</html>
