afia<?php

$pdo = new PDO('sqlite:/tmp/ghasido-ai.sqlite');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$rows = $pdo->query('select id, username, email, status from users order by id limit 20')->fetchAll(PDO::FETCH_ASSOC);
echo 'count='.count($rows).PHP_EOL;
foreach ($rows as $r) {
    echo ($r['id'] ?? '?').' | '.($r['username'] ?? 'NULL').' | '.($r['email'] ?? 'NULL').' | '.($r['status'] ?? 'NULL').PHP_EOL;
}
