<?php

declare(strict_types=1);

$host = '127.0.0.1';
$port = 3306;
$database = 'gestion_caisse';
$applicationUser = 'caisse_app';
$adminUser = getenv('MYSQL_ADMIN_USER') ?: 'root';
$adminPassword = getenv('MYSQL_ADMIN_PASSWORD');

if ($adminPassword === false) {
    fwrite(STDERR, "Le mot de passe administrateur MySQL n'a pas été transmis.\n");
    exit(1);
}

$project = __DIR__;
$envPath = $project . DIRECTORY_SEPARATOR . '.env';
$sqlitePath = $project . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'database.sqlite';
$backupDirectory = $project . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'backups';
$applicationPassword = bin2hex(random_bytes(24));

function title(string $message): void
{
    echo "\n{$message}\n";
}

function runCommand(string $command): void
{
    passthru($command, $exitCode);
    if ($exitCode !== 0) {
        throw new RuntimeException("La commande a échoué avec le code {$exitCode}.");
    }
}

function updateEnvironment(string $path, array $values): void
{
    $content = file_exists($path) ? (string) file_get_contents($path) : '';
    foreach ($values as $key => $value) {
        $line = $key . '=' . $value;
        $pattern = '/^' . preg_quote($key, '/') . '=.*$/m';
        if (preg_match($pattern, $content)) {
            $content = (string) preg_replace($pattern, $line, $content);
        } else {
            $content = rtrim($content) . PHP_EOL . $line . PHP_EOL;
        }
    }
    file_put_contents($path, rtrim($content) . PHP_EOL);
}

function sqliteTableExists(PDO $pdo, string $table): bool
{
    $statement = $pdo->prepare("SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = ?");
    $statement->execute([$table]);
    return (bool) $statement->fetchColumn();
}

function importSqlite(PDO $mysql, string $sqlitePath): void
{
    if (!file_exists($sqlitePath) || filesize($sqlitePath) === 0) {
        echo "   Aucune ancienne base SQLite à importer.\n";
        return;
    }

    $sqlite = new PDO('sqlite:' . $sqlitePath, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    if (!sqliteTableExists($sqlite, 'users')) {
        echo "   La base SQLite ne contient aucune donnée applicative.\n";
        return;
    }

    $existingUsers = (int) $mysql->query('SELECT COUNT(*) FROM users')->fetchColumn();
    $existingTransactions = (int) $mysql->query('SELECT COUNT(*) FROM transactions')->fetchColumn();
    if ($existingUsers > 0 || $existingTransactions > 0) {
        echo "   La base MySQL contient déjà des données : import SQLite ignoré.\n";
        return;
    }

    $users = $sqlite->query('SELECT id, name, email, password, is_admin, is_active, remember_token, created_at, updated_at FROM users ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
    $transactions = sqliteTableExists($sqlite, 'transactions')
        ? $sqlite->query('SELECT id, request_key, user_id, type, amount_minor, payment_method, description, justification, occurred_on, cancelled_at, cancelled_by, cancellation_reason, created_at, updated_at FROM transactions ORDER BY id')->fetchAll(PDO::FETCH_ASSOC)
        : [];
    $cashBalance = sqliteTableExists($sqlite, 'cash_accounts')
        ? $sqlite->query('SELECT balance_minor FROM cash_accounts WHERE id = 1')->fetchColumn()
        : false;

    $mysql->beginTransaction();
    try {
        $insertUser = $mysql->prepare('INSERT INTO users (id, name, email, password, is_admin, is_active, remember_token, created_at, updated_at) VALUES (:id, :name, :email, :password, :is_admin, :is_active, :remember_token, :created_at, :updated_at)');
        foreach ($users as $user) {
            $insertUser->execute($user);
        }

        $insertTransaction = $mysql->prepare('INSERT INTO transactions (id, request_key, user_id, type, amount_minor, payment_method, description, justification, occurred_on, cancelled_at, cancelled_by, cancellation_reason, created_at, updated_at) VALUES (:id, :request_key, :user_id, :type, :amount_minor, :payment_method, :description, :justification, :occurred_on, :cancelled_at, :cancelled_by, :cancellation_reason, :created_at, :updated_at)');
        foreach ($transactions as $transaction) {
            $insertTransaction->execute($transaction);
        }

        if ($cashBalance !== false) {
            $updateBalance = $mysql->prepare('UPDATE cash_accounts SET balance_minor = ? WHERE id = 1');
            $updateBalance->execute([$cashBalance]);
        }

        $mysql->commit();
        echo '   ' . count($users) . ' utilisateur(s) et ' . count($transactions) . " opération(s) importés.\n";
    } catch (Throwable $exception) {
        $mysql->rollBack();
        throw $exception;
    }
}

try {
    title("1/6 Connexion à MySQL sur {$host}:{$port}…");
    $admin = new PDO(
        "mysql:host={$host};port={$port};charset=utf8mb4",
        $adminUser,
        $adminPassword,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]
    );
    echo "   Connexion réussie.\n";

    title("2/6 Création de la base et du compte de l’application…");
    $quotedPassword = $admin->quote($applicationPassword);
    $admin->exec("CREATE DATABASE IF NOT EXISTS `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    foreach (['localhost', '127.0.0.1'] as $allowedHost) {
        $admin->exec("CREATE USER IF NOT EXISTS '{$applicationUser}'@'{$allowedHost}' IDENTIFIED BY {$quotedPassword}");
        $admin->exec("ALTER USER '{$applicationUser}'@'{$allowedHost}' IDENTIFIED BY {$quotedPassword}");
        $admin->exec("GRANT ALL PRIVILEGES ON `{$database}`.* TO '{$applicationUser}'@'{$allowedHost}'");
    }
    $admin->exec('FLUSH PRIVILEGES');
    echo "   Base et compte applicatif prêts.\n";

    title('3/6 Sauvegarde de la base SQLite actuelle…');
    if (file_exists($sqlitePath) && filesize($sqlitePath) > 0) {
        if (!is_dir($backupDirectory)) {
            mkdir($backupDirectory, 0775, true);
        }
        $backupPath = $backupDirectory . DIRECTORY_SEPARATOR . 'database-before-mysql-' . date('Ymd-His') . '.sqlite';
        copy($sqlitePath, $backupPath);
        echo "   Sauvegarde créée : {$backupPath}\n";
    } else {
        echo "   Aucune base SQLite existante.\n";
    }

    title('4/6 Configuration de Laravel…');
    updateEnvironment($envPath, [
        'DB_CONNECTION' => 'mysql',
        'DB_HOST' => $host,
        'DB_PORT' => (string) $port,
        'DB_DATABASE' => $database,
        'DB_USERNAME' => $applicationUser,
        'DB_PASSWORD' => '"' . $applicationPassword . '"',
    ]);
    runCommand('call "' . $project . DIRECTORY_SEPARATOR . 'php.cmd" artisan config:clear');

    title('5/6 Création des tables MySQL…');
    runCommand('call "' . $project . DIRECTORY_SEPARATOR . 'php.cmd" artisan migrate --force');

    title('6/6 Transfert des données existantes…');
    $mysql = new PDO(
        "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4",
        $applicationUser,
        $applicationPassword,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]
    );
    importSqlite($mysql, $sqlitePath);

    echo "\nConfiguration terminée. Laravel stocke maintenant ses données dans MySQL.\n";
} catch (Throwable $exception) {
    fwrite(STDERR, "\nERREUR : {$exception->getMessage()}\n");
    exit(1);
}
