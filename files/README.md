# DriveBack

A USB backup plugin for Unraid — a clean, Hyper Backup-style frontend for rclone mirror backups to connected USB drives.

## Features

- Create named backup jobs (source share → USB drive)
- Pure rclone mirror — raw, browsable files on your USB drive
- Nightly scheduled backups with configurable time
- Enable/disable jobs per-schedule
- Manual "Run Now" per job
- Per-job log viewer
- Integrates natively into the Unraid UI

## Requirements

- Unraid 7.x
- rclone installed (via NerdTools or manually)
- USB drive mounted via Unassigned Devices plugin

## Installation

Add the following URL to Unraid's Community Applications or the Plugins tab:

```
https://raw.githubusercontent.com/forcesword/driveback/main/driveback.plg
```

## Usage

1. Connect your USB drive — it will appear under `/mnt/disks/` via Unassigned Devices
2. Open the **DriveBack** tab in your Unraid UI
3. Click **New Job**, select a source share and destination USB drive
4. Set a backup time and save
5. The job runs nightly at the configured time, or click **Run** to trigger manually

## How it works

DriveBack uses `rclone sync` under the hood:

```bash
rclone sync /mnt/user/yourshare /mnt/disks/YourUSB/backup
```

Files are mirrored as-is — no proprietary format, no restore tool needed. Plug the USB into any machine and browse your files directly.

## Roadmap

- [ ] Encryption support (v2)
- [ ] Retention / versioning with `--backup-dir`
- [ ] Email/webhook notifications on job completion
- [ ] Bandwidth throttling
- [ ] Exclusion filters

## License

MIT
