#!/usr/bin/env bash
# Script for deploying the Mollie WooCommerce plugin to the Wordpress store. This script is a modified version of the
# following script: https://github.com/GaryJones/wordpress-plugin-svn-deploy.
#
# All prompts for input were replaced by hardcoded variables or environment variables to make it fully automated.
#
# Steps to deploying:
#
#  1. Check local plugin directory exists.
#  2. Check main plugin file exists.
#  3. Check readme.txt version matches main plugin file version.
#  4. Check if Git tag exists for version number (must match exactly).
#  5. Checkout SVN repo.
#  6. Set to SVN ignore some the files we don't need to commit.
#  7. Export HEAD of master from git to the trunk of SVN.
#  8. Initialise and update git submodules.
#  9. Install Composer and install the required dependencies of the submodules.
# 10. Move /trunk/assets up to /assets.
# 11. Move into /trunk, and SVN commit.
# 12. Move into /assets, and SVN commit.
# 13. Copy /trunk into /tags/{version}, and SVN commit.
# 14. Delete temporary local SVN checkout.

echo
echo "Mollie WooCommerce Plugin SVN Deploy"
echo

# Set the required variables to deploy.
PLUGINSLUG="mollie-payments-for-woocommerce"
CURRENTDIR=$(pwd)
SVNPATH="$CURRENTDIR/deployment/$PLUGINSLUG"
SVNURL="https://plugins.svn.wordpress.org/$PLUGINSLUG"
SVNUSER="molopsdeploy"
PLUGINDIR="$CURRENTDIR/$PLUGINSLUG"
MAINFILE="$PLUGINSLUG.php"

# Check directory exists.
if [ ! -d "$PLUGINDIR" ]; then
  echo "Directory $PLUGINDIR not found. Aborting."
  exit 1;
fi

# Check main plugin file exists.
if [ ! -f "$PLUGINDIR/$MAINFILE" ]; then
  echo "Plugin file $PLUGINDIR/$MAINFILE not found. Aborting."
  exit 1;
fi

echo "Checking version in main plugin file matches version in readme.txt file..."
echo

# Check version in readme.txt is the same as plugin file after translating both
# to Unix line breaks to work around grep's failure to identify Mac line breaks.
PLUGINVERSION=$(grep -i "Version:" $PLUGINDIR/$MAINFILE | awk -F' ' '{print $NF}' | tr -d '\r')
echo "$MAINFILE version: $PLUGINVERSION"
READMEVERSION=$(grep -i "Stable tag:" $PLUGINDIR/readme.txt | awk -F' ' '{print $NF}' | tr -d '\r')
echo "readme.txt version: $READMEVERSION"

if [ "$READMEVERSION" = "trunk" ]; then
	echo "Version in readme.txt & $MAINFILE don't match, but Stable tag is trunk. Let's continue..."
elif [ "$PLUGINVERSION" != "$READMEVERSION" ]; then
	echo "Version in readme.txt & $MAINFILE don't match. Exiting...."
	exit 1;
elif [ "$PLUGINVERSION" = "$READMEVERSION" ]; then
	echo "Versions match in readme.txt and $MAINFILE. Let's continue..."
fi

echo

echo "That's all of the data collected."
echo
echo "Slug: $PLUGINSLUG"
echo "Plugin directory: $PLUGINDIR"
echo "Main file: $MAINFILE"
echo "Temp checkout path: $SVNPATH"
echo "Remote SVN repo: $SVNURL"
echo "SVN username: $SVNUSER"
echo

# Let's begin...
echo ".........................................."
echo
echo "Preparing to deploy WordPress plugin"
echo
echo ".........................................."
echo

echo

echo "Changing to $PLUGINDIR"
cd $PLUGINDIR

# Check for git tag (may need to allow for leading "v"?)
# if git show-ref --tags --quiet --verify -- "refs/tags/$PLUGINVERSION"
if git show-ref --tags --quiet --verify -- "refs/tags/$PLUGINVERSION"
	then
		echo "Git tag $PLUGINVERSION does exist. Let's continue..."
	else
		echo "$PLUGINVERSION does not exist as a git tag. Aborting.";
		exit 1;
fi

echo

echo "Creating local copy of SVN repo trunk..."
svn checkout $SVNURL $SVNPATH --depth immediates
svn update --quiet $SVNPATH/trunk --set-depth infinity

# Remove all files from the SVN trunk, the correct files will be added below.
rm -rf $SVNPATH/trunk/*

# Go back to root repository folder; the master & submodules need to checked out from here.
echo "Changing to $CURRENTDIR"
cd $CURRENTDIR

echo "Exporting the HEAD of master from git to the trunk of SVN"
git checkout-index -a -f --prefix=$SVNPATH/trunk/

# If submodule exist, recursively check out their indexes
if [ -f ".gitmodules" ]
	then
		echo "Exporting the HEAD of each submodule from git to the trunk of SVN"
		git submodule init
		git submodule update
		git config -f .gitmodules --get-regexp '^submodule\..*\.path$' |
			while read path_key path
			do
				echo "This is the submodule path: $path"
				echo "The following line is the command to checkout the submodule."
				echo "git submodule foreach --recursive 'git checkout-index -a -f --prefix=$SVNPATH/trunk/$path/'"
				git submodule foreach --recursive "git checkout-index -a -f --prefix=$SVNPATH/trunk/$path/"
			done
fi

echo

# Install Composer, and use it to install all the submodule's dependencies.
./deployment/install_composer.sh
find $SVNPATH/trunk/$PLUGINSLUG/includes -name "composer.json" | while read line; do
    ./composer.phar install --no-dev --working-dir=$(dirname -- $line)
done

# Support for the /assets folder on the .org repo.
echo "Moving assets."
# Make the directory if it doesn't already exist
mkdir -p $SVNPATH/assets/
mv $SVNPATH/trunk/assets/* $SVNPATH/assets/
svn add --force $SVNPATH/assets/

echo

echo "Changing directory to SVN folder."
cd $SVNPATH

# Move all files inside the 'mollie-payments-for-woocommerce' to a temporary folder, then
# remove all leftover files in the trunk folder, and return the moved files to the trunk folder.
echo "Only include the plugin folder's files from the GitHub repository in the SVN trunk"
mkdir ./temp_trunk/
mv ./trunk/$PLUGINSLUG/* ./temp_trunk/
rm -rf ./trunk
mv ./temp_trunk ./trunk

# Now that all files are in the correct positions, we can ignore a bunch of files.
echo "Ignoring all the files we won't need to commit to SVN"
svn propset svn:ignore -R -F $CURRENTDIR/deployment/.svnignore "$SVNPATH/trunk/"

# Delete all files that should not now be added.
svn status | grep -v "^.[ \t]*\..*" | grep "^\!" | awk '{print $2"@"}' | xargs svn del
# Add all new files that are not set to be ignored
svn status | grep -v "^.[ \t]*\..*" | grep "^?" | awk '{print $2"@"}' | xargs svn add

# The script is still WIP; don't do any commits!
exit 1

echo "Committing to trunk."
svn commit --username=$SVNUSER -m "Preparing for $PLUGINVERSION release"

echo

# Stop at this point.

echo "Updating WordPress plugin repo assets and committing."
cd $SVNPATH/assets/
# Delete all new files that are not set to be ignored
svn status | grep -v "^.[ \t]*\..*" | grep "^\!" | awk '{print $2"@"}' | xargs svn del
# Add all new files that are not set to be ignored
svn status | grep -v "^.[ \t]*\..*" | grep "^?" | awk '{print $2"@"}' | xargs svn add
svn update --quiet --accept working $SVNPATH/assets/*
svn commit --username=$SVNUSER -m "Updating assets"

echo

echo "Creating new SVN tag and committing it."
cd $SVNPATH
svn copy --quiet trunk/ tags/$PLUGINVERSION/
# Remove assets and trunk directories from tag directory
svn delete --force --quiet $SVNPATH/tags/$PLUGINVERSION/assets
svn delete --force --quiet $SVNPATH/tags/$PLUGINVERSION/trunk
svn update --quiet --accept working $SVNPATH/tags/$PLUGINVERSION
cd $SVNPATH/tags/$PLUGINVERSION
svn commit --username=$SVNUSER -m "Tagging version $PLUGINVERSION"

echo

echo "Removing temporary directory $SVNPATH."
cd $SVNPATH
cd ..
rm -fr $SVNPATH/

echo "*** FIN ***"