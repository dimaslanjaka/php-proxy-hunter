@echo off

git reflog expire --expire=now --all
git gc --prune=now --aggressive
git fsck --full