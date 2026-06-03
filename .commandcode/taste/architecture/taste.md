# Architecture
- Avoid adding WordPress filters for simple configuration values like TTL unless explicitly requested. Confidence: 0.70
- Prefer optional/opt-in features over mandatory ones, especially for security configurations. Confidence: 0.70
- Prefer server-side configuration over client-side configuration when the server is the source of truth (e.g., domain validation, access control). Confidence: 0.75
- Public repositories should bypass domain validation entirely. Confidence: 0.75
- Use subsystem-specific prefixes for WordPress filters/hooks (e.g., `git_updater_lite_` instead of `git_updater_`) to isolate functionality and prevent unintended side effects on other parts of the codebase. Confidence: 0.70
- In Git Updater, `private_package` in Additions blocks API access entirely (not sharable); it does NOT indicate whether the repository is private on the git host. Use existing repo metadata from cached API data (not plugin/theme headers) for detection instead. Confidence: 0.85
- When the system already stores relevant metadata, use it directly for feature targeting rather than introducing heuristic detection (e.g., composer.json parsing, auth token presence). Confidence: 0.75
