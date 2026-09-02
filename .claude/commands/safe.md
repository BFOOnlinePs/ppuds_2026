# CLAUDE.md — Project Development Rules

## 1. CORE PRINCIPLE — PRESERVE THE EXISTING CODEBASE

You are working inside an existing production-oriented codebase.

Your highest priority is:

> PRESERVE THE EXISTING ARCHITECTURE, CODING STYLE, BUSINESS LOGIC, DATABASE STRUCTURE, UI PATTERNS, SECURITY MODEL, TRANSLATION SYSTEM, AND EXISTING FUNCTIONALITY.

Do NOT treat this project as a greenfield project.

Do NOT implement things using your preferred architecture when the project already has an established pattern.

Before writing or modifying code, inspect the existing implementation and understand how the project currently solves similar problems.

### Absolute Rules

* Do NOT refactor unrelated code.
* Do NOT rewrite working code unnecessarily.
* Do NOT rename existing classes, methods, variables, routes, database columns, components, or files unless explicitly required.
* Do NOT change architecture unless explicitly requested.
* Do NOT introduce a new design pattern when an existing pattern is already being used.
* Do NOT install a package unless it is genuinely necessary.
* Do NOT replace an existing package/library with another one.
* Do NOT upgrade/downgrade dependencies unless explicitly requested.
* Do NOT modify unrelated files.
* Do NOT remove existing functionality.
* Do NOT change existing business logic unless required for the requested task.
* Do NOT make assumptions about how the application should work.
* Reuse existing implementations whenever possible.

If you find multiple possible approaches, always choose the approach that is most consistent with the existing codebase.

---

# 2. REQUIRED WORKFLOW

For EVERY task, follow this workflow.

## STEP 1 — UNDERSTAND

First understand exactly what the user is requesting.

Break the task into:

* Backend changes
* Frontend changes
* Database changes
* Permissions/authorization
* Translations
* Validation
* API changes
* UI changes
* Business logic
* Potential side effects

Do not immediately start editing files.

---

## STEP 2 — INSPECT THE CODEBASE

Before implementing anything:

* Find similar existing functionality.
* Search for related Models.
* Search for related Controllers.
* Search for Services/Actions.
* Search for Requests.
* Search for Policies.
* Search for Permissions.
* Search for translations.
* Search for database tables and migrations.
* Search for existing Vue components.
* Search for existing API patterns.
* Search for existing routes.
* Search for existing tests.

Use the existing implementation as the primary source of truth.

---

## STEP 3 — IDENTIFY THE EXISTING PATTERN

Before creating anything new, determine:

* How similar features are implemented.
* Where business logic is located.
* How validation is handled.
* How permissions are checked.
* How translations are stored.
* How database changes are handled.
* How frontend components communicate with the backend.
* How API responses are structured.
* How errors are handled.
* How notifications are displayed.

Follow those patterns.

---

## STEP 4 — PLAN THE MINIMUM CHANGE

Before editing, determine the smallest set of files that need to change.

Prefer:

Existing file modification

>

Existing component/service reuse

>

Small new implementation

>

New abstraction

Do not create unnecessary layers.

---

# 3. DATABASE & MIGRATIONS

Database changes are HIGH RISK.

Before changing database-related code:

1. Inspect existing migrations.
2. Inspect the relevant Model.
3. Search the entire project for the affected table.
4. Search for every affected column.
5. Check relationships.
6. Check foreign keys.
7. Check indexes.
8. Check seeders/factories if relevant.
9. Check API usage.
10. Check frontend usage.

## Migration Rules

* NEVER modify an existing migration that may already have been executed.
* Create a new migration for structural changes.
* NEVER drop tables unless explicitly requested.
* NEVER delete columns unless explicitly requested.
* NEVER rename database columns without checking all usages.
* Preserve existing data.
* Preserve foreign key relationships.
* Preserve indexes.
* Follow the project's existing migration naming/style.
* Do not change database engine, charset, or collation without explicit instruction.

Before creating a migration, determine whether the requested change actually requires a database change.

---

# 4. MODELS & RELATIONSHIPS

Before modifying a Model:

* Inspect its existing relationships.
* Inspect casts.
* Inspect accessors/mutators.
* Inspect scopes.
* Inspect traits.
* Inspect translatable configuration.
* Inspect media configuration.
* Inspect permissions/roles relationships.
* Search for usages throughout the project.

Do not duplicate relationships or create conflicting implementations.

Reuse existing relationships whenever possible.

---

# 5. TRANSLATIONS — VERY IMPORTANT

Translations are a first-class requirement.

Whenever adding or modifying user-facing text, ALWAYS check the project's existing translation system.

Do NOT hardcode user-facing text when the project already supports translations.

Before adding text:

1. Identify the existing translation package/system.
2. Identify the existing translation file structure.
3. Identify supported languages.
4. Follow the existing translation keys/naming convention.
5. Add translations for ALL supported languages when required.
6. Check backend translations.
7. Check frontend translations.
8. Check validation messages.
9. Check notifications.
10. Check emails/messages if relevant.

Never create a second translation system.

Do not rename existing translation keys without checking all usages.

Do not silently remove translations.

If a feature introduces a new user-facing string, make sure it is properly translated according to the existing project architecture.

---

# 6. PERMISSIONS & AUTHORIZATION — CRITICAL

Security must never be bypassed.

Before implementing any feature involving users, roles, actions, resources, pages, APIs, or admin functionality:

1. Inspect the existing permission system.
2. Inspect Roles.
3. Inspect Permissions.
4. Inspect Policies/Gates.
5. Inspect middleware.
6. Inspect existing authorization patterns.
7. Search for similar permission checks.

Follow the existing authorization architecture.

## Rules

* NEVER bypass existing permission checks.
* NEVER assume that authentication means authorization.
* NEVER expose functionality to users without checking permissions.
* NEVER create hardcoded role checks if the project uses a permission system.
* Reuse existing permissions when appropriate.
* Create new permissions only when the feature genuinely requires them.
* Follow the existing permission naming convention.
* Ensure frontend visibility and backend authorization are consistent.

IMPORTANT:

Frontend hiding is NOT security.

Backend authorization must always remain enforced.

---

# 7. ROUTES & APIs

Before adding a route:

* Search for existing routes.
* Check naming conventions.
* Check middleware.
* Check permissions.
* Check route model binding.
* Check API versioning if applicable.
* Check whether an existing route can be reused.

Do not create duplicate routes.

Do not change existing route names unless explicitly requested.

For APIs:

* Follow the existing request/response structure.
* Follow existing Resources/Transformers.
* Follow existing validation patterns.
* Preserve backward compatibility whenever possible.
* Do not change API response structures without explicit instruction.

---

# 8. VALIDATION

Always inspect how the project currently handles validation.

Prefer existing:

* Form Requests
* Validation classes
* Rules
* Custom validation rules
* Existing validation messages

Do not duplicate validation logic unnecessarily.

For every new input, consider:

* Required/optional
* Type
* Format
* Authorization
* Uniqueness
* Database existence
* Relationships
* Security
* Localization of validation messages

---

# 9. BUSINESS LOGIC

Business logic is critical.

Before modifying business logic:

* Find where the existing logic lives.
* Search for all callers.
* Understand side effects.
* Check events/listeners.
* Check jobs/queues.
* Check notifications.
* Check transactions.
* Check logs/activity logs.
* Check related database updates.

Do not move business logic between layers simply because another architecture looks cleaner.

Follow the existing project's architecture.

---

# 10. EVENTS, JOBS & SIDE EFFECTS

Before modifying functionality, check whether it triggers:

* Events
* Listeners
* Jobs
* Queues
* Notifications
* Emails
* WebSockets
* Broadcasts
* Activity logs
* Cache invalidation
* External APIs
* Webhooks

Do not accidentally remove or bypass side effects.

If an operation currently triggers an event, notification, activity log, or broadcast, preserve that behavior unless explicitly requested otherwise.

---

# 11. FRONTEND — VUE / INERTIA

Follow the existing frontend architecture exactly.

Before creating a component:

* Search for similar components.
* Reuse existing components.
* Follow existing folder structure.
* Follow existing naming conventions.
* Follow existing state management.
* Follow existing API/request patterns.
* Follow existing UI components.
* Follow existing form handling.
* Follow existing validation/error handling.

Do not introduce another frontend architecture.

Do not introduce another state management library.

Do not replace existing components unnecessarily.

---

# 12. UI / UX

Do NOT redesign existing UI unless explicitly requested.

When adding UI:

* Match the existing design.
* Reuse existing components.
* Reuse existing spacing.
* Reuse existing typography.
* Reuse existing buttons/forms/modals.
* Preserve responsive behavior.
* Preserve accessibility.
* Preserve RTL/LTR behavior.
* Preserve dark/light mode if supported.

Do not introduce random styling patterns.

---

# 13. RTL / LTR

The application may support multiple languages and directions.

Whenever modifying UI:

Check:

* RTL layout
* LTR layout
* Text alignment
* Icons
* Margins/padding
* Flex direction
* Tables
* Forms
* Modals
* Dropdowns
* Pagination
* Navigation
* Responsive layouts

Do not assume the application is always LTR.

---

# 14. FILE UPLOADS & MEDIA

Before implementing uploads:

* Inspect the existing media/upload system.
* Check whether the project uses Spatie Media Library or another existing system.
* Reuse existing configuration.
* Follow existing collection names.
* Follow existing conversion/resizing patterns.
* Follow existing validation.
* Follow existing storage configuration.

Do not introduce another upload mechanism without a strong reason.

---

# 15. PACKAGES & DEPENDENCIES

Before installing a package:

1. Search the project for existing functionality.
2. Check whether the framework already provides the functionality.
3. Check whether an installed package already provides it.
4. Only install a package if necessary.

NEVER install packages automatically just because they are convenient.

NEVER upgrade dependencies as part of an unrelated feature.

---

# 16. SECURITY

Always consider:

* Authentication
* Authorization
* Mass assignment
* Validation
* SQL injection
* XSS
* CSRF
* File upload security
* Sensitive data exposure
* IDOR
* Permission bypass
* API authorization
* Rate limiting
* Unsafe redirects

Do not weaken existing security mechanisms.

---

# 17. PERFORMANCE

Do not optimize blindly.

When modifying database-heavy code, check for:

* N+1 queries
* Unnecessary queries
* Missing eager loading
* Large datasets
* Pagination
* Repeated API requests
* Unnecessary frontend renders

However:

Do NOT perform unrelated performance refactoring.

---

# 18. TESTING

After implementing a feature or fix:

* Run relevant tests when available.
* Check syntax.
* Check imports.
* Check routes.
* Check migrations.
* Check permissions.
* Check translations.
* Check frontend compilation.
* Check obvious edge cases.

Do not modify tests simply to make them pass unless the test itself is incorrect.

---

# 19. GIT SAFETY

NEVER:

* git reset --hard
* git clean -fd
* Delete uncommitted work
* Revert user changes
* Overwrite files without checking their current state

Treat existing uncommitted changes as user-owned work.

Never destroy existing work.

---

# 20. DO NOT "IMPROVE" MY CODE WITHOUT PERMISSION

This is one of the most important rules.

If you see code that you personally consider:

* ugly
* outdated
* repetitive
* unconventional
* not best practice
* unnecessarily complex

DO NOT change it unless it is directly related to the requested task.

The existing codebase's conventions take priority over your personal preferences.

---

# 21. WHEN SOMETHING IS UNCLEAR

Do not guess.

If the correct implementation depends on an architectural decision that cannot be determined from the codebase:

STOP and explain the ambiguity.

Prefer asking before making a potentially destructive architectural decision.

---

# 22. FINAL VERIFICATION

Before finishing every task, perform a final review:

### Code

* Did I follow the existing architecture?
* Did I follow the existing coding style?
* Did I modify only necessary files?
* Did I accidentally refactor unrelated code?

### Database

* Is a migration required?
* Did I preserve existing data?
* Did I preserve relationships/indexes?
* Did I avoid modifying an old migration?

### Permissions

* Is authorization enforced?
* Did I follow the existing permission system?
* Is backend authorization present?

### Translation

* Did I check all user-facing text?
* Did I follow the existing translation system?
* Did I handle all supported languages?
* Did I consider RTL/LTR?

### Backend

* Is validation correct?
* Are existing business rules preserved?
* Are events/jobs/notifications preserved?
* Are APIs backward compatible?

### Frontend

* Did I reuse existing components?
* Did I preserve responsive behavior?
* Did I preserve RTL/LTR?
* Did I preserve existing UI patterns?

### Safety

* Did I modify unrelated files?
* Did I install unnecessary packages?
* Did I change dependencies?
* Did I remove existing functionality?

If any answer is NO, fix it before finishing.

---

# FINAL PRINCIPLE

Think like a senior engineer joining an existing production team.

Do not try to make the project look like your own project.

Your responsibility is to understand:

> "How does this project already work?"

and then implement the requested change:

> "using the project's existing way of doing things."

The existing codebase is the source of truth.

**MINIMUM CHANGE. MAXIMUM COMPATIBILITY. ZERO UNNECESSARY REFACTORING.**
