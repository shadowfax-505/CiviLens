# Continuous acquisition on macOS

Two user LaunchAgents keep governed acquisition running across reboots and logins.
User agents, not system daemons: they run as the operator, need no `sudo`, and stop when that
operator logs out. Acquisition acts under a person's recorded authorisation, so running it as
that person rather than as root matches who is answerable for it.

| Agent | Does |
| --- | --- |
| `com.civiclens.queue` | `queue:work --queue=ingestion,default`, restarted by launchd if it exits |
| `com.civiclens.scheduler` | `schedule:run` once a minute, which is what fires `civiclens:sources-dispatch` |

`schedule:run` on a one-minute `StartInterval` rather than a long-lived `schedule:work`: a process
that exits every minute cannot drift, leak, or hold stale configuration, and launchd restarting it
is the normal case rather than a recovery.

`--max-time=3600` recycles the worker hourly. A PHP worker holds application code in memory, so a
long-lived one keeps running the code it started with — which is how a deployed fix can appear to
have no effect.

## Install

Replace `{{PHP_BIN}}` and `{{CIVICLENS_PATH}}`, then:

```bash
launchctl bootstrap gui/$UID ~/Library/LaunchAgents/com.civiclens.queue.plist
launchctl bootstrap gui/$UID ~/Library/LaunchAgents/com.civiclens.scheduler.plist
```

## Check and stop

```bash
launchctl list | grep civiclens
tail -f storage/logs/scheduler.log storage/logs/queue-worker.log
launchctl bootout gui/$UID/com.civiclens.queue
launchctl bootout gui/$UID/com.civiclens.scheduler
```

Stopping the agents stops all fetching. `INGESTION_ENABLED=false` also stops it, and pausing an
endpoint stops just that one.
