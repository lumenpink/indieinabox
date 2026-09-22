#!/bin/bash
set -e

echo "=== Initializing Pleroma Test User ==="
echo "Generating Pleroma test configuration and user..."

if command -v pleroma_ctl >/dev/null 2>&1; then
    pleroma_ctl user new testuser testuser@pleroma.local --password "pleroma_test_pass" --admin || true
else
    echo "Pleroma CLI tool not detected in local host shell; running inside container when deployed."
fi

echo "✓ Pleroma initialization script completed."
