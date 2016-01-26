install a git client in windows
===============================================================
  1. install *Git Shell*: [http://git-scm.com/downloads](http://git-scm.com/downloads)
  2. install *TortoiseGit*: [http://download.tortoisegit.org](http://download.tortoisegit.org)
  3. git book **Pro Git**: [http://git-scm.com/book/zh/v1](http://git-scm.com/book/zh/v1)


add ssh keys with git shell(windows) or linux shell to gitlab
===============================================================
  1. `ssh-keygen -t rsa -C "$your_email"`
  2. copy the text of file (`~/.ssh/id_rsa.pub`) to gitlab


init git config
===============================================================
  1. git config file:
    - system: `/etc/gitconfig`
    - user  : `~/.gitconfig`
    - repo  : `.git/config`
  2. init configuration
    - `$git config --global user.name "张三"`
    - `$git config --global user.email zs@yy.com`
    - `$git config --global color.ui true`
    - `$git config --global push.default simple`
    - `$git config --global core.editor gvim.exe`
    - `$git config --global merge.tool gvimdiff.bat`
    - `$git config --global core.autocrlf false`
    - `$git config --list`

create project:
===============================================================
  1. create new project with private access and add members:
    - added as developer with commit privacy users
    - added as reporter with only merge request users
    - added as guest with no fork privacy users
  2. fork a exist project with private access anbd add members:
    - added as developer with commit privacy users(your self)
    - added as reporter with privacy to deal with your merge request users, 
      your team leader and code checker users
    - added as guest with no fork privacy users


general development for all user
===============================================================
  1. for init
    - fork a repo on web.
    - clone repo to local 
       * `$git clone http://xxxx/my/repo myrepo`
       * `$cd myrepo`
       * `$git remote add upstream http://xxxx/up/repo`
  2. for sync local repo with myrepo and upstream
    - `$git pull`
    - `$git fetch upstream`
    - `$git merge upstream/master`
  3. do something with files and commit to myrepo
    - `$git commit -a /your/file/path`
    - `$git push`
  4. login to web repo, create a new merge request


local repo development for all user
===============================================================
  1. update files
    - `$git pull`
    - `$git fetch upstream`
    - `$git merge upstream/master`
  2. for little changes 
    - edit files
    - `$git commit -a /your/file/path`
    - `$git push`
  3. for create new branch development (recommended)
    - `$git branch add-feature-name`
        or `$git branch fix-bug3223`
        or `$git branch update-files`
        or `$git branch change-code-name`
    - `$git checkout mybranchname`
        or `$git checkout -b mybranchname master`
    - edit files
    - commit/merge and remove branch
      * `$git commit -a /your/file/path`
      * `$git checkout master`
      * `$git merge --no-ff mybranchname`
      * `$git commit -a /your/file/path`
      * `$git push`
      * `$git branch -d mybranchname`
  4. show current branch list/status
    - `$git branch`
    - `$git status`
  

resolve conflict merge request (for developer/master/owner role)
===============================================================
  1. fetch code and merge code
    - `$git clone http://xxxx/up/repo uprepo`
    - `$cd uprepo`
    - `$git fetch http://xxxx/other/repo master`
         or `$git fetch http://xxxx/other/repo otherbranchname`
    - `$git checkout -b other/repobranch FETCH_HEAD`
    - `$git checkout master`
    - `$git merge --no-ff other/reporbanch`
  2. edit files to resolve conflict
  3. push files
    - `$git commit -a your/file/path`
    - `$git push`


branch commands
===============================================================
  1. create a new branch
    - create a new branch
      * `$git branch mybranch1`
    - switch to a branch
      * `$git checkout mybranch1`
    - create and switch to a new branch
      * `$git checkout -b mybranch1`
  2. merge a branchA to other branchB
    - `$git checkout branchB`
    - `$git merge branchA`
    - call a merge tool to deal with merge work
      * `$git mergetool`
  3. remove a branch
    - `$git branch -d mybranch1`
  4. others
    - `$git branch -v`
    - `$git branch --merged`
    - `$git branch --no-merged`
  5. push a local branch to remote branch
    - `$git push origin mylocalbranch1`
        or `$git push origin mylocalbranch1:remotebranch2`
    - merge a remote branch to a local branch
      * `$git fetch origin`
      * `$git checkout mylocalbranch1`
      * `$git merge origin/remotebranch2`
    - fetch a remote branch to a new local branch
      * `$git fetch origin`
      * `$git checkout -b mylocalbranch2 origin/remtoebranch2`
  6. remove a remote branch
    - `$git push origin :remotebranch2`


how to release a version
===============================================================
  1. web click to create a tag in gitlab
  2. switch local files to new tag 
    - `$git checkout mytagname`
  3. other commands
    - `$git tag`
    - `$git tag -l 'v1.3.*'`
    - create a normal tag 
      * `$git tag -a v1.4 -m 'version 1.4' `
      * `$git tag -a v1.4 -m 'version 1.4' 9fceb02`
    - create a light tag
      * `$git tag v1.4-1w`
      * `$git tag v1.4-1w 9fceb02`
    - `$git show v1.4`
    - push one tag to remote repo
      * `$git push origin v1.4`
    - push all tags to remote repo
      * `$git push origin --tags`


other git commands
===============================================================
  1. `$git status`
  2. `$git log`
     or `$git log -p -2`
  3. `$git add file1`
  4. rm files
    - remove local file and git file
      * `$git rm file1`
        or `$git rm -f file1`
    - keep local file and remove git file
      * `$git rm --cached file1`
  5. move files
    - `$git mv file_from file_to`
  6. `$git help command`
  7. cancel a commit
    - `$git commit -a --amend`
  8. unadd new file or changed file
    - `$git reset HEAD file1`
    - `$git checkout -- file1`
  9. remote repo.
    - `$git remote -v`
    - `$git remote add alias1 http://xxxxx/yy/repo`
    - `$git fetch alias1`
    - `$git push alias1 master`
    - `$git remote show alias1`
    - fetch and merge remote repo to local current branch 
      * `$git pull alias1 master`
    - `$git remote rename alias1 alias2`
    - `$git remote rm alias2`
