CREATE DATABASE IF NOT EXISTS logpager;
USE logpager;
CREATE TABLE IF NOT EXISTS geo (
    ip VARCHAR(64) PRIMARY KEY,
    cache_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    json_data JSON
);
CREATE TABLE IF NOT EXISTS blacklist (
    cidr VARCHAR(64) PRIMARY KEY,
    add_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    log_line TEXT
);
