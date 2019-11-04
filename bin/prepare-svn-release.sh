#!/bin/bash

if [ $# -eq 0 ]; then
	echo 'Usage: `./deploy-to-svn.sh <tag | HEAD>`'
	exit 1
fi

EDIT_FLOW_GIT_DIR=$(dirname "$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )" )
EDIT_FLOW_SVN_DIR="/tmp/edit-flow"
TARGET=$1

cd $EDIT_FLOW_GIT_DIR

# Make sure we don't have uncommitted changes.
if [[ -n $( git status -s --porcelain ) ]]; then
	echo "Uncommitted changes found."
	echo "Please deal with them and try again clean."
	exit 1
fi

if [ "$1" != "HEAD" ]; then

	# Make sure we're trying to deploy something that's been tagged. Don't deploy non-tagged.
	if [ -z $( git tag | grep "^$TARGET$" ) ]; then
		echo "Tag $TARGET not found in git repository."
		echo "Please try again with a valid tag."
		exit 1
	fi
else
	read -p "You are about to deploy a change from an unstable state 'HEAD'. This should only be done to update string typos for translators. Are you sure? [y/N]" -n 1 -r
	if [[ $REPLY != "y" && $REPLY != "Y" ]]
	then
		exit 1
	fi
fi

git checkout $TARGET

# Prep a home to drop our new files in. Just make it in /tmp so we can start fresh each time.
rm -rf $EDIT_FLOW_SVN_DIR

echo "Checking out SVN shallowly to $EDIT_FLOW_SVN_DIR"
svn -q checkout https://plugins.svn.wordpress.org/edit-flow/ --depth=empty $EDIT_FLOW_SVN_DIR
echo "Done!"

cd $EDIT_FLOW_SVN_DIR

echo "Checking out SVN trunk to $EDIT_FLOW_SVN_DIR/trunk"
svn -q up trunk
echo "Done!"

echo "Checking out SVN tags shallowly to $EDIT_FLOW_SVN_DIR/tags"
svn -q up tags --depth=empty
echo "Done!"

echo "Deleting everything in trunk except for .svn directories"
for file in $(find $EDIT_FLOW_SVN_DIR/trunk/* -not -path "*.svn*"); do
	rm $file 2>/dev/null
done
echo "Done!"

echo "Rsync'ing everything over from Git except for .git stuffs"
rsync -r --exclude='*.git*' $EDIT_FLOW_GIT_DIR/* $EDIT_FLOW_SVN_DIR/trunk
echo "Done!"

echo "Purging paths included in .svnignore"
# check .svnignore
for file in $( cat "$EDIT_FLOW_GIT_DIR/.svnignore" 2>/dev/null ); do
	rm -rf $EDIT_FLOW_SVN_DIR/trunk/$file
done
echo "Done!"

# Instructions for next steps
echo ""
echo "================"
echo "Plugin release for $TARGET has been staged."
echo ""
echo "Please validate 'svn status' results before committing."
echo ""
echo "Some helpful commands:"
echo ""
echo "- goto dir"
echo "cd $EDIT_FLOW_SVN_DIR"
echo "- rm files:"
echo "svn st | grep ^\! | awk '{print \$2}' | xargs svn rm"
echo "- add files:"
echo "svn st | grep ^? | awk '{print \$2}' | xargs svn add"
echo "- review changes:"
echo "svn diff | colordiff | less -FRX"
echo "- tag the release"
echo "svn cp trunk tags/$TARGET"
echo ""
echo "Are there any new files that shouldn't be deployed?"
echo "Please add them to .svnignore in the GitHub repo."
echo "================"
