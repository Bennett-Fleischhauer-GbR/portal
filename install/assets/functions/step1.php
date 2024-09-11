<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

function handleStep1() {
    // Variable für Erfolgsmeldungen und Fehler
    $successMessages = [];
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Bereinigung des hostname-Feldes
        $hostname = $_POST['hostname'];
        
        // Entferne 'http://' oder 'https://' und führende oder abschließende '/'
        $hostname = preg_replace('#^https?://#', '', rtrim($hostname, '/'));
        
        $username = $_POST['username'];
        $password = $_POST['password'];
        $database = $_POST['database'];
        
        try {
            // Verbindung zur Datenbank herstellen
            $conn = new mysqli($hostname, $username, $password, $database);
            if ($conn->connect_error) {
                throw new Exception($conn->connect_error);
            }

            // Verbindung erfolgreich
            $successMessages[] = "Connection to the database was successful.";

            // Foreign Key Checks deaktivieren und alle Tabellen löschen
            $conn->query("SET FOREIGN_KEY_CHECKS = 0");

            $result = $conn->query("SHOW TABLES");
            if ($result) {
                while ($row = $result->fetch_array()) {
                    $table = $row[0];
                    $dropTableQuery = "DROP TABLE IF EXISTS `$table`";
                    if ($conn->query($dropTableQuery) !== TRUE) {
                        throw new Exception("Error deleting table '$table': " . $conn->error);
                    }
                }
                $successMessages[] = "All tables were successfully deleted.";
            } else {
                throw new Exception("Error retrieving table names.");
            }

            $conn->query("SET FOREIGN_KEY_CHECKS = 1");

            // SQL Datei einlesen und ausführen
            $sqlFilePath = __DIR__ . '/../sql/database.sql';
            if (!file_exists($sqlFilePath)) {
                throw new Exception("SQL file 'database.sql' not found.");
            }

            $sqlContent = file_get_contents($sqlFilePath);
            if ($sqlContent === false) {
                throw new Exception("Error reading the SQL file.");
            }

            if ($conn->multi_query($sqlContent) === TRUE) {
                do {
                    // Weitergehen, bis alle Abfragen abgeschlossen sind
                } while ($conn->more_results() && $conn->next_result());
                
                $successMessages[] = "SQL file successfully imported.";
            } else {
                throw new Exception("Error executing the SQL file: " . $conn->error);
            }

            $conn->close();

            // Konfigurationsdatei erstellen
            $configContent = "<?php\n" .
                "if (basename(\$_SERVER['PHP_SELF']) == basename(__FILE__)) {\n" .
                "    header(\"Location: /404.php\");\n" .
                "    exit;\n" .
                "}\n\n" .
                "\$servername = \"$hostname\";\n" .
                "\$username = \"$username\";\n" .
                "\$password = \"$password\";\n" .
                "\$dbname = \"$database\";\n\n" .
                "\$conn = new mysqli(\$servername, \$username, \$password, \$dbname);\n\n" .
                "if (\$conn->connect_error) {\n" .
                "    die(\"Connection failed: \" . \$conn->connect_error);\n" .
                "}\n?>";

            $configDirPath = __DIR__ . '/../../../src/config';
            $configFilePath = $configDirPath . '/dbconnect.php';
            
            if (!file_exists($configDirPath)) {
                if (!mkdir($configDirPath, 0777, true)) {
                    throw new Exception("Error creating directory 'src/config'.");
                }
            }

            $file = fopen($configFilePath, "w");
            if ($file) {
                fwrite($file, $configContent);
                fclose($file);
                $successMessages[] = "The file 'dbconnect.php' was successfully created in the /src/config folder.";
            } else {
                throw new Exception("Error creating the file 'dbconnect.php'.");
            }

            // Erfolg zurückgeben, wenn alles abgeschlossen ist
            return ['status' => 'success', 'messages' => $successMessages];

        } catch (Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    return '';
}
?>
