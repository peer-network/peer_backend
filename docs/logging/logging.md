# Logging Level Rules (Draft)

## Goals
- Make logs consistent, searchable, and actionable.
- Default to the lowest level that still conveys the required signal.
- Avoid PII and secrets at every level.

## General Rules
- Never log secrets, credentials, access tokens, session IDs, or full PII.
- Log identifiers as hashed or truncated when possible.
- Prefer structured logs: include `request_id`, `user_id`, `service`, `duration_ms`, `status_code`.
- Log once per event; avoid duplicate errors at multiple layers.
- Include stack traces only for `ERROR` and above.
- Use correlation IDs consistently across services and background jobs.

## Levels

### DEBUG
- Use for high-volume, low-importance details that help diagnose behavior.
- Examples: function entry/exit, cache hits/misses, request parsing details, retry counters.
- Must not include PII, secrets, tokens, passwords, or raw payloads.

### INFO
- User Input Validation Failed(3xxxx response codes errors): Use when the failure is expected and part of normal flow (e.g., missing optional field, client-side validation missed but is common). No action needed beyond returning a 4xx to the client.
- Use for normal, expected application flow that is useful in production.
- Examples: startup/shutdown, scheduled jobs started/completed, external API call success, user-visible operations completed.
- Keep concise and business-relevant. No sensitive data.

### WARNING
- User Input Validation Failed: Use when the failure is unexpected, noisy, or indicates misuse (e.g., repeated failures, suspicious patterns, rate-limited validation errors). Should be worth investigation or monitoring.
- Use for unexpected events that are handled gracefully and don’t break the request.
- Examples: transient external API failure with retry, validation fallbacks, partial data returned.
- Should include context to debug quickly (correlation id, endpoint, dependency, retry count).

### ERROR
- 4xxxx reposnse codes
- Use for failures that impact a request or background job but are recoverable.
- Examples: failed DB transaction, external API errors after retries, business rule violations.
- Include exception class, error code, and minimal safe context. Avoid raw payloads.

### CRITICAL
- Use for serious failures that threaten service availability or data integrity.
- Examples: database unavailable, message queue down, data corruption detected, repeated crash loops.
- Should trigger alerts.

### ALERT/EMERGENCY (if supported; otherwise fold into CRITICAL)
- Use for immediate, service-wide impact that requires urgent human intervention.
- Examples: security breach detected, service-wide outage, massive data loss risk.

## Sampling & Rate Limits
- DEBUG logs should be disabled in production by default or heavily sampled.
- For noisy warnings/errors (e.g., retries), consider rate limiting or aggregation.