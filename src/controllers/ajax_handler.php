<?php
session_start();

function isAjax()
{
    return isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

// Überprüfen, ob der Benutzer eingeloggt ist, andernfalls für AJAX-Anfragen JSON-Antwort und für normale Anfragen Umleitung
if (!isset($_SESSION['user_id'])) {
    if (isAjax()) {
        exit();
    } else {
        // Redirect non-AJAX requests to an error page if the user is not logged in
        header("Location: ../../public/errors/403.php");
        exit();
    }
}

require __DIR__ . '/../config/dbconnect.php'; // Datenbankverbindung

// Benutzersprache abrufen
$user_id = $_SESSION['user_id'];
$sql = "SELECT user_language FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_language = $stmt->get_result()->fetch_assoc()['user_language'];
$stmt->close();

$language = $user_language ?? 'en'; // Fallback auf Englisch, falls keine Sprache eingestellt ist

$language_file = __DIR__ . "/../languages/{$language}.php";

// Prüfen, ob die Sprachdatei existiert
if (file_exists($language_file)) {
    include $language_file;
} else {
    // Fallback auf Englisch, wenn die Sprachdatei nicht gefunden wird
    include __DIR__ . '/../languages/en.php';
}

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Nicht autorisiert']);
    exit();
}

$user_id = $_SESSION['user_id'];

// Funktion um die URL zu prüfen und ggf. das Protokoll hinzuzufügen
function ensureUrlProtocol($url)
{
    if (!preg_match("~^(?:f|ht)tps?://~i", $url)) {
        $url = "https://" . $url;
    }
    return $url;
}

// Funktion zum Abrufen des Favicons einer URL
function getFavicon($url)
{

    // Vorheriges Debugging: logge die URL
    error_log("Abruf des Favicons für URL: " . $url);

    $cacheFile = __DIR__ . '/../src/cache/favicon_cache.json';

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

// Kategorieposition ändern
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'change_category_position' && isset($_POST['category_id']) && isset($_POST['direction'])) {
    $category_id = (int) $_POST['category_id'];
    $direction = (int) $_POST['direction'];

    // Aktuelle Position der Kategorie herausfinden
    $stmt = $conn->prepare("SELECT position FROM categories WHERE id = ?");
    $stmt->bind_param("i", $category_id);
    $stmt->execute();
    $stmt->bind_result($current_position);
    $stmt->fetch();
    $stmt->close();

    // Zielposition berechnen
    $new_position = $current_position + $direction;

    // Begrenzen, falls die Positionen außerhalb des gültigen Bereichs liegen
    $stmt = $conn->prepare("SELECT MIN(position), MAX(position) FROM categories");
    $stmt->execute();
    $stmt->bind_result($min_position, $max_position);
    $stmt->fetch();
    $stmt->close();

    $new_position = max($min_position, min($new_position, $max_position));

    // Kategorie, die sich bereits an der Zielposition befindet, finden
    $stmt = $conn->prepare("SELECT id FROM categories WHERE position = ?");
    $stmt->bind_param("i", $new_position);
    $stmt->execute();
    $stmt->bind_result($swap_category_id);
    $stmt->fetch();
    $stmt->close();

    // Positionen tauschen, falls möglich
    if ($swap_category_id) {
        $stmt = $conn->prepare("UPDATE categories SET position = ? WHERE id = ?");
        $stmt->bind_param("ii", $current_position, $swap_category_id);
        $stmt->execute();

        $stmt = $conn->prepare("UPDATE categories SET position = ? WHERE id = ?");
        $stmt->bind_param("ii", $new_position, $category_id);
        $stmt->execute();
    }

    echo json_encode(['success' => true]);
    exit();
}

// Eintrag hinzufügen
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'add_entry') {
    $category_id = $_POST['category_id'];

    // Ermittlung der Spaltennamen für die Kategorie
    $columns = $conn->prepare("SELECT column_name FROM category_columns WHERE category_id = ?");
    $columns->bind_param("i", $category_id);
    $columns->execute();
    $columns_result = $columns->get_result();

    $column_names = [];
    $field_values = [];
    $placeholders = [];
    $types = "";
    $favicon = ''; // Favicon initialisieren

    // Domain und Verzeichnis dynamisch ermitteln
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
    $domain = $_SERVER['HTTP_HOST'];
    $base_dir = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/') . '/';
    $base_url = $protocol . $domain . $base_dir;

    while ($col = $columns_result->fetch_assoc()) {
        $column_name = $col['column_name'];
        $column_names[] = "`" . $column_name . "`";

        if (preg_match('/url|internetadresse/i', $column_name)) {
            // URL-Spalte: Protokoll sicherstellen und Favicon abrufen
            $field_value = ensureUrlProtocol($_POST[$column_name]);
            $favicon = getFavicon($field_value);
        } elseif (preg_match('/\bdatei\b/i', $column_name)) {
            // Datei-Spalte: Dateipfad erhalten und vollständige URL generieren
            $file_name = $_POST[$column_name . '_path'] ?? '';
            if (!empty($file_name)) {
                $field_value = $base_url . $file_name;
                $favicon = '../../assets/icons/default-pdf-icon.png';
            } else {
                $field_value = null; // Falls keine Datei hochgeladen wurde
            }
        } else {
            // Andere Spalten
            $field_value = $_POST[$column_name] ?? null;
        }

        $field_values[] = $field_value;
        $placeholders[] = "?";
        $types .= "s";
    }

    // Ermittlung der maximalen Position
    $position_query = "SELECT MAX(position) AS max_position FROM links WHERE category_id = ?";
    $position_stmt = $conn->prepare($position_query);
    $position_stmt->bind_param("i", $category_id);
    $position_stmt->execute();
    $position_result = $position_stmt->get_result()->fetch_assoc();
    $max_position = $position_result['max_position'] ?? 0;
    $new_position = $max_position + 1;

    // Eintrag in die Datenbank einfügen
    $query = "INSERT INTO links (category_id, position, " . implode(", ", $column_names) . ") VALUES (?, ?, " . implode(", ", $placeholders) . ")";
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => $lang['insert_query_error']]);
        exit();
    }
    $types = "ii" . $types;
    $stmt->bind_param($types, $category_id, $new_position, ...$field_values);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        $new_id = $stmt->insert_id;
        $response_data = [
            'success' => true,
            'message' => $lang['entry_added_successfully'],
            'entry_id' => $new_id,
            'favicon' => $favicon,
            'category_id' => $category_id,
            'columns' => [] // Platz für die dynamischen Spaltennamen
        ];

        // Füge alle Spaltenwerte zur Antwort hinzu
        foreach ($column_names as $index => $column_name) {
            $column_key = str_replace('`', '', $column_name); // Entferne die Backticks

            $response_data['columns'][] = ['name' => $column_key]; // Füge die Spaltennamen zur Antwort hinzu

            // Überprüfen ob es eine URL-Spalte ist und den entsprechenden Wert zurückgeben
            if (preg_match('/url|internetadresse/i', $column_key)) {
                $response_data[$column_key] = $field_values[$index]; // Setze die URL in die Antwortdaten
            } elseif (preg_match('/datei/i', $column_key)) {
                $response_data[$column_key . '_path'] = $field_values[$index]; // Fügt den Pfad für Dateien hinzu
            } else {
                $response_data[$column_key] = $field_values[$index] !== null ? $field_values[$index] : ''; // Andere Spalten, sicherstellen, dass keine undefined Werte gesetzt werden
            }
        }

        echo json_encode($response_data);
    } else {
        echo json_encode(['success' => false, 'message' => $lang['entry_add_failed']]);
    }

    $stmt->close();
    exit();
}

// Eintrag löschen
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'delete_entry') {
    $entry_id = $_POST['entry_id'];

    // Zuerst die Kategorie-ID des zu löschenden Eintrags abrufen
    $stmt = $conn->prepare("SELECT category_id FROM links WHERE id = ?");
    $stmt->bind_param("i", $entry_id);
    $stmt->execute();
    $stmt->bind_result($category_id);
    $stmt->fetch();
    $stmt->close();

    // Eintrag löschen
    $stmt = $conn->prepare("DELETE FROM links WHERE id = ?");
    $stmt->bind_param("i", $entry_id);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        echo json_encode([
            'success' => true,
            'message' => $lang['entry_deleted_successfully'],
            'category_id' => $category_id // Hinzugefügt für die dynamische Überprüfung
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => $lang['entry_delete_failed'] . $entry_id]);
    }

    $stmt->close();
}

// Eintrag aktualisieren
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'update_entry') {
    $id = $_POST['id'];
    $category_id = $_POST['category_id'] ?? null;

    // Dynamisches Erfassen der Spalten der Kategorie
    $columns = $conn->prepare("SELECT column_name FROM category_columns WHERE category_id = ?");
    $columns->bind_param("i", $category_id);
    $columns->execute();
    $columns_result = $columns->get_result();

    $updates = [];
    $values = [];
    $types = "";
    $url_value = null;
    $favicon = ''; // Favicon initialisieren
    $debug = "";

    // Domain und Verzeichnis dynamisch ermitteln
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
    $domain = $_SERVER['HTTP_HOST'];
    $base_dir = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/') . '/';
    $base_url = $protocol . $domain . $base_dir;

    while ($col = $columns_result->fetch_assoc()) {
        $column_name = $col['column_name'];
        $updates[] = "`" . $column_name . "` = ?";
        $debug = "" . json_encode($col) . "";

        if (preg_match('/url|internetadresse/i', $column_name)) {
            // URL-Spalte: Protokoll sicherstellen und Favicon abrufen
            $value = ensureUrlProtocol($_POST[$column_name]);
            $url_value = $value; // Speichern der URL für die spätere Verwendung
            $favicon = getFavicon($url_value);
        } elseif (preg_match('/\bdatei\b/i', $column_name)) {
            // Datei-Spalte: Dateipfad erhalten und vollständige URL generieren
            $file_name = $_POST[$column_name . '_path'] ?? '';
            if (!empty($file_name) && !(str_starts_with($file_name, 'http://') || str_starts_with($file_name, 'https://'))) {
                $debug = "" . $file_name . "";
                $value = $base_url . $file_name;
            } else if (!empty($file_name)) {
                $value = $file_name;
            } else {
                $value = null; // Falls keine Datei hochgeladen wurde
            }
            $favicon = '../../assets/icons/default-pdf-icon.png';
        } else {
            // Andere Spalten
            $value = $_POST[$column_name] ?? null;
        }

        $values[] = $value;
        $types .= "s";
    }

    $values[] = $id;
    $types .= "i";

    $query = "UPDATE links SET " . implode(", ", $updates) . " WHERE id = ?";
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        error_log("Fehler beim Vorbereiten der Update-Abfrage: " . $conn->error);
        echo json_encode(['success' => false, 'message' => $lang['update_query_error']]);
        exit();
    }

    $stmt->bind_param($types, ...$values);
    $stmt->execute();

    if ($stmt->errno) {
        error_log('SQL Error: ' . $stmt->error);
        echo json_encode(['success' => false, 'message' => $lang['entry_update_failed']]);
    } else {
        echo json_encode(['success' => true, 'favicon' => $favicon ?? null, 'filepath' => $value ?? null, 'debug' => $debug ?? null]);
    }

    $stmt->close();
}

// Kategorie löschen
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'delete_category') {
    $category_id = (int) $_POST['category_id'];

    // Überprüfen, ob es Einträge in der Kategorie gibt
    $stmt = $conn->prepare("SELECT COUNT(*) FROM links WHERE category_id = ?");
    $stmt->bind_param("i", $category_id);
    $stmt->execute();
    $stmt->bind_result($count);
    $stmt->fetch();
    $stmt->close();

    if ($count == 0) {
        // Zuerst die Spaltennamen dieser Kategorie abrufen
        $stmt = $conn->prepare("SELECT column_name FROM category_columns WHERE category_id = ?");
        $stmt->bind_param("i", $category_id);
        $stmt->execute();
        $columns_result = $stmt->get_result();
        $columns = [];
        while ($row = $columns_result->fetch_assoc()) {
            $columns[] = $row['column_name'];
        }
        $stmt->close();

        // Kategorie und zugehörige Spalten in category_columns löschen
        $stmt = $conn->prepare("DELETE FROM categories WHERE id = ?");
        $stmt->bind_param("i", $category_id);
        $stmt->execute();
        $stmt->close();

        $stmt = $conn->prepare("DELETE FROM category_columns WHERE category_id = ?");
        $stmt->bind_param("i", $category_id);
        $stmt->execute();
        $stmt->close();

        // Spalten in der links-Tabelle löschen, wenn sie in keiner anderen Kategorie verwendet werden
        foreach ($columns as $column_name) {
            $stmt = $conn->prepare("SELECT COUNT(*) FROM category_columns WHERE column_name = ?");
            $stmt->bind_param("s", $column_name);
            $stmt->execute();
            $stmt->bind_result($count);
            $stmt->fetch();
            $stmt->close();

            if ($count == 0) { // Spalte wird in keiner anderen Kategorie verwendet
                $stmt = $conn->prepare("SELECT COUNT(*) FROM links WHERE $column_name IS NOT NULL");
                $stmt->execute();
                $stmt->bind_result($not_null_count);
                $stmt->fetch();
                $stmt->close();

                if ($not_null_count == 0) { // Alle Werte sind NULL, also kann die Spalte gelöscht werden
                    $conn->query("ALTER TABLE links DROP COLUMN $column_name");
                }
            }
        }

        // Da die Kategorie jetzt global verwaltet wird, gibt es keine benutzerspezifischen Kategorienpositionen mehr zu löschen.

        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => $lang['category_not_empty']]);
    }
    exit();
}


// Neue Kategorie und Spalten hinzufügen
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'add_category') {
        $new_category = trim($_POST['new_category']);
        $columns = $_POST['columns']; // Dies ist jetzt ein Array von Spaltennamen
        $url_or_file = $_POST['url_or_file']; // Das Dropdown-Auswahlfeld

        // Spalte 'URL' oder 'Datei' am Ende des Arrays hinzufügen, basierend auf der Benutzerauswahl
        $columns[] = $url_or_file;

        // Überprüfen, ob die Kategorie bereits existiert
        $stmt = $conn->prepare("SELECT id FROM categories WHERE name = ?");
        $stmt->bind_param("s", $new_category);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            echo json_encode(['success' => false, 'message' => $lang['category_exists']]);
        } else {
            // Neue Position ermitteln: die maximale aktuelle Position + 1
            $new_position_stmt = $conn->query("SELECT MAX(position) AS max_position FROM categories");
            $new_position = $new_position_stmt->fetch_assoc()['max_position'] + 1;

            // Neue Kategorie hinzufügen
            $stmt = $conn->prepare("INSERT INTO categories (name, position) VALUES (?, ?)");
            $stmt->bind_param("si", $new_category, $new_position);
            $stmt->execute();
            $category_id = $stmt->insert_id;
            $stmt->close();

            // Spalten zur Kategorie hinzufügen
            foreach ($columns as $column_name) {
                $column_name = trim($column_name);
                $stmt = $conn->prepare("INSERT INTO category_columns (category_id, column_name, display_name) VALUES (?, ?, ?)");
                $stmt->bind_param("iss", $category_id, $column_name, $column_name);
                $stmt->execute();

                // Spalte zur 'links'-Tabelle hinzufügen, falls sie noch nicht existiert
                $result = $conn->query("SHOW COLUMNS FROM links LIKE '$column_name'");
                $column_name_lowercase = strtolower($column_name);
                if ($result->num_rows == 0) {
                    $conn->query("ALTER TABLE links ADD $column_name_lowercase VARCHAR(255) NULL");
                }
            }

            echo json_encode(['success' => true]);
        }
    }
}


//Datei Upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {
    $targetDir = "docs";
    if (!file_exists($targetDir)) {
        mkdir($targetDir);
    }
    $fileName = basename($_FILES['file']['name']);
    $targetFilePath = $targetDir . "/" . $fileName;
    $fileType = pathinfo($targetFilePath, PATHINFO_EXTENSION);

    // Überprüfe, ob die Datei ein PDF ist
    if ($fileType === 'pdf') {
        // Verschiebe die Datei in den Zielordner
        if (move_uploaded_file($_FILES['file']['tmp_name'], $targetFilePath)) {
            echo json_encode(['success' => true, 'filePath' => $targetFilePath]);
        } else {
            echo json_encode(['success' => false, 'message' => $lang['file_upload_error']]);
        }
    } else {
        echo json_encode(['success' => false, 'message' => $lang['file_type_error']]);
    }
}

//Speichern der Prosition der Einträger der Tabellen
if (isset($_POST["action"]) && $_POST["action"] == 'update_link_order') {
    $order = $_POST['order'];

    foreach ($order as $item) {
        $stmt = $conn->prepare("UPDATE links SET position = ? WHERE id = ?");
        $stmt->bind_param("ii", $item['position'], $item['id']);
        $stmt->execute();
        $stmt->close();
    }

    echo json_encode(['success' => true]);
    exit();
}


$conn->close();

?>