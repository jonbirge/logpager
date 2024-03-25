IF NOT EXISTS (SELECT 1 FROM sys.databases WHERE name = 'logpager')
BEGIN
    CREATE DATABASE logpager;
END

USE logpager;

IF NOT EXISTS (SELECT 1 FROM sys.tables WHERE name = 'geo')
BEGIN
    CREATE TABLE geo (
        ip VARCHAR(255) PRIMARY KEY,
        data TEXT
    );
END
