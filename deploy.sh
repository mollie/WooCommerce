#! /bin/bash
# Original by https://github.com/GaryJones/wordpress-plugin-git-flow-svn-deploy

echo
echo "Deploy mollie-payments-for-woocommerce WordPress Plugin"
echo

# Set up some default values. Feel free to change these in your own script
CURRENTDIR=`pwd`
PLUGINSLUG="mollie-payments-for-woocommerce"
PLUGINDIR="$CURRENTDIR/$PLUGINSLUG"
SVNPATH="/tmp/$PLUGINSLUG"
SVNURL="http://plugins.svn.wordpress.org/$PLUGINSLUG"
MAINFILE="$PLUGINSLUG.php"

default_svnuser="mollie"

# Get some user input
# Can't use the -i flag for read, since that doesn't work for bash 3

printf "Your WordPress repo SVN username ($default_svnuser): "
read -e input
SVNUSER="${input:-$default_svnuser}" # Populate with default if empty
echo

echo "That's all of the data collected."
echo
echo "Slug: $PLUGINSLUG"
echo "Temp checkout path: $SVNPATH"
echo "Remote SVN repo: $SVNURL"
echo "SVN username: $SVNUSER"
echo "Plugin directory: $PLUGINDIR"
echo "Main file: $MAINFILE"
echo

printf "OK to proceed (Y|n)? "
read -e input
PROCEED="${input:-y}"
echo

# Allow user cancellation
if [ "$PROCEED" != "y" ]; then echo "Aborting..."; exit 1; fi

# git config
GITPATH="$PLUGINDIR/" # this file should be in the base of your git repository

# Let's begin...
echo ".........................................."
echo
echo "Preparing to deploy WordPress plugin"
echo
echo ".........................................."
echo

# Check version in readme.txt is the same as plugin file after translating both to unix line breaks to work around grep's failure to identify mac line breaks
PLUGINVERSION=`grep "Version:" $PLUGINDIR/$MAINFILE | awk -F' ' '{print $NF}' | tr -d '\r'`
echo "$MAINFILE version: $PLUGINVERSION"
READMEVERSION=`grep "^Stable tag:" $PLUGINDIR/readme.txt | awk -F' ' '{print $NF}' | tr -d '\r'`
echo "readme.txt version: $READMEVERSION"

if [ "$READMEVERSION" = "trunk" ]; then
	echo "Version in readme.txt & $MAINFILE don't match, but Stable tag is trunk. Let's proceed..."
elif [ "$PLUGINVERSION" != "$READMEVERSION" ]; then
	echo "Version in readme.txt & $MAINFILE don't match. Exiting...."
	exit 1;
elif [ "$PLUGINVERSION" = "$READMEVERSION" ]; then
	echo "Versions match in readme.txt and $MAINFILE. Let's proceed..."
fi

if git show-ref --tags --quiet --verify -- "refs/tags/$PLUGINVERSION"
	then
		echo "Version $PLUGINVERSION already exists as git tag. Exiting....";
		exit 1;
	else
		echo "Git version does not exist. Let's proceed..."
fi

default_commitmsg="Release $PLUGINVERSION, see readme.txt for changelog."

printf "Enter a commit message for this new version ($default_commitmsg): "
read -e input
COMMITMSG="${input:-$default_commitmsg}" # Populate with default if empty
git commit -am "$COMMITMSG"

echo "Tagging new version in git"
git tag -a "$PLUGINVERSION" -m "Tagging version $PLUGINVERSION"

echo "Pushing git master to origin, with tags"
git push origin master
git push origin master --tags

echo
echo "Clear $SVNPATH"
rm -fr $SVNPATH/

echo
echo "Creating local copy of SVN repo trunk ..."
svn checkout $SVNURL $SVNPATH --depth immediates
svn update --quiet $SVNPATH/trunk --set-depth infinity

echo "Ignoring GitHub specific files"
svn propset svn:ignore "README.md
Thumbs.db
.git
.gitignore" "$SVNPATH/trunk/"

echo "Copying plugin files to the trunk of SVN"
rsync $PLUGINSLUG/* -ri --del -m --exclude ".*" $SVNPATH/trunk/ | grep sT

# Support for the /assets folder on the .org repo.
echo "Moving assets"
# Make the directory if it doesn't already exist
mkdir -p $SVNPATH/assets/
rsync $CURRENTDIR/assets/* -ri --del -m --exclude ".*" $SVNPATH/assets/ | grep sT
svn add --force $SVNPATH/assets/

echo "Changing directory to SVN and committing to trunk"
cd $SVNPATH/trunk/
# Delete all files that should not now be added.
svn status | grep -v "^.[ \t]*\..*" | grep "^\!" | awk '{print $2}' | xargs svn del
# Add all new files that are not set to be ignored
svn status | grep -v "^.[ \t]*\..*" | grep "^?" | awk '{print $2}' | xargs svn add
svn commit --username=$SVNUSER -m "Preparing for $PLUGINVERSION release"

echo "Updating WordPress plugin repo assets and committing"
cd $SVNPATH/assets/
# Delete all new files that are not set to be ignored
svn status | grep -v "^.[ \t]*\..*" | grep "^\!" | awk '{print $2}' | xargs svn del
# Add all new files that are not set to be ignored
svn status | grep -v "^.[ \t]*\..*" | grep "^?" | awk '{print $2}' | xargs svn add
svn update --accept mine-full $SVNPATH/assets/*
svn commit --username=$SVNUSER -m "Updating assets for $PLUGINVERSION release"

echo "Creating new SVN tag and committing it"
cd $SVNPATH
svn update --quiet $SVNPATH/tags/$PLUGINVERSION
svn copy --quiet trunk/ tags/$PLUGINVERSION/
# Remove assets and trunk directories from tag directory
# svn delete --force --quiet $SVNPATH/tags/$PLUGINVERSION/assets
# svn delete --force --quiet $SVNPATH/tags/$PLUGINVERSION/trunk
cd $SVNPATH/tags/$PLUGINVERSION
svn commit --username=$SVNUSER -m "Tagging version $PLUGINVERSION"

echo "Removing temporary directory $SVNPATH"
cd $SVNPATH
cd ..
rm -fr $SVNPATH/

echo "*** FINISHED ***"
