# Developer Release Checklist (ContextWP)

## 🔁 Workflow
- [ ] Create a new branch for feature/fix (e.g. `feature/xyz`)
- [ ] Complete development, commit regularly
- [ ] Update version number (contextwp.php) as needed:
  - [ ] MAJOR for breaking changes
  - [ ] MINOR for new features
  - [ ] PATCH for fixes / improvements

## 📦 Required Updates
- [ ] `contextwp.php` version header
- [ ] `composer.json` version field (keep synchronized with contextwp.php)
- [ ] `README.md` (only if version is referenced)
- [ ] `CHANGELOG.md`
- [ ] `IMPROVEMENTS.md` (if used)
- [ ] Remove any dev/test files (`test-settings.php`, etc)

## 🧪 Testing
- [ ] Plugin activates and deactivates without error
- [ ] All endpoints work via Postman or front end
- [ ] Floating chat UI appears and works

## 🚀 Finalise
- [ ] Merge into `main`
- [ ] Tag the release (e.g. `git tag v0.2.0 && git push origin --tags`)
