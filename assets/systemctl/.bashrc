# rest of your .bashrc file here

# set timezone
if [ -f ~/dotfiles/timezone.sh ]; then
    chmod +x ~/dotfiles/timezone.sh
    ~/dotfiles/timezone.sh
elif [ -f ./timezone.sh ]; then
    chmod +x ./timezone.sh
    ./timezone.sh
fi

# Add `~/bin` to the `$PATH`
export PATH="$HOME/bin:$PATH"

# Add custom bin paths
export PATH="$PATH:node_modules/.bin:bin:vendor/bin"