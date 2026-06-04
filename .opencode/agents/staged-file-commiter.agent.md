---
name: "Staged Files Committer"
description: "Git expert for staged diff analysis and conventional commit generation"
triggers:
  - "commit staged"
  - "staged commit"
  - "gen commit"
  - "create commit"
  - "generate commit staged files"
  - "generate commit for staged changes"
tags:
  - git
  - commits
  - staged
mode: all
---

# Context-Aware Staged Commit Agent

**Purpose:** Automatically commit multiple staged files in batches by context.

When multiple files are staged together, this agent analyzes their context (e.g., feature, bugfix, docs) and commits them in separate commits per context. This ensures a clean commit history with conventional commit messages.

---

## Workflow

### Step 1 — Detect Staged Files

**Bash/Zsh/sh**

```bash
git diff --name-only --staged
```

**PowerShell**

```powershell
git diff --name-only --staged
```

**CMD**

```cmd
git diff --name-only --staged
```

> Output is the list of currently staged files.

---

### Step 2 — Analyze Context

* Agent analyzes **file names, paths, or diff content** to assign a **context label** (e.g., `feat`, `fix`, `docs`).
* Each file gets assigned to a **context group**.

---

### Step 3 — Unstage All Files

**Bash/Zsh/sh**

```bash
git reset
```

**PowerShell**

```powershell
git reset
```

**CMD**

```cmd
git reset
```

> Now all files are unstaged, ready to stage by context.

---

### Step 4 — Stage and Commit by Context

For each context group:

**Bash/Zsh/sh**

```bash
git add file1 file2 file3   # files in same context
git commit -m "feat(scope): commit message"
```

**PowerShell**

```powershell
git add file1,file2,file3
git commit -m "feat(scope): commit message"
```

**CMD**

```cmd
git add file1 file2 file3
git commit -m "feat(scope): commit message"
```

> Repeat for all context groups.

---

### Step 5 — Optional: Auto-Generate Commit Messages

* Analyze **diff of files in group**
* Generate **conventional commit messages** (type, scope, subject) automatically
* Save to temporary file if needed:

**Bash**

```bash
cat > commit.txt << 'EOF'
feat(scope): add new feature for context group
EOF
git commit -F commit.txt
```

**PowerShell**

```powershell
@"
feat(scope): add new feature for context group
"@ | Set-Content commit.txt
git commit -F commit.txt
```

**CMD**

```cmd
echo feat(scope): add new feature for context group > commit.txt
git commit -F commit.txt
```

---

### Step 6 — Output

After all commits:

1. List commits with **SHA, type, scope**

   ```bash
   git log --oneline --max-count=10
   ```
2. Show files committed per context group
3. Suggest next steps: `Ready to push` or `Check for missed files`

---

### Optional Enhancements

* Use **AI or pattern matching** to detect context from filenames, folders, or diff content.
* Handle **multi-line commit messages** safely for CMD users.
* Include **breaking change detection** per context group.

---

💡 **Key Idea:** Agent **doesn’t commit a mixed context group** — only commits files with the same inferred context at a time. This ensures clean, conventional commit history even when multiple changes are staged.

---
