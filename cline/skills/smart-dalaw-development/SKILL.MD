
---
name: smart-dalaw-development
description: Safely develop, debug, maintain, and test the existing Smart Dalaw Laravel application. Use for booking bugs, visitor details, facility schedules, ID uploads, email and SMS notifications, QR codes, admin dashboards, reports, and database issues.
---

# Smart Dalaw Development Skill

## Core Development Principles
**Understand the existing system. Reuse what works. Make the smallest safe change that satisfies the request. Do not add complexity without evidence that it is needed.**

- **Inspect before implementing:** Read relevant code and existing project instructions before making changes.
- **Prefer minimal changes:** Modify existing code when sufficient. Avoid unnecessary files, abstractions, dependencies, architectural changes, and unrelated refactoring.
- **Preserve existing behavior:** Do not redesign the UI or alter established business rules unless explicitly required.
- **Prioritize correctness and security:** Never sacrifice validation, authorization, privacy, accessibility, data integrity, or meaningful tests to reduce code.
- **Use context efficiently:** Search relevant files, retrieve necessary sections, avoid repeated explanations, and summarize tool outputs without losing important details.
- **Clarify uncertainty:** Ask concise questions when a missing business decision materially affects the implementation. Do not guess or ask for information already documented.
- **Verify before reporting:** Run relevant tests, report actual results, and never claim unverified success.

These principles apply to every Smart Dalaw task and take precedence over an agent's preference for unnecessary complexity. Follow all project-specific requirements and security constraints.

## Minimal-Change Engineering Rules

Use these rules with the Core Development Principles above. They clarify what "minimal" means and never override the safety, privacy, testing, or project-specific requirements in this skill.

### A. Inspect before implementing

- State the exact requested outcome before touching code.
- Trace the current implementation and data flow: route, controller, request/validation, model and relationships, view, and tests.
- Search for existing components, models, controllers, helpers, packages, and tests that already solve the problem. Reuse them.
- Follow existing patterns unless a specific, demonstrated reason justifies a deviation.
- Do not create files, classes, services, abstractions, or dependencies merely because they are possible.

### B. Use the smallest sufficient change

- Make the smallest change that correctly satisfies the requested behavior.
- Prefer modifying an existing function or component over adding another layer.
- Do not add design patterns, repositories, service layers, event systems, or packages without a concrete need.
- Do not refactor unrelated code during a feature or bug fix.
- Do not change working UI, routes, database structures, naming conventions, or behavior unless the task requires it.
- Do not build features for hypothetical future requirements.

### C. Preserve correctness and safety

Minimal code is not careless code. Never remove or weaken necessary validation, authentication and authorization, security and privacy protections, error handling, data-integrity and transaction safeguards, accessibility, or tests for important or risky behavior. This includes the project's rules for visitor information and ID photos. If the smallest implementation would compromise any of these, choose the smallest safe implementation instead.

### D. Use evidence

- Identify the actual cause of a bug before attempting a fix.
- Prefer evidence from the code, logs, and test results over assumptions.
- Do not invent requirements or silently decide ambiguous business rules. Ask one concise question when a missing decision materially affects the correct implementation.
- Do not ask for requirements already documented in this skill or the conversation.
- If a proposed solution is significantly more complex than the existing system requires, explain why that complexity is necessary before proceeding.

### E. Test proportionally

- Inspect and run relevant existing tests when practical, and record pre-existing failures.
- Add or update tests for new behavior, changed branches, security boundaries, and important failure cases.
- Run focused tests first, then broaden to related regression tests when appropriate.
- Do not add large testing frameworks or infrastructure for a small change.
- Never claim tests passed without running them. Report tests that were skipped or could not run.

### F. Use context efficiently

- Read only the files needed to understand the task, then expand when evidence requires it.
- Prefer targeted searches and specific file sections over repeatedly reading whole files or directories.
- Do not repeat information already established in this skill or the conversation.
- Summarize findings, decisions, and remaining uncertainties instead of reproducing long tool output.
- Keep progress reports and final explanations concise and practical.
- Preserve exact technical details needed for correctness. Never compress away requirements, error messages, security conditions, or test failures.
- Retrieve original source content when a summary is insufficient to make a safe decision.
- Do not skip inspection or testing just to save context.

These context rules are workflow guidance only. They do not provide automatic compression, caching, or retrieval.

### G. Pre-change decision rule

Before implementing, answer internally:

1. What exact behavior must change?
2. Where is that behavior already implemented?
3. Can a small modification to existing code meet the requirement?
4. What is the simplest safe solution?
5. What existing behavior could this change break?
6. Which tests provide evidence that the change works?

If an existing solution is sufficient, use it. If a new abstraction, dependency, migration, or architectural change is genuinely necessary, explain the concrete reason and keep its scope limited. Do not produce this checklist as a lengthy report; communicate only important findings.

## Project Scope — San Fernando City, Pampanga

- Focus current Smart Dalaw development exclusively on existing BJMP jail facilities located in San Fernando City, Pampanga.
- Apply booking, visitation schedules, capacity rules, visitor ID uploads, email notifications, reports, and admin workflows to the facilities within this scope.
- Inspect existing facility records before changing facility-related logic. Do not invent facility names, schedules, or data.
- Preserve the existing facility dropdown and UI.
- Do not add new facilities from other cities or expand the project's geographic scope unless explicitly instructed.
- Do not delete or disable existing records for other cities.
- Before implementing changes that could affect multiple facilities or cities, ensure the changes remain scoped to San Fernando City, Pampanga.

## 1. Purpose and Priorities

Smart Dalaw is a mobile-first BJMP visitation booking system with public booking and facility/admin management.

Priorities, in order:
1. Fix existing blocking errors.
2. Protect working functionality and data.
3. Preserve the existing UI and architecture.
4. Implement correct, secure business workflows.
5. Test changes and prevent regressions.

Use the smallest complete fix. Do not redesign the application unless explicitly requested.

## 2. Mandatory Workflow

For every task:

1. Inspect the relevant source code, routes, controllers, models, relationships, migrations, frontend components, configuration, logs, and tests.
2. Check Git status and preserve all existing user changes.
3. Reproduce the issue or establish its current status where possible.
4. Run relevant existing tests before changing code. Record pre-existing failures separately.
5. Trace the actual data flow and identify the root cause before editing.
6. Implement the smallest appropriate change using existing project conventions.
7. Add or update focused tests and run relevant regression tests.
8. Review the diff and check related workflows.
9. Report the root cause, changed files, tests and actual results, and remaining issues.

Never claim a test passed unless it was actually run and passed. If tests cannot run, explain why and provide appropriate verification steps.

Do not implement unrelated new features while a known blocking error remains unresolved, unless the user explicitly changes the priority.

## 3. Current Blocking Issue — P0

The user reported an Internal Server Error at `exp-smart-dalaw.test` with:

- Exception: `Symfony\Component\ErrorHandler\Error\FatalError`
- Message: `Maximum execution time of 30 seconds exceeded`
- Reported location: `storage/framework/views/8c830f5c2a87fa1b3e6386d35eaba380.php:1`
- Environment: Laravel 13.33.0, PHP 8.4.22

Treat this as the first issue to investigate. Verify whether it is still reproducible before assuming it remains unresolved.

### Investigation procedure

1. Inspect `storage/logs/laravel.log` and identify the relevant request and error details.
2. Identify the original Blade template associated with the compiled view.
3. Trace the route, controller, Blade components, partials, model relationships, and database queries involved.
4. Investigate plausible causes, including expensive queries, repeated rendering, recursive includes, infinite loops, and other long-running operations.
5. Determine whether the failure affects one page or multiple workflows.
6. Check Git status before making changes.

### Fix and verification rules

- Fix the verified underlying cause.
- Never edit generated files in `storage/framework/views/` as the permanent solution.
- Do not simply increase the execution timeout to conceal an application defect.
- Do not make speculative changes before collecting evidence.
- Preserve the existing UI, data, and unrelated features.
- Test the previously failing request and related booking/admin workflows.
- Check logs for new exceptions after testing.

If the root cause cannot be established, report the evidence and next diagnostic step. Do not claim the issue is fixed without verification.

## 4. Preserve the Existing UI

- Keep the existing Smart Dalaw layout, components, colors, spacing, and visual identity.
- Preserve the dark navy/midnight blue, amber/gold, and white/light-gray styling already used by the application.
- Keep existing pages mobile-friendly and consistent with one another.
- Do not redesign pages, replace components, or remove working controls unless requested.
- Do not add unnecessary forms, modals, search fields, cards, or decorative elements.
- Make only the UI changes required by the task.

## 5. Smart Dalaw Business Requirements

### Public booking

- Visitors must be able to select a facility, visitation date, and valid time slot and complete the booking.
- Keep facility selection as a dropdown. Do not add a facility search field unless explicitly requested.
- Preserve the selected facility ID throughout the booking workflow.
- Display the correct existing facility logo after selection. Investigate the actual asset, mapping, and data flow; do not hardcode a fake logo.
- Require booking at least one day in advance. Apply the existing timezone and date rules consistently.
- Show only valid facility-specific visitation dates and available slots.
- Enforce availability and schedule rules on the backend, not only in the frontend.
- Prevent invalid, conflicting, closed, or unavailable bookings.
- Respect existing capacity rules. A previously discussed target was at least seven online slots per hour while leaving capacity for walk-ins. Inspect the current implementation before changing capacity logic.
- Let visitors enter PDL names as text rather than exposing a public searchable list of PDL records.
- Preserve the existing payment flow and optional visitor convenience-fee model. Do not introduce mandatory fees without authorization.

### Facility schedules

Use each facility's actual configuration rather than applying one hardcoded schedule to every facility.

Previously discussed example for San Fernando City District Jail:

- Monday: no visitation.
- Tuesday–Friday: 1 PM–5 PM.
- Saturday–Sunday: 8 AM–12 PM and 1 PM–5 PM.

This is a reference example. Verify current configuration before applying it.

Closed days, breaks, expired slots, and full slots must not be displayed or accepted by the backend.

### Visitor and accompanying-visitor information

- Support a primary visitor and multiple accompanying visitors according to the existing business rules.
- Persist and retrieve all visitor records correctly.
- Booking details, review pages, status pages, and relevant reports must display the primary visitor and all accompanying visitors.
- Display accurate visitor totals. Distinguish booking counts from individual visitor counts.
- Show the saved visitor sex value in the admin edit form and preserve it when editing other fields.
- Make accompanying-visitor details available to authorized officers before making a booking decision.
- Validate all submitted visitor information on the server.

### Personal information and ID uploads

- Provide ID photo capture through a supported device camera or upload of an existing image.
- Provide a file-upload fallback if camera access is unavailable or denied.
- Include the ID upload in the existing personal-information section without redesigning the page.
- Validate file type, size, and upload errors on the server.
- Store ID images privately and associate each image with the correct visitor or booking.
- Display the submitted personal information and an appropriate ID image preview on the booking completion/confirmation page after successful booking.
- Make the same information available in authorized admin booking details and officer review screens.
- Enforce authorization for viewing or downloading ID images. Do not expose private images through public URLs.
- Do not add facial recognition or automated identity verification unless explicitly requested.

### Booking status and QR codes

- Preserve the existing status workflow: Pending, Officer Review, Approved, and Rejected, where implemented.
- Show rejection reasons where required.
- Keep visitor information, facility, date, time, booking reference, QR code, and status consistent across related pages.
- Ensure QR codes reference the correct booking and expose sensitive information only to authorized users.
- Do not approve a booking simply to make it appear in a report.

### Facility administration and on-site visits

- Allow authorized facility administrators to record legitimate on-site/physical visitations using the existing workflow or a minimal extension.
- Avoid duplicate or conflicting visitation records.
- Enforce role-based authorization and facility-level data access.
- Prevent administrators from accessing or modifying records belonging to other facilities without permission.

### Daily visitation summaries

- Include approved online bookings in the correct summary for their scheduled facility and visitation date.
- Define whether each report counts bookings, individual visitors, or completed visits. Do not mix these metrics.
- Approval does not automatically mean a visit was completed.
- Include status totals such as approved, rejected, pending, completed, and no-show where supported by the current data model.
- Calculate visitor counts by sex (Male/Female) and age group:
  - Minor: under 18.
  - Adult: 18–59.
  - Senior: 60 and older.
- Include accompanying visitors in people-based counts without double-counting.
- Apply facility, date, and status filters consistently.
- Include on-site visits according to the report's defined purpose.
- Handle missing, null, and empty filter parameters safely.

## 6. Notifications — Email First, SMS Later

### Email booking confirmations: first priority

Once blocking errors are resolved, prioritize email notifications before SMS.

- Send a booking confirmation after the booking has been successfully saved.
- Use the actual persisted booking data, not unverified form input.
- Include the booking reference, facility, visitation date and time, current status, and relevant visitor details permitted by the application's privacy rules.
- Include the PDL name only where appropriate under the existing privacy rules.
- Use a clear, mobile-readable email format consistent with the application.
- Do not include passwords, ID images, or unnecessary sensitive personal information.
- Follow the project's existing Laravel mail configuration and notification patterns.
- Check queue configuration before using queued delivery.
- Handle email failures without losing the saved booking or incorrectly reporting that the booking itself failed.
- Distinguish notification dispatch from confirmed email delivery.
- Test notification content, dispatch behavior, and failure handling.

### SMS free-trial investigation: second priority

After the email workflow is implemented and tested:

1. Research legitimate SMS providers using their official documentation.
2. Verify whether a free trial is currently available and whether it supports Philippine mobile numbers.
3. Explain trial credits, eligibility, verification, sender-ID restrictions, limitations, and possible costs in simple terms.
4. Recommend an option only after verifying the relevant details.
5. Keep SMS optional and separate from email. Email must continue to work when SMS is unavailable or fails.
6. Never hardcode or commit API credentials.
7. Do not create accounts, submit personal/payment information, enable paid services, or incur charges without explicit user approval.
8. If credentials or account verification are required, explain the steps and wait for the user's approval or input.

Do not implement SMS merely because a provider advertises a trial. Verify suitability and required access first.

## 7. Laravel and Database Safety

- Verify the installed Laravel and PHP versions before relying on version-specific behavior.
- Follow the existing MVC structure, naming conventions, and Eloquent relationship patterns.
- Use server-side validation, authorization policies/gates, and safe database operations.
- Protect against SQL injection, mass-assignment vulnerabilities, XSS, CSRF issues, broken access control, and unsafe file access.
- Never expose credentials, secrets, private ID images, or sensitive visitor data in logs, source control, or public responses.
- Never delete, reset, or recreate the database to fix a bug.
- Do not run destructive commands such as `migrate:fresh` or `db:wipe` without explicit authorization and a verified backup.
- Make schema changes only when necessary. Explain migration effects and preserve existing data.
- Do not change `.env`, dependencies, authentication, or configuration unless necessary for the requested task.
- Never overwrite or discard the user's existing work.
- Do not disable security protections or validation to make a test pass.

## 8. Debugging and Testing

### Before a change

- Inspect the relevant implementation and existing tests.
- Run focused baseline tests when possible.
- Record failures that already exist.
- Identify the root cause and plan a minimal fix.

### After a change

- Run relevant unit, feature, and regression tests available in the project.
- Run appropriate formatting or static-analysis checks if configured.
- Review the diff for unrelated changes.
- Verify the affected workflow and important adjacent workflows.
- Report any tests that could not be run.

### Booking regression checklist

- Valid facility, date, and slot selection.
- Rejection of closed days, unavailable slots, past dates, same-day bookings, and full slots.
- Server-side validation when frontend controls are bypassed.
- Correct facility selection and logo.
- Persistence and display of primary and accompanying visitors.
- Saved visitor sex remains visible during editing.
- Personal information and ID images display correctly to authorized users.
- Unauthorized ID access is denied.
- Approved bookings appear in the appropriate summaries.
- Booking counts and individual visitor counts remain distinct.
- Missing or empty date filters do not cause exceptions.
- Email notification content and failure handling work as expected.
- Existing pages and workflows remain functional.

Use only the checks relevant to the current task. Do not claim that a workflow works unless it was verified.

## 9. Working With the User

- Keep explanations direct, practical, and beginner-friendly.
- Avoid overwhelming the user with unnecessary technical detail.
- Reuse relevant project context from previous conversations instead of asking the user to repeat known requirements.
- Ask questions only when an essential detail cannot safely be inferred or verified.
- Explain the root cause and fix in plain English.
- Never claim to have inspected local files, run commands, or tested the application unless actually done.
- Follow the user's latest explicit instruction if priorities change.
- If a requirement is unclear, inspect the existing implementation first and ask only when necessary.

## 10. Definition of Done

A task is complete only when:

- The requested behavior is implemented end to end.
- The underlying cause has been addressed.
- Existing UI, working features, and user data are preserved.
- Validation, authorization, privacy, and data integrity remain intact.
- Relevant tests have been run and results accurately reported.
- Any limitations or unresolved issues are clearly disclosed.

Final response must briefly state:
1. Root cause or purpose of the change.
2. Files modified.
3. What changed.
4. Tests run and actual results.
5. Remaining issues or manual steps.
