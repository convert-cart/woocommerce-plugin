#!/bin/bash

# Variables
BRANCH="master"
MAIN_VERSION=$1
BETA_VERSION="${MAIN_VERSION}-beta"
BACKUP_BRANCH="temp-tagging-branch"  # Define the backup branch name

# Color codes
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Exit the script if any command fails
set -e

# Function to handle errors (POSIX compliant)
handle_error() {
    printf "${RED}Error occurred: %s${NC}\n" "$1"
    cleanup
    exit 1
}

# Cleanup function
cleanup() {
    git reset .
    git clean -fd .
    git checkout .

    # Checkout master branch
    printf "${YELLOW}Checking out master branch during cleanup...${NC}\n"
    git checkout $BRANCH || printf "${RED}Failed to checkout $BRANCH during cleanup.${NC}\n"

    # Delete the temporary branch if it exists
    if git show-ref --verify --quiet refs/heads/$BACKUP_BRANCH; then
        git branch -D $BACKUP_BRANCH || printf "${RED}Failed to delete temporary branch $BACKUP_BRANCH.${NC}\n"
    fi
}

# Ensure version number is provided
if [ -z "$MAIN_VERSION" ]; then
    handle_error "Please provide a version number. Usage: ./tagger.sh VERSION_NUMBER"
fi

# Check for uncommitted changes and warn the user
if [ -n "$(git status --porcelain)" ]; then
    printf "${RED}Warning: You have uncommitted changes!${NC}\n"
    printf "${RED}All your local changes will be lost if you continue.${NC}\n"
    printf "Please commit your changes before proceeding.\n"
    printf "Do you want to continue and discard all changes? (y/n): "
    read response
    if [ "$response" != "y" ]; then
        printf "${YELLOW}Exiting without making any changes.${NC}\n"
        exit 0
    fi
fi

# Prompt for tag creation options
echo "Select the tags you want to create:"
echo "1) Production tag"
echo "2) Beta tag"
echo "3) Both"
echo -n "Enter your choice (1/2/3): "
read choice

# Ensure we are on the latest master branch
printf "${YELLOW}Checking out the latest master branch...${NC}\n"
git checkout $BRANCH || handle_error "Failed to checkout $BRANCH"
git pull origin $BRANCH || handle_error "Failed to pull latest changes from $BRANCH"

# Check if old backup branch exists and remove it
if git show-ref --verify --quiet refs/heads/$BACKUP_BRANCH; then
    printf "${YELLOW}Old backup branch '$BACKUP_BRANCH' found. Deleting it...${NC}\n"
    git branch -D $BACKUP_BRANCH || handle_error "Failed to delete old backup branch"
fi

# Create a backup branch to prevent affecting master directly
git checkout -b $BACKUP_BRANCH || handle_error "Failed to create temporary branch $BACKUP_BRANCH"

# Function to check if a tag exists on remote
check_remote_tag_exists() {
    git fetch --tags
    git ls-remote --tags origin | grep -q "refs/tags/$1"
}

# Function to get tag creation date from remote
get_tag_creation_date() {
    git log -1 --format=%aD "$1" 2>/dev/null
}

# Function to confirm tag deletion
confirm_tag_deletion() {
    echo -n "${YELLOW}Tag '$1' already exists on remote (created on: $2). \nDo you want to delete it and recreate it? (y/n): ${NC}"
    read response
    if [ "$response" != "y" ]; then
        printf "${YELLOW}Keeping existing tag '%s'. Skipping creation...${NC}\n" "$1"
        return 1
    fi
    return 0
}

# Check for existing remote tags
if [ "$choice" = "1" ] || [ "$choice" = "3" ]; then
    if check_remote_tag_exists "$MAIN_VERSION"; then
        creation_date=$(get_tag_creation_date "$MAIN_VERSION")
        if confirm_tag_deletion "$MAIN_VERSION" "$creation_date"; then
            git tag -d "$MAIN_VERSION" || handle_error "Failed to delete existing production tag"
        else
            printf "${GREEN}Skipping creation of production tag %s.${NC}\n" "$MAIN_VERSION"
        fi
    fi
fi

if [ "$choice" = "2" ] || [ "$choice" = "3" ]; then
    if check_remote_tag_exists "$BETA_VERSION"; then
        creation_date=$(get_tag_creation_date "$BETA_VERSION")
        if confirm_tag_deletion "$BETA_VERSION" "$creation_date"; then
            git tag -d "$BETA_VERSION" || handle_error "Failed to delete existing beta tag"
        else
            printf "${GREEN}Skipping creation of beta tag %s.${NC}\n" "$BETA_VERSION"
        fi
    fi
fi

# Update version in composer.json
printf "${YELLOW}Updating composer.json with version %s...${NC}\n" "$MAIN_VERSION"
sed -i "s/\"version\": \".*\"/\"version\": \"$MAIN_VERSION\"/" composer.json || handle_error "Failed to update composer.json"

# Update version and stable tag in README.md
printf "${YELLOW}Updating version and stable tag in README.md...${NC}\n"
sed -i "s/^Stable tag: .*/Stable tag: $MAIN_VERSION/" README.md || handle_error "Failed to update stable tag in README.md"
sed -i "s/badge\/v[0-9.]*\"/badge\/v$MAIN_VERSION\"/" README.md || handle_error "Failed to update badge version in README.md"

# Update version, stable tag, and CC_PLUGIN_VERSION in cc-analytics.php
printf "${YELLOW}Updating version, stable tag, and CC_PLUGIN_VERSION in cc-analytics.php...${NC}\n"
sed -i "s/^ \* Version: .*/ \* Version: $MAIN_VERSION/" cc-analytics.php || handle_error "Failed to update version in cc-analytics.php"
sed -i "s/^[[:space:]]*\* Stable Tag: .*/ \* Stable Tag: $MAIN_VERSION/" cc-analytics.php || handle_error "Failed to update stable tag in cc-analytics.php"
sed -i "s/define('CC_PLUGIN_VERSION', '.*');/define('CC_PLUGIN_VERSION', '$MAIN_VERSION');/" cc-analytics.php || handle_error "Failed to update CC_PLUGIN_VERSION in cc-analytics.php"

# Validate that the CHANGELOG.md is updated
if ! grep -q "$MAIN_VERSION" CHANGELOG.md; then
    handle_error "CHANGELOG.md is not updated with version $MAIN_VERSION"
fi

# Commit changes for production tag if chosen
if [ "$choice" = "1" ] || [ "$choice" = "3" ]; then
    git add composer.json README.md includes/class-wc-cc-analytics.php CHANGELOG.md || handle_error "Failed to add files for beta commit"
    git commit -m "Release version $MAIN_VERSION" || handle_error "Failed to commit production changes"
    git tag -a "$MAIN_VERSION" -m "Version $MAIN_VERSION" || handle_error "Failed to create production tag"
    printf "${GREEN}Production tag %s created successfully${NC}\n" "$MAIN_VERSION"
fi

# Update to beta version in composer.json
printf "${YELLOW}Updating composer.json with version %s...${NC}\n" "$BETA_VERSION"
sed -i "s/\"version\": \"$MAIN_VERSION\"/\"version\": \"$BETA_VERSION\"/" composer.json || handle_error "Failed to update composer.json for beta"

# Update CDN URL for beta tag
printf "${YELLOW}Updating CDN URL for beta tag...${NC}\n"
sed -i "s/cdn\.convertcart\.com/cdn-beta.convertcart.com/g" includes/class-wc-cc-analytics.php || handle_error "Failed to update CDN URL for beta tag"

# Update stable tag, version, and CC_PLUGIN_VERSION in cc-analytics.php for beta version
printf "${YELLOW}Updating stable tag, version, and CC_PLUGIN_VERSION in cc-analytics.php for beta...${NC}\n"
sed -i "s/^ \* Version: .*/ \* Version: $BETA_VERSION/" cc-analytics.php || handle_error "Failed to update version for beta"
sed -i "s/^[[:space:]]*\* Stable Tag: .*/ \* Stable Tag: $BETA_VERSION/" cc-analytics.php || handle_error "Failed to update stable tag for beta"
sed -i "s/define('CC_PLUGIN_VERSION', '.*');/define('CC_PLUGIN_VERSION', '$BETA_VERSION');/" cc-analytics.php || handle_error "Failed to update CC_PLUGIN_VERSION for beta"

# Update stable tag and version in README.md for beta version
printf "${YELLOW}Updating stable tag and version in README.md for beta...${NC}\n"
sed -i "s/Stable tag: .*/Stable tag: $BETA_VERSION/" README.md || handle_error "Failed to update stable tag for beta"
sed -i "s/badge\/v[0-9.]*\"/badge\/v$BETA_VERSION\"/" README.md || handle_error "Failed to update badge version in README.md for beta"

# Commit changes for beta tag if chosen
if [ "$choice" = "2" ] || [ "$choice" = "3" ]; then
    git add composer.json README.md includes/class-wc-cc-analytics.php CHANGELOG.md || handle_error "Failed to add files for beta commit"
    git commit -m "Release beta version $BETA_VERSION" || handle_error "Failed to commit beta version"
    git tag -a "$BETA_VERSION" -m "Beta version $BETA_VERSION" || handle_error "Failed to create beta tag"
    printf "${GREEN}Beta tag %s created successfully${NC}\n" "$BETA_VERSION"
fi

# Push tags to remote if created
if [ "$choice" = "1" ] || [ "$choice" = "3" ]; then
    printf "${YELLOW}Pushing production tag to remote...${NC}\n"
    git push -f origin "$MAIN_VERSION" || handle_error "Failed to push production tag"
fi

if [ "$choice" = "2" ] || [ "$choice" = "3" ]; then
    printf "${YELLOW}Pushing beta tag to remote...${NC}\n"
    git push -f origin "$BETA_VERSION" || handle_error "Failed to push beta tag"
fi

# Final cleanup: Checkout master and clean up the temporary branch
cleanup
printf "${GREEN}Tags processing completed.${NC}\n"
