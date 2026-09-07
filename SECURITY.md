# Security Policy

## Supported Versions

Security fixes are normally provided for the latest stable major version of Laravel Worker Watch.

| Version | Supported |
|---|---|
| 1.x | Yes |
| < 1.0 | Development / pre-release |

## Reporting a Vulnerability

Please do not report security vulnerabilities through public GitHub issues, discussions, or pull requests.

Instead, use GitHub's private vulnerability reporting feature if it is enabled for this repository.

If private reporting is not available, contact the maintainer privately through the contact information published on the maintainer's GitHub profile.

When reporting a vulnerability, please include as much of the following as possible:

- affected package version;
- affected Laravel and PHP versions;
- vulnerability description;
- reproduction steps or proof of concept;
- potential impact;
- suggested mitigation, if known.

Please allow reasonable time for investigation and remediation before public disclosure.

## Scope

Security reports may include issues involving:

- unsafe handling of worker metadata;
- unintended information disclosure;
- cache key collisions or isolation problems;
- event or command behavior that creates a security impact;
- dependency-related vulnerabilities directly affecting the package.

Reports about general Laravel, PHP, Redis, Supervisor, Docker, Kubernetes, or operating-system vulnerabilities should normally be reported to the relevant upstream project unless Laravel Worker Watch introduces or materially worsens the issue.
