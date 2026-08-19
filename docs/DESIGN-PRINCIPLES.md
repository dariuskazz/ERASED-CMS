# ERASED CMS Design Principles

These principles guide architecture, product decisions, and code review.

1. **Keep the core small.** Core provides stable platform services; packages provide features.
2. **Everything extensible uses the Package Engine.** Themes, modules, language packs, profiles, widgets, and commercial extensions share one lifecycle.
3. **Capabilities over hardcoded dependencies.** Consumers request a capability; the platform resolves an enabled provider.
4. **Upgrade without reinstalling.** New functionality must be addable later without rebuilding the site.
5. **Protect user data.** Disabling or removing a package must preserve data by default.
6. **Back up before destructive operations.** Updates, migrations, uninstall operations, and profile changes require recoverable state.
7. **No runtime schema mutation.** Database evolution belongs in versioned migrations.
8. **Shared-hosting compatibility matters.** Release packages must run without requiring Composer, Node.js, or shell access on the server.
9. **Privacy and self-hosting first.** External services are optional integrations, never mandatory dependencies.
10. **AI is optional.** Every AI-assisted workflow must have a complete non-AI workflow.
11. **Humans approve changes.** Assistants and automation may propose actions but must not silently change important settings or content.
12. **Provider independence.** Avoid locking the platform to a single payment, search, authentication, storage, database, or AI provider.
13. **Secure defaults.** Security should not depend on installing extra packages.
14. **Document public contracts.** Package formats, capabilities, lifecycle hooks, migrations, and extension APIs must be versioned and documented.
15. **Test before marking complete.** Work is complete only after syntax checks, relevant smoke or integration tests, rollback validation where applicable, and a GitHub update.

## Decision test

Before accepting a major feature, ask:

- Does it belong in the core?
- Can it be implemented as a package or capability provider?
- Can it be upgraded and removed safely?
- Does it work without a mandatory cloud or AI service?
- Can it run on supported shared-hosting environments?
- Will the decision still make sense in five years?
