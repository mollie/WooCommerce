source /usr/lib/git-core/git-sh-prompt

# ignore duplicate commands, ignore commands starting with a space
# keep the last 5000 entries
export HISTCONTROL=erasedups
export HISTSIZE=5000

# Add global and "project root level" composer executables to the PATH
export PATH="$HOME/.composer/vendor/bin:$PATH"
export PATH="/var/www/html/vendor/bin:$PATH"

# append to the history instead of overwriting (good for multiple connections)
shopt -s histappend

export PS1="\[\033[01;32m\]\u@\h\[\033[00m\]:\[\033[01;34m\]\w\[\033[00m\]\$\[$(tput sgr0)\] "

export PHP_IDE_CONFIG='serverName=mollie.ddev.site'
#export XDEBUG_CONFIG="mode=debug client_host=127.0.0.1 client_port=9003 start_with_request=yes"

alias ls='ls --group-directories-first'
alias cp='cp -aiv'
alias grep='grep --color=always'
alias tgz='tar -pczf'

profile(){
   XDEBUG_MODE=profile "$1"
}

# all files in homeadditions/bashrc.d/*.sh will be
# interpreted as shell script so you can use these to
# customize your bash
if [ -d "${HOME}/bashrc.d" ]; then
    for FN in ${HOME}/bashrc.d/*.sh ; do
        source "${FN}"
    done
fi

cd "${DDEV_DOCROOT}"

