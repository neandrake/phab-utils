### Maintaining Customizations
Some customizations to Phabricator can't be accomplished by extensions but require modifying the Phabricator source code.

This can be maintained in a number of different ways. Here is the process I'm currently using. Note that it has a few rough edges.

#### General Process
1. Set up and host a forked version of `arcanist` and `phabricator` repositories. This will be the `origin` remote location which the Phabricator server clones/pulls from.
2. Create a separate branch which will contain the customizations, e.g. `custom`. Note that even if customizations only exist in one repo this branch needs to exist in both repos, as the `service` script assumes to setup both repositories to the same named branch.
3. Update Phabricator by pulling updates from public/upstream, merge `custom` onto latest `stable`, push to the forked repo.

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
$ git config merge.ff only

# checkout the stable, master, and custom branches
$ git checkout --track -b origin-stable origin/stable
$ git checkout --track -b origin-master origin/master
$ git checkout --track -b custom origin/custom

# add upstream remote
$ git remote add upstream git@github.com:phacility/phabricator.git
$ git checkout --track -b upstream-stable upstream/stable
$ git checkout --track -b upstream-master upstream/master
```

#### Updating
When ready to pull in the latest updates from Phabricator, pull in the public changes from `upstream`, update the `upstream-master` and `upstream-stable` branches to match, merge the `origin-master` and `origin-stable` branches to the upstream versions, then merge `custom` on top of `origin-stable` (or `origin-master`).

```bash
# pull updates from both origin and upstream, update the local branches to their updated states
# do this for both arcanist and phabricator, for both stable and master branch variants
$ git fetch --all
$ git checkout upstream-stable
$ git pull                       # ff-only pull to update upstream-stable to (remote) upstream/stable
$ git checkout origin-stable
$ git merge upstream-stable      # merge the origin-stable to match upstream-stable
$ git push origin origin-stable  # push the updated stable branch to origin

# merge custom branch onto new stable
$ git checkout custom
$ git merge origin-stable
# address conflicts, if any

# push the custom branch
$ git push origin custom
```

