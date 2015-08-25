# phab-utils
Utilities for managing Phabricator install

If you have questions use [Issues](https://github.com/neandrake/phab-utils/issues), if you would like to contribute use [Pull Requests](https://github.com/neandrake/phab-utils/pulls).

#### TODO
- [ ] Create `systemd` service files for aplict and phd. Currently these do not startup when the system reboots.
- [ ] Consider creating a single `systemd` service file to manage all services using something similar to `service` script.

### Environment
- Use the Phabricator Documentation for initial installation:
  - https://secure.phabricator.com/book/phabricator/article/installation_guide/
- CentOS 7 Minimial Install (no GUI)
- HTTP Server: nginx
- PHP Proxy: php-fpm
- SSHD - on port 22 managed by phabricator
- Accounts:
  - `phabricator` - This is likely unnecessary, though is the account I use for managing most phabricator stuff over the terminal
  - `nginx` - This is the accounts which the nginx service runs under
  - `phab-phd` - This is the account which is configured for the phd daemons to run under
  - Group `phacility` - This is a group which each of the above accounts is a member of. I use this for managing several path permissions to allow group access.
- Path Structure
  - `/usr/local/phacility/` - The root folder location where the phabricator application is installed
  - `/usr/local/phacility/phabricator/` - The `phabricator` git repository
  - `/usr/local/phacility/libphutil/` - The `libphutil` git repository
  - `/usr/local/phacility/arcanist/` - The `arcanist` git repository
  - `/usr/local/phacility/service` - The service script from this repository
  - `/usr/local/phacility/accounts/` - Contains the home directories of the accounts listed above
  - `/usr/local/phacility/backups/` - During upgrades backup of the database and configurations are placed here

### Managing Services with the `service` Script
 - The [service script](/service/service) is based off of the rough outline script from Phabricator:
   - https://secure.phabricator.com/book/phabricator/article/upgrading/
 - *My version of the script requires to be run as root/sudo, and will exit immediately if it determines that it does not have privilege.*
 - The `service` script relies on having the SSHD service configured to be used by `systemctl`, see below.

#### Configure the script
If you use this script there are some variables at the top of the file which you should configure.
- `DATESTR` - This is used to format timestamp when performing upgrade. It's used in logs as well as a folder name for placing backups.
- `BRANCH` - This defaults to `stable`, it indicates what the default branch to use when upgrading. Set this to `master` if you prefer to track the master branch upstream.
- Under the `init()` function:
  - `ROOT` - The root install location (absolute). See Path Structure above, this should point to the root where phabricator is installed
  - `BAKPATH` - Relative path to upgrade backup location (absolute). Used to locate where backups of database should be dumped to. As this can grow in size if you regularly upgrade, you may want to keep this in a separate location or have it regularly pruned.
  - `REVLOG` - Path to file for storing the upgrade log. During each upgrade the database dump is logged along with each of the git repository `HEAD` revision prior to the upgrade. This is useful in the event of needing to restore to a previous working version.
  - `PHAB_USER` - Several actions are performed under this account, detailed above. The `ROOT` directory is set to be owned by this account.
  - `PHAB_GROUP` - The group account which the `ROOT` directory is set to be owned by, detailed above.
  - `PHAB_PHD_USER` - The user account which the phd daemons runs as, detailed above.
  - `WEB_USER` - THe user account which the http/nginx service runs as, detailed above.

#### Commands:
##### Stop/Start
Stops or starts services related to Phabricator:
- nginx
- php-fpm
- sshd (for phabricator on 22 only)
- aphlict
- phd

*Note that this doesn't stop/start MySQL - MySQL is required to be running during upgrade*

##### Restart
Performs a `stop` followed by `start`
This is useful when making configuration changes which requires restarting the phd daemons to pick up on the configuration changes, even though it's unnecessary for all services to restart.

##### Upgrade
Upgrades the phabricator install to the latest version, creating a backup of the database and log of the revisions used.
1. Stops all services
2. Creates a backup folder for the day's upgrade in [install-location]/backups/
3. Copies the local.json from phabricator directory into backup location
4. Does a `./bin/storage dump` to backup the database contents into an archive in the backup location.
5. Updates each of the repositories, `libphutil`, `arcanist`, and `phabricator`, for each one updating the log to indicate which commit each one was previously at and upgraded to.
6. Updates the ownership of the phabricator install files/folders.
7. Runs database migration using `./bin/storage upgrade`.
8. Starts all services

The upgrade process is useful for creating backup of content prior to upgrade along with tracking which revision of install is used.

#### SSHD
To configure a second sshd daemon to be controlled by Phabricator on CentOS 7, we configure `systemd` (which is the default service manager on that version).

I found this resource very helpful:
  - https://wiki.archlinux.org/index.php/Systemd

1. Follow the guide from Phabricator for hosting repositories on Diffusion:
  - https://secure.phabricator.com/book/phabricator/article/diffusion_hosting/
2. After following the guide you should have these additional files, on my system they reside in these absolute paths (See the contents in [the sshd folder](/sshd/):
  - `/usr/lib/systemd/system/sshd-phab.service` - This is a `systemd` service definition. Placing this file here allows for it to be controlled by `systemctl` command, and can be configured to run on startup - in the same fashion as the regular sshd service.
  - `/etc/ssh/sshd_config.phabricator` - This is the SSHD config which is used by the .service for executing the sshd binary.
  - `/usr/libexec/phabricator-ssh-hook.sh` - This is referenced by the sshd_config.phabricator file to be used for delegating ssh authentication to a phabricator script. This file is verbatim from the Phabricator guide linked above - aside from configuring the `VCS_USER` and `ROOT` variables.
3. Run `systemctl daemon-reload` to pick up on the new service. This should report everything is ok.
4. You may need to run `systemctl enable sshd-phab.service` to configure the service to run on startup, however the current configuration should be set in that regard already.
