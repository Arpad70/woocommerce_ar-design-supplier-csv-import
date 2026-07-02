# Release Process

## První standalone release

1. Upravte `VERSION` a hlavní plugin header.
2. Ověřte konzistenci:

```bash
php scripts/verify-version-consistency.php
```

3. Vytvořte release ZIP a tag:

```bash
git add .
git commit -m "Initialize standalone supplier csv import module"
git tag v1.0.0
git push origin main --tags
```
