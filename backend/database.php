<?php
/**
 * Terminfinder Database Connection
 */
class Database {
    private $host;
    private $dbname;
    private $username;
    private $password;
    private $pdo;

    public function __construct() {
        $this->host = $_ENV['DB_HOST'] ?? 'database';
        $this->dbname = $_ENV['DB_NAME'] ?? 'terminfinder';
        $this->username = $_ENV['DB_USER'] ?? 'terminfinder_user';
        $this->password = $_ENV['DB_PASSWORD'] ?? 'terminfinder_pass';
    }

    public function connect() {
        if ($this->pdo === null) {
            try {
                $dsn = "mysql:host={$this->host};dbname={$this->dbname};charset=utf8mb4";
                $this->pdo = new PDO($dsn, $this->username, $this->password, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]);
            } catch (PDOException $e) {
                throw new Exception("Database connection failed: " . $e->getMessage());
            }
        }
        return $this->pdo;
    }

    public function close() {
        $this->pdo = null;
    }
}
?>