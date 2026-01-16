<?php
/** Local development configuration example.
 * Copy to config.local.php and adjust for your XAMPP environment.
 * This file is ignored in production; connection.php only loads it when SERVER_NAME is localhost/127.0.0.1.
 */

define('DB_HOST', '127.0.0.1'); // or 'localhost'
define('DB_USER', 'root');
define('DB_PASS', ''); // default XAMPP root password is empty
define('DB_NAME', 'sdmc'); // replace with your local DB name
// Optional: define('DB_PORT', 3306);
