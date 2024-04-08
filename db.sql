CREATE DATABASE IF NOT EXISTS logpager;
USE logpager;
CREATE TABLE IF NOT EXISTS geo (
    ip VARCHAR(64) PRIMARY KEY,
    cache_time TIMESTAMP,
    json_data TEXT
);
