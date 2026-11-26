<?php

const FEATURE_SCHEMA_VERSION = '2026-11-admin-crud-1';

function feature_schema_is_current(): bool
{
    $versionTable = (int)scalar(
        'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
        ['schema_migrations']
    );
    if (!$versionTable) {
        return false;
    }

    return (bool)scalar('SELECT COUNT(*) FROM schema_migrations WHERE version = ?', [FEATURE_SCHEMA_VERSION]);
}

function require_feature_schema(): void
{
    if (feature_schema_is_current()) {
        return;
    }

    $user = current_user();
    flash('error', 'This feature is not available until the administrator completes the system upgrade.');
    redirect($user && $user['role'] === 'admin' ? 'admin/database_upgrade.php' : 'index.php');
}

function upgrade_feature_schema(): void
{
    $pdo = db();
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS schema_migrations (
            version VARCHAR(100) NOT NULL PRIMARY KEY,
            applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB'
    );

    $columns = [
        'users' => [
            'privacy_consent_at' => 'DATETIME NULL',
            'privacy_notice_version' => 'VARCHAR(20) NULL',
        ],
        'complaints' => [
            'archived_at' => 'DATETIME NULL',
            'archived_by' => 'INT NULL',
        ],
        'announcements' => [
            'archived_at' => 'DATETIME NULL',
            'archived_by' => 'INT NULL',
        ],
    ];
    foreach ($columns as $table => $tableColumns) {
        foreach ($tableColumns as $name => $definition) {
            $exists = (int)scalar(
                'SELECT COUNT(*) FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
                [$table, $name]
            );
            if (!$exists) {
                $pdo->exec('ALTER TABLE ' . $table . ' ADD COLUMN ' . $name . ' ' . $definition);
            }
        }
    }
    foreach ([
        ['complaints', 'idx_complaints_archived'],
        ['announcements', 'idx_announcements_archived'],
    ] as [$table, $index]) {
        $exists = (int)scalar(
            'SELECT COUNT(*) FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?',
            [$table, $index]
        );
        if (!$exists) {
            $pdo->exec('ALTER TABLE ' . $table . ' ADD INDEX ' . $index . ' (archived_at)');
        }
    }

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS complaint_messages (
            id INT AUTO_INCREMENT PRIMARY KEY,
            complaint_id INT NOT NULL,
            sender_id INT NOT NULL,
            message TEXT NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_message_complaint (complaint_id, created_at),
            CONSTRAINT fk_message_complaint FOREIGN KEY (complaint_id) REFERENCES complaints(id) ON DELETE CASCADE,
            CONSTRAINT fk_message_sender FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB'
    );
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS complaint_feedback (
            id INT AUTO_INCREMENT PRIMARY KEY,
            complaint_id INT NOT NULL,
            resident_id INT NOT NULL,
            resolved_at DATETIME NOT NULL,
            decision ENUM("confirmed","reopened") NOT NULL,
            rating TINYINT NULL,
            feedback TEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_feedback_resolution (complaint_id, resolved_at),
            CONSTRAINT fk_feedback_complaint FOREIGN KEY (complaint_id) REFERENCES complaints(id) ON DELETE CASCADE,
            CONSTRAINT fk_feedback_resident FOREIGN KEY (resident_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB'
    );
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS mediation_cases (
            id INT AUTO_INCREMENT PRIMARY KEY,
            complaint_id INT NOT NULL UNIQUE,
            respondent_name VARCHAR(100) NOT NULL,
            respondent_contact VARCHAR(11) NULL,
            respondent_address VARCHAR(255) NOT NULL,
            hearing_at DATETIME NULL,
            hearing_location VARCHAR(150) NULL,
            summons_issued_at DATETIME NULL,
            summons_served_at DATETIME NULL,
            case_status ENUM("Pending","Scheduled","Summons issued","Summons served","Settled","Unsettled","Closed") NOT NULL DEFAULT "Pending",
            outcome TEXT NULL,
            updated_by INT NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            CONSTRAINT fk_mediation_complaint FOREIGN KEY (complaint_id) REFERENCES complaints(id) ON DELETE CASCADE,
            CONSTRAINT fk_mediation_updater FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE RESTRICT
        ) ENGINE=InnoDB'
    );
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS announcements (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(150) NOT NULL,
            body TEXT NOT NULL,
            is_published TINYINT(1) NOT NULL DEFAULT 0,
            publish_until DATETIME NULL,
            created_by INT NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_announcement_public (is_published, publish_until, created_at),
            CONSTRAINT fk_announcement_author FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT
        ) ENGINE=InnoDB'
    );
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS site_settings (
            setting_key VARCHAR(60) NOT NULL PRIMARY KEY,
            setting_value TEXT NOT NULL,
            updated_by INT NULL,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            CONSTRAINT fk_site_settings_updater FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB'
    );
    $pdo->exec('UPDATE complaints SET resolved_at = updated_at WHERE status = "Resolved" AND resolved_at IS NULL');
    run(
        'INSERT INTO schema_migrations (version) VALUES (?)
         ON DUPLICATE KEY UPDATE version = VALUES(version)',
        [FEATURE_SCHEMA_VERSION]
    );
}
