# Releases Folder

## Purpose
Version management and production packaging for ghst_ platform releases

## Rules
- Each version gets its own folder: `vMAJOR.MINOR.PATCH-STAGE/`
- Version numbering follows semantic versioning
- Production zip packages exclude development files (.git, logs, cache, etc.)
- Every release must include RELEASE_NOTES.md
- Beta releases use `-beta` suffix
- Stable releases have no suffix
- Never overwrite existing releases

## File Structure
```
releases/
├── CLAUDE.md (this file)
├── README.md (version control strategy)
├── v1.0.0-beta/
│   ├── ghst_v1.0.0-beta.zip (production package)
│   └── RELEASE_NOTES.md (detailed release info)
├── v1.0.1-beta/
│   ├── ghst_v1.0.1-beta.zip
│   └── RELEASE_NOTES.md
└── v1.0.0/
    ├── ghst_v1.0.0.zip (stable release)
    └── RELEASE_NOTES.md
```

## Versioning Rules
- **MAJOR**: Breaking changes (v1.x.x → v2.x.x)
- **MINOR**: New features, non-breaking (v1.0.x → v1.1.x)
- **PATCH**: Bug fixes only (v1.0.0 → v1.0.1)
- **STAGE**: beta, rc (release candidate), or none (stable)

## Package Contents
### Always Include:
- All core PHP application files
- Database schema files
- Installation system
- Production-ready assets
- Configuration examples

### Always Exclude:
- .git/ folder and git files
- Development logs and cache
- User uploaded content
- Temporary files (.tmp, .log)
- System files (.DS_Store)
- Environment files (.env*)

## Primary Agents
- **project-shipper** - Orchestrates release packaging and deployment
- **devops-automator** - Handles production packaging automation
- **test-writer-fixer** - Ensures release quality before packaging
- **studio-producer** - Coordinates release timeline and dependencies

## Release Process
1. Complete development work in main project
2. Create new version folder in releases/
3. Package production files (exclude development assets)
4. Create RELEASE_NOTES.md with comprehensive details
5. Test package integrity
6. Update releases/README.md with new version info

## Naming Conventions
- Folder names: `v1.0.0-beta`, `v1.0.0`
- Zip files: `ghst_v1.0.0-beta.zip`, `ghst_v1.0.0.zip`
- Notes files: `RELEASE_NOTES.md` (consistent across all releases)

## Sprint Integration
- Post-beta bug fixes increment PATCH version
- New features increment MINOR version
- Breaking changes increment MAJOR version
- All beta work stays in beta until ready for stable