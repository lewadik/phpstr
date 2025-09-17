#!/bin/bash

# S3 Storage CLI Installer
# This script sets up the S3 Storage CLI tools system-wide

set -e

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

# Configuration
INSTALL_DIR="/usr/local/bin"
CONFIG_DIR="$HOME/.s3-storage"
ALIASES_FILE="$CONFIG_DIR/aliases.sh"
CONFIG_FILE="$CONFIG_DIR/config"

echo -e "${GREEN}S3 Storage CLI Installer${NC}"
echo "========================"

# Check if running as root for system-wide install
if [ "$EUID" -eq 0 ]; then
    echo -e "${YELLOW}Running as root - installing system-wide${NC}"
    INSTALL_DIR="/usr/local/bin"
else
    echo -e "${YELLOW}Running as user - installing to user directory${NC}"
    INSTALL_DIR="$HOME/.local/bin"
    mkdir -p "$INSTALL_DIR"
fi

# Create config directory
echo -e "${BLUE}Creating configuration directory...${NC}"
mkdir -p "$CONFIG_DIR"

# Copy aliases file
echo -e "${BLUE}Installing CLI aliases...${NC}"
cp "$(dirname "$0")/s3-aliases.sh" "$ALIASES_FILE"
chmod +x "$ALIASES_FILE"

# Create main CLI script
echo -e "${BLUE}Creating main CLI script...${NC}"
cat > "$INSTALL_DIR/s3-storage" << 'EOF'
#!/bin/bash

# S3 Storage CLI Main Script
CONFIG_DIR="$HOME/.s3-storage"
ALIASES_FILE="$CONFIG_DIR/aliases.sh"
CONFIG_FILE="$CONFIG_DIR/config"

# Load configuration if it exists
if [ -f "$CONFIG_FILE" ]; then
    source "$CONFIG_FILE"
fi

# Load aliases
if [ -f "$ALIASES_FILE" ]; then
    source "$ALIASES_FILE"
fi

# If no arguments, show help
if [ $# -eq 0 ]; then
    s3-help
    exit 0
fi

# Handle special commands
case "$1" in
    "config")
        shift
        s3-config "$@"
        ;;
    "install-completion")
        s3-install-completion
        ;;
    *)
        # Try to execute the command
        if type "s3-$1" >/dev/null 2>&1; then
            cmd="s3-$1"
            shift
            "$cmd" "$@"
        else
            echo "Unknown command: $1"
            echo "Type 's3-storage' for help"
            exit 1
        fi
        ;;
esac
EOF

chmod +x "$INSTALL_DIR/s3-storage"

# Create configuration management function
cat >> "$ALIASES_FILE" << 'EOF'

# Configuration management
s3-config() {
    local action="$1"
    local key="$2"
    local value="$3"
    
    case "$action" in
        "set")
            if [ -z "$key" ] || [ -z "$value" ]; then
                echo -e "${S3_COLOR_RED}Usage: s3-config set <key> <value>${S3_COLOR_NC}"
                echo -e "${S3_COLOR_YELLOW}Example: s3-config set url http://localhost:8000${S3_COLOR_NC}"
                return 1
            fi
            
            # Create config file if it doesn't exist
            touch "$CONFIG_FILE"
            
            # Remove existing key and add new one
            grep -v "^export S3_API_${key^^}=" "$CONFIG_FILE" > "$CONFIG_FILE.tmp" 2>/dev/null || true
            echo "export S3_API_${key^^}=\"$value\"" >> "$CONFIG_FILE.tmp"
            mv "$CONFIG_FILE.tmp" "$CONFIG_FILE"
            
            echo -e "${S3_COLOR_GREEN}Configuration updated: S3_API_${key^^}=$value${S3_COLOR_NC}"
            echo -e "${S3_COLOR_YELLOW}Restart your shell or run: source ~/.s3-storage/config${S3_COLOR_NC}"
            ;;
        "get")
            if [ -z "$key" ]; then
                echo -e "${S3_COLOR_BLUE}Current configuration:${S3_COLOR_NC}"
                if [ -f "$CONFIG_FILE" ]; then
                    cat "$CONFIG_FILE"
                else
                    echo "No configuration file found"
                fi
            else
                if [ -f "$CONFIG_FILE" ]; then
                    grep "^export S3_API_${key^^}=" "$CONFIG_FILE" || echo "Key not found: $key"
                else
                    echo "No configuration file found"
                fi
            fi
            ;;
        "unset")
            if [ -z "$key" ]; then
                echo -e "${S3_COLOR_RED}Usage: s3-config unset <key>${S3_COLOR_NC}"
                return 1
            fi
            
            if [ -f "$CONFIG_FILE" ]; then
                grep -v "^export S3_API_${key^^}=" "$CONFIG_FILE" > "$CONFIG_FILE.tmp" 2>/dev/null || true
                mv "$CONFIG_FILE.tmp" "$CONFIG_FILE"
                echo -e "${S3_COLOR_GREEN}Configuration removed: S3_API_${key^^}${S3_COLOR_NC}"
            fi
            ;;
        *)
            echo -e "${S3_COLOR_YELLOW}Configuration Management${S3_COLOR_NC}"
            echo "Usage: s3-config <action> [key] [value]"
            echo ""
            echo "Actions:"
            echo "  set <key> <value>  - Set configuration value"
            echo "  get [key]          - Get configuration value(s)"
            echo "  unset <key>        - Remove configuration value"
            echo ""
            echo "Available keys:"
            echo "  url      - API base URL (S3_API_BASE_URL)"
            echo "  key      - API key (S3_API_KEY)"
            echo ""
            echo "Examples:"
            echo "  s3-config set url http://localhost:8000"
            echo "  s3-config set key your-api-key"
            echo "  s3-config get"
            echo "  s3-config unset key"
            ;;
    esac
}

# Install shell completion
s3-install-completion() {
    local shell_type="$1"
    
    if [ -z "$shell_type" ]; then
        # Detect shell
        if [ -n "$BASH_VERSION" ]; then
            shell_type="bash"
        elif [ -n "$ZSH_VERSION" ]; then
            shell_type="zsh"
        else
            echo -e "${S3_COLOR_RED}Could not detect shell type. Please specify: bash or zsh${S3_COLOR_NC}"
            return 1
        fi
    fi
    
    case "$shell_type" in
        "bash")
            local completion_file="$HOME/.bash_completion.d/s3-storage"
            mkdir -p "$(dirname "$completion_file")"
            
            cat > "$completion_file" << 'COMPLETION_EOF'
# S3 Storage CLI Bash Completion

_s3_storage_completion() {
    local cur prev commands
    COMPREPLY=()
    cur="${COMP_WORDS[COMP_CWORD]}"
    prev="${COMP_WORDS[COMP_CWORD-1]}"
    
    commands="info upload upload-content list list-prefix chmod rm download upload-batch sync stats help config install-completion"
    
    if [ ${COMP_CWORD} -eq 1 ]; then
        COMPREPLY=($(compgen -W "${commands}" -- ${cur}))
        return 0
    fi
    
    case "${prev}" in
        "chmod")
            COMPREPLY=($(compgen -W "private public-read public-read-write" -- ${cur}))
            ;;
        "upload"|"upload-batch"|"sync")
            COMPREPLY=($(compgen -f -- ${cur}))
            ;;
        "config")
            COMPREPLY=($(compgen -W "set get unset" -- ${cur}))
            ;;
    esac
}

complete -F _s3_storage_completion s3-storage
COMPLETION_EOF
            
            echo -e "${S3_COLOR_GREEN}Bash completion installed to $completion_file${S3_COLOR_NC}"
            echo -e "${S3_COLOR_YELLOW}Add this to your ~/.bashrc:${S3_COLOR_NC}"
            echo "source $completion_file"
            ;;
        "zsh")
            local completion_dir="$HOME/.zsh/completions"
            mkdir -p "$completion_dir"
            
            cat > "$completion_dir/_s3-storage" << 'COMPLETION_EOF'
#compdef s3-storage

_s3_storage() {
    local context state line
    
    _arguments \
        '1:command:(info upload upload-content list list-prefix chmod rm download upload-batch sync stats help config install-completion)' \
        '*::arg:->args'
    
    case $state in
        args)
            case $words[2] in
                chmod)
                    _arguments '2:access-type:(private public-read public-read-write)'
                    ;;
                upload|upload-batch|sync)
                    _files
                    ;;
                config)
                    _arguments '2:action:(set get unset)'
                    ;;
            esac
            ;;
    esac
}

_s3_storage "$@"
COMPLETION_EOF
            
            echo -e "${S3_COLOR_GREEN}Zsh completion installed to $completion_dir/_s3-storage${S3_COLOR_NC}"
            echo -e "${S3_COLOR_YELLOW}Add this to your ~/.zshrc:${S3_COLOR_NC}"
            echo "fpath=($completion_dir \$fpath)"
            echo "autoload -U compinit && compinit"
            ;;
        *)
            echo -e "${S3_COLOR_RED}Unsupported shell: $shell_type${S3_COLOR_NC}"
            echo "Supported shells: bash, zsh"
            return 1
            ;;
    esac
}
EOF

# Create desktop entry (if in GUI environment)
if [ -n "$DISPLAY" ] && [ -d "$HOME/.local/share/applications" ]; then
    echo -e "${BLUE}Creating desktop entry...${NC}"
    cat > "$HOME/.local/share/applications/s3-storage.desktop" << EOF
[Desktop Entry]
Name=S3 Storage CLI
Comment=Command line interface for S3 Storage API
Exec=gnome-terminal -- s3-storage
Icon=folder-remote
Terminal=true
Type=Application
Categories=Development;FileManager;
EOF
fi

# Add to PATH if not already there
if ! echo "$PATH" | grep -q "$INSTALL_DIR"; then
    echo -e "${YELLOW}Adding $INSTALL_DIR to PATH...${NC}"
    
    # Determine shell config file
    if [ -n "$BASH_VERSION" ]; then
        shell_config="$HOME/.bashrc"
    elif [ -n "$ZSH_VERSION" ]; then
        shell_config="$HOME/.zshrc"
    else
        shell_config="$HOME/.profile"
    fi
    
    echo "" >> "$shell_config"
    echo "# S3 Storage CLI" >> "$shell_config"
    echo "export PATH=\"$INSTALL_DIR:\$PATH\"" >> "$shell_config"
    echo "source \"$CONFIG_DIR/config\" 2>/dev/null || true" >> "$shell_config"
fi

echo ""
echo -e "${GREEN}Installation completed successfully!${NC}"
echo ""
echo -e "${BLUE}Quick Start:${NC}"
echo "1. Configure your API endpoint:"
echo "   s3-storage config set url http://your-server.com:8000"
echo ""
echo "2. (Optional) Set API key:"
echo "   s3-storage config set key your-api-key"
echo ""
echo "3. Test the connection:"
echo "   s3-storage info"
echo ""
echo "4. Get help:"
echo "   s3-storage help"
echo ""
echo "5. Install shell completion (optional):"
echo "   s3-storage install-completion"
echo ""
echo -e "${YELLOW}Note: Restart your shell or run 'source ~/.bashrc' to use the new commands${NC}"

# Offer to install completion
echo ""
read -p "Install shell completion now? (y/N): " install_completion
if [ "$install_completion" = "y" ] || [ "$install_completion" = "Y" ]; then
    source "$ALIASES_FILE"
    s3-install-completion
fi

echo ""
echo -e "${GREEN}Happy file managing! 🚀${NC}"