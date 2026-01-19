-- Terminfinder Database Schema

-- Gruppen Tabelle
CREATE TABLE IF NOT EXISTS `groups` (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(255) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_code (code)
);

-- Gruppen-Passwörter Tabelle
CREATE TABLE IF NOT EXISTS `group_passwords` (
    id INT AUTO_INCREMENT PRIMARY KEY,
    group_code VARCHAR(255) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (group_code) REFERENCES `groups`(code) ON DELETE CASCADE,
    UNIQUE KEY unique_group_password (group_code)
);

-- Verfügbarkeiten Tabelle
CREATE TABLE IF NOT EXISTS `availabilities` (
    id INT AUTO_INCREMENT PRIMARY KEY,
    group_code VARCHAR(255) NOT NULL,
    user_name VARCHAR(255) NOT NULL,
    date DATE NOT NULL,
    time_slot VARCHAR(10) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (group_code) REFERENCES `groups`(code) ON DELETE CASCADE,
    UNIQUE KEY unique_user_date_slot (group_code, user_name, date, time_slot),
    INDEX idx_group_code (group_code),
    INDEX idx_user_name (user_name),
    INDEX idx_date (date)
);

-- Beispiel-Daten einfügen (optional für Tests)
INSERT IGNORE INTO `groups` (code) VALUES 
('demo2024'),
('team2024');

INSERT IGNORE INTO `group_passwords` (group_code, password_hash) VALUES 
('demo2024', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'), -- password = "demo123"
('team2024', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'); -- password = "team123"

-- Demo Verfügbarkeiten
INSERT IGNORE INTO `availabilities` (group_code, user_name, date, time_slot) VALUES 
('demo2024', 'Max Mustermann', '2026-01-20', 'morning'),
('demo2024', 'Max Mustermann', '2026-01-20', 'afternoon'),
('demo2024', 'Anna Schmidt', '2026-01-20', 'morning'),
('demo2024', 'Anna Schmidt', '2026-01-21', 'evening'),
('demo2024', 'Tom Weber', '2026-01-20', 'morning'),
('demo2024', 'Tom Weber', '2026-01-22', 'afternoon');

-- Share links table: stores hashed tokens for deeplinks that allow auto-authentication
CREATE TABLE IF NOT EXISTS `share_links` (
    id INT AUTO_INCREMENT PRIMARY KEY,
    group_code VARCHAR(255) NOT NULL,
    token_hash CHAR(64) NOT NULL,
    expires_at DATETIME DEFAULT NULL,
    single_use TINYINT(1) DEFAULT 0,
    used_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_share_group_code (group_code),
    UNIQUE KEY unique_token_hash (token_hash),
    FOREIGN KEY (group_code) REFERENCES `groups`(code) ON DELETE CASCADE
);