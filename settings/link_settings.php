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

// Überprüfen, ob der Benutzer ein Admin oder Autor ist, andernfalls zur 404-Seite umleiten
if ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'author') {
    header("Location: ../errors/403.php");
    exit();
}

include __DIR__ . '/../src/config/dbconnect.php';

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

// URL mit https ergänzen, falls das Protokoll fehlt
function ensureUrlProtocol($url)
{
    if (!preg_match("~^(?:f|ht)tps?://~i", $url)) {
        $url = "https://" . $url;
    }
    return $url;
}

// Funktion, um ein Standard-PDF-Icon zu verwenden
function getDefaultPdfIcon()
{
    // Korrekt auf das Verzeichnis zugreifen, das sich einen Ordner höher befindet
    return '/../assets/icons/default-pdf-icon.png';
}

// Funktion zum Abrufen des Favicons einer URL oder eines Standardbildes für eine Datei
function getFavicon($url)
{
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

// Kategorien und Links abrufen
$stmt = $conn->prepare("SELECT * FROM categories ORDER BY position");
$stmt->execute();
$categories = $stmt->get_result();
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
    <?php
    $page_specific_title = $lang['link_management'];
    include __DIR__ . '/../src/includes/page_title.php';
    ?>
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
    <!-- Header einbinden -->
    <?php include __DIR__ . '/../src/includes/header.php'; ?>

    <!-- Hauptinhalt -->
    <div class="container main-content">
        <div class="row mb-4 align-items-center">
            <div class="col-6">
                <h2><?php echo $lang['administration']; ?></h2>
                <p><?php echo $lang['all_links_on_homepage']; ?></p>
            </div>
        </div>
        <?php while ($category = $categories->fetch_assoc()): ?>
            <?php
            $category_id = $category['id'];
            $category_name = $category['name'];

            $columns = $conn->prepare("SELECT * FROM category_columns WHERE category_id = ? ORDER BY id");
            $columns->bind_param("i", $category_id);
            $columns->execute();
            $columns_result = $columns->get_result();

            $column_names = [];
            while ($col = $columns_result->fetch_assoc()) {
                $column_names[] = $col['column_name'];
            }

            $links = $conn->prepare("SELECT * FROM links WHERE category_id = ? ORDER BY position");
            $links->bind_param("i", $category_id);
            $links->execute();
            $links_result = $links->get_result();
            ?>

            <div class="card mb-5" data-category-id="<?php echo $category_id; ?>">
                <div class="card-body">
                    <div class="row mb-2 d-flex justify-content-between align-items-center">
                        <div class="col-6">
                            <h3><?php echo htmlspecialchars($category_name); ?></h3>
                        </div>
                        <div class="col-6 text-end">
                            <div class="btn-group">
                                <a href="link_settings.php?move_up=<?php echo $category_id; ?>"
                                    class="btn btn-outline-secondary">
                                    <i class="fas fa-arrow-up"></i>
                                </a>
                                <a href="link_settings.php?move_down=<?php echo $category_id; ?>"
                                    class="btn btn-outline-secondary">
                                    <i class="fas fa-arrow-down"></i>
                                </a>
                                <button
                                    class="reset-table btn btn-outline-secondary"><?php echo $lang['reset_columns_width']; ?></button>
                            </div>
                        </div>
                    </div>

                    <!-- Desktop Ansicht -->
                    <div class="d-none d-md-block">
                        <table class="table table-striped resizable-table">
                            <thead>
                                <tr>
                                    <th></th> <!-- Neue Spalte für das Drag-and-Drop-Icon -->
                                    <th><?php echo $lang['favicon']; ?></th>
                                    <?php foreach ($column_names as $col_name): ?>
                                        <th><?php echo htmlspecialchars($col_name); ?></th>
                                    <?php endforeach; ?>
                                    <th><?php echo $lang['actions']; ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($link = $links_result->fetch_assoc()): ?>
                                    <tr data-id="<?php echo $link['id']; ?>" data-category-id="<?php echo $category_id; ?>"
                                        class="link-row">
                                        <td><i class="fas fa-arrows-alt-v handle"></i></td> <!-- Drag-and-Drop-Icon -->
                                        <td>
                                            <?php
                                            if (isset($link['url']) && !empty($link['url'])) {
                                                $favicon_src = getFavicon($link['url']);
                                            } elseif (isset($link['datei']) && !empty($link['datei'])) {
                                                $favicon_src = getDefaultPdfIcon();
                                            } else {
                                                //$favicon_src = 'icons/default-favicon.svg';
                                            }
                                            ?>
                                            <img src="<?php echo $favicon_src; ?>" alt="Favicon" class="icon">
                                        </td>
                                        <?php foreach ($column_names as $col_name): ?>
                                            <td>
                                                <?php
                                                $col_name_lower = strtolower($col_name);
                                                if ($col_name_lower !== null && preg_match('/url|internetadresse/i', $col_name_lower)): ?>
                                                    <?php if (!empty($link[$col_name_lower])): ?>
                                                        <span class="link-data"
                                                            data-value="<?php echo htmlspecialchars($link[$col_name_lower]); ?>">
                                                            <a href="<?php echo htmlspecialchars($link[$col_name_lower]); ?>"
                                                                target="_blank">
                                                                <?php echo htmlspecialchars($link[$col_name_lower]); ?>
                                                            </a>
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="text-muted"><?php echo $lang['no_url_available']; ?></span>
                                                    <?php endif; ?>
                                                    <input type="text" name="<?php echo htmlspecialchars($col_name); ?>"
                                                        class="form-control link-edit-field"
                                                        value="<?php echo htmlspecialchars($link[$col_name_lower]); ?>"
                                                        style="display:none;">
                                                <?php elseif ($col_name_lower !== null && preg_match('/datei/i', $col_name_lower)): ?>
                                                    <span class="link-data"
                                                        data-value="<?php echo htmlspecialchars($link[$col_name_lower] ?? ''); ?>">
                                                        <a href="<?php echo htmlspecialchars($link[$col_name_lower]); ?>"
                                                            target="_blank">
                                                            <?php echo htmlspecialchars($link[$col_name_lower]); ?>
                                                        </a>
                                                    </span>
                                                    <input type="file" name="<?php echo htmlspecialchars($col_name); ?>"
                                                        accept="application/pdf" class="form-control link-edit-field file-upload-field"
                                                        style="display:none;">
                                                    <input type="text" name="<?php echo htmlspecialchars($col_name); ?>_path"
                                                        class="form-control link-edit-field file-path-field"
                                                        value="<?php echo htmlspecialchars($link[$col_name_lower] ?? ''); ?>"
                                                        style="display:none;">
                                                <?php else: ?>
                                                    <span class="link-data"
                                                        data-value="<?php echo htmlspecialchars($link[$col_name_lower] ?? ''); ?>">
                                                        <?php echo htmlspecialchars($link[$col_name_lower] ?? ''); ?>
                                                    </span>
                                                    <input type="text" name="<?php echo htmlspecialchars($col_name); ?>"
                                                        class="form-control link-edit-field"
                                                        value="<?php echo htmlspecialchars($link[$col_name_lower] ?? ''); ?>"
                                                        style="display:none;">
                                                <?php endif; ?>
                                            </td>
                                        <?php endforeach; ?>
                                        <td>
                                            <button
                                                class="btn btn-sm btn-warning edit-entry"><?php echo $lang['edit']; ?></button>
                                            <button class="btn btn-sm btn-success save-entry"
                                                style="display:none;"><?php echo $lang['save']; ?></button>
                                            <button class="btn btn-sm btn-secondary cancel-edit"
                                                style="display:none;"><?php echo $lang['cancel']; ?></button>
                                            <button class="btn btn-sm btn-danger delete-entry"
                                                data-id="<?php echo $link['id']; ?>"><?php echo $lang['delete']; ?></button>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                                <tr id="new-entry-row">
                                    <form class="add-entry-form" enctype="multipart/form-data">
                                        <input type="hidden" name="category_id" value="<?php echo $category_id; ?>">
                                        <td></td> <!-- Leere Zelle für das Icon zum verschieben -->
                                        <td></td> <!-- Leere Zelle für das Favicon -->
                                        <?php foreach ($column_names as $col_name): ?>
                                            <td>
                                                <?php if (strtolower($col_name) === 'datei'): ?>
                                                    <input type="file" name="<?php echo htmlspecialchars($col_name); ?>"
                                                        accept="application/pdf" class="form-control file-upload-field" required>
                                                    <input type="text" name="<?php echo htmlspecialchars($col_name); ?>_path"
                                                        class="form-control file-path-field" style="display:none;">
                                                <?php else: ?>
                                                    <input type="text" name="<?php echo htmlspecialchars($col_name); ?>"
                                                        class="form-control" required>
                                                <?php endif; ?>
                                            </td>
                                        <?php endforeach; ?>
                                        <td>
                                            <button type="submit"
                                                class="btn btn-sm btn-success"><?php echo $lang['add']; ?></button>
                                        </td>
                                    </form>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Kategorie löschen -->
                    <?php
                    // Zähle die Einträge in der Kategorie direkt beim Laden der Seite
                    $stmt = $conn->prepare("SELECT COUNT(*) FROM links WHERE category_id = ?");
                    $stmt->bind_param("i", $category_id);
                    $stmt->execute();
                    $stmt->bind_result($count);
                    $stmt->fetch();
                    $stmt->close();

                    $buttonDisabled = ($count > 0) ? 'disabled' : '';
                    $tooltipVisible = ($count > 0) ? '' : 'style="display:none;"';
                    ?>
                    <div class="mt-3">
                        <div class="tooltip-container">
                            <button type="button" class="btn btn-danger delete-category-btn"
                                id="delete-category-btn-<?php echo $category_id; ?>" data-id="<?php echo $category_id; ?>"
                                <?php echo $buttonDisabled; ?>>
                                <?php echo $lang['category_delete']; ?>
                            </button>
                            <?php $tooltipText = 'tooltip-' . $category_id; ?>
                            <span id="tooltip-<?php echo $tooltipText; ?>" class="tooltip-text" <?php echo $tooltipVisible; ?>>
                                <?php echo $lang['category_delete_confirm']; ?>
                            </span>
                        </div>
                    </div>

                </div>
            </div>

        <?php endwhile; ?>

        <!-- Formular zum Hinzufügen einer neuen Kategorie -->
        <div class="card mb-5">
            <div class="card-body">
                <div class="row mb-0 d-flex justify-content-between align-items-center">
                    <div class="col-6">
                        <h3><?php echo $lang['add_new_category']; ?></h3>
                    </div>
                </div>
                <form method="post" id="new-category-form">
                    <div class="mb-4">
                        <label for="new_category" class="form-label"><?php echo $lang['category_name']; ?></label>
                        <input type="text" name="new_category" class="form-control" required>
                    </div>

                    <hr> <!-- Trennlinie zwischen Kategoriename und Spaltennamen -->

                    <div id="columns-container">
                        <!-- Favicon-Spalte, wird nicht übergeben -->
                        <div class="mb-3">
                            <label class="form-label"><?php echo $lang['favicon_column']; ?></label>
                            <input type="text" class="form-control" value="Favicon" readonly
                                style="background-color: #e9ecef;">
                        </div>

                        <!-- Leere Eingabespalte -->
                        <div class="mb-3">
                            <label class="form-label"><?php echo $lang['additional_column']; ?></label>
                            <input type="text" name="columns[]" class="form-control"
                                placeholder="<?php echo $lang['column_name']; ?>" required>
                        </div>

                        <!-- URL-Spalte, wird nicht übergeben -->
                        <div class="mb-3">
                            <label class="form-label"><?php echo $lang['url_or_file_column']; ?></label>
                            <select name="url_or_file" class="form-control" required>
                                <option value="URL">URL</option>
                                <option value="Datei">Datei</option>
                            </select>
                        </div>

                    </div>

                    <div class="d-flex justify-content-between">
                        <div>
                            <button type="button" id="remove-column" class="btn btn-danger">-
                                <?php echo $lang['remove_column']; ?></button>
                            <button type="button" id="add-column" class="btn btn-success">+
                                <?php echo $lang['add_column']; ?></button>
                        </div>
                        <button name="add_category" class="btn btn-primary"><?php echo $lang['add']; ?></button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <!-- Footer einbinden -->
    <?php include __DIR__ . '/../src/includes/footer.php'; ?>

    <div class="theme-toggle">
        <i class="fas fa-adjust"></i><span class="theme-name"></span>
    </div>

    <!-- Bootstrap Bundle mit Popper.js -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../js/scripts.js"></script>

    <!-- JavaScript zum dynamischen Hinzufügen und Entfernen von Spaltenfeldern -->
    <script>
        document.getElementById('add-column').addEventListener('click', function () {
            const container = document.getElementById('columns-container');
            const newField = document.createElement('div');
            newField.classList.add('mb-3');
            newField.innerHTML = `
        <label class="form-label"><?php echo $lang['additional_column']; ?></label>
        <input type="text" name="columns[]" class="form-control" placeholder="<?php echo $lang['column_name']; ?>" required>
    `;
            container.insertBefore(newField, container.lastElementChild);
        });

        document.getElementById('remove-column').addEventListener('click', function () {
            const container = document.getElementById('columns-container');
            const fields = container.querySelectorAll('div.mb-3:not(:first-child):not(:last-child)');
            if (fields.length > 0) {
                container.removeChild(fields[fields.length - 1]);
            }
        });

    </script>

    <link rel="stylesheet" href="https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://code.jquery.com/ui/1.12.1/jquery-ui.min.js"></script>

    <script>
document.addEventListener('DOMContentLoaded', function () {
    const tables = document.querySelectorAll('.resizable-table');

    tables.forEach((table) => {
        const thElements = Array.from(table.querySelectorAll('th'));
        const resetButton = table.closest('.card-body').querySelector('.reset-table');
        const categoryId = table.closest('.card').getAttribute('data-category-id'); // Hole die Kategorie-ID

        if (!categoryId) {
            console.error('Kategorie-ID konnte nicht gefunden werden.');
            return; // Beende die Funktion, wenn keine category_id gefunden wurde
        }

        let originalWidths = [];

        // Lade gespeicherte Breiten aus LocalStorage oder speichere die originalen Breiten
        thElements.forEach((th, index) => {
            const storedWidth = localStorage.getItem(`category-${categoryId}-column-${index}`); // Verwende categoryId als Teil des Schlüssels
            const originalWidth = localStorage.getItem(`category-${categoryId}-original-column-${index}`);

            if (storedWidth) {
                th.style.width = storedWidth;
            } else {
                const width = parseInt(document.defaultView.getComputedStyle(th).width, 10);
                th.style.width = width + 'px'; // Sicherstellen, dass die Breite gesetzt ist
                originalWidths.push(width);
                // Speichere die ursprüngliche Breite im LocalStorage
                localStorage.setItem(`category-${categoryId}-original-column-${index}`, width);
            }

            if (originalWidth) {
                originalWidths[index] = parseInt(originalWidth, 10);
            }
        });

        thElements.forEach((th, index) => {
            const resizer = document.createElement('div');
            resizer.className = 'resizer';
            th.appendChild(resizer);

            if (index === 0) {
                // Keine Resizer für die erste Spalte
                resizer.style.display = 'none';
                return;
            }

            let startX, startWidth, nextStartWidth;

            resizer.addEventListener('mousedown', function (e) {
                startX = e.pageX;
                startWidth = parseInt(document.defaultView.getComputedStyle(th).width, 10);
                if (index < thElements.length - 1) {
                    nextStartWidth = parseInt(document.defaultView.getComputedStyle(thElements[index + 1]).width, 10);
                }

                document.documentElement.addEventListener('mousemove', doDrag, false);
                document.documentElement.addEventListener('mouseup', stopDrag, false);
            });

            function doDrag(e) {
                const dx = e.pageX - startX;

                if (index < thElements.length - 1) {
                    const newWidth = startWidth + dx;
                    const newNextWidth = nextStartWidth - dx;

                    if (newWidth > 100 && newNextWidth > 100) {
                        th.style.width = newWidth + 'px';
                        thElements[index + 1].style.width = newNextWidth + 'px';
                    }
                } else {
                    const newWidth = startWidth + dx;
                    if (newWidth > 100) {
                        th.style.width = newWidth + 'px';
                    }
                }
            }

            function stopDrag() {
                // Speichere die neue Breite in LocalStorage
                thElements.forEach((th, index) => {
                    localStorage.setItem(`category-${categoryId}-column-${index}`, th.style.width); // Verwende categoryId als Teil des Schlüssels
                });

                document.documentElement.removeEventListener('mousemove', doDrag, false);
                document.documentElement.removeEventListener('mouseup', stopDrag, false);
            }
        });

        // Setze die Spaltenbreiten zurück
        resetButton.addEventListener('click', function () {
            thElements.forEach((th, index) => {
                const originalWidth = localStorage.getItem(`category-${categoryId}-original-column-${index}`); // Verwende categoryId als Teil des Schlüssels
                th.style.width = originalWidth + 'px';
                // Lösche gespeicherte Breiten aus LocalStorage
                localStorage.removeItem(`category-${categoryId}-column-${index}`);
            });
        });
    });
});
    </script>

    <script>
        // Funktion zum Hinzufügen der Event-Listener
        function addEventListenersToButtons() {
            // Bearbeiten eines Eintrags
            document.querySelectorAll('.edit-entry').forEach(function (button) {
                button.addEventListener('click', function () {
                    const row = this.closest('.link-row');
                    row.querySelectorAll('.link-data').forEach(el => el.style.display = 'none');
                    row.querySelectorAll('.link-edit-field').forEach(el => el.style.display = 'block');
                    row.querySelector('.edit-entry').style.display = 'none';
                    row.querySelector('.save-entry').style.display = 'inline-block';
                    row.querySelector('.cancel-edit').style.display = 'inline-block';
                    row.querySelector('.delete-entry').style.display = 'none';
                });
            });

            // Abbrechen einer Bearbeitung
            document.querySelectorAll('.cancel-edit').forEach(function (button) {
                button.addEventListener('click', function (evt) {
                    const row = this.closest('.link-row');
                    row.querySelectorAll('.link-data').forEach(el => el.style.display = 'block');
                    row.querySelectorAll('.link-edit-field').forEach(el => el.style.display = 'none');
                    row.querySelector('.edit-entry').style.display = 'inline-block';
                    row.querySelector('.save-entry').style.display = 'none';
                    row.querySelector('.cancel-edit').style.display = 'none';
                    row.querySelector('.delete-entry').style.display = 'inline-block';
                });
            });

            // Event Listener für Speichern-Button
            document.querySelectorAll('.save-entry').forEach(function (button) {
                button.addEventListener('click', function () {
                    const row = this.closest('.link-row');
                    const entryId = row.getAttribute('data-id');
                    const categoryId = row.getAttribute('data-category-id');

                    const linkDataFields = row.querySelectorAll('.link-data');
                    const linkDataEditFields = row.querySelectorAll('.link-edit-field');

                    let formChanged = true;

                    if (linkDataFields.length === linkDataEditFields.length) {
                        formChanged = false;
                        for (let i = 0; i < linkDataFields.length; i++) {
                            const field = linkDataFields[i];
                            const editField = linkDataEditFields[i];
                            if (field.dataset.value != editField.value) {
                                formChanged = true;
                                break;
                            }
                        }
                    }

                    if (formChanged) {
                        const formData = new FormData();
                        formData.append('action', 'update_entry');
                        formData.append('id', entryId);
                        formData.append('category_id', categoryId);

                        row.querySelectorAll('.link-edit-field').forEach(function (input, index) {
                            if (input.type === 'file') {
                                const file = input.files[0];
                                if (file) {
                                    const uploadFormData = new FormData();
                                    uploadFormData.append('file', file);
                                    fetch('/../src/controllers/ajax_handler.php', {
                                        method: 'POST',
                                        body: uploadFormData,
                                    })
                                        .then(response => response.json())
                                        .then(data => {
                                            if (data.success) {
                                                formData.append(input.name + '_path', data.filePath);
                                                submitUpdateForm(formData, row);
                                            } else {
                                                console.error('Fehler beim Hochladen der Datei:', data.message);
                                            }
                                        })
                                        .catch(error => console.error('AJAX Fehler:', error));
                                }
                            } else {
                                formData.append(input.name, input.value);
                            }
                        });

                        if (!formData.has('file')) {
                            submitUpdateForm(formData, row);
                        }
                    } else {
                        toggleRowViewMode(row);
                    }
                });
            });

            function submitUpdateForm(formData, row) {
                fetch('../src/controllers/ajax_handler.php', {
                    method: 'POST',
                    body: formData,
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            const favicon = row.querySelector('.icon');
                            favicon.src = data.favicon;

                            favicon.onerror = function () {
                                handleFaviconError(favicon);
                            };

                            row.querySelectorAll('.link-data').forEach(function (el, index) {
                                const fieldName = row.querySelectorAll('.link-edit-field')[index].name;
                                let fieldValue = row.querySelectorAll('.link-edit-field')[index].value;

                                if (/url|internetadresse/i.test(fieldName)) {
                                    fieldValue = ensureUrlProtocol(fieldValue);
                                    el.innerHTML = `<a href="${fieldValue}" target="_blank">${fieldValue}</a>`;
                                } else if (/datei/i.test(fieldName)) {
                                    el.innerHTML = `<a href="${data.filepath}" target="_blank">${data.filepath}</a>`;
                                    console.log(data.filepath);
                                } else {
                                    if (fieldValue != "") {
                                        el.dataset.value = fieldValue;
                                        el.innerText = fieldValue;
                                    }
                                }
                                el.dataset.value = fieldValue;
                            });

                            toggleRowViewMode(row);
                        } else {
                            console.error('Update fehlgeschlagen:', data.message);
                        }
                    })
                    .catch(error => console.error('AJAX Fehler:', error));
            }

            function toggleRowViewMode(row) {
                row.querySelectorAll('.link-data').forEach(el => el.style.display = 'block');
                row.querySelectorAll('.link-edit-field').forEach(el => el.style.display = 'none');

                row.querySelector('.edit-entry').style.display = 'inline-block';
                row.querySelector('.save-entry').style.display = 'none';
                row.querySelector('.cancel-edit').style.display = 'none';
                row.querySelector('.delete-entry').style.display = 'inline-block';
            }

            // Löschen eines Eintrags
            document.querySelectorAll('.delete-entry').forEach(function (button) {
                button.addEventListener('click', function () {
                    const entryId = this.getAttribute('data-id');

                    const formData = new URLSearchParams();
                    formData.append('action', 'delete_entry');
                    formData.append('entry_id', entryId);

                    fetch('../src/controllers/ajax_handler.php', {
                        method: 'POST',
                        body: formData,
                    })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                const row = this.closest('.link-row');
                                row.parentNode.removeChild(row);

                                // Überprüfe die Anzahl der Einträge in der Kategorie
                                checkCategoryEntries(data.category_id);
                            } else {
                                console.error(data.message);
                            }
                        })
                        .catch(error => console.error('Fehler:', error));
                });
            });
        }

        // Initiales Hinzufügen der Event-Listener
        addEventListenersToButtons();

        // Hinzufügen eines neuen Eintrags
        document.querySelectorAll('.add-entry-form').forEach(function (form) {
            form.addEventListener('submit', function (e) {
                e.preventDefault();

                const formData = new FormData(this);
                let urlValue = formData.get('URL');

                if (urlValue) {
                    urlValue = ensureUrlProtocol(urlValue);
                    formData.set('URL', urlValue);
                }

                formData.set('action', 'add_entry');

                fetch('../src/controllers/ajax_handler.php', {
                    method: 'POST',
                    body: formData,
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            const newRow = document.createElement('tr');
                            newRow.classList.add('link-row');
                            newRow.setAttribute('data-id', data.entry_id);
                            newRow.setAttribute('data-category-id', data.category_id);
                            let cells = '';

                            cells += `<td><i class="fas fa-arrows-alt-v handle"></i></td>`;
                            cells += `<td><img src="${data.favicon}" alt="Favicon" class="icon" onerror="handleFaviconError(this)"></td>`;

                            // Dynamische Verarbeitung der Spalten basierend auf dem zurückgegebenen Spaltennamen-Array
                            data.columns.forEach(column => {
                                const columnName = column.name;
                                const columnValue = data[columnName] !== undefined ? data[columnName] : '';

                                if (/url|internetadresse/i.test(columnName)) {
                                    cells += `<td><span class="link-data"><a href="${columnValue}" target="_blank">${columnValue}</a></span><input type="text" name="${columnName}" class="form-control link-edit-field" value="${columnValue}" style="display:none;"></td>`;
                                } else if (/datei/i.test(columnName)) {
                                    const filePath = data[columnName + '_path'] || '';
                                    cells += `<td><span class="link-data" data-value="${filePath}"><a href="${filePath}" target="_blank">${filePath}</a></span><input type="file" name="${columnName}"
                                    accept="application/pdf" class="form-control link-edit-field file-upload-field"
                                    style="display:none;" data-value="${filePath}"><input type="text" name="${columnName}_path" class="form-control file-path-field link-edit-field" value="${filePath}" style="display:none;"></td>`;

                                    
                                    const nextRowFileUploadField = this.parentElement.querySelector('.file-upload-field');
                                    const nextRowFilePathField = this.parentElement.querySelector('.file-path-field');
                                    
                                    nextRowFileUploadField.style.display = "block";
                                    nextRowFilePathField.style.display = "none";
                                } else {
                                    cells += `<td><span class="link-data">${columnValue}</span><input type="text" name="${columnName}" class="form-control link-edit-field" value="${columnValue}" style="display:none;"></td>`;
                                }
                            });

                            cells += `
                                <td>
                                    <button class="btn btn-sm btn-warning edit-entry"><?php echo $lang['edit']; ?></button>
                                    <button class="btn btn-sm btn-success save-entry" style="display:none;"><?php echo $lang['save']; ?></button>
                                    <button class="btn btn-sm btn-secondary cancel-edit" style="display:none;"><?php echo $lang['cancel']; ?></button>
                                    <button class="btn btn-sm btn-danger delete-entry" data-id="${data.entry_id}"><?php echo $lang['delete']; ?></button>
                                </td>
                            `;

                            newRow.innerHTML = cells;

                            const table = this.closest('table');
                            table.querySelector('#new-entry-row').insertAdjacentElement('beforebegin', newRow);
                            this.reset();

                             // Toggle the visibility of the next entry row's file input fields
                             console.log(this);
                             

                            addEventListenersToButtons();

                            checkCategoryEntries(data.category_id);
                        } else {
                            alert(data.message);
                        }
                    });
            });
        });

        // Utility-Funktion zum Sicherstellen des richtigen URL-Protokolls
        function ensureUrlProtocol(url) {
            if (!url.startsWith('http://') && !url.startsWith('https://')) {
                return 'https://' + url;
            }
            return url;
        }

        // Funktion zum Behandeln von Favicon-Fehlern
        function handleFaviconError(favicon) {
            const currentTheme = document.documentElement.getAttribute('data-bs-theme');
            if (currentTheme === 'dark') {
                favicon.src = '../assets/icons/default-icon-hell.svg';
            } else {
                favicon.src = '../assets/icons/default-icon-dunkel.svg';
            }
        }

        //Kategorien nach oben und unten verschieben
        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('.btn-outline-secondary').forEach(function (button) {
                button.addEventListener('click', function (e) {
                    e.preventDefault();

                    // Bestimme, ob es nach oben oder unten geht
                    const isMoveUp = this.querySelector('.fa-arrow-up') !== null;
                    const direction = isMoveUp ? -1 : 1;

                    // Finde das zu verschiebende Kategorien-Element
                    const categoryCard = this.closest('.card');
                    const categoryId = this.href.split('=').pop();

                    // Finde das vorherige Element, um sicherzustellen, dass es sich um eine Kategorie-Box handelt
                    const previousElement = categoryCard.previousElementSibling;

                    // Überprüfe, ob das Element direkt unter der Überschrift ist, bevor es nach oben verschoben wird
                    if (isMoveUp && previousElement && previousElement.querySelector('h2')) {
                        // Verhindere das Verschieben, wenn das vorherige Element die Überschrift ist
                        return;
                    }

                    // Finde die "Kategorie hinzufügen"-Box
                    const addCategoryForm = document.getElementById('new-category-form');
                    const addCategoryCard = addCategoryForm.closest('.card');

                    // Überprüfe, ob die Kategorie-Box nach unten verschoben werden soll und direkt vor der "Kategorie hinzufügen"-Box steht
                    if (!isMoveUp && categoryCard.nextElementSibling === addCategoryCard) {
                        // Verhindere das Verschieben, wenn das nächste Element die "Kategorie hinzufügen"-Box ist
                        return;
                    }

                    fetch('../src/controllers/ajax_handler.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `action=change_category_position&category_id=${categoryId}&direction=${direction}`,
                    })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                // Bewege das DOM-Element nach oben oder unten
                                if (direction === -1 && categoryCard.previousElementSibling) {
                                    categoryCard.parentNode.insertBefore(categoryCard, categoryCard.previousElementSibling);
                                } else if (direction === 1 && categoryCard.nextElementSibling && categoryCard.nextElementSibling !== addCategoryCard) {
                                    categoryCard.parentNode.insertBefore(categoryCard.nextElementSibling, categoryCard);
                                }
                            } else {
                                console.error('Failed to update category position.');
                            }
                        })
                        .catch(error => console.error('Error:', error));
                });
            });
        });

        // Delete Button pro Kategorie (de)aktivieren
        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('.delete-category-btn').forEach(function (button) {
                button.addEventListener('click', function () {
                    if (!confirm('<?php echo $lang['category_delete_confirm']; ?>')) {
                        return;
                    }

                    const categoryId = this.getAttribute('data-id');

                    fetch('../src/controllers/ajax_handler.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: new URLSearchParams({
                            action: 'delete_category',
                            category_id: categoryId
                        })
                    })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                this.closest('.card').remove();
                            } else {
                                alert(data.message);
                            }
                        })
                        .catch(error => console.error('Error:', error));
                });
            });
        });

        // Neue Kategorie hinzufügen
        document.getElementById('new-category-form').addEventListener('submit', function (e) {
            e.preventDefault();

            const formData = new FormData(this);
            formData.append('action', 'add_category');

            fetch('../src/controllers/ajax_handler.php', {
                method: 'POST',
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert('Fehler: ' + data.message);
                    }
                })
                .catch(error => console.error('Fehler:', error));
        });

        // Funktion zur Überprüfung der Anzahl der Einträge in der Kategorie
        function checkCategoryEntries(categoryId) {
            $.ajax({
                url: '../src/controllers/check_category_entries.php',
                type: 'POST',
                data: { category_id: categoryId },
                success: function (response) {
                    var count = parseInt(response);
                    var deleteBtn = $('#delete-category-btn-' + categoryId);
                    var tooltip = $('#tooltip-' + categoryId);

                    if (count > 0) {
                        deleteBtn.prop('disabled', true);
                        tooltip.show();
                    } else {
                        deleteBtn.prop('disabled', false);
                        tooltip.hide();
                    }
                }
            });
        }

        // Datei-Upload-Event-Listener für jede Datei-Spalte
        document.querySelectorAll('.file-upload-field').forEach(function (fileInput) {
            fileInput.addEventListener('change', function () {
                const filePathField = this.closest('td').querySelector('.file-path-field');
                const file = this.files[0];

                if (file) {
                    const formData = new FormData();
                    formData.append('file', file);

                    fetch('../src/controllers/ajax_handler.php', {
                        method: 'POST',
                        body: formData
                    })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                filePathField.value = data.filePath;
                                filePathField.style.display = 'block';
                                this.style.display = 'none';
                            } else {
                                alert('Fehler beim Hochladen der Datei: ' + data.message);
                            }
                        })
                        .catch(error => console.error('Fehler beim Hochladen der Datei:', error));
                }
            });
        });

        // Aktivieren Sie das Sortieren für die Tabellenzeilen
        $(function () {
            $(".resizable-table tbody").sortable({
                handle: ".handle", // Das Icon, das als Griff für das Ziehen verwendet wird
                axis: "y", // Nur vertikales Ziehen erlauben
                helper: function (e, ui) {
                    // Erstellen einer Kopie der zu verschiebenden Zeile
                    ui.children().each(function () {
                        $(this).width($(this).width()); // Setzt die Breite der Zellen
                    });
                    return ui;
                },
                placeholder: "sortable-placeholder", // Platzhalter-Klasse
                start: function (event, ui) {
                    // Höhe des Platzhalters an die Höhe der aktuellen Zeile anpassen
                    ui.placeholder.height(ui.helper.outerHeight());
                    ui.placeholder.css('visibility', 'visible'); // Sichtbar machen
                },
                update: function (event, ui) {
                    let order = [];
                    $(".resizable-table tbody tr").each(function (index) {
                        order.push({
                            id: $(this).data("id"),
                            position: index + 1
                        });
                    });

                    // AJAX-Request zum Speichern der neuen Reihenfolge in der Datenbank
                    $.ajax({
                        url: '../src/controllers/ajax_handler.php',
                        method: 'POST',
                        data: {
                            action: 'update_link_order',
                            order: order
                        },
                        success: function (response) {
                            console.log(response);
                        },
                        error: function (xhr, status, error) {
                            console.error(error);
                        }
                    });
                }
            }).disableSelection();
        });



    </script>

</body>

</html>

<?php $conn->close(); ?>