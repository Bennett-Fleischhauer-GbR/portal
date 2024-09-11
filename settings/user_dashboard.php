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

function get_admin_emails($conn)
{
    $admin_emails = [];
    $sql = "SELECT email FROM users WHERE role = 'admin'";
    $result = $conn->query($sql);

    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $admin_emails[] = $row['email'];
        }
    }
    return $admin_emails;
}

$user_list_message = '';
$new_user_message = '';

// Handle deletion of a user
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user_id'])) {
    $delete_user_id = $_POST['delete_user_id'];

    // Fetch user email before deletion
    $user_email_sql = "SELECT email, first_name FROM users WHERE id = ?";
    $user_email_stmt = $conn->prepare($user_email_sql);
    $user_email_stmt->bind_param("i", $delete_user_id);
    $user_email_stmt->execute();
    $user_email_result = $user_email_stmt->get_result();
    $user_email = $user_email_result->fetch_assoc();
    $user_email_stmt->close();

    if ($user_email) {
        // Delete related password reset entries first
        $delete_reset_sql = "DELETE FROM password_resets WHERE user_id = ?";
        $delete_reset_stmt = $conn->prepare($delete_reset_sql);
        $delete_reset_stmt->bind_param("i", $delete_user_id);
        $delete_reset_stmt->execute();
        $delete_reset_stmt->close();

        // Delete the user
        $delete_sql = "DELETE FROM users WHERE id = ?";
        $delete_stmt = $conn->prepare($delete_sql);
        $delete_stmt->bind_param("i", $delete_user_id);

        if ($delete_stmt->execute()) {
            // Send email notification to the deleted user
            $subject = $lang['email_subject_user_deleted'];
            $header = $lang['email_header_user_deleted'];
            $content = "
                <p>{$lang['dear']} {$user_email['first_name']},</p>
                <p>{$lang['email_body_user_deleted']}</p>
                <p>{$lang['contact_us_immediately']}</p>
            ";

            send_email($user_email['email'], $subject, $header, $content, get_smtp_settings($conn), $conn);

            // Notify all admins
            $admin_emails = get_admin_emails($conn);
            $admin_subject = $lang['email_subject_admin_user_deleted'];
            $admin_content = "
                <p>{$lang['email_body_admin_user_deleted']}:</p>
                <p><strong>{$lang['first_name']}:</strong> {$user_email['first_name']}</p>
                <p><strong>{$lang['email']}:</strong> {$user_email['email']}</p>
            ";

            foreach ($admin_emails as $admin_email) {
                send_email($admin_email, $admin_subject, $lang['email_admin_notification'], $admin_content, get_smtp_settings($conn), $conn);
            }

            $user_list_message = "<div class='alert alert-success'>{$lang['user_deleted_success']}</div>";
        } else {
            $user_list_message = "<div class='alert alert-danger'>{$lang['user_deleted_error']}</div>";
        }

        $delete_stmt->close();
    } else {
        $user_list_message = "<div class='alert alert-danger'>{$lang['user_not_found']}</div>";
    }
}

// Handle update of user role
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_user_id']) && isset($_POST['role'])) {
    $update_user_id = $_POST['update_user_id'];
    $new_role = $_POST['role'];

    $update_sql = "UPDATE users SET role = ? WHERE id = ?";
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->bind_param("si", $new_role, $update_user_id);

    if ($update_stmt->execute()) {
        // Fetch user email for notification
        $user_email_sql = "SELECT email FROM users WHERE id = ?";
        $user_email_stmt = $conn->prepare($user_email_sql);
        $user_email_stmt->bind_param("i", $update_user_id);
        $user_email_stmt->execute();
        $user_email_result = $user_email_stmt->get_result();
        $user_email = $user_email_result->fetch_assoc()['email'];
        $user_email_stmt->close();

        // Send role change notification email
        $subject = $lang['email_subject_role_updated'];
        $header = $lang['email_header_role_updated'];
        $content = "
            <p>{$lang['dear_user']},</p>
            <p>{$lang['email_body_role_updated']} <strong>$new_role</strong>.</p>
            <p>{$lang['contact_us_if_questions']}</p>
        ";

        send_email($user_email, $subject, $header, $content, get_smtp_settings($conn), $conn);

        // Notify all admins
        $admin_emails = get_admin_emails($conn);
        $admin_subject = $lang['email_subject_admin_role_updated'];
        $admin_content = "
            <p>{$lang['email_body_admin_role_updated']}:</p>
            <p><strong>{$lang['email']}:</strong> $user_email</p>
            <p><strong>{$lang['new_role']}:</strong> $new_role</p>
        ";

        foreach ($admin_emails as $admin_email) {
            send_email($admin_email, $admin_subject, $lang['email_admin_notification'], $admin_content, get_smtp_settings($conn), $conn);
        }

        $user_list_message = "<div class='alert alert-success'>{$lang['user_role_updated_success']}</div>";
    } else {
        $user_list_message = "<div class='alert alert-danger'>{$lang['user_role_updated_error']}</div>";
    }

    $update_stmt->close();
}

// Handle sending password reset link
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_reset_link'])) {
    $email = $_POST['send_reset_link'];

    // Fetch user information
    $sql = "SELECT * FROM users WHERE email = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        $user_id = $user['id'];

        $token = bin2hex(random_bytes(50));
        $created_at = date('Y-m-d H:i:s');

        // Store token in the database
        $sql = "INSERT INTO password_resets (user_id, token, created_at) VALUES (?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iss", $user_id, $token, $created_at);
        $stmt->execute();
        $stmt->close();

        // Dynamische Domain und Protokoll ermitteln
        $protocol = $_SERVER['REQUEST_SCHEME'];
        $domain = $_SERVER['HTTP_HOST'];

        // Korrekte Pfadstruktur zu auth/password_update.php
        $reset_link = "$protocol://$domain/auth/password_update.php?token=$token";

        // Email content
        $subject = $lang['email_subject_password_reset'];
        $header = $lang['email_header_password_reset'];
        $content = "
            <p>{$lang['dear_user']},</p>
            <p>{$lang['email_body_password_reset']}</p>
            <p><a href='$reset_link' style='color: #1a82e2; text-decoration: none;'>{$lang['reset_password']}</a></p>
            <p>{$lang['email_body_password_reset_ignore']}</p>
        ";

        if (send_email($email, $subject, $header, $content, get_smtp_settings($conn), $conn)) {
            $user_list_message = "<div class='alert alert-success'>{$lang['password_reset_email_sent']}</div>";
        } else {
            $user_list_message = "<div class='alert alert-danger'>{$lang['password_reset_email_error']}</div>";
        }
    } else {
        $user_list_message = "<div class='alert alert-danger'>{$lang['user_not_found']}</div>";
    }
}

// Handle creation of a new user
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_user'])) {
    $first_name = $_POST['first_name'];
    $last_name = $_POST['last_name'];
    $email = $_POST['email'];
    $username = $_POST['username'];
    $city = $_POST['city'];
    $role = $_POST['role'];
    $full_name = $first_name . ' ' . $last_name;
    $box_order = 'box1,box2,box3';

    // Check if email or username already exists
    $check_sql = "SELECT id FROM users WHERE email = ? OR username = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("ss", $email, $username);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();

    if ($check_result->num_rows > 0) {
        // If a user with the same email or username exists, show an error message
        $new_user_message = "<div class='alert alert-danger'>{$lang['user_exists_error']}</div>";
    } else {
        // Proceed with user creation if no duplicate found
        $create_sql = "INSERT INTO users (first_name, last_name, email, username, city, role, full_name, box_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $create_stmt = $conn->prepare($create_sql);
        $create_stmt->bind_param("ssssssss", $first_name, $last_name, $email, $username, $city, $role, $full_name, $box_order);

        if ($create_stmt->execute()) {
            $user_id = $create_stmt->insert_id; // Get the newly created user's ID

            // Generate password reset token
            $token = bin2hex(random_bytes(50));
            $created_at = date('Y-m-d H:i:s');

            // Store token in the database
            $sql = "INSERT INTO password_resets (user_id, token, created_at) VALUES (?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("iss", $user_id, $token, $created_at);
            $stmt->execute();
            $stmt->close();

            // Dynamische Domain und Protokoll ermitteln
            $protocol = $_SERVER['REQUEST_SCHEME'];
            $domain = $_SERVER['HTTP_HOST'];

            // Korrekte Pfadstruktur zu auth/password_update.php
            $reset_link = "$protocol://$domain/auth/password_update.php?token=$token";

            // Send new account creation email with password reset link
            $subject = $lang['email_subject_account_created'];
            $header = $lang['email_header_account_created'];
            $content = "
                <p>{$lang['dear_user']},</p>
                <p>{$lang['email_body_account_created']} <strong>$role</strong>.</p>
                <p>{$lang['username']} <strong>$username</strong></p>
                <p>{$lang['email_body_set_password']}</p>
                <p><a href='$reset_link' style='color: #1a82e2; text-decoration: none;'>{$lang['reset_password']}</a></p>
                <p>{$lang['contact_us_if_questions']}</p>
            ";

            send_email($email, $subject, $header, $content, get_smtp_settings($conn), $conn);

            // Notify all admins
            $admin_emails = get_admin_emails($conn);
            $admin_subject = $lang['email_subject_admin_new_user'];
            $admin_content = "
                <p>{$lang['email_body_admin_new_user']}:</p>
                <p><strong>{$lang['first_name']}:</strong> $first_name</p>
                <p><strong>{$lang['last_name']}:</strong> $last_name</p>
                <p><strong>{$lang['email']}:</strong> $email</p>
                <p><strong>{$lang['username']}:</strong> $username</p>
                <p><strong>{$lang['role']}:</strong> $role</p>
            ";

            foreach ($admin_emails as $admin_email) {
                send_email($admin_email, $admin_subject, $lang['email_admin_notification'], $admin_content, get_smtp_settings($conn), $conn);
            }

            $new_user_message = "<div class='alert alert-success'>{$lang['user_created_success']}</div>";
        } else {
            $new_user_message = "<div class='alert alert-danger'>{$lang['user_created_error']}</div>";
        }

        $create_stmt->close();
    }

    $check_stmt->close();
}

function get_smtp_settings($conn)
{
    $sql = "SELECT smtp_host, smtp_port, smtp_user, smtp_password, smtp_encryption FROM smtp_settings WHERE id = 1";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $result = $stmt->get_result();
    $smtp_settings = $result->fetch_assoc();
    $stmt->close();

    return $smtp_settings ?: null;
}

// Fetch logged-in user data
$user_id = $_SESSION['user_id'];
$user_sql = "SELECT first_name, email FROM users WHERE id = ?";
$user_stmt = $conn->prepare($user_sql);
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user_result = $user_stmt->get_result();
$user = $user_result->fetch_assoc();
$first_name = $user['first_name'];
$email = $user['email'];
$user_stmt->close();

// Fetch all users for the dashboard
$users_per_page = 10;
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
$start = ($page - 1) * $users_per_page;

$sql = "SELECT id, first_name, last_name, email, username, city, role FROM users LIMIT ?, ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $start, $users_per_page);
$stmt->execute();
$result = $stmt->get_result();
$users = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$total_sql = "SELECT COUNT(*) as total FROM users";
$total_result = $conn->query($total_sql);
$total_users = $total_result->fetch_assoc()['total'];
$total_pages = ceil($total_users / $users_per_page);
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
    $page_specific_title = $lang['user_dashboard']; ?>
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
        <!-- Benutzer-Dashboard Überschrift -->
        <div class="row mb-4">
            <div class="col-12">
                <h2><?php echo $lang['user_dashboard']; ?></h2>
                <p><?php echo $lang['overview_users']; ?></p>
            </div>
        </div>

        <!-- Benutzerliste -->
        <div class="card mb-5">
            <div class="card-body">
                <div class="row mb-2 d-flex justify-content-between align-items-center">
                    <div class="col-6">
                        <h3><?php echo $lang['user_list']; ?></h3>
                    </div>
                    <div class="col-6 text-end">
                        <button
                            class="reset-table btn btn-outline-secondary"><?php echo $lang['reset_columns_width']; ?></button>
                    </div>
                </div>
                <?php echo $user_list_message; ?>

                <!-- Desktop Ansicht -->
                <div class="table-responsive d-none d-md-block">
                    <table class="table table-striped user-table resizable-table">
                        <thead>
                            <tr>
                                <th scope="col"><?php echo $lang['avatar']; ?></th>
                                <th scope="col"><?php echo $lang['first_name']; ?></th>
                                <th scope="col"><?php echo $lang['last_name']; ?></th>
                                <th scope="col"><?php echo $lang['username']; ?></th>
                                <th scope="col"><?php echo $lang['email']; ?></th>
                                <th scope="col"><?php echo $lang['city']; ?></th>
                                <th scope="col"><?php echo $lang['role']; ?></th>
                                <th scope="col"><?php echo $lang['actions']; ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td><img src="<?php echo getGravatar($user['email']); ?>" alt="Avatar"
                                            class="rounded-circle gravatar-icon"></td>
                                    <td><?php echo htmlspecialchars($user['first_name']); ?></td>
                                    <td><?php echo htmlspecialchars($user['last_name']); ?></td>
                                    <td><?php echo htmlspecialchars($user['username']); ?></td>
                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                    <td><?php echo htmlspecialchars($user['city']); ?></td>
                                    <td>
                                        <form method="post" action="user_dashboard.php" style="display:inline;">
                                            <input type="hidden" name="update_user_id" value="<?php echo $user['id']; ?>">
                                            <select name="role" class="form-select form-select-sm"
                                                onchange="this.form.submit()">
                                                <option value="user" <?php echo ($user['role'] === 'user') ? 'selected' : ''; ?>>User</option>
                                                <option value="author" <?php echo ($user['role'] === 'author') ? 'selected' : ''; ?>>Autor</option>
                                                <option value="admin" <?php echo ($user['role'] === 'admin') ? 'selected' : ''; ?>>Admin</option>
                                            </select>
                                        </form>
                                    </td>
                                    <td>
                                        <form method="post" action="user_dashboard.php"
                                            onsubmit="return confirm('<?php echo $lang['are_you_sure_delete_user']; ?>');"
                                            style="display:inline;">
                                            <input type="hidden" name="delete_user_id" value="<?php echo $user['id']; ?>">
                                            <button type="submit"
                                                class="btn btn-danger btn-sm"><?php echo $lang['delete']; ?></button>
                                        </form>
                                        <form method="post" action="user_dashboard.php" style="display:inline;">
                                            <input type="hidden" name="send_reset_link"
                                                value="<?php echo $user['email']; ?>">
                                            <button type="submit"
                                                class="btn btn-warning btn-sm"><?php echo $lang['reset_password']; ?></button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Mobile Ansicht -->
                <div class="d-block d-md-none">
                    <?php foreach ($users as $user): ?>
                        <div class="mobile-card mb-3">
                            <div class="mobile-card-header d-flex align-items-center">
                                <img src="<?php echo getGravatar($user['email']); ?>" alt="Avatar"
                                    class="rounded-circle gravatar-icon me-3">
                                <div>
                                    <h5><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>
                                    </h5>
                                    <p><?php echo htmlspecialchars($user['username']); ?></p>
                                </div>
                            </div>
                            <div class="mobile-card-content">
                                <p><strong><?php echo $lang['email']; ?>:</strong>
                                    <?php echo htmlspecialchars($user['email']); ?></p>
                                <p><strong><?php echo $lang['city']; ?>:</strong>
                                    <?php echo htmlspecialchars($user['city']); ?></p>
                                <p><strong><?php echo $lang['role']; ?>:</strong>
                                    <?php echo htmlspecialchars($user['role']); ?></p>
                            </div>
                            <div class="mobile-card-actions">
                                <form method="post" action="user_dashboard.php" style="display:inline;">
                                    <input type="hidden" name="update_user_id" value="<?php echo $user['id']; ?>">
                                    <select name="role" class="form-select form-select-sm" onchange="this.form.submit()">
                                        <option value="user" <?php echo ($user['role'] === 'user') ? 'selected' : ''; ?>>User
                                        </option>
                                        <option value="admin" <?php echo ($user['role'] === 'admin') ? 'selected' : ''; ?>>
                                            Admin</option>
                                    </select>
                                </form>
                                <form method="post" action="user_dashboard.php"
                                    onsubmit="return confirm('<?php echo $lang['are_you_sure_delete_user']; ?>');"
                                    style="display:inline;">
                                    <input type="hidden" name="delete_user_id" value="<?php echo $user['id']; ?>">
                                    <button type="submit"
                                        class="btn btn-danger btn-sm mt-2"><?php echo $lang['delete']; ?></button>
                                </form>
                                <form method="post" action="user_dashboard.php" style="display:inline;">
                                    <input type="hidden" name="send_reset_link" value="<?php echo $user['email']; ?>">
                                    <button type="submit"
                                        class="btn btn-warning btn-sm mt-2"><?php echo $lang['reset_password']; ?></button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

            </div>
        </div>

        <!-- Neuen Benutzer anlegen -->
        <div class="card mb-5">
            <div class="card-body">
                <h3><?php echo $lang['create_new_user']; ?></h3>
                <?php if (!empty($new_user_message))
                    echo $new_user_message; ?>
                <form method="post" action="user_dashboard.php">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="first_name" class="form-label"><?php echo $lang['first_name']; ?></label>
                            <input type="text" class="form-control" id="first_name" name="first_name" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="last_name" class="form-label"><?php echo $lang['last_name']; ?></label>
                            <input type="text" class="form-control" id="last_name" name="last_name" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="email" class="form-label"><?php echo $lang['email']; ?></label>
                        <input type="email" class="form-control" id="email" name="email" required>
                    </div>
                    <div class="mb-3">
                        <label for="username" class="form-label"><?php echo $lang['username']; ?></label>
                        <input type="text" class="form-control" id="username" name="username" required>
                    </div>
                    <div class="mb-3">
                        <label for="city" class="form-label"><?php echo $lang['city']; ?></label>
                        <input type="text" class="form-control" id="city" name="city">
                    </div>
                    <div class="mb-3">
                        <label for="role" class="form-label"><?php echo $lang['role']; ?></label>
                        <select class="form-control" id="role" name="role">
                            <option value="user">User</option>
                            <option value="author">Author</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                    <button type="submit" name="create_user"
                        class="btn btn-primary"><?php echo $lang['create_user']; ?></button>
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
    <script>
        document.addEventListener('DOMContentLoaded', function () {
    const tables = document.querySelectorAll('.resizable-table');

    tables.forEach((table, tableIndex) => {
        const thElements = Array.from(table.querySelectorAll('th'));
        const resetButton = table.closest('.card-body').querySelector('.reset-table');
        let originalWidths = [];

        // Lade gespeicherte Breiten aus LocalStorage oder speichere die originalen Breiten
        thElements.forEach((th, index) => {
            const storedWidth = localStorage.getItem(`user-table-${tableIndex}-column-${index}`);
            const originalWidth = localStorage.getItem(`user-table-${tableIndex}-original-column-${index}`);

            if (storedWidth) {
                th.style.width = storedWidth;
            } else {
                const width = parseInt(document.defaultView.getComputedStyle(th).width, 10);
                th.style.width = width + 'px'; // Sicherstellen, dass die Breite gesetzt ist
                originalWidths.push(width);
                // Speichere die ursprüngliche Breite im LocalStorage
                localStorage.setItem(`user-table-${tableIndex}-original-column-${index}`, width);
            }

            if (originalWidth) {
                originalWidths[index] = parseInt(originalWidth, 10);
            }
        });

        thElements.forEach((th, index) => {
    const resizer = document.createElement('div');
    resizer.className = 'resizer';
    th.appendChild(resizer);

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

        if (index === 0) {
            // Breitere Ziehbare Breite für die erste Spalte
            const newWidth = startWidth + dx * 1.5; // Erhöhen der Ziehgeschwindigkeit
            if (newWidth > 100) {
                th.style.width = newWidth + 'px';
            }
        } else if (index < thElements.length - 1) {
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
            localStorage.setItem(`user-table-${tableIndex}-column-${index}`, th.style.width);
        });

        document.documentElement.removeEventListener('mousemove', doDrag, false);
        document.documentElement.removeEventListener('mouseup', stopDrag, false);
    }
});


        // Setze die Spaltenbreiten zurück
        resetButton.addEventListener('click', function () {
            thElements.forEach((th, index) => {
                const originalWidth = localStorage.getItem(`user-table-${tableIndex}-original-column-${index}`);
                th.style.width = originalWidth + 'px';
                // Lösche gespeicherte Breiten aus LocalStorage
                localStorage.removeItem(`user-table-${tableIndex}-column-${index}`);
            });
        });
    });
});

    </script>

</body>

</html>
