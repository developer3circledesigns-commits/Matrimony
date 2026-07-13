-- XAMPP: Grant root user access to the matrimony databases
-- By default, XAMPP MySQL uses root with no password.
-- For production, create a dedicated user and update config/database.php accordingly.
GRANT ALL PRIVILEGES ON `matrimony`.* TO 'root'@'localhost';
GRANT ALL PRIVILEGES ON `matrimony_test`.* TO 'root'@'localhost';
FLUSH PRIVILEGES;
