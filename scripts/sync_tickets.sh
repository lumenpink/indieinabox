#!/usr/bin/env bash
set -euo pipefail

# ==============================================================================
# Codeberg Ticket Synchronizer for Antigravity & cccp
# Enforces a strict 10KB context boundary without truncating tickets mid-text.
# ==============================================================================

OWNER="${CODEBERG_OWNER:-indieinabox}"
REPO="${CODEBERG_REPO:-indieinabox}"
MAX_BYTES="${MAX_BYTES:-10240}" # 10 KB ceiling
OUTPUT_DIR=".antigravity"
OUTPUT_FILE="${OUTPUT_DIR}/tickets.md"
TEMP_FILE="${OUTPUT_DIR}/tickets.tmp"

mkdir -p "$OUTPUT_DIR"

API_URL="https://codeberg.org/api/v1/repos/${OWNER}/${REPO}/issues?state=open&type=issues&limit=30"

CURL_ARGS=(-s -f)
if [[ -n "${CODEBERG_TOKEN:-}" ]]; then
  CURL_ARGS+=(-H "Authorization: token ${CODEBERG_TOKEN}")
fi

echo "Fetching open issues from Codeberg (${OWNER}/${REPO})..."
RESPONSE=$(curl "${CURL_ARGS[@]}" "$API_URL" || true)

if [[ -z "$RESPONSE" ]] || ! echo "$RESPONSE" | jq -e . >/dev/null 2>&1; then
  echo "Error: Failed to retrieve or parse issues from Codeberg API." >&2
  exit 1
fi

# Initialize temporary file with clean markdown metadata
cat <<EOF > "$TEMP_FILE"
# Active Tickets - indieinabox
> Synced at: $(date -u '+%Y-%m-%d %H:%M:%S UTC')
> Ceiling: ${MAX_BYTES} bytes (strictly uncut tickets)

EOF

CURRENT_SIZE=$(wc -c < "$TEMP_FILE" | tr -d ' ')
COUNT=0
STOPPED_EARLY=0

# Iterate through tickets and append only complete blocks within budget
while IFS= read -r issue_json; do
  [[ -z "$issue_json" ]] && continue

  TICKET_MD=$(echo "$issue_json" | jq -r '
    "## [#" + (.number|tostring) + "] " + .title + "\n" +
    "- **Status**: `" + .state + "`\n" +
    "- **Labels**: " + (if (.labels | length) > 0 then ([.labels[].name] | join(", ")) else "none" end) + "\n" +
    "- **Link**: " + .html_url + "\n\n" +
    "### Description\n" +
    (.body // "_No description provided._") + "\n\n" +
    "---\n"
  ')

  ENTRY_SIZE=$(printf '%s\n' "$TICKET_MD" | wc -c | tr -d ' ')

  # Stop before exceeding 10KB to prevent partial ticket ingestion
  if (( CURRENT_SIZE + ENTRY_SIZE > MAX_BYTES )); then
    STOPPED_EARLY=1
    break
  fi

  printf '%s\n' "$TICKET_MD" >> "$TEMP_FILE"
  CURRENT_SIZE=$(( CURRENT_SIZE + ENTRY_SIZE ))
  COUNT=$(( COUNT + 1 ))
done < <(echo "$RESPONSE" | jq -c '.[]')

mv "$TEMP_FILE" "$OUTPUT_FILE"

FINAL_SIZE=$(wc -c < "$OUTPUT_FILE" | tr -d ' ')
echo "Done! Synced ${COUNT} complete ticket(s) into ${OUTPUT_FILE} (${FINAL_SIZE} / ${MAX_BYTES} bytes)."

if (( STOPPED_EARLY == 1 )); then
  echo "Notice: Sync stopped early at ${FINAL_SIZE} bytes to avoid severing the next issue mid-text."
fi
