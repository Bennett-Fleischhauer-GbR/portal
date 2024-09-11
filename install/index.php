<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

ob_start();
session_start();

function getStep()
{
    return isset($_GET['step']) ? intval($_GET['step']) : 1;
}

$step = getStep();
$message = ''; // Erfolgs- oder Fehlermeldung
$success = false; // Variable, um festzustellen, ob der Schritt erfolgreich war

// Verlinkung zu den ausgelagerten Funktionsdateien je nach Step
if ($step === 1) {
    include 'assets/functions/step1.php';
} elseif ($step === 2) {
    include __DIR__ . '/../src/config/dbconnect.php';
    include 'assets/functions/step2.php';
} elseif ($step === 3) {
    include __DIR__ . '/../src/config/dbconnect.php';
    include 'assets/functions/step3.php';
}

$steps = [
    1 => "Database Setup",
    2 => "Create Admin User",
    3 => "Add SMTP Connection",
    4 => "Complete Installation"
];

$totalSteps = count($steps);
?>


<!DOCTYPE html>
<html lang="en">

<head>
    <!-- Meta-Tags -->
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="author" content="Bennett & Fleischhauer GbR">
    <meta name="robots" content="noindex, nofollow">

    <!-- Titel der Seite -->
    <title>Installer | Portal</title>

    <!-- Benutzerdefinierte Styles -->
    <link href="assets/css/style.css" rel="stylesheet">

    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="../assets/icons/default-favicon.ico">

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body>
    <div class="sidebar">
        <img src="assets/images/portal_black.svg" alt="Portal logo" class="logo">
        <div class="steps">
            <?php foreach ($steps as $num => $desc): ?>
                <div class="step <?= $step === $num ? 'active' : '' ?>">
                    <?= $num ?>. <?= $desc ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="content-container">
        <?php if ($step === 1): ?>
            <div class="header">
                <h2>Step <?= $step ?> of <?= $totalSteps ?>: <?= $steps[$step] ?></h2>
                <p>In this step, the database connection will be established, the necessary tables will be created, and the
                    database information will be saved in a file for the system to use.</p>
            </div>

            <div class="step-form">
                <?php
                if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                    $result = handleStep1();

                    if ($result['status'] === 'success') {
                        // Erfolgsmeldung
                        $message = "<div class='alert alert-success' style='color: green;'><strong>Success:</strong><ul>";
                        foreach ($result['messages'] as $msg) {
                            $message .= "<li>$msg</li>";
                        }
                        $message .= "</ul></div>";
                        $success = true;
                    } elseif ($result['status'] === 'error') {
                        // Fehlermeldung
                        echo "<div class='alert alert-danger' style='color: red;'><strong>Error:</strong> " . $result['message'] . "</div>";
                    }

                    if ($message) {
                        echo $message; // Erfolgs- oder Fehlermeldung ausgeben
                    }
                }

                // Initialisiere die Variablen für die Eingabefelder mit leeren Werten
                $hostname = isset($_POST['hostname']) ? $_POST['hostname'] : '';
                $username = isset($_POST['username']) ? $_POST['username'] : '';
                $database = isset($_POST['database']) ? $_POST['database'] : '';
                ?>

                <!-- Formular nur anzeigen, wenn der Schritt nicht erfolgreich war -->
                <?php if (!$success): ?>
                    <div id="database-form">
                        <form action="" method="post">
                            <div class="form-group">
                                <label for="hostname">Hostname:</label>
                                <input type="text" id="hostname" name="hostname" value="<?= htmlspecialchars($hostname); ?>"
                                    required>
                            </div>

                            <div class="form-group">
                                <label for="username">Username:</label>
                                <input type="text" id="username" name="username" value="<?= htmlspecialchars($username); ?>"
                                    required>
                            </div>

                            <div class="form-group">
                                <label for="password">Password:</label>
                                <input type="password" id="password" name="password" required>
                            </div>

                            <div class="form-group">
                                <label for="database">Database Name:</label>
                                <input type="text" id="database" name="database" value="<?= htmlspecialchars($database); ?>"
                                    required>
                            </div>

                            <button type="submit" class="btn btn-primary" style="width: 100%;">Create Database</button>
                        </form>
                    </div>
                <?php endif; ?>

                <!-- "Next Step"-Button anzeigen, wenn die Erstellung erfolgreich war -->
                <?php if ($success): ?>
                    <div style="text-align: right; margin-top: 20px;">
                        <a href="?step=2" class="btn btn-success" style="width: 100%;">Next Step</a>
                    </div>
                <?php endif; ?>
            </div>

        <?php elseif ($step === 2): ?>
            <div class="header">
                <h2>Step <?= $step ?> of <?= $totalSteps ?>: <?= $steps[$step] ?></h2>
                <p>In this step, an admin user will be created who can log into the system later.</p>
            </div>

            <div class="step-form">
                <?php
                include __DIR__ . '/../src/config/dbconnect.php';

                if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                    $result = handleStep2($conn);

                    if ($result['status'] === 'success') {
                        // Erfolgsmeldung
                        $message = "<div class='alert alert-success' style='color: green;'><strong>Success:</strong><ul>";
                        foreach ($result['messages'] as $msg) {
                            $message .= "<li>$msg</li>";
                        }
                        $message .= "</ul></div>";
                        $success = true;
                    } elseif ($result['status'] === 'error') {
                        // Fehlermeldung
                        echo "<div class='alert alert-danger' style='color: red;'><strong>Error:</strong> " . $result['message'] . "</div>";
                    }

                    if ($message) {
                        echo $message; // Erfolgs- oder Fehlermeldung ausgeben
                    }
                }

                // Initialisiere die Variablen für die Eingabefelder mit leeren Werten
                $email = isset($_POST['email']) ? $_POST['email'] : '';
                $username = isset($_POST['username']) ? $_POST['username'] : '';
                $first_name = isset($_POST['first_name']) ? $_POST['first_name'] : '';
                $last_name = isset($_POST['last_name']) ? $_POST['last_name'] : '';
                $city = isset($_POST['city']) ? $_POST['city'] : '';
                ?>

                <!-- Formular nur anzeigen, wenn der Schritt nicht erfolgreich war -->
                <?php if (!$success): ?>
                    <div id="admin-form">
                        <form action="" method="POST">
                            <div class="form-group">
                                <label for="email">Email:</label>
                                <input type="email" id="email" name="email" value="<?= htmlspecialchars($email); ?>" required>
                            </div>

                            <div class="form-group">
                                <label for="username">Username:</label>
                                <input type="text" id="username" name="username" value="<?= htmlspecialchars($username); ?>"
                                    required>
                            </div>

                            <div class="form-group">
                                <label for="first_name">First Name:</label>
                                <input type="text" id="first_name" name="first_name"
                                    value="<?= htmlspecialchars($first_name); ?>" required>
                            </div>

                            <div class="form-group">
                                <label for="last_name">Last Name:</label>
                                <input type="text" id="last_name" name="last_name" value="<?= htmlspecialchars($last_name); ?>"
                                    required>
                            </div>

                            <div class="form-group">
                                <label for="city">City:</label>
                                <input type="text" id="city" name="city" value="<?= htmlspecialchars($city); ?>" required>
                            </div>

                            <div class="form-group">
                                <label for="password">Password:</label>
                                <input type="password" id="password" name="password" required>
                            </div>

                            <button type="submit" class="btn btn-primary">Add User</button>
                        </form>
                    </div>
                <?php endif; ?>

                <!-- "Next Step"-Button anzeigen, wenn die Erstellung erfolgreich war -->
                <?php if ($success): ?>
                    <div style="text-align: right; margin-top: 20px;">
                        <a href="?step=3" class="btn btn-success" style="width: 100%;">Next Step</a>
                    </div>
                <?php endif; ?>
            </div>

        <?php elseif ($step === 3): ?>
            <div class="header">
                <h2>Step <?= $step ?> of <?= $totalSteps ?>: <?= $steps[$step] ?></h2>
                <p>Add SMTP Connection.</p>
            </div>

            <div class="step-form">
                <?php
                include __DIR__ . '/../src/config/dbconnect.php';

                if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                    $result = handleStep3($conn);

                    if ($result['status'] === 'success') {
                        // Erfolgsmeldung
                        $message = "<div class='alert alert-success' style='color: green;'><strong>Success:</strong><ul>";
                        foreach ($result['messages'] as $msg) {
                            $message .= "<li>$msg</li>";
                        }
                        $message .= "</ul></div>";
                        $success = true;
                    } elseif ($result['status'] === 'error') {
                        // Fehlermeldung
                        echo "<div class='alert alert-danger' style='color: red;'><strong>Error:</strong> " . $result['message'] . "</div>";
                    }

                    if ($message) {
                        echo $message; // Erfolgs- oder Fehlermeldung ausgeben
                    }
                }

                // Initialisiere die Variablen für die Eingabefelder mit leeren Werten
                $smtp_host = isset($_POST['smtp_host']) ? $_POST['smtp_host'] : '';
                $smtp_port = isset($_POST['smtp_port']) ? $_POST['smtp_port'] : '';
                $smtp_user = isset($_POST['smtp_user']) ? $_POST['smtp_user'] : '';
                $smtp_password = isset($_POST['smtp_password']) ? $_POST['smtp_password'] : '';
                $encryption_key = isset($_POST['encryption_key']) ? $_POST['encryption_key'] : '';
                $smtp_encryption = isset($_POST['smtp_encryption']) ? $_POST['smtp_encryption'] : '';
                ?>

                <!-- Formular nur anzeigen, wenn der Schritt nicht erfolgreich war -->
                <?php if (!$success): ?>
                    <div id="smtp-form">
                        <form action="" method="POST">
                            <div class="form-group">
                                <label for="smtp_host">SMTP Host:</label>
                                <input type="text" id="smtp_host" name="smtp_host" value="<?= htmlspecialchars($smtp_host); ?>"
                                    required>
                            </div>

                            <div class="form-group">
                                <label for="smtp_port">SMTP Port:</label>
                                <input type="text" id="smtp_port" name="smtp_port" value="<?= htmlspecialchars($smtp_port); ?>" required>
                                <small class="form-text text-muted">
                                    Usually, port 465 is used for SSL and port 587 for TLS encryption.
                                </small>
                            </div>


                            <div class="form-group">
                                <label for="smtp_user">SMTP User:</label>
                                <input type="text" id="smtp_user" name="smtp_user" value="<?= htmlspecialchars($smtp_user); ?>"
                                    required>
                            </div>

                            <div class="form-group">
                                <label for="smtp_password">SMTP Password:</label>
                                <input type="password" id="smtp_password" name="smtp_password" required>
                            </div>

                            <div class="form-group">
                                <label for="smtp_encryption">SMTP Encryption:</label>
                                <select id="smtp_encryption" name="smtp_encryption" class="form-control" required>
                                    <option value="tls" <?= $smtp_encryption === 'tls' ? 'selected' : ''; ?>>TLS</option>
                                    <option value="ssl" <?= $smtp_encryption === 'ssl' ? 'selected' : ''; ?>>SSL</option>
                                </select>
                            </div>


                            <button type="submit" class="btn btn-primary" style="width: 100%;">Add SMTP Settings</button>
                        </form>
                    </div>
                <?php endif; ?>

                <!-- "Next Step"-Button anzeigen, wenn die Erstellung erfolgreich war -->
                <?php if ($success): ?>
                    <div style="text-align: right; margin-top: 20px;">
                        <a href="?step=4" class="btn btn-success" style="width: 100%;">Next Step</a>
                    </div>
                <?php endif; ?>
            </div>

        <?php elseif ($step === 4): ?>
            <div class="header">
                <h2>Step <?= $step ?> of <?= $totalSteps ?>: <?= $steps[$step] ?></h2>
                <p>The installation will now be completed, and you will be redirected to the login page.</p>
            </div>

            <div class="step-form">
                <?php
                if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                    // Redirect to the login page and trigger the deletion process
                    header("Location: /auth/login.php?deleteInstaller=true");
                    exit(); // Beende das Skript hier, damit keine weiteren Inhalte geladen werden
                }
                ?>

                <form action="" method="POST">
                    <button type="submit" class="btn btn-danger" style="width: 100%;">Complete Installation</button>
                </form>
            </div>

        <?php endif; ?>
    </div>

    <footer>
        &copy; 2023 – 2024 | Bennett & Fleischhauer GbR – All rights reserved.
    </footer>
</body>

</html>

<?php
ob_end_flush();
?>