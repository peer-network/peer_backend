# Ops Scripts

This directory stores **one-off database operations** that must be executed after a deployment but are not part of the schema migrations.

## Scope
- Data backfills, cleanup jobs, and other idempotent/once-only operations
- Scripts that depend on application code already being deployed
- No schema-altering statements; schema migrations belong in `sql_data_for_import`

## Naming
Use timestamped, descriptive filenames so the execution order is obvious, e.g. `20251202000000_migrate_lp_fee_to_credit.sql`.

## Expected Workflow
1. Create the script in this folder and document the intent at the top of the file.
2. Test it locally against a snapshot of the production database when possible.
3. Peer review it like any other change.
4. After the release, run it once in each environment that needs the data change.
5. Record the execution (link to ticket, Slack thread, etc.) so we know it has been completed.

## Tips
- Keep scripts idempotent when possible so re-running them is safe.
- Wrap large batches in transactions to make rollbacks easier.
- Avoid referencing application tables that may be renamed soon; tie the script to a ticket if it relies on ongoing work.

Anything violating the above guidelines should be moved to the proper migrations folder or converted into an automated job.
