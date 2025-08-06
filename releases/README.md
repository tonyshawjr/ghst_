# ghst_ Release Management

## Version Control Strategy

### Release Structure
```
releases/
├── v1.0.0-beta/           # Current beta release
├── v1.0.1-beta/           # Bug fixes for beta
├── v1.0.2-beta/           # More bug fixes
├── v1.1.0-beta/           # New features in beta
└── v1.0.0/                # Stable production release
```

### Version Numbering

**Format**: `vMAJOR.MINOR.PATCH-STAGE`

- **MAJOR**: Breaking changes (v1.x.x → v2.x.x)
- **MINOR**: New features, non-breaking (v1.0.x → v1.1.x)  
- **PATCH**: Bug fixes only (v1.0.0 → v1.0.1)
- **STAGE**: beta, rc (release candidate), or none (stable)

### Examples
- `v1.0.0-beta` - First beta release
- `v1.0.1-beta` - Beta with bug fixes
- `v1.1.0-beta` - Beta with new features
- `v1.0.0` - First stable release
- `v1.0.1` - Stable with bug fixes

## Sprint Workflow

### 1. Current Development (Working Directory)
- All active development happens in main project folder
- Bug fixes, features, improvements
- Testing and validation

### 2. Sprint Planning  
- Create bug/feature list in `SPRINT.md`
- Assign version number for next release
- Set sprint timeline (1-2 weeks)

### 3. Sprint Completion
- Package new release zip
- Move to `releases/vX.X.X-beta/` folder
- Update `CHANGELOG.md` with changes
- Create release notes

### 4. Beta Testing
- Deploy beta to test server
- Collect feedback and issues
- Document bugs for next sprint

### 5. Stable Release
- After beta testing complete
- Remove `-beta` suffix
- Create final production package

## Current Status

**Active Version**: v1.0.0-beta  
**Next Version**: v1.0.1-beta (bug fixes)  
**Release Date**: August 6, 2024  

## Release History

### v1.0.0-beta (August 6, 2024)
- Initial production-ready release
- All core features implemented
- OAuth settings migration complete
- Ready for deployment and testing