# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- Admin diagnostics panel for managed Yandex Metrica debug events.
- DB-backed debug log collector for `window.ushakovCookieAnalytics`.

### Changed
- Managed Yandex Metrica debug events are stored in module diagnostics without browser console output.

### Fixed
- Release package now includes the module `lib/` directory.

## [0.0.1] - 2025-08-04

### Added
- **Initial release**: Cookie consent banner for 1C-Bitrix
- **Multi-site support**: Separate settings for each site
- **Flexible positioning**: Top/bottom position, left/center/right alignment
- **Customizable appearance**: Colors, border radius, shadows, z-index
- **Button customization**: Accept button and close button with configurable positions and colors
- **Delay settings**: Configurable show delay in milliseconds
- **Consent storage**: Session-based or N-days storage via cookies
- **HTML editor**: Visual editor for banner text with automatic link to `/cookies-agreement.php`
- **Hover effects**: Automatic 20% color darkening for interactive elements
- **CSS validation**: px, rem, em, % validation for all dimensional parameters
- **Anti-duplication**: Prevents duplicate consent records in registry
- **Backward compatibility**: Supports both new and old Bitrix cores (UserConsent API)
- **Security features**: SameSite=Lax cookies, Secure flag over HTTPS, XSS protection
- **Automatic file management**: Creates/removes `/cookies-agreement.php` during install/uninstall

### Technical Features
- **UserConsent integration**: Optional recording to Bitrix Consent Registry
- **Responsive design**: Mobile-friendly with optional mobile disable
- **Performance optimized**: Lightweight JavaScript with minimal DOM manipulation
- **Error handling**: Comprehensive error handling and logging
- **Session management**: Prevents duplicate logging within same session
