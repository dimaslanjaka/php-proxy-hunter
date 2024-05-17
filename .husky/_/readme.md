show git hooks status

```bash
git config --get core.hooksPath
```

enable or change git hooks directory

```bash
git config core.hooksPath .husky/_
```

to uninstall

```bash
git config --unset core.hooksPath
```