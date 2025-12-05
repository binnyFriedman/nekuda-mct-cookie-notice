#!/bin/bash

# Release script for Nekuda MCT Cookie Notice
# Usage: ./release.sh [patch|minor|major]

set -e

PLUGIN_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PLUGIN_NAME="nekuda-mct-cookie-notice"
MAIN_FILE="$PLUGIN_DIR/$PLUGIN_NAME.php"
README_FILE="$PLUGIN_DIR/readme.txt"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Get current version from main plugin file header
get_current_version() {
    grep -m1 "Version:" "$MAIN_FILE" | sed 's/.*Version: //' | tr -d '[:space:]'
}

# Increment version based on type (patch/minor/major)
increment_version() {
    local version=$1
    local type=$2

    IFS='.' read -ra parts <<< "$version"
    local major=${parts[0]}
    local minor=${parts[1]}
    local patch=${parts[2]}

    case $type in
        major)
            major=$((major + 1))
            minor=0
            patch=0
            ;;
        minor)
            minor=$((minor + 1))
            patch=0
            ;;
        patch)
            patch=$((patch + 1))
            ;;
        *)
            echo -e "${RED}Invalid version type: $type${NC}"
            echo "Usage: $0 [patch|minor|major]"
            exit 1
            ;;
    esac

    echo "$major.$minor.$patch"
}

# Update version in plugin header and readme.txt
update_version() {
    local old_version=$1
    local new_version=$2

    echo -e "${YELLOW}Updating version from $old_version to $new_version...${NC}"

    # Update main plugin file header (single source of truth for PHP)
    sed -i '' "s/Version: $old_version/Version: $new_version/" "$MAIN_FILE"

    # Update readme.txt stable tag (for WordPress.org)
    sed -i '' "s/Stable tag: $old_version/Stable tag: $new_version/" "$README_FILE"

    echo -e "${GREEN}Version updated in 2 files${NC}"
}

# Generate changelog from git commits since last tag
generate_changelog() {
    local new_version=$1
    local changelog_entry=""

    # Check if we're in a git repository
    if ! git rev-parse --git-dir > /dev/null 2>&1; then
        echo -e "${YELLOW}Not a git repository. Creating minimal changelog entry.${NC}"
        changelog_entry="= $new_version =\n* Release $new_version"
    else
        # Get the last tag
        local last_tag=$(git describe --tags --abbrev=0 2>/dev/null || echo "")

        if [ -z "$last_tag" ]; then
            # No previous tag, get all commits
            local commits=$(git log --pretty=format:"* %s" --no-merges 2>/dev/null || echo "")
        else
            # Get commits since last tag
            local commits=$(git log "$last_tag"..HEAD --pretty=format:"* %s" --no-merges 2>/dev/null || echo "")
        fi

        if [ -z "$commits" ]; then
            commits="* Release $new_version"
        fi

        changelog_entry="= $new_version =\n$commits"
    fi

    echo -e "$changelog_entry"
}

# Update changelog in readme.txt
update_changelog() {
    local changelog_entry=$1

    echo -e "${YELLOW}Updating changelog...${NC}"

    # Insert new changelog entry after "== Changelog ==" line
    local temp_file=$(mktemp)
    awk -v entry="$changelog_entry" '
        /^== Changelog ==/ {
            print
            getline
            print
            print entry
            print ""
            next
        }
        { print }
    ' "$README_FILE" > "$temp_file"

    mv "$temp_file" "$README_FILE"

    echo -e "${GREEN}Changelog updated${NC}"
}

# Create distribution zip
create_zip() {
    local version=$1
    local dist_dir="$PLUGIN_DIR/dist"
    local zip_name="$PLUGIN_NAME-$version.zip"

    echo -e "${YELLOW}Creating distribution package...${NC}"

    # Create dist directory if it doesn't exist
    mkdir -p "$dist_dir"

    # Create a temporary directory for the plugin files
    local temp_dir=$(mktemp -d)
    local plugin_temp="$temp_dir/$PLUGIN_NAME"

    mkdir -p "$plugin_temp"

    # Copy plugin files (excluding dev files)
    rsync -av --exclude='dist' \
              --exclude='release.sh' \
              --exclude='.git' \
              --exclude='.gitignore' \
              --exclude='node_modules' \
              --exclude='.DS_Store' \
              --exclude='*.log' \
              "$PLUGIN_DIR/" "$plugin_temp/"

    # Create zip file
    cd "$temp_dir"
    zip -r "$dist_dir/$zip_name" "$PLUGIN_NAME"

    # Cleanup
    rm -rf "$temp_dir"

    echo -e "${GREEN}Created: dist/$zip_name${NC}"
}

# Main execution
main() {
    local bump_type=${1:-patch}

    echo -e "${GREEN}==============================${NC}"
    echo -e "${GREEN}  $PLUGIN_NAME Release Script${NC}"
    echo -e "${GREEN}==============================${NC}"
    echo ""

    # Get current version
    local current_version=$(get_current_version)
    echo -e "Current version: ${YELLOW}$current_version${NC}"

    # Calculate new version
    local new_version=$(increment_version "$current_version" "$bump_type")
    echo -e "New version: ${GREEN}$new_version${NC} ($bump_type bump)"
    echo ""

    # Confirm with user
    read -p "Proceed with release? (y/n) " -n 1 -r
    echo ""
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        echo -e "${RED}Release cancelled${NC}"
        exit 1
    fi

    # Generate changelog
    local changelog=$(generate_changelog "$new_version")

    # Update files
    update_version "$current_version" "$new_version"
    update_changelog "$changelog"

    # Create zip
    create_zip "$new_version"

    echo ""
    echo -e "${GREEN}==============================${NC}"
    echo -e "${GREEN}  Release $new_version complete!${NC}"
    echo -e "${GREEN}==============================${NC}"
    echo ""
    echo -e "Next steps:"
    echo -e "  1. Review changes: ${YELLOW}git diff${NC}"
    echo -e "  2. Commit: ${YELLOW}git add -A && git commit -m 'Release $new_version'${NC}"
    echo -e "  3. Tag: ${YELLOW}git tag v$new_version${NC}"
    echo -e "  4. Push: ${YELLOW}git push && git push --tags${NC}"
}

main "$@"
