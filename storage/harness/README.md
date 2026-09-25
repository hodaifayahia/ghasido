# Visual verification harness (spec 0003, Part I)

Everything here runs inside WSL from the repo root.

1. `bash storage/harness/serve.sh reset` — creates `~/.guesvia-harness/spec3.sqlite`, runs `migrate:fresh --seed`, starts `php -S 127.0.0.1:8090` (log: `storage/harness/php-server.log`). Assets are served by the Vite that `public/hot` points at (the Sail container's `:5173`, which watches this same tree).
2. Write a job file (see the header of `shoot.mjs`) under `storage/harness/jobs/`, then `node storage/harness/shoot.mjs storage/harness/jobs/<name>.json` — Playwright: storage/harness/node_modules is a symlink to ~/.guesvia-harness/pw/node_modules (bash storage/harness/link-pw.sh recreates it).
3. `python3 storage/harness/compare.py desginphotos/employ/photo_2_….jpg storage/harness/shots/vocab-1280x853.png storage/harness/shots/cmp-vocab.png --overlay` and view the PNG with the Read tool.

Seeded logins (password `password`): `harness@guesvia.test` (Super Admin), `samira` (employee, pre-test not done), `amine` (employee, lesson 1 done).
