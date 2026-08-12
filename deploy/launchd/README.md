# Continuous acquisition on macOS

Two user LaunchAgents keep governed acquisition running across reboots and logins.
User agents, not system daemons: they run as the operator, need no `sudo`, and stop when that
operator logs out. Acquisition acts under a person's recorded authorisation, so running it as
that person rather than as root matches who is answerable for it.

| Agent | Does |
| --- | --- |
| `com.civiclens.queue` | `queue:work --queue=ingestion,default`, restarted by launchd if it exits |
| `com.civiclens.scheduler` | `schedule:run` once a minute, which is what fires `civiclens:sources-dispatch` |
| `com.civiclens.clamd` | the malware scanner daemon every fetched artifact is checked against |

`schedule:run` on a one-minute `StartInterval` rather than a long-lived `schedule:work`: a process
that exits every minute cannot drift, leak, or hold stale configuration, and launchd restarting it
is the normal case rather than a recovery.

`--max-time=3600` recycles the worker hourly. A PHP worker holds application code in memory, so a
long-lived one keeps running the code it started with — which is how a deployed fix can appear to
have no effect.

## The scanner is not optional

An artifact that cannot be scanned is quarantined and never parsed. That is deliberate — quarantine
exists to keep unscanned files away from the parsers — but it means the pipeline stops at
acquisition until `clamd` is running. Ten audit reports sat quarantined with
`malware_status=unavailable` until it was installed.

```bash
brew install clamav
freshclam --config-file=/opt/homebrew/etc/clamav/freshclam.conf   # ~250MB, first run only
```

`clamd.conf` and `freshclam.conf` need a `DatabaseDirectory` and, for clamd, a `LocalSocket`. The
worker calls the scanner by absolute path via `INGESTION_MALWARE_SCANNER_BINARY`, because a
launchd agent runs with a minimal `PATH` that does not include Homebrew — a bare `clamdscan`
resolves in a shell and not in the worker, which reads as "no scanner" and quarantines everything.

Verify with the EICAR test string: the scanner must exit 1 on it and 0 on an ordinary file.

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
