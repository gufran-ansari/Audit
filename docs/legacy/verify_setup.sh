#!/bin/bash

# Audit withGufran - Phase 2 Part 2 Setup Verification
# This script verifies that all files are in place

echo "=========================================="
echo "  Phase 2 Part 2 Setup Verification"
echo "=========================================="
echo ""

# Color codes
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Check counter
PASSED=0
FAILED=0

# Function to check file
check_file() {
    if [ -f "$1" ]; then
        echo -e "${GREEN}✓${NC} $1"
        ((PASSED++))
    else
        echo -e "${RED}✗${NC} $1"
        ((FAILED++))
    fi
}

# Function to check directory
check_dir() {
    if [ -d "$1" ]; then
        echo -e "${GREEN}✓${NC} $1/"
        ((PASSED++))
    else
        echo -e "${RED}✗${NC} $1/"
        ((FAILED++))
    fi
}

echo "Checking Backend Files..."
echo "-------------------------"
check_dir "app/Modules/Projects"
check_file "app/Modules/Projects/ProjectController.php"
check_file "app/Modules/Projects/ProjectService.php"
check_file "app/Modules/Projects/ProjectRepository.php"
check_file "app/Modules/Projects/ProjectValidator.php"
check_file "routes/projects.php"
echo ""

echo "Checking Frontend Files..."
echo "-------------------------"
check_file "projects.php"
check_file "project.php"
check_file "assets/js/projects.js"
check_file "assets/css/styles.css"
echo ""

echo "Checking Database Files..."
echo "-------------------------"
check_file "database/migrations.sql"
check_file "database/Database.php"
echo ""

echo "Checking Configuration..."
echo "-------------------------"
check_file "config/app.php"
check_file "config/database.php"
check_file ".env.example"
echo ""

echo "Checking Documentation..."
echo "-------------------------"
check_file "docs/PHASE2_PART2_COMPLETE.md"
check_file "docs/QUICK_REFERENCE.md"
check_file "SETUP.md"
check_file "PHASE2_PART2_SUMMARY.txt"
echo ""

echo "Checking Existing Auth System..."
echo "-------------------------"
check_file "login.php"
check_file "dashboard.php"
check_file "app/Controllers/AuthController.php"
check_file "app/Services/AuthService.php"
check_file "app/Models/User.php"
echo ""

echo "=========================================="
echo "  Verification Summary"
echo "=========================================="
echo -e "Passed: ${GREEN}$PASSED${NC}"
echo -e "Failed: ${RED}$FAILED${NC}"
echo ""

if [ $FAILED -eq 0 ]; then
    echo -e "${GREEN}✓ All files verified successfully!${NC}"
    echo ""
    echo "Next Steps:"
    echo "1. Run database migrations: mysql -u root -p < database/migrations.sql"
    echo "2. Configure .env file"
    echo "3. Access: http://localhost/login.php"
    echo ""
else
    echo -e "${RED}✗ Some files are missing. Please check the output above.${NC}"
    echo ""
fi

echo "=========================================="
