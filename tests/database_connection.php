<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
// Exercise independent builders and atomic writes on a real in-memory PDO.
define('APP_ROOT', dirname(__DIR__));
putenv('DB_DRIVER=sqlite');
putenv('DB_PATH=:memory:');
require APP_ROOT . '/config.php';
require APP_ROOT . '/scheme/Database.php';
require APP_ROOT . '/scheme/helpers.php';
function check($condition, $label) {
    if (!$condition) throw new RuntimeException($label);
    echo "PASS: $label\n";
}
$first = db();
$second = db();
check($first !== $second, 'query builders remain independent');
$pdo = new ReflectionProperty(Database::class, 'db');
check($pdo->getValue($first) === $pdo->getValue($second), 'builders share one request-scoped PDO connection');
$first->raw('CREATE TABLE fixture (id INTEGER PRIMARY KEY, value TEXT)');
$first->raw("INSERT INTO fixture VALUES (1, 'one'), (2, 'two')");
$one = $first->table('fixture')->select('value')->where('id', 1);
$two = $second->table('fixture')->select('value')->where('id', 2);
check($one->get()['value'] === 'one', 'interleaved builder preserves first query');
check($two->get()['value'] === 'two', 'interleaved builder preserves second query');
$first->transaction();
db()->raw("INSERT INTO fixture VALUES (3, 'rollback')");
$first->roll_back();
check((int)db()->raw('SELECT COUNT(*) FROM fixture')->fetchColumn() === 2, 'rollback covers writes made through helper connections');
$first->transaction();
db()->raw("INSERT INTO fixture VALUES (3, 'commit')");
$first->commit();
check((int)db()->raw('SELECT COUNT(*) FROM fixture')->fetchColumn() === 3, 'commit persists writes on the shared connection');
