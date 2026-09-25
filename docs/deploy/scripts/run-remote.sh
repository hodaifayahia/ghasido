#!/bin/bash
# Usage: run-remote.sh <script path (WSL form)> — runs it on Hostinger over SSH.
exec ssh -i ~/.ssh/clickdz_hostinger_deploy -p 65002 -o BatchMode=yes -o ConnectTimeout=30 \
  -o ServerAliveInterval=30 u673635734@89.117.116.239 'bash -s' < <(tr -d '\r' < "$1")
