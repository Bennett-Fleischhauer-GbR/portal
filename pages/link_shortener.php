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

include __DIR__ . '/../src/config/dbconnect.php';
require __DIR__ . '/../src/controllers/email_sender.php';

// Benutzersprache abrufen
$user_id = $_SESSION['user_id'] ?? null;
if ($user_id) {
    $sql = "SELECT user_language FROM users WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $user_language = $stmt->get_result()->fetch_assoc()['user_language'];
    $stmt->close();
    $language = $user_language ?? 'en';
} else {
    $language = 'en';
}

$language_file = __DIR__ . "/../src/languages/{$language}.php";

// Prüfen, ob die Sprachdatei existiert
if (file_exists($language_file)) {
    include $language_file;
} else {
    include __DIR__ . '/../src/languages/en.php';
}

// Link Shortener Einstellungen aus der Datenbank abrufen
$sql = "SELECT link_shortener_api_url, link_shortener_api_token, link_shortener_enabled FROM settings WHERE id = 1";
$stmt = $conn->prepare($sql);
$stmt->execute();
$link_shortener_settings = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Prüfen, ob die Link Shortener-Funktion aktiviert ist
if (!$link_shortener_settings['link_shortener_enabled']) {
    header("Location: /404.php");
    exit();
}

// Initialisiere Variablen
$short_url = '';
$error_message = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Abrufen der API-URL und des API-Tokens aus der Datenbank
    $api_url = $link_shortener_settings['link_shortener_api_url'];
    $api_token = $link_shortener_settings['link_shortener_api_token'];

    // Lange URL und Keyword aus dem Formular
    $url_to_shorten = $_POST['url_to_shorten'];
    $custom_keyword = $_POST['custom_keyword'] ?? '';

    // Daten für die POST-Anfrage vorbereiten
    $data = array(
        'url' => $url_to_shorten,
        'keyword' => $custom_keyword,
        'signature' => $api_token,
        'action' => 'shorturl',
        'format' => 'json'
    );

    // cURL-Session initialisieren
    $ch = curl_init();

    // Optionen für die cURL-Session setzen
    curl_setopt($ch, CURLOPT_URL, $api_url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    // Anfrage ausführen und Antwort speichern
    $response = curl_exec($ch);

    // cURL-Session schließen
    curl_close($ch);

    // Antwort dekodieren
    $result = json_decode($response, true);

    // Überprüfen, ob die Anfrage erfolgreich war
    if (isset($result['status']) && $result['status'] == 'success') {
        $short_url = $result['shorturl'];
    } else {
        $error_message = isset($result['message']) ? $result['message'] : 'Unbekannter Fehler';
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="<?php echo $language; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="author" content="Bennett & Fleischhauer GbR">
    <meta name="robots" content="noindex, nofollow">

    <!-- Titel der Seite -->
    <?php include __DIR__ . '/../src/includes/page_title.php'; 
    $page_specific_title = $lang['shorten_url']; ?>
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
            <h2><?php echo $lang['shorten_url_title']; ?></h2>
            <p><?php echo $lang['shorten_url_description']; ?></p>
        </div>
    </div>

    <div class="card mb-5">
        <div class="card-body">
            <h3><?php echo $lang['shorten_url']; ?></h3>
            <?php if ($short_url): ?>
                <div class='alert alert-success'>
                    <?php echo $lang['shorten_url_success']; ?>: 
                    <a href="<?php echo htmlspecialchars($short_url); ?>" target="_blank"><?php echo htmlspecialchars($short_url); ?></a>
                </div>
            <?php elseif ($error_message): ?>
                <div class='alert alert-danger'>
                    <?php echo $lang['shorten_url_error']; ?>: <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>
            <form method="post" action="">
                <div class="mb-3">
                    <label for="url_to_shorten" class="form-label"><?php echo $lang['long_url']; ?></label>
                    <input type="url" class="form-control" id="url_to_shorten" name="url_to_shorten" required>
                </div>
                <div class="mb-3">
                    <label for="custom_keyword" class="form-label"><?php echo $lang['custom_keyword']; ?> (<?php echo $lang['optional']; ?>)</label>
                    <input type="text" class="form-control" id="custom_keyword" name="custom_keyword">
                </div>
                <button type="submit" class="btn btn-primary"><?php echo $lang['shorten_button']; ?></button>
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
