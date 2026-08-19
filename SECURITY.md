# Security Policy

ERASED CMS is under active development. Security reports are welcome and should be handled privately until a fix is available.

## Supported versions

| Version | Status |
|---|---|
| `main` (`0.9.3-beta`, `v0.9-dev` — Release Candidate) | Active development; hardening and pre-release testing, not yet recommended for production |

## Reporting a vulnerability

Do not open a public issue containing exploit details, credentials, personal data, or a working proof of concept.

Use GitHub's private vulnerability-reporting feature when it is enabled for the repository. If private reporting is not available, contact the repository owner privately through the contact method listed on their GitHub profile.

Include:

- affected branch or version;
- affected component;
- reproduction steps;
- expected and actual behavior;
- impact assessment;
- relevant logs with secrets removed;
- suggested mitigation, if known.

## Security expectations

ERASED CMS aims to provide:

- secure authentication and session handling;
- CSRF and output-escaping protections;
- validated uploads and package archives;
- path-traversal protection;
- versioned migrations rather than runtime schema mutation;
- package backup and rollback;
- explicit confirmation for permanent data deletion;
- no mandatory telemetry or cloud service;
- no secrets stored in the repository.

## Disclosure

Please allow maintainers reasonable time to investigate, develop a fix, test upgrade and rollback behavior, and publish an advisory before public disclosure.
