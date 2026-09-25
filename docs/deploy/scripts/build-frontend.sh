#!/bin/bash
# Production frontend build into a temp dir; never touches public/build or public/hot.
set -e
cd ~/clickDz/ghasido
out=/tmp/guesvia-deploy/build
rm -rf "$out"
mkdir -p /tmp/guesvia-deploy
export NODE_OPTIONS=--max-old-space-size=3072
start=$(date +%s)
timeout 1500 npx vp build --outDir "$out" --emptyOutDir > /tmp/guesvia-deploy/build.log 2>&1
echo "exit=$? after $(( $(date +%s) - start ))s"
tail -n 15 /tmp/guesvia-deploy/build.log
echo "== output"
ls -la "$out" | head
du -sh "$out"
test -f "$out/manifest.json" && echo "manifest ok" || echo "NO MANIFEST"
