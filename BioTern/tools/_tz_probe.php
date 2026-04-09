<?php
require __DIR__ . '/../config/db.php';
echo 'php_timezone=' . date_default_timezone_get() . PHP_EOL;
$r = mysqli_query($conn, "SELECT @@session.time_zone AS session_tz, NOW() AS now_ts");
if ($r) {
  $row = mysqli_fetch_assoc($r);
  echo 'db_session_time_zone=' . $row['session_tz'] . PHP_EOL;
  echo 'db_now=' . $row['now_ts'] . PHP_EOL;
} else {
  echo 'db_error=' . mysqli_error($conn) . PHP_EOL;
}
