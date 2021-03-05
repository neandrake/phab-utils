### Maintaining Customizations
Some customizations to Phabricator can't be accomplished by extensions but require modifying the Phabricator source code.

This can be maintained in a number of different ways. Here is the process I'm currently using. Note that it has a few rough edges. Rather than merging the `custom` branch into `stable` I am instead rebasing the `custom` branch on top of `stable`, which requires force-pushing the `custom` branch updates as well as ensuring when updating that the old branch state is blown away (the `service` script accounts for this using `git reset --hard`).

#### General Process
1. Set up and host a forked version of `arcanist` and `phabricator` repositories. This will be the `origin` remote location which the Phabricator server clones/pulls from.
2. Create a separate branch which will contain the customizations, e.g. `custom`. Note that even if customizations only exist in one repo this branch needs to exist in both repos, as the `service` script assumes to setup both repositories to the same named branch.
3. Update Phabricator by pulling updates from public/upstream, rebase `custom` onto latest `stable`, push to the forked repo.

#### Phabricator Server Setup
The phabricator server needs to be configured to pull from the forked repository as well as use the `custom` branch. For both `phabricator` and `arcanist` repositories on the Phabricator server do the following.

If hosting these repositories in a forge that requires credentials to access, configure git to use and store these credentials on the Phabricator server. In this example `phabricator` and `arcanist` do not share credentials.
```bash
# setup credentials, use separate files for phabricator vs. arcanist since git by default stores credentials per-server
$ cd /usr/local/phacility/phabricator
$ git config credential.helper 'store --file=/usr/local/phacility/.git-credentials-phabricator'
$ git fetch --all
# use the project credentials when prompted, this will store for future use

$ cd /usr/local/phacility/arcanist
$ git config credential.helper 'store --file=/usr/local/phacility/.git-credentials-arcanist'
$ git fetch --all
# use the project credentials when prompted, this will store for future use
```

Then update the `service` script to set the default branch, `BRANCH=custom`.

#### Local Repository Setup
To update Phabricator a copy of the forked repository will need to be cloned locally, `origin`. Additionally on this local repository a new remote location will need to be setup pointing to the public Phabricator repositories, `upstream`.

Do this for both `phabricator` and `arcanist` repositories:
```bash
$ git clone ssh://git@my-hosted-server.com:phacility/phabricator.git
$ cd phabricator
# setup to only fast-forward merge
$ git config pull.ff only

# checkout the stable, master, and custom branches
$ git checkout --track -b stable origin/stable
$ git checkout --track -b master origin/master
$ git checkout --track -b custom origin/custom

# add upstream remote
$ git remote add upstream git@github.com:phacility/phabricator.git
```

#### Updating
When ready to pull in the latest updates from Phabricator, pull in the public changes, update the `master` and `stable` branches to match, then rebase `custom` on top of `stable` (or `master`)

```bash
# update from upstream's stable & master and update origin/stable & origin/master match
$ git fetch upstream
$ git checkout origin/stable
$ git pull upstream stable # should do a fast-forward
$ git checkout origin/master
$ git pull upstream master # should do a fast-forward

# push updated stable & master to the forked repository
$ git push -u origin --all

# rebase custom branch on top of new stable
$ git checkout custom
$ git rebase stable
# address conflicts, if any (don't merge)

# force-push the custom branch since we did a rebase instead of merge
$ git push --force -u origin custom
```

