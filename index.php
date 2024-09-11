<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

session_start();

// Überprüfen, ob der Benutzer eingeloggt ist, andernfalls zur Login-Seite umleiten
if (!isset($_SESSION['user_id'])) {
    header("Location: auth/login.php");
    exit();
}

include 'src/config/dbconnect.php';

// Sprachdatei laden
$sql = "SELECT show_boxes AS admin_show_boxes FROM settings WHERE id = 1";
$stmt = $conn->prepare($sql);
$stmt->execute();
$portal_settings = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Benutzersprache und Temperatureinheit abrufen
$user_id = $_SESSION['user_id'];
$sql = "SELECT user_language, temperature_unit FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$userSettings = $stmt->get_result()->fetch_assoc();
$user_language = $userSettings['user_language'] ?? 'en'; // Fallback auf Englisch, falls keine Sprache eingestellt ist
$units = $userSettings['temperature_unit'] ?? 'metric'; // Fallback auf 'metric', falls keine Einheit eingestellt ist
$stmt->close();

$language = $user_language ?? 'en'; // Fallback auf Englisch, falls keine Sprache eingestellt ist

$language_file = __DIR__ . "/src/languages/{$language}.php";

// Prüfen, ob die Sprachdatei existiert
if (file_exists($language_file)) {
    include $language_file;
} else {
    // Fallback auf Englisch, wenn die Sprachdatei nicht gefunden wird
    include __DIR__ . '/src/languages/en.php';
}

// Benutzerinformationen abrufen
$sql = "SELECT first_name, email, city, role, box_order, show_boxes FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$first_name = $user['first_name'];
$email = $user['email'];
$city = $user['city'];
$role = $user['role'];
$savedOrderFromDB = explode(',', $user['box_order']);
$stmt->close();

// Überprüfen, ob Boxen angezeigt werden sollen
$show_boxes = $user['show_boxes'] && $portal_settings['admin_show_boxes'];

// Website-Einstellungen abrufen, um den Begrüßungstext anzuzeigen
$sql = "SELECT greeting_text FROM settings WHERE id = 1";
$result = $conn->query($sql);
$settings = $result->fetch_assoc();
$greeting_text = $settings['greeting_text'];

// Tageszeitabhängige Begrüßung
$currentHour = date("H");

if ($currentHour < 4) {
    $greeting = $lang['welcome'];
} elseif ($currentHour < 12) {
    $greeting = $lang['good_morning'];
} elseif ($currentHour < 14) {
    $greeting = $lang['welcome'];
} elseif ($currentHour < 18) {
    $greeting = $lang['good_afternoon'];
} elseif ($currentHour < 23) {
    $greeting = $lang['good_evening'];
} else {
    $greeting = $lang['welcome'];
}

// Funktion zum Abrufen des Favicons einer URL
function getFavicon($url)
{
    $cacheDir = 'json';
    $cacheFile = __DIR__ . '/src/cache/favicon_cache.json';

    if (!is_dir($cacheDir)) {
        mkdir($cacheDir, 0755, true);
    }

    // Sicherstellen, dass die URL mit 'http://' oder 'https://' beginnt
    if (!preg_match("~^(?:f|ht)tps?://~i", $url)) {
        $url = "http://" . $url;
    }

    $parsedUrl = parse_url($url);
    $baseDomain = $parsedUrl['scheme'] . '://' . $parsedUrl['host'];

    // Cache aus der Datei laden, falls vorhanden
    $cache = [];
    if (file_exists($cacheFile)) {
        $cache = json_decode(file_get_contents($cacheFile), true);
    }

    // Prüfen, ob das Favicon bereits im Cache ist
    if (isset($cache[$baseDomain])) {
        return $cache[$baseDomain];
    }

    // Favicon standardmäßig auf '/favicon.ico' setzen
    $html = @file_get_contents($baseDomain);
    $favicon = $baseDomain . '/favicon.ico';

    // HTML-Daten parsen, um nach einem <link> mit 'rel' Attributen zu suchen
    if ($html) {
        $dom = new DOMDocument();
        @$dom->loadHTML($html);

        $links = $dom->getElementsByTagName('link');
        foreach ($links as $link) {
            $rel = strtolower($link->getAttribute('rel'));
            if ($rel == 'icon' || $rel == 'shortcut icon' || $rel == 'apple-touch-icon') {
                $href = $link->getAttribute('href');

                // Überprüfen, ob der href-Wert ein absolutes oder relatives URL-Schema hat
                if (parse_url($href, PHP_URL_SCHEME) === null) {
                    $href = rtrim($baseDomain, '/') . '/' . ltrim($href, '/');
                }

                $favicon = $href;
                break;
            }
        }
    }

    // Favicon-URL im Cache speichern
    $cache[$baseDomain] = $favicon;
    file_put_contents($cacheFile, json_encode($cache));

    return $favicon;
}

// Funktion, um ein Standard-PDF-Icon zu verwenden
function getDefaultPdfIcon()
{
    // Korrekt auf das Verzeichnis zugreifen, das sich einen Ordner höher befindet
    return 'assets/icons/default-pdf-icon.png';
}


// OpenWeather API-Schlüssel und Einstellungen abrufen
$sql = "SELECT openweather_api_key FROM settings WHERE id = 1";
$result = $conn->query($sql);
$settings = $result->fetch_assoc();
$apiKey = $settings['openweather_api_key'];

$weatherError = '';
$currentWeather = '';
$currentTemp = '';

// Sprachzuordnung für die API-Anfrage
$languageMapping = [
    'de' => 'de',
    'en' => 'en',
    'es' => 'es',
    'zh' => 'zh_cn',
    'fr' => 'fr',
    'pt' => 'pt',
    'ja' => 'ja',
    'it' => 'it',
    'ar' => 'ar',
    'tr' => 'tr'
    // Füge weitere Sprachzuordnungen nach Bedarf hinzu
];

$apiLanguage = isset($languageMapping[$user_language]) ? $languageMapping[$user_language] : 'en'; // Standard: Englisch

$weatherError = '';
if (empty($apiKey)) {
    // Fehlermeldung, wenn der API-Schlüssel fehlt
    $weatherError = "<div class='alert alert-danger text-center' style='color: red;'><strong>Achtung!</strong> Der OpenWeather API-Schlüssel fehlt. Bitte in den Einstellungen hinzufügen.</div>";
}

// Wetterdaten mit der OpenWeather API abrufen oder aus der Datenbank laden
if (!empty($city) && empty($weatherError)) {
    // Abfrage der gespeicherten Wetterdaten für die Kombination aus Stadt, Sprache und Einheiten
    $stmt = $conn->prepare("SELECT weather_description, temperature, last_updated FROM weather_data WHERE city = ? AND language = ? AND units = ?");
    $stmt->bind_param("sss", $city, $apiLanguage, $units);
    $stmt->execute();
    $weatherData = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    // Überprüfen, ob die Daten älter als 30 Minuten sind oder nicht vorhanden sind
    if ($weatherData && strtotime($weatherData['last_updated']) > (time() - 1800)) {
        // Verwende gespeicherte Wetterdaten
        $currentWeather = $weatherData['weather_description'];
        $currentTemp = $weatherData['temperature'];
    } else {
        // Wetterdaten über die API abrufen, wenn kein Datensatz existiert oder Daten veraltet sind
        $weatherApiUrl = "https://api.openweathermap.org/data/2.5/weather?q=$city&appid=$apiKey&units=$units&lang=$apiLanguage";
        $weatherResponse = @file_get_contents($weatherApiUrl);

        if ($weatherResponse) {
            $weatherData = json_decode($weatherResponse, true);

            if (isset($weatherData['weather'][0]['description']) && isset($weatherData['main']['temp'])) {
                $currentWeather = $weatherData['weather'][0]['description'];
                $currentTemp = $weatherData['main']['temp'];

                // Wetterdaten in der Datenbank speichern oder aktualisieren
                $stmt = $conn->prepare("INSERT INTO weather_data (city, language, units, weather_description, temperature) 
                                        VALUES (?, ?, ?, ?, ?)
                                        ON DUPLICATE KEY UPDATE weather_description = VALUES(weather_description), 
                                        temperature = VALUES(temperature), last_updated = CURRENT_TIMESTAMP");
                $stmt->bind_param("ssssd", $city, $apiLanguage, $units, $currentWeather, $currentTemp);
                $stmt->execute();
                $stmt->close();
            } else {
                // API hat keine Wetterdaten zurückgegeben
                $weatherError = "<div class='alert alert-danger text-center' style='color: red;'><strong>Achtung!</strong> Fehler beim Abrufen der Wetterdaten.</div>";
            }
        } else {
            // Fehler beim Abrufen der API, möglicherweise aufgrund von erschöpften API-Aufrufen
            $weatherError = "<div class='alert alert-danger text-center' style='color: red;'><strong>Achtung!</strong> API-Anfrage fehlgeschlagen. Bitte überprüfen Sie den API-Schlüssel und die Stadt-Einstellungen.</div>";
        }
    }
} else if (empty($city)) {
    $weatherError = "<div class='alert alert-danger text-center' style='color: red;'><strong>Achtung!</strong> Stadt nicht eingestellt. Bitte konfigurieren Sie die Stadt in den Benutzereinstellungen.</div>";
}

// Funktion zum Senden einer E-Mail bei API-Fehlern
function sendWeatherApiErrorEmail($conn, $lang)
{
    // E-Mail-Inhalt vorbereiten
    $subject = $lang['weather_api_email_subject'];
    $header = $lang['weather_api_email_header'];
    $content = "<p>{$lang['weather_api_email_content']}</p>";

    // Empfänger-Emails abrufen (z.B. Admins)
    $stmt = $conn->prepare("SELECT email FROM users WHERE role = 'admin'");
    $stmt->execute();
    $result = $stmt->get_result();
    $admins = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // E-Mail an jeden Admin senden
    foreach ($admins as $admin) {
        send_email($admin['email'], $subject, $header, $content, get_smtp_settings($conn), $conn);
    }
}

// Kategorien und Links abrufen
$stmt = $conn->prepare("
    SELECT * 
    FROM categories
    ORDER BY position
");
$stmt->execute();
$categories = $stmt->get_result();

$hasLinks = false; // Variable zur Überprüfung, ob es mindestens einen Link gibt
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
    <?php include __DIR__ . '/src/includes/page_title.php';
    $page_specific_title = $lang['dashboard']; ?>
    <title><?php echo $page_specific_title . " | " . $site_title . " | " . $company_name; ?></title>

    <!-- Favicon -->
    <?php include __DIR__ . '/src/includes/favicon.php'; ?>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Font Awesome für Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">

    <!-- Benutzerdefinierte Styles -->
    <link rel="stylesheet" href="css/index.css">

    <!-- Dynamische Styles (falls notwendig) -->
    <?php include __DIR__ . '/src/includes/dynamic_styles.php'; ?>
</head>

<body>
    <!-- Header einbinden -->
    <?php include __DIR__ . '/src/includes/header.php'; ?>

    <!-- Hauptinhalt -->
    <div class="container main-content">
        <div class="row mb-4">
            <div class="col-12">
                <h2><?php echo $greeting; ?> <?php echo htmlspecialchars($first_name); ?>,</h2>
                <p><?php echo htmlspecialchars($greeting_text); ?></p>
            </div>
        </div>

        <!-- Boxen für Datum, Uhrzeit und Wetter -->
        <?php if ($show_boxes): ?>
            <div class="row row-equal-height mb-3" id="sortable-boxes">
                <div class="col-md-4 col-sm-6 mb-3" id="box1" data-id="box1">
                    <div class="card h-100">
                        <div class="card-body text-center">
                            <h5 class="card-title"><?php echo $lang['current_date']; ?></h5>
                            <p class="card-text" id="current-date"></p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 col-sm-6 mb-3" id="box2" data-id="box2">
                    <div class="card h-100">
                        <div class="card-body text-center">
                            <h5 class="card-title"><?php echo $lang['current_time']; ?></h5>
                            <p class="card-text" id="current-time"></p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 col-sm-12 mb-3" id="box3" data-id="box3">
                    <div class="card h-100">
                        <div class="card-body text-center">
                            <h5 class="card-title"><?php echo $lang['weather_in']; ?>     <?php echo htmlspecialchars($city); ?>
                            </h5>
                            <?php if ($weatherError): ?>
                                <!-- Zeige die Fehlermeldung, wenn sie existiert -->
                                <?php echo $weatherError; ?>
                            <?php else: ?>
                                <p class="card-text"><?php echo htmlspecialchars($currentWeather); ?>,
                                    <?php echo htmlspecialchars($currentTemp); ?>°C
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php while ($category = $categories->fetch_assoc()): ?>
            <?php
            $category_id = $category['id'];
            $category_name = $category['name'];

            // Hole die Spalten der Kategorie
            $columns = $conn->prepare("SELECT * FROM category_columns WHERE category_id = ? ORDER BY id");
            $columns->bind_param("i", $category_id);
            $columns->execute();
            $columns_result = $columns->get_result();

            $column_names = [];
            while ($col = $columns_result->fetch_assoc()) {
                $column_names[] = $col['column_name'];
            }

            // Hole die Links der Kategorie
            $links = $conn->prepare("SELECT * FROM links WHERE category_id = ? ORDER BY id");
            $links->bind_param("i", $category_id);
            $links->execute();
            $links_result = $links->get_result();

            // **Prüfe, ob es mindestens einen Eintrag gibt**
            if ($links_result->num_rows > 0): // Nur wenn es Einträge gibt, zeige die Box an
                $hasLinks = true; // Setze die Variable auf true, sobald ein Link gefunden wurde
                ?>
                <div class="card mb-5">
                    <div class="card-body">
                        <h3><?php echo htmlspecialchars($category_name); ?></h3>
                        <div class="row row-cols-1 row-cols-md-2 g-4">
                            <?php while ($link = $links_result->fetch_assoc()): ?>
                                <div class="col">
                                    <?php
                                    // Überprüfen, ob URL oder Datei verwendet werden soll
                                    $linkHref = !empty($link['url']) ? $link['url'] : $link['datei'];

                                    // Wenn kein Link verfügbar ist, Überspringen
                                    if (empty($linkHref)) {
                                        continue;
                                    }

                                    // Wenn es eine Datei ist, ist der Pfad relativ, daher den Pfad ergänzen
                                    $isFile = false;
                                    if (!empty($link['datei'])) {
                                        $linkHref = $link['datei'];
                                        $isFile = true;
                                    }

                                    // Favicon oder Standard-Icon für Datei
                                    $icon = $isFile ? getDefaultPdfIcon() : getFavicon($linkHref);
                                    ?>
                                    <a href="<?php echo htmlspecialchars($linkHref); ?>" class="card h-100 hover-shadow"
                                        target="_blank">
                                        <div class="card-body d-flex align-items-center">
                                            <img src="<?php echo htmlspecialchars($icon ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                                alt="<?php echo htmlspecialchars($link[$column_names[0]] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                                class="icon">
                                            <div class="ms-3">
                                                <!-- Erste Spalte dynamisch anzeigen -->
                                                <div style="font-size: 1.1rem;">
                                                    <?php echo htmlspecialchars($link[strtolower($column_names[0])]); ?>
                                                </div>
                                                <!-- Alle weiteren Spalten kleiner darstellen und mit Spaltennamen -->
                                                <?php $isFirstColumn = true; ?>
                                                <?php foreach ($column_names as $col_name): ?>
                                                    <?php if ($col_name !== 'URL' && $col_name !== 'Datei' && $col_name !== $column_names[0]): ?>
                                                        <p class="mb-0"><small><?php echo htmlspecialchars($col_name); ?>:
                                                                <?php echo htmlspecialchars($link[strtolower($col_name)]); ?></small></p>
                                                    <?php endif; ?>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    </a>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        <?php endwhile; ?>

        <!-- Zeige die grüne Box an, wenn keine Kategorien oder Links vorhanden sind -->
        <?php if (!$hasLinks): ?>
            <div class="alert alert-warning">
                <?php echo $lang['no_data_part1'] . "<a href='/settings/link_settings.php'>" . $lang['no_data_part2'] . "</a>" . $lang['no_data_part3']; ?>
            </div>
        <?php endif; ?>

    </div><!-- Ende des Hauptinhalts-Containers -->

    <!-- Footer einbinden -->
    <?php include __DIR__ . '/src/includes/footer.php'; ?>

    <div class="theme-toggle">
        <i class="fas fa-adjust"></i><span class="theme-name"></span>
    </div>

    <!-- Bootstrap Bundle mit Popper.js -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/scripts.js"></script>
    <script>
        // Funktion zum Aktualisieren des Datums
        function updateDate() {
            const now = new Date();
            const options = { day: '2-digit', month: '2-digit', year: 'numeric' }; // Formatoptionen für führende Nullen
            const formattedDate = now.toLocaleDateString(undefined, options); // `undefined` übernimmt die Standardsprache des Browsers
            document.getElementById('current-date').innerText = formattedDate;
        }

        // Funktion zum Aktualisieren der Uhrzeit
        function updateTime() {
            const now = new Date();
            document.getElementById('current-time').innerText = now.toLocaleTimeString();
        }

        // Initialisieren der Anzeige von Datum und Uhrzeit
        document.addEventListener('DOMContentLoaded', function () {
            updateDate(); // Datum beim Laden der Seite sofort anzeigen
            updateTime(); // Uhrzeit beim Laden der Seite sofort anzeigen
            setInterval(updateTime, 1000); // Uhrzeit jede Sekunde aktualisieren
        });
    </script>



    <!-- SortableJS -->
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var sortable = new Sortable(document.getElementById('sortable-boxes'), {
                animation: 150,
                ghostClass: 'sortable-ghost',  // Klasse für den leeren Platzhalter
                dragClass: 'sortable-drag',    // Klasse für das Element während des Ziehens
                onStart: function (evt) {
                    // Verstecke nur das gezogene Element
                    evt.item.classList.add('hide-original');
                },
                onEnd: function (evt) {
                    // Zeige das Element wieder an, sobald das Ziehen abgeschlossen ist
                    evt.item.classList.remove('hide-original');

                    // Reihenfolge der Boxen erfassen
                    var order = sortable.toArray();

                    // Ajax-Anfrage zum Speichern der Reihenfolge
                    var xhr = new XMLHttpRequest();
                    xhr.open("POST", "../src/controllers/save_order.php", true);
                    xhr.setRequestHeader("Content-Type", "application/json;charset=UTF-8");
                    xhr.send(JSON.stringify({
                        order: order
                    }));
                }
            });

            // Reihenfolge beim Laden der Seite wiederherstellen
            var savedOrder = <?php echo json_encode($savedOrderFromDB); ?>;
            if (savedOrder) {
                savedOrder.forEach(function (id) {
                    var el = document.getElementById(id);
                    if (el) {
                        document.getElementById('sortable-boxes').appendChild(el);
                    }
                });
            }
        });
    </script>

</body>

</html>