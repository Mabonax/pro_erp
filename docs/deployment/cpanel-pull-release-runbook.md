# cPanel pull release runbook

Use this runbook when operating Program of Action production deployment from Afrihost/cPanel.

## Publish a release

1. Open GitHub Actions in `Mabonax/pro_erp`.
2. Run `Production release`.
3. Use:
   - `erp_ref`: `program-of-action-erp`
   - `website_ref`: `main`
   - `allow_merge_commit`: `true` only for reviewed merge commits
4. Wait for both build jobs to pass.
5. Approve the `production` environment job.
6. Confirm GitHub created a `poa-production-*` release.

This does not deploy by itself. Afrihost pulls the release.

## Manual server pull

In cPanel Terminal:

```bash
/usr/bin/bash /home/prograg9g3o8/deploy/poa-release/deploy.sh
```

## Cron command

```bash
/usr/bin/bash /home/prograg9g3o8/deploy/poa-release/deploy.sh >> /home/prograg9g3o8/deploy/poa-release/logs/cron.log 2>&1
```

Schedule:

```text
*/5 * * * *
```

## Logs

```bash
tail -n 200 /home/prograg9g3o8/deploy/poa-release/logs/cron.log
ls -1 /home/prograg9g3o8/deploy/poa-release/logs
tail -n 200 /home/prograg9g3o8/deploy/poa-release/logs/deploy-*.log
```

## State

```bash
cat /home/prograg9g3o8/deploy/poa-release/state/deployment-state.json
```

## Common failures

- `No approved release found`: approve and publish a `poa-production-*` GitHub Release.
- `Checksum mismatch`: do not deploy; publish a new release.
- `Archive contains a parent traversal path`: do not deploy; investigate release packaging.
- `production .env missing`: restore the server `.env` for that app.
- `Health check failed`: inspect Laravel logs and keep the failed release out of service.

## Emergency stop

Disable the cPanel cron job.

If a deployment is running, wait for it to finish or remove the lock only after verifying no deploy process remains:

```bash
rmdir /home/prograg9g3o8/deploy/poa-release/locks/poa-release.lock
```
