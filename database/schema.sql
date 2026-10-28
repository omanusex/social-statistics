-- Social Media Statistics Platform - database schema (MySQL / MariaDB)
-- Import with phpMyAdmin or run install.php

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS audit_log;
DROP TABLE IF EXISTS survey_response_platforms;
DROP TABLE IF EXISTS survey_responses;
DROP TABLE IF EXISTS stats;
DROP TABLE IF EXISTS imports;
DROP TABLE IF EXISTS metrics;
DROP TABLE IF EXISTS platforms;
DROP TABLE IF EXISTS countries;
DROP TABLE IF EXISTS sources;
DROP TABLE IF EXISTS settings;
DROP TABLE IF EXISTS users;

SET FOREIGN_KEY_CHECKS = 1;

-- Administrators who manage the data
CREATE TABLE users (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name           VARCHAR(100) NOT NULL,
    email          VARCHAR(150) NOT NULL UNIQUE,
    password_hash  VARCHAR(255) NOT NULL,
    role           ENUM('admin','editor') NOT NULL DEFAULT 'editor',
    failed_logins  TINYINT UNSIGNED NOT NULL DEFAULT 0,
    locked_until   DATETIME NULL,
    last_login_at  DATETIME NULL,
    created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Countries / regions that statistics can be filtered by
CREATE TABLE countries (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    iso2        CHAR(2) NOT NULL UNIQUE,          -- 'WW' = worldwide
    iso3        CHAR(3) NULL,                     -- used by the World Bank API
    name_en     VARCHAR(80) NOT NULL,
    name_ar     VARCHAR(80) NOT NULL,
    region      ENUM('GCC','Arab','World') NOT NULL,
    is_default  TINYINT(1) NOT NULL DEFAULT 0,
    is_active   TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Social media platforms
CREATE TABLE platforms (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slug             VARCHAR(40) NOT NULL UNIQUE,
    name             VARCHAR(60) NOT NULL,
    statcounter_name VARCHAR(60) NULL,            -- column header in StatCounter CSV
    color            CHAR(7) NOT NULL DEFAULT '#6c757d',
    sort_order       SMALLINT NOT NULL DEFAULT 100
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Statistic types (what a value means)
CREATE TABLE metrics (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code            VARCHAR(40) NOT NULL UNIQUE,
    name_en         VARCHAR(100) NOT NULL,
    name_ar         VARCHAR(100) NOT NULL,
    unit            VARCHAR(20) NOT NULL,          -- '%', 'people'
    granularity     ENUM('monthly','yearly') NOT NULL,
    per_platform    TINYINT(1) NOT NULL DEFAULT 1, -- 0 = country-level value (platform_id is NULL)
    description_en  TEXT NULL,
    description_ar  TEXT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- External data sources
CREATE TABLE sources (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code            VARCHAR(30) NOT NULL UNIQUE,
    name            VARCHAR(100) NOT NULL,
    url             VARCHAR(255) NOT NULL,
    license         VARCHAR(100) NOT NULL,
    last_synced_at  DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Every file upload or API sync is recorded here
CREATE TABLE imports (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id        INT UNSIGNED NULL,
    source_id      INT UNSIGNED NOT NULL,
    type           ENUM('file','api') NOT NULL,
    filename       VARCHAR(255) NULL,
    rows_inserted  INT UNSIGNED NOT NULL DEFAULT 0,
    rows_updated   INT UNSIGNED NOT NULL DEFAULT 0,
    rows_failed    INT UNSIGNED NOT NULL DEFAULT 0,
    status         ENUM('success','partial','failed') NOT NULL,
    message        TEXT NULL,
    created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_imports_user   FOREIGN KEY (user_id)   REFERENCES users(id)   ON DELETE SET NULL,
    CONSTRAINT fk_imports_source FOREIGN KEY (source_id) REFERENCES sources(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- The statistics themselves: one value per country / platform / metric / device / period
CREATE TABLE stats (
    id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    country_id   INT UNSIGNED NOT NULL,
    platform_id  INT UNSIGNED NULL,
    metric_id    INT UNSIGNED NOT NULL,
    device       ENUM('all','desktop','mobile') NOT NULL DEFAULT 'all',
    period       DATE NOT NULL,                   -- first day of the month / year
    value        DECIMAL(16,2) NOT NULL,
    source_id    INT UNSIGNED NOT NULL,
    import_id    INT UNSIGNED NULL,
    updated_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    -- platform_key lets the unique index treat NULL platform as a real value
    platform_key INT UNSIGNED AS (IFNULL(platform_id, 0)) STORED,
    UNIQUE KEY uq_stat (country_id, platform_key, metric_id, device, period),
    KEY idx_lookup (metric_id, device, period),
    CONSTRAINT fk_stats_country  FOREIGN KEY (country_id)  REFERENCES countries(id) ON DELETE CASCADE,
    CONSTRAINT fk_stats_platform FOREIGN KEY (platform_id) REFERENCES platforms(id) ON DELETE CASCADE,
    CONSTRAINT fk_stats_metric   FOREIGN KEY (metric_id)   REFERENCES metrics(id),
    CONSTRAINT fk_stats_source   FOREIGN KEY (source_id)   REFERENCES sources(id),
    CONSTRAINT fk_stats_import   FOREIGN KEY (import_id)   REFERENCES imports(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Local study: questionnaire answers (imported or submitted online)
CREATE TABLE survey_responses (
    id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    age_group         ENUM('13-15','16-18','19-21','22-25') NOT NULL,
    gender            ENUM('male','female') NOT NULL,
    favourite_id      INT UNSIGNED NULL,
    main_purpose      ENUM('education','entertainment','communication','news') NOT NULL,
    daily_hours       ENUM('<1','1-2','3-4','5+') NOT NULL,
    late_night_use    TINYINT(1) NOT NULL DEFAULT 0,
    origin            ENUM('import','online') NOT NULL,
    import_id         INT UNSIGNED NULL,
    created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_resp_fav    FOREIGN KEY (favourite_id) REFERENCES platforms(id) ON DELETE SET NULL,
    CONSTRAINT fk_resp_import FOREIGN KEY (import_id)    REFERENCES imports(id)   ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Which platforms each respondent uses (many-to-many)
CREATE TABLE survey_response_platforms (
    response_id  INT UNSIGNED NOT NULL,
    platform_id  INT UNSIGNED NOT NULL,
    PRIMARY KEY (response_id, platform_id),
    CONSTRAINT fk_srp_resp FOREIGN KEY (response_id) REFERENCES survey_responses(id) ON DELETE CASCADE,
    CONSTRAINT fk_srp_plat FOREIGN KEY (platform_id) REFERENCES platforms(id)        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Site settings (key / value)
CREATE TABLE settings (
    name   VARCHAR(50) PRIMARY KEY,
    value  VARCHAR(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Security audit trail of admin actions
CREATE TABLE audit_log (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED NULL,
    action      VARCHAR(50) NOT NULL,
    details     VARCHAR(500) NULL,
    ip_address  VARCHAR(45) NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_audit_date (created_at),
    CONSTRAINT fk_audit_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
