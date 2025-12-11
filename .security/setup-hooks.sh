#!/bin/sh
set -e

echo "Setting up Git hooks..."

# Point Git to .githooks directory
git config core.hooksPath .githooks

# Ensure pre-commit is executable
chmod +x .githooks/pre-commit

echo "Git hooks installed. Pre-commit scan will now run automatically."

# Check if gitleaks is installed
if command -v gitleaks >/dev/null 2>&1; then
  echo "⚡ Gitleaks already installed: $(gitleaks version)"
  exit 0
fi

# Install Gitleaks if missing
VERSION="8.28.0"
OS=$(uname -s | tr '[:upper:]' '[:lower:]')
ARCH=$(uname -m)

echo "Installing Gitleaks v$VERSION for $OS-$ARCH..."

case "$OS-$ARCH" in
  linux-x86_64)
    URL="https://github.com/gitleaks/gitleaks/releases/download/v$VERSION/gitleaks_${VERSION}_linux_x64.tar.gz"
    ;;
  linux-aarch64)
    URL="https://github.com/gitleaks/gitleaks/releases/download/v$VERSION/gitleaks_${VERSION}_linux_arm64.tar.gz"
    ;;
  darwin-arm64)
    URL="https://github.com/gitleaks/gitleaks/releases/download/v$VERSION/gitleaks_${VERSION}_darwin_arm64.tar.gz"
    ;;
  darwin-x86_64)
    URL="https://github.com/gitleaks/gitleaks/releases/download/v$VERSION/gitleaks_${VERSION}_darwin_x64.tar.gz"
    ;;
  *)
    echo "Unsupported OS/Arch ($OS-$ARCH). Please install manually:"
    echo "https://github.com/gitleaks/gitleaks/releases/tag/v$VERSION"
    exit 1
    ;;
esac

curl -sSL "$URL" -o gitleaks.tar.gz
tar -xvzf gitleaks.tar.gz gitleaks
sudo mv gitleaks /usr/local/bin/
rm -f gitleaks.tar.gz

echo "Installed Gitleaks v$(gitleaks version)"