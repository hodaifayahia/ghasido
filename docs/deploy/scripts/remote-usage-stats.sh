#!/bin/bash
# Read-only: AI usage per feature on the live database (spec 0007 sizing).
APP=/home/u673635734/domains/ghasido.com/guesvia
cd "$APP" || exit 1
php -r '
$db = new PDO("sqlite:database/database.sqlite", null, null, [PDO::SQLITE_ATTR_OPEN_FLAGS => PDO::SQLITE_OPEN_READONLY]);
$q = $db->query("select feature, provider, count(*) n, round(avg(prompt_tokens)) p, round(avg(completion_tokens)) c, sum(prompt_tokens + completion_tokens) total, min(occurred_at) first, max(occurred_at) last from ai_usages group by feature, provider order by total desc");
printf("%-24s %-22s %6s %8s %8s %12s  %s .. %s\n", "feature", "provider", "calls", "avg_in", "avg_out", "total", "first", "last");
foreach ($q as $r) {
    printf("%-24s %-22s %6d %8d %8d %12d  %s .. %s\n", $r["feature"], $r["provider"], $r["n"], $r["p"], $r["c"], $r["total"], substr($r["first"], 0, 10), substr($r["last"], 0, 10));
}
$u = $db->query("select count(*) from users")->fetchColumn();
$ra = $db->query("select count(*), round(avg(json_array_length(transcript))) from roleplay_attempts where is_preview = 0")->fetch(PDO::FETCH_NUM);
echo "users: $u; role-play attempts: {$ra[0]}, avg transcript lines: {$ra[1]}\n";
'
